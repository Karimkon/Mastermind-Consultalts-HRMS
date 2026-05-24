<?php
namespace App\Support;

class BankCodes
{
    // Uganda bank name → KCB sort code (head office)
    private static array $codes = [
        'Absa Bank Uganda Limited'          => '013847',
        'Absa'                              => '013847',
        'Bank of Baroda'                    => '020147',
        'Stanbic Bank'                      => '040047',
        'Stanbic Bank Ltd'                  => '040047',
        'DFCU Bank'                         => '053647',
        'DFCU'                              => '053647',
        'Tropical Bank'                     => '060147',
        'Tropical Bank Limited'             => '060147',
        'Standard Chartered'                => '080147',
        'Standard Chartered Bank'           => '080147',
        'Orient Bank'                       => '110147',
        'Bank of Africa'                    => '130447',
        'Post Bank Uganda'                  => '560147',
        'Post Bank'                         => '560147',
        'Centenary Bank'                    => '168547',
        'Cairo International Bank'          => '180047',
        'Diamond Trust Bank'                => '190047',
        'DTB'                               => '190047',
        'Diamond Trust Bank Uganda'         => '190047',
        'Citi Bank'                         => '220147',
        'Citibank'                          => '220147',
        'Housing Finance Bank'              => '230147',
        'United Bank for Africa'            => '260147',
        'UBA'                               => '260147',
        'Guaranty Trust Bank'               => '270147',
        'GT Bank'                           => '270147',
        'ECOBANK'                           => '290147',
        'Equity Bank'                       => '300047',
        'Equity Bank Uganda'                => '300047',
        'ABC Bank'                          => '310047',
        'Exim Bank Uganda Limited'          => '320047',
        'Exim Bank'                         => '320047',
        'Bank of India Uganda'              => '340147',
        'NCBA Uganda'                       => '360147',
        'NCBA'                              => '360147',
        'Finance Trust Bank'                => '370147',
        'Opportunity Bank Uganda'           => '380147',
        'Opportunity Bank'                  => '380147',
        'National Bank of Commerce'         => 'UG0010022',
        'Bank of Uganda'                    => '990147',
        'KCB Bank'                          => '252947',
        'KCB'                               => '252947',
        'KCB Bank (U)'                      => '252947',
        'UGAFODE'                           => '600147',
        'Finca Uganda'                      => '530147',
        'Finca Uganda Limited'              => '530147',
        'Mercantile Credit Bank'            => '550147',
        'Pride Microfinance'                => '570147',
        'Brac Bank Uganda'                  => '630147',
        'Top Finance Bank Uganda'           => '620147',
    ];

    public static function sortCode(string $bankName): string
    {
        // Exact match
        if (isset(self::$codes[$bankName])) {
            return self::$codes[$bankName];
        }
        // Case-insensitive partial match
        $lower = strtolower($bankName);
        foreach (self::$codes as $name => $code) {
            if (str_contains(strtolower($name), $lower) || str_contains($lower, strtolower($name))) {
                return $code;
            }
        }
        return '';
    }

    public static function all(): array
    {
        return self::$codes;
    }
}
