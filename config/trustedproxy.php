<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Detrás de Nginx, Cloudflare o un balanceador, Laravel debe confiar en
    | X-Forwarded-Proto para detectar HTTPS. Usa * en VPS típico; lista de IPs
    | si querés restringir. false / 0 = no confiar (solo desarrollo directo).
    |
    */

    'proxies' => match (trim((string) env('TRUSTED_PROXIES', '*'))) {
        '', 'false', '0' => null,
        default => env('TRUSTED_PROXIES', '*'),
    },

];
