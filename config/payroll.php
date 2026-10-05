<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local Service Tax
    |--------------------------------------------------------------------------
    |
    | LST is charged annually on people in gainful employment and collected by
    | the local government, deducted from pay in equal instalments over the
    | first months of the Ugandan financial year (July–June).
    |
    | The schedule below is the Fifth Schedule of the Local Governments Act as
    | commonly applied: bands on MONTHLY income, each giving an ANNUAL charge.
    | It is config rather than code because a local government may apply its
    | own schedule and because the figures change by statute, not by release —
    | correcting them here needs no deployment and no developer.
    |
    | VERIFY THESE AGAINST THE SCHEDULE YOUR LOCAL GOVERNMENT ACTUALLY ISSUES
    | BEFORE THE FIRST COLLECTION MONTH. They were not supplied with the
    | request and are the published national schedule, not a figure taken from
    | a Mastermind assessment.
    |
    */

    'lst' => [

        // Switch the whole deduction off without touching a single employee.
        'enabled' => env('PAYROLL_LST_ENABLED', true),

        // The months LST is taken in. July–October is the usual practice:
        // four equal instalments across the start of the financial year.
        'months' => [7, 8, 9, 10],

        // Monthly income up to `up_to` (inclusive) is charged `annual` a year.
        // `up_to` of null is the top band, which has no ceiling. Lowest first.
        'bands' => [
            ['up_to' =>   100_000, 'annual' =>       0],   // below the threshold
            ['up_to' =>   200_000, 'annual' =>   5_000],
            ['up_to' =>   300_000, 'annual' =>  10_000],
            ['up_to' =>   400_000, 'annual' =>  20_000],
            ['up_to' =>   500_000, 'annual' =>  30_000],
            ['up_to' =>   600_000, 'annual' =>  40_000],
            ['up_to' =>   700_000, 'annual' =>  60_000],
            ['up_to' =>   800_000, 'annual' =>  70_000],
            ['up_to' =>   900_000, 'annual' =>  80_000],
            ['up_to' => 1_000_000, 'annual' =>  90_000],
            ['up_to' =>      null, 'annual' => 100_000],
        ],
    ],

];
