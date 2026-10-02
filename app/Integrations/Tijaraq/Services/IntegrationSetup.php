<?php

namespace App\Integrations\Tijaraq\Services;

use Common\Settings\DotEnvEditor;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Backs the admin "TijaraQ integration" page: generates the secrets and the
 * SSO key pair, writes the helpdesk side to .env / the key directory and
 * returns the matching block for the main app.
 *
 * Secrets stay environment-only (never the settings table) and are only ever
 * returned by generate(), once. status() reports names and booleans, never
 * values.
 */
class IntegrationSetup
{
    public const PARTS = ['api_key', 'webhook_secret', 'sso_key'];

    // relative to base_path(); tests point this at a throw-away file
    protected string $envFile = '.env';

    public function useEnvFile(string $relativePath): static
    {
        $this->envFile = $relativePath;
        return $this;
    }

    public function status(): array
    {
        $config = config('tijaraq-integration');
        $keyDir = $this->keyDirectory();

        $kids = [];
        foreach (glob($keyDir . DIRECTORY_SEPARATOR . '*.pem') ?: [] as $file) {
            $kids[] = basename($file, '.pem');
        }

        $checks = [
            'enabled' => (bool) $config['enabled'],
            'api_key' => !empty($config['api_keys']),
            'webhook_secret' => !empty($config['webhook_secret']),
            'webhook_url' => !empty($config['webhook_url']),
            'sso_public_key' => !empty($kids),
            'env_writable' => $this->envWritable(),
        ];

        return [
            'enabled' => $checks['enabled'],
            'api_key_ids' => array_keys($config['api_keys'] ?? []),
            'webhook_url' => $config['webhook_url'],
            'webhook_secret_set' => $checks['webhook_secret'],
            'sso_issuer' => $config['sso']['issuer'],
            'sso_audience' => $config['sso']['audience'],
            'sso_kids' => $kids,
            'sso_public_keys_path' => $config['sso']['public_keys_path'],
            'allowed_ips' => implode(', ', $config['allowed_ips'] ?? []),
            'rate_limit_per_tenant' => $config['rate_limit_per_tenant'],
            'checks' => $checks,
            'ready' =>
                $checks['api_key'] &&
                $checks['webhook_secret'] &&
                $checks['webhook_url'] &&
                $checks['sso_public_key'],
            'defaults' => [
                'helpdesk_url' => rtrim((string) config('app.url'), '/'),
                'main_app_url' => $this->guessMainAppUrl(),
            ],
        ];
    }

    /**
     * @param array{parts?:string[],main_app_url:string,helpdesk_url:string,key_id?:?string,keep_old_api_key?:bool,enable?:bool} $options
     * @return array main app values (secrets included, shown once)
     */
    public function generate(array $options): array
    {
        $parts = array_values(array_intersect($options['parts'] ?? self::PARTS, self::PARTS));
        if (empty($parts)) {
            throw new RuntimeException('Nothing to generate.');
        }
        if (!$this->envWritable()) {
            throw new RuntimeException(
                'The .env file is not writable, so the helpdesk side cannot be saved.',
            );
        }

        $mainUrl = rtrim($options['main_app_url'], '/');
        $helpdeskUrl = rtrim($options['helpdesk_url'], '/');

        $env = [
            'TIJARAQ_INTEGRATION_ENABLED' => ($options['enable'] ?? true) ? 'true' : 'false',
            'TIJARAQ_WEBHOOK_URL' => $mainUrl . '/webhooks/helpdesk',
            'TIJARAQ_SSO_ISSUER' => $mainUrl,
            'TIJARAQ_SSO_AUDIENCE' => $helpdeskUrl,
            'TIJARAQ_SSO_PUBLIC_KEYS_PATH' => $this->relativeKeyPath(),
        ];

        $result = [
            'main_app' => [
                'HELPDESK_ENABLED' => 'true',
                'HELPDESK_BASE_URL' => $helpdeskUrl,
                'HELPDESK_SSO_ISSUER' => $mainUrl,
            ],
            'generated' => $parts,
            'private_key' => null,
            'notes' => [],
        ];

        if (in_array('api_key', $parts, true)) {
            $secret = bin2hex(random_bytes(48));
            $keys = ($options['keep_old_api_key'] ?? false)
                ? config('tijaraq-integration.api_keys', [])
                : [];
            $keyId = $this->nextKeyId($keys, $options['key_id'] ?? null);
            $keys[$keyId] = $secret;

            $env['TIJARAQ_API_KEYS'] = json_encode($keys, JSON_UNESCAPED_SLASHES);
            $result['main_app']['HELPDESK_API_KEY_ID'] = $keyId;
            $result['main_app']['HELPDESK_API_SECRET'] = $secret;

            if (count($keys) > 1) {
                $result['notes'][] = 'Old API key ids are still accepted. Remove them from TIJARAQ_API_KEYS once the main app uses the new one.';
            }
        }

        if (in_array('webhook_secret', $parts, true)) {
            $secret = bin2hex(random_bytes(48));
            $env['TIJARAQ_WEBHOOK_SECRET'] = $secret;
            $result['main_app']['HELPDESK_WEBHOOK_SECRET'] = $secret;
        }

        if (in_array('sso_key', $parts, true)) {
            $kid = $this->nextKid($options['sso_kid'] ?? null);
            [$private, $public] = $this->generateKeyPair();

            $dir = $this->keyDirectory();
            if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
                throw new RuntimeException("Could not create $dir");
            }
            file_put_contents($dir . DIRECTORY_SEPARATOR . $kid . '.pem', $public);

            $result['main_app']['HELPDESK_SSO_KID'] = $kid;
            $result['main_app']['HELPDESK_SSO_PRIVATE_KEY_PATH'] = "storage/app/private/helpdesk-sso/$kid.pem";
            $result['private_key'] = ['kid' => $kid, 'pem' => $private];
            $result['notes'][] = "Save the private key as storage/app/private/helpdesk-sso/$kid.pem in the main app. It is not stored here and cannot be shown again.";
        }

