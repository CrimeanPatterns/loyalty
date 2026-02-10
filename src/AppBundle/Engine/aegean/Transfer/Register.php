<?php

namespace AwardWallet\Engine\aegean\Transfer;

class Register extends \TAccountCheckerAegean
{
    public static $fieldMap1 = [
        'Title'      => 'Title',
        'FirstName'  => 'FirstName',
        'LastName'   => 'LastName',
        'BirthDay'   => 'DateOfBirth.Day',
        'BirthMonth' => 'DateOfBirth.Month',
        'BirthYear'  => 'DateOfBirth.Year',
        'BirthDate'  => 'DateOfBirth',
        'Email'      => [
            'Email',
            'ConfirmEmail',
        ],
    ];

    public static $fieldMap2 = [
        'Nationality' => 'Nationality',

        'AddressLine1' => 'AddressLine1',
        'AddressLine2' => 'AddressLine2',
        'AddressLine3' => 'AddressLine3',

        'City'            => 'City',
        'StateOrProvince' => 'StateProvince',
        'Country'         => 'Country',
        'PostalCode'      => 'ZipCode',
        'POBox'           => 'POBox',

        'Password' => [
            'Pin',
            'ConfirmPin',
        ],
    ];

    public static $addressTypeMap = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $phoneTypes = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $titles = [
        'MISS' => 'MISS',
        'MR.'  => 'MR.',
        'MRS.' => 'MRS.',
        'MS.'  => 'MS.',
        'MSTR' => 'MSTR',
    ];

    public static $addressTypes = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $nationalities = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua And Barbuda',
        'AN' => 'Antilles Nether',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BA' => 'Bosna And Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory',
        'VG' => 'British Virgin Islands',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BU' => 'Burma',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde Isl.',
        'KY' => 'Cayman Isl.',
        'CF' => 'Central Africa',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Island',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CD' => 'Congo Dem. Rep.',
        'CK' => 'Cook Island',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D Ivorie',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Rep.',
        'TP' => 'East Timor',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Eq Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'MK' => 'FYROM',
        'FK' => 'Falkland Island',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'PS' => 'Formet Gaza and Held Terr.',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Terr.',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'XA' => 'Gaza',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard and McDonald Terr.',
        'XH' => 'Held Territories',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'XU' => 'Khabarovsk Krai',
        'KI' => 'Kiribati',
        'KP' => 'Korea North',
        'KR' => 'Korea South',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao P.Dem.Rep.',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macau',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maloptiones',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Island',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'UM' => 'Minor U.S. Outlying Islands',
        'MD' => 'Moldova',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
        'MS' => 'Montserrat',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'NC' => 'New Caledonia',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NU' => 'Niue',
        'NF' => 'Norfolk Island',
        'XB' => 'Northern Ireland',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'XX' => 'Other',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Roumania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'KN' => 'Saint Kitts and Nevis',
        'MF' => 'Saint Martin',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome And Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovak Republic',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'DS' => 'South Georgia &amp; Sandwich Isl.',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'PM' => 'St Pierre And Miquelon',
        'VC' => 'St Vincent &amp; Grenadines',
        'SH' => 'St. Helena',
        'LC' => 'St.lucia',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga Island',
        'TT' => 'Trinidad And Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks And Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukranie',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'VI' => 'Us Virgin Isl.',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'WF' => 'Wallis And Futuna Islands',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'YD' => 'Yemen Democratic',
        'ZR' => 'Zaire',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public static $countries = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua And Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BQ' => 'Bonaire',
        'BA' => 'Bosna And Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory',
        'VG' => 'British Virgin Islands',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde Isl.',
        'KY' => 'Cayman Isl.',
        'CF' => 'Central Africa',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Island',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CD' => 'Congo Dem. Rep.',
        'CK' => 'Cook Island',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D Ivorie',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Rep.',
        'TL' => 'East Timor',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Eq Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'MK' => 'FYROM',
        'FK' => 'Falkland Island',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'PS' => 'Formet Gaza and Held Terr.',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Terr.',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard and McDonald Terr.',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KP' => 'Korea North',
        'KR' => 'Korea South',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao P.Dem.Rep.',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macau',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'ML' => 'Mali',
        'MV' => 'Maloptiones',
        'MT' => 'Malta',
        'MH' => 'Marshall Island',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'UM' => 'Minor U.S. Outlying Islands',
        'MD' => 'Moldova',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
        'MS' => 'Montserrat',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'NC' => 'New Caledonia',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NU' => 'Niue',
        'NF' => 'Norfolk Island',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Roumania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthelemy',
        'KN' => 'Saint Kitts and Nevis',
        'MF' => 'Saint Martin',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome And Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SX' => 'Sint Maarten',
        'SK' => 'Slovak Republic',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia & Sandwich Isl.',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'PM' => 'St Pierre And Miquelon',
        'VC' => 'St Vincent & Grenadines',
        'SH' => 'St. Helena',
        'LC' => 'St.lucia',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga Island',
        'TT' => 'Trinidad And Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks And Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukranie',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'VI' => 'Us Virgin Isl.',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'WF' => 'Wallis And Futuna Islands',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    protected $fields;

