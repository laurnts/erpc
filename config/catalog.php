<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Public Catalog
    |--------------------------------------------------------------------------
    |
    | The public product catalog replaces the marketing homepage. It is scoped
    | to exactly one team (single-tenant storefront). When CATALOG_TEAM_ID is
    | not set, the first team is resolved at runtime. The enabled flag is the
    | kill switch that restores the static marketing homepage.
    |
    */

    'enabled' => (bool) env('CATALOG_ENABLED', true),

    'team_id' => env('CATALOG_TEAM_ID'),

];
