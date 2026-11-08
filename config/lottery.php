<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bulk Invalidation Administrators
    |--------------------------------------------------------------------------
    |
    | Users do not have roles, so bulk invalidation and the valid period are
    | limited to these user IDs (comma separated). When the list is empty
    | nobody can use them.
    |
    */

    'invalidation_admin_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('LOTTERY_INVALIDATION_ADMIN_IDS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Bank Timezone
    |--------------------------------------------------------------------------
    |
    | Statement transfer times are stored as the bank's local wall-clock time,
    | so invalidation date windows and "today" are evaluated in this zone.
    |
    */

    'bank_timezone' => 'Asia/Ulaanbaatar',

];
