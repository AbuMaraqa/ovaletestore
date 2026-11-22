<?php

// Public pages must not leak the admin route map; admin pages get everything except debug routes.
return [
    'groups' => [
        'storefront' => ['!admin.*', '!sanctum.*', '!ignition.*', '!storage.*', '!two-factor.*', '!password.confirm*', '!user-password.*', '!user-profile-information.*'],
        'admin' => ['*', '!ignition.*', '!storage.*'],
    ],
];
