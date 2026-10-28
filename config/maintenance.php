<?php

return [
    // Secret for the token-protected /maintenance/{task} URL. Leave empty to disable that URL
    // (logged-in admins can still use the dashboard "Maintenance" tab).
    'token' => env('MAINTENANCE_TOKEN'),
];
