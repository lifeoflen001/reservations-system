<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'username' => 'email',
    'features' => [
        Features::twoFactorAuthentication(['confirm' => true, 'window' => 1]),
    ],
];
