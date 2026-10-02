<?php

namespace App\Integrations\Tijaraq\Http\Controllers;

use App\Integrations\Tijaraq\Services\AuditLogger;
use App\Integrations\Tijaraq\Services\CustomerProvisioner;
use App\Integrations\Tijaraq\Support\JwtVerifier;
use App\Integrations\Tijaraq\Support\RedirectSanitizer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/** GET /sso/tijaraq?token=<JWT>&redirect=/relative/path */
class SsoController extends Controller
{
    public function __invoke(
        Request $request,
        JwtVerifier $verifier,
        CustomerProvisioner $provisioner,
        AuditLogger $audit,
    ) {
        $token = (string) $request->query('token');

        try {
            $claims = $verifier->verify($token);

            $user = $provisioner->provision(
                tenant: $claims['cid'],
                externalUserId: $claims['sub'],
                email: $claims['email'],
                name: $claims['name'],
                emailVerified: $claims['email_verified'],
            );

            // SSO is for merchants only, never for staff accounts
            if (CustomerProvisioner::isProtected($user)) {
                throw new \RuntimeException('identity resolves to a staff account');
            }
        } catch (Throwable $e) {
            // the reason goes to the log, never to the visitor
            Log::warning('TijaraQ SSO rejected', ['reason' => $e->getMessage()]);
            $audit->logSso('sso.failed', $request, meta: [
                'reason' => substr($e->getMessage(), 0, 120),
            ]);

            return $this->failurePage();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $audit->logSso('sso.login', $request, $claims['cid'], $claims['sub']);

        return redirect(
            RedirectSanitizer::sanitize(
                $request->query('redirect'),
                config('tijaraq-integration.sso.default_redirect'),
            ),
        );
    }

    protected function failurePage()
    {
        $url = e(config('tijaraq-integration.sso.fallback_url'));
        $html = <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in to support</title>
<style>body{font-family:system-ui,sans-serif;background:#f6f8fa;color:#111;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}.card{background:#fff;border-radius:12px;padding:32px;max-width:420px;box-shadow:0 1px 4px rgba(0,0,0,.12);text-align:center}a{display:inline-block;margin-top:16px;background:#3b82f6;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px}</style>
</head><body><div class="card"><h1>We couldn't sign you in</h1>
<p>Your sign-in link has expired or is no longer valid.</p>
<a href="$url">Open from your TijaraQ dashboard</a></div></body></html>
HTML;

        return response($html, 401)->header('Cache-Control', 'no-store');
    }
}
