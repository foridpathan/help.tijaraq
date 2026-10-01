<?php

use Common\Core\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\AuthenticateSession;

return [
    'middleware' => [
        'validate_csrf_token' => VerifyCsrfToken::class,
        'encrypt_cookies' => EncryptCookies::class,
        'authenticate_session' => AuthenticateSession::class,
    ],
];
