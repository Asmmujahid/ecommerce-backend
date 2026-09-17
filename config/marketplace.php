<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vendor-Owned Product
    |--------------------------------------------------------------------------
    |
    | Admin marketplace commission from vendor-owned products.
    |
    */

    'commission_rate' => env(
        'MARKETPLACE_COMMISSION_RATE',
        10
    ),

    /*
    |--------------------------------------------------------------------------
    | Admin-Owned Product
    |--------------------------------------------------------------------------
    |
    | Vendor selling/service fee for selling admin-owned products.
    |
    */

    'vendor_selling_fee_rate' => env(
        'VENDOR_SELLING_FEE_RATE',
        10
    ),
];