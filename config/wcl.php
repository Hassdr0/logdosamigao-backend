<?php
return [
    'client_id'     => env('WCL_CLIENT_ID'),
    'client_secret' => env('WCL_CLIENT_SECRET'),
    'token_url'     => 'https://www.warcraftlogs.com/oauth/token',
    'api_url'       => 'https://www.warcraftlogs.com/api/v2/client',
    'token_ttl'     => 82800,
];
