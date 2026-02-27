<?php
// config/auth.php
return [
    'jwt' => [
        'secret' => env('JWT_SECRET', ''),
        'expiry' => 3600, // 1 hour
        'refresh_expiry' => 86400 * 7, // 7 days
    ],
    'roles' => [
        'admin' => ['chat:read', 'chat:write', 'embed:read', 'embed:write', 'users:manage', 'prompts:manage'],
        'user' => ['chat:read', 'chat:write', 'embed:read'],
        'service' => ['chat:read', 'embed:read', 'embed:write'],
    ],
];
