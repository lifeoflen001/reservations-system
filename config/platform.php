<?php

return [
    'support' => [
        'max_minutes' => 60,
        'allowed_durations' => [15, 30, 60],
    ],
    'two_factor' => [
        'mandatory' => true,
        'challenge_attempts' => 6,
        'challenge_window_seconds' => 60,
    ],
];
