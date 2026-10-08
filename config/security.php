<?php

return [
    'csp_policy' => env('SECURITY_CSP_POLICY', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob: https:; font-src 'self' data: https:; style-src 'self' 'unsafe-inline' https:; script-src 'self' https:; connect-src 'self' https: wss:"),
    'csp_enforce' => (bool) env('SECURITY_CSP_ENFORCE', false),
];