        (new DotEnvEditor($this->envFile))->write($env);
        $this->clearConfigCache();

        $result['status'] = $this->status();

        return $result;
    }

    /** Non-secret settings only. */
    public function updateSettings(array $values): array
    {
        $env = [];
        $map = [
            'enabled' => 'TIJARAQ_INTEGRATION_ENABLED',
            'webhook_url' => 'TIJARAQ_WEBHOOK_URL',
            'sso_issuer' => 'TIJARAQ_SSO_ISSUER',
            'sso_audience' => 'TIJARAQ_SSO_AUDIENCE',
            'allowed_ips' => 'TIJARAQ_ALLOWED_IPS',
            'rate_limit_per_tenant' => 'TIJARAQ_RATE_LIMIT_PER_TENANT',
        ];

        foreach ($map as $field => $key) {
            if (!array_key_exists($field, $values)) {
                continue;
            }
            $value = $values[$field];
            $env[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        if (!$this->envWritable()) {
            throw new RuntimeException('The .env file is not writable.');
        }

        // an empty allow-list must be written as an empty value, not removed
        $editor = new DotEnvEditor($this->envFile);
        $empty = array_filter($env, fn($v) => $v === '');
        $editor->write(array_diff_key($env, $empty));
        foreach (array_keys($empty) as $key) {
            $this->writeEmpty($key);
        }

        $this->clearConfigCache();

        return $this->status();
    }

    protected function writeEmpty(string $key): void
    {
        $path = base_path($this->envFile);
        $content = file_get_contents($path);
        $line = "$key=";

        if (preg_match("/^$key=.*$/m", $content)) {
            $content = preg_replace("/^$key=.*$/m", $line, $content);
        } else {
            $content = rtrim($content) . "\n$line\n";
        }
        file_put_contents($path, $content);
    }

    /** @return array{0:string,1:string} [private pem, public pem] */
    protected function generateKeyPair(): array
    {
        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        // Windows PHP builds often need an explicit openssl.cnf
        foreach ([
            getenv('OPENSSL_CONF') ?: null,
            dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
        ] as $candidate) {
            if ($candidate && is_file($candidate)) {
                $options['config'] = $candidate;
                break;
            }
        }

        $key = openssl_pkey_new($options);
        if (!$key || !openssl_pkey_export($key, $private, null, $options)) {
            throw new RuntimeException(
                'Could not generate the SSO key pair: ' . (openssl_error_string() ?: 'OpenSSL is not available'),
            );
        }

        return [$private, openssl_pkey_get_details($key)['key']];
    }

    protected function nextKeyId(array $existing, ?string $wanted): string
    {
        if ($wanted && preg_match('/^[A-Za-z0-9_-]{1,32}$/', $wanted)) {
            return $wanted;
        }
        for ($i = 1; ; $i++) {
            if (!isset($existing["k$i"])) {
                return "k$i";
            }
        }
    }

    protected function nextKid(?string $wanted): string
    {
        $base = $wanted && preg_match('/^[A-Za-z0-9._-]{1,48}$/', $wanted) && !str_contains($wanted, '..')
            ? $wanted
            : 'sso-' . date('Y-m');

        $kid = $base;
        for ($i = 2; is_file($this->keyDirectory() . DIRECTORY_SEPARATOR . $kid . '.pem'); $i++) {
            $kid = "$base-$i";
        }

        return $kid;
    }

    protected function keyDirectory(): string
    {
        $dir = (string) config('tijaraq-integration.sso.public_keys_path');
        if (!str_starts_with($dir, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $dir)) {
            $dir = base_path($dir);
        }

        return rtrim($dir, '/\\');
    }

    protected function relativeKeyPath(): string
    {
        return rtrim((string) config('tijaraq-integration.sso.public_keys_path'), '/\\') . '/';
    }

    protected function envWritable(): bool
    {
        $path = base_path($this->envFile);

        return is_file($path) && is_writable($path);
    }

    protected function clearConfigCache(): void
    {
        if (file_exists(app()->getCachedConfigPath())) {
            Artisan::call('config:clear');
        }
    }

    protected function guessMainAppUrl(): string
    {
        $issuer = config('tijaraq-integration.sso.issuer');
        $helpdesk = parse_url((string) config('app.url'));
        $scheme = $helpdesk['scheme'] ?? 'https';
        $host = $helpdesk['host'] ?? '';

        // help.tijaraq.test -> app.tijaraq.test
        if (str_starts_with($host, 'help.')) {
            return $scheme . '://app.' . substr($host, 5);
        }

        return $issuer ?: '';
    }
}
