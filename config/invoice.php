<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    |
    | This file is for storing invoice related configuration options.
    |
    */

    'calculate_vat' => env('INVOICE_CALCULATE_VAT', true),
    'vat_percentage' => env('INVOICE_VAT_PERCENTAGE', 0.20), // Default to 20%

];