    public function registerAccount(array $fields)
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $this->http->removeCookies();
        $this->http->LogHeaders = true;
        $this->fields = $fields;

        $this->checkFields();
        $this->fixFields();
        $this->registerStep1();
        $this->checkRegErrors();
        $this->registerStep2();
        $this->checkRegErrors();
        $this->checkSuccess();

        return true;
    }

    public function getRegisterFields()
    {
        return [
            'Title' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Title ',
                    'Required' => true,
                    'Options'  => self::$titles,
                ],
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'First Name',
                    'Required' => true,
                ],
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Last Name',
                    'Required' => true,
                ],
            'BirthDay' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Day of Birth Date',
                    'Required' => true,
                ],
            'BirthMonth' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Month of Birth Date',
                    'Required' => true,
                ],
            'BirthYear' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Year of Birth Date',
                    'Required' => true,
                ],
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email ',
                    'Required' => true,
                ],

            'Nationality' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Nationality, country code',
                    'Required' => true,
                    'Options'  => self::$nationalities,
                ],

            'PhoneType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Type',
                    'Required' => true,
                    'Options'  => self::$phoneTypes,
                ],
            'PhoneCountryCodeNumeric' =>
                [
                    'Type'     => 'string',
                    'Caption'  => '1-3-number Phone Country Code',
                    'Required' => true,
                ],
            'PhoneAreaCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Area Code',
                    'Required' => true,
                ],
            'PhoneLocalNumber' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Number',
                    'Required' => true,
                ],

            'AddressType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Type',
                    'Required' => true,
                    'Options'  => self::$addressTypes,
                ],
            'AddressLine1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Line',
                    'Required' => true,
                ],
            'AddressLine2' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Line 2',
                    'Required' => false,
                ],
            'AddressLine3' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Line 3',
                    'Required' => false,
                ],

            'City' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'City',
                    'Required' => true,
                ],
            'StateOrProvince' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'State or Province',
                    'Required' => false,
                ],
            'Country' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Country Code',
                    'Required' => true,
                    'Options'  => self::$countries,
                ],
            'PostalCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Zip Code',
                    'Required' => false,
                    //'Options' => self::$countries,
                ],
            'POBox' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'POBox',
                    'Required' => false,
                    //'Options' => self::$countries,
                ],
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password (4-6 numeric character, cannot start with zero)',
                    'Required' => true,
                ],
        ];
    }

    private function fixFields()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $this->fields['BirthDay'] = sprintf("%02d", $this->fields['BirthDay']);
        $this->fields['BirthMonth'] = sprintf("%02d", $this->fields['BirthMonth']);
        $this->fields['BirthDate'] = sprintf('%s/%s/%s',
            $this->fields['BirthDay'], $this->fields['BirthMonth'], $this->fields['BirthYear']);

        $this->fields['FirstName'] = strtoupper($this->fields['FirstName']);
        $this->fields['LastName'] = strtoupper($this->fields['LastName']);

        $this->fields['AddressType'] = self::$addressTypeMap[$this->fields['AddressType']];

        $this->fields['Phone'] = $this->getPhone();

        // Provider uses wrong country codes for:
        // - East Timor (TP instead of standard TL)
        // - South Georgia & Sandwich Isl. (DS instead of standard GS)
        // Map from our standard ISO code to wrong code used by provider
        $wrongCountryCodesFixingMap = [
            'TL' => 'TP',
            'GS' => 'DS',
        ];

        if (isset($wrongCountryCodesFixingMap[$this->fields['Country']])) {
            $origCountryCode = $this->fields['Country'];
            $fields['Country'] = $wrongCountryCodesFixingMap[$this->fields['Country']];
            $this->logger->debug('Mapped standard country code "' . $origCountryCode . '" to provider code "' . $this->fields['Country'] . '"');
        }
    }

    private function registerStep1()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $this->http->GetURL('https://en.aegeanair.com/milesandbonus/member/enroll/');

        $status = $this->http->ParseForm(null, 1, true, "//form[contains(@action, '/enroll/')]");
        $this->http->log('[INFO] form1:');
        // $this->http->log(print_r($this->http->Form, true));
        if (!$status) {
            throw new \EngineError('Failed to parse step1 form');
        }

        $this->populateForm(self::$fieldMap1);

        $status = $this->http->PostForm();

        if (!$status) {
            throw new \EngineError('Failed to post step1 form');
        }

        $exists = $this->http->findPreg('/One or more.*? accounts exist/');

        if ($exists) {
            throw new \ProviderError($exists);
        }
    }

    private function registerStep2()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $status = $this->http->ParseForm(null, 1, true, "//form[contains(@action, '/enroll-member/')]");
        $this->http->log('[INFO] form2:');
        // $this->http->log(print_r($this->http->Form, true));
        if (!$status) {
            throw new \EngineError('Failed to parse step1 form');
        }

        $this->fixPostStep2();
        $this->populateForm(self::$fieldMap2);

        $status = $this->http->PostForm();

        if (!$status) {
            throw new \EngineError('Failed to post step1 form');
        }
    }

    private function fixPostStep2()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        unset($this->http->Form['']);

        $toSet = [
            'CommunicationLanguage' => 'EN',
            'TermsAndConditions'    => 'true',
        ];

        foreach ($toSet as $key => $value) {
            $this->http->setInputValue($key, $value);
        }

        // kinda involved
        if ($this->fields['PhoneType'] === 'M') {
            $toSetPhone = [
                'MobilePhoneCountryCode' => $this->fields['PhoneCountryCodeNumeric'],
                'MobilePhoneNumber'      => $this->fields['PhoneCountryCodeNumeric'],
                'OtherPhoneType'         => 'false',
            ];
        } else {
            $toSetPhone = [
                'ContactPhoneCountryCode' => $this->fields['PhoneCountryCodeNumeric'],
                'ContactPhoneNumber'      => $this->fields['PhoneCountryCodeNumeric'],
            ];

            if ($this->fields['PhoneType'] === 'H') {
                $toSetPhone['ContactPhoneType'] = 'Home';
            } elseif ($this->fields['PhoneType'] === 'B') {
                $toSetPhone['ContactPhoneType'] = 'Business';
            }
        }

        foreach ($toSetPhone as $key => $value) {
            $this->http->setInputValue($key, $value);
        }
    }

    private function getPhone()
    {
        return sprintf('%s%s', $this->fields['PhoneAreaCode'], $this->fields['PhoneLocalNumber']);
    }

    private function populateForm($fieldMap)
    {
        $this->http->log('[INFO] ' . __METHOD__);

        foreach ($fieldMap as $awkey => $keys) {
            if (!isset($this->fields[$awkey])) {
                continue;
            }

            if (!is_array($keys)) {
                $keys = [$keys];
            }

            foreach ($keys as $k) {
                $this->http->setInputValue($k, $this->fields[$awkey]);
            }
        }
    }

    private function checkFields()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        if (!in_array($this->fields['Country'], array_keys(self::$countries))) {
            throw new \UserInputError('Invalid country code');
        }

        if (!in_array($this->fields['Nationality'], array_keys(self::$nationalities))) {
            throw new \UserInputError('Invalid nationality code');
        }

        if (preg_match('/^0/', $this->fields['Password'])) {
            throw new \UserInputError('Password cannot start with zero');
        }
    }

    private function checkSuccess()
    {
        $success = $this->http->findPreg('/Welcome to Miles\+Bonus/i');

        if ($success) {
            $acc = $this->http->findSingleNode('//*[@class = "name"]/preceding::span[1]');
            $msg = sprintf('Successfull registration, your account number is %s', $acc);
            $this->http->log(sprintf('[INFO] %s', $msg));
            $this->ErrorMessage = $msg;
        } else {
            throw new \EngineError('Unexpected response on registration submit request');
        }
    }

    private function checkRegErrors()
    {
        $error = $this->http->findSingleNode('//*[contains(@class, "validation-summary-errors")]');

        if ($error) {
            $this->http->log(sprintf('[ERROR] %s', $error));

            throw new \UserInputError($error);
        }
    }
}
