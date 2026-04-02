<?php
return [
    'client_id'     => env('WCL_CLIENT_ID'),
    'client_secret' => env('WCL_CLIENT_SECRET'),
    'token_url'     => 'https://www.warcraftlogs.com/oauth/token',
    'api_url'       => 'https://www.warcraftlogs.com/api/v2/client',
    'token_ttl'     => 82800,

    // Season atual — para mudar de season basta alterar WCL_SEASON no .env
    'current_season' => env('WCL_SEASON', 'midnight_s1'),

    'seasons' => [
        'midnight_s1' => [
            'mplus_zone_id' => 47,

            // encounter_id => nome da instância de raid
            'raid_encounters' => [
                // Voidspire (6 bosses)
                3176 => 'Voidspire',  // Imperator Averzian
                3177 => 'Voidspire',  // Vorasius
                3178 => 'Voidspire',  // Vaelgor & Ezzorak
                3179 => 'Voidspire',  // Fallen-King Salhadaar
                3180 => 'Voidspire',  // Lightblinded Vanguard
                3181 => 'Voidspire',  // Crown of the Cosmos
                // Dreamrift (1 boss)
                3306 => 'Dreamrift',  // Chimaerus, the Undreamt God
                // March on Quel'Danas (2 bosses)
                3182 => "March on Quel'Danas",  // Belo'ren, Child of Al'ar
                3183 => "March on Quel'Danas",  // Midnight Falls
            ],

            'raid_total_bosses' => [
                'Voidspire'           => 6,
                'Dreamrift'           => 1,
                "March on Quel'Danas" => 2,
            ],
        ],

        // Season 2 — descomentar quando lançar:
        // 'midnight_s2' => [
        //     'mplus_zone_id' => 99,  // atualizar com o novo zone ID
        //     'raid_encounters' => [
        //         XXXX => 'NovaRaid',
        //     ],
        //     'raid_total_bosses' => [
        //         'NovaRaid' => 8,
        //     ],
        // ],
    ],
];
