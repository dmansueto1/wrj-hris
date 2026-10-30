<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | The forwarded host, scheme and client IP (X-Forwarded-*) are believed only
    | when they come from these proxies. Locally that is any proxy (ngrok, for
    | testing on other devices); on a server, only the IPs in TRUSTED_PROXIES
    | (comma separated, e.g. a load balancer), and nobody when it is not set.
    | Trusting everyone would let anyone fake their IP with X-Forwarded-For and
    | get around the login lockout and the device request limit (T-11.1).
    |
    | Read by Illuminate\Http\Middleware\TrustProxies.
    |
    */

    'proxies' => env('TRUSTED_PROXIES', env('APP_ENV') === 'local' ? '*' : null),

];
