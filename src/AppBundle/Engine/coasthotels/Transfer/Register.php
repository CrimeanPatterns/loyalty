<?php

namespace AwardWallet\Engine\coasthotels\Transfer;

class Register extends \TAccountCheckerCoasthotels
{
    public static $fieldMap = [
        'Title'                   => 'salutation',
        'FirstName'               => 'firstName',
        'LastName'                => 'lastName',
        'PostalCode'              => 'zip',
        'Country'                 => 'country',
        'StateOrProvince'         => 'state',
        'City'                    => 'city',
        'AddressLine1'            => 'address',
        'AddressLine2'            => 'address2',
        'PhoneNumber'             => 'homePhoneNumber',
        'Email'                   => 'email',
        'BirthMonth'              => 'birthdayMonth',
        'BirthDay'                => 'birthdayDay',
        'EarningPreference'       => 'membershipProgramToCollectPointsForId',
        'PartnerMembershipNumber' => 'membershipNumberForProgramToCollectPointsFor',
        'Password'                => 'password',
    ];

    public static $titles = [
        'Dr'   => 'Dr',
        'Mr'   => 'Mr',
        'Mrs'  => 'Mrs',
        'Ms'   => 'Ms',
        'Prof' => 'Prof',
    ];

    public static $countries = [
        'US' => 'UNITED STATES',
        'CA' => 'CANADA',
        'AF' => 'AFGHANISTAN',
        'AX' => 'ALAND ISLANDS',
        'AL' => 'ALBANIA',
        'DZ' => 'ALGERIA',
        'AS' => 'AMERICAN SAMOA',
        'AD' => 'ANDORRA',
        'AO' => 'ANGOLA',
        'AI' => 'ANGUILLA',
        'AG' => 'ANTIGUA AND BARBUDA',
        'AR' => 'ARGENTINA',
        'AM' => 'ARMENIA',
        'AW' => 'ARUBA',
        'AU' => 'AUSTRALIA',
        'AT' => 'AUSTRIA',
        'AZ' => 'AZERBAIJAN',
        'BS' => 'BAHAMAS',
        'BH' => 'BAHRAIN',
        'BD' => 'BANGLADESH',
        'BB' => 'BARBADOS',
        'BY' => 'BELARUS',
        'BE' => 'BELGIUM',
        'BZ' => 'BELIZE',
        'BJ' => 'BENIN',
        'BM' => 'BERMUDA',
        'BT' => 'BHUTAN',
        'BO' => 'BOLIVIA',
        'BA' => 'BOSNIA AND HERZEGOVINA',
        'BW' => 'BOTSWANA',
        'BR' => 'BRAZIL',
        'VG' => 'BRITISH VIRGIN ISLANDS',
        'BN' => 'BRUNEI DARUSSALAM',
        'BG' => 'BULGARIA',
        'BF' => 'BURKINA FASO',
        'BI' => 'BURUNDI',
        'KH' => 'CAMBODIA',
        'CM' => 'CAMEROON',
        'CV' => 'CAPE VERDE',
        'KY' => 'CAYMAN ISLANDS',
        'CF' => 'CENTRAL AFRICAN REPUBLIC',
        'TD' => 'CHAD',
        'CL' => 'CHILE',
        'CN' => 'CHINA',
        'CO' => 'COLOMBIA',
        'KM' => 'COMOROS',
        'CG' => 'CONGO',
        'CK' => 'COOK ISLANDS',
        'CR' => 'COSTA RICA',
        'HR' => 'CROATIA',
        'CY' => 'CYPRUS',
        'CZ' => 'CZECH REPUBLIC',
        'DK' => 'DENMARK',
        'DJ' => 'DJIBOUTI',
        'DM' => 'DOMINICA',
        'DO' => 'DOMINICAN REPUBLIC',
        'TL' => 'TIMOR-LESTE',
        'EC' => 'ECUADOR',
        'EG' => 'EGYPT',
        'SV' => 'EL SALVADOR',
        'GQ' => 'EQUATORIAL GUINEA',
        'ER' => 'ERITREA',
        'EE' => 'ESTONIA',
        'ET' => 'ETHIOPIA',
        'FO' => 'FAEROE ISLANDS',
        'FK' => 'FALKLAND ISLANDS (MALVINAS)',
        'FJ' => 'FIJI',
        'FI' => 'FINLAND',
        'FR' => 'FRANCE',
        'PF' => 'FRENCH POLYNESIA',
        'GA' => 'GABON',
        'GM' => 'GAMBIA',
        'GE' => 'GEORGIA',
        'DE' => 'GERMANY',
        'GH' => 'GHANA',
        'GI' => 'GIBRALTAR',
        'GL' => 'GREENLAND',
        'GD' => 'GRENADA',
        'GP' => 'GUADELOUPE',
        'GU' => 'GUAM',
        'GT' => 'GUATEMALA',
        'GN' => 'GUINEA',
        'GW' => 'GUINEA-BISSAU',
        'GY' => 'GUYANA',
        'HT' => 'HAITI',
        'HN' => 'HONDURAS',
        'HK' => 'HONG KONG',
        'HU' => 'HUNGARY',
        'IS' => 'ICELAND',
        'IN' => 'INDIA',
        'ID' => 'INDONESIA',
        'IE' => 'IRELAND',
        'IL' => 'ISRAEL',
        'IT' => 'ITALY',
        'JM' => 'JAMAICA',
        'JP' => 'JAPAN',
        'JO' => 'JORDAN',
        'KZ' => 'KAZAKHSTAN',
        'KE' => 'KENYA',
        'KI' => 'KIRIBATI',
        'KW' => 'KUWAIT',
        'KG' => 'KYRGYZSTAN',
        'LA' => 'LAO PEOPLES DEMOCRATIC REPUBL',
        'LV' => 'LATVIA',
        'LB' => 'LEBANON',
        'LS' => 'LESOTHO',
        'LR' => 'LIBERIA',
        'LY' => 'LIBYAN ARAB JAMAHIRIYA',
        'LI' => 'LIECHTENSTEIN',
        'LT' => 'LITHUANIA',
        'LU' => 'LUXEMBOURG',
        'MK' => 'MACEDONIA',
        'MG' => 'MADAGASCAR',
        'MW' => 'MALAWI',
        'MY' => 'MALAYSIA',
        'MV' => 'MALDIVES',
        'ML' => 'MALI',
        'MT' => 'MALTA',
        'MH' => 'MARSHALL ISLANDS',
        'MQ' => 'MARTINIQUE',
        'MR' => 'MAURITANIA',
        'MU' => 'MAURITIUS',
        'MX' => 'MEXICO',
        'FM' => 'MICRONESIA (FEDERATED STATES)',
        'MC' => 'MONACO',
        'MN' => 'MONGOLIA',
        'ME' => 'MONTENEGRO',
        'MS' => 'MONTSERRAT',
        'MA' => 'MOROCCO',
        'MZ' => 'MOZAMBIQUE',
        'NA' => 'NAMIBIA',
        'NR' => 'NAURU',
        'NP' => 'NEPAL',
        'NL' => 'NETHERLANDS',
        'NC' => 'NEW CALEDONIA',
        'NZ' => 'NEW ZEALAND',
        'NI' => 'NICARAGUA',
        'NE' => 'NIGER',
        'NG' => 'NIGERIA',
        'NU' => 'NIUE',
        'NF' => 'NORFOLK ISLAND',
        'MP' => 'NORTHERN MARIANA ISLANDS',
        'NO' => 'NORWAY',
        'OM' => 'OMAN',
        'PK' => 'PAKISTAN',
        'PW' => 'PALAU',
        'PA' => 'PANAMA',
        'PG' => 'PAPUA NEW GUINEA',
        'PY' => 'PARAGUAY',
        'PE' => 'PERU',
        'PH' => 'PHILIPPINES',
        'PN' => 'PITCAIRN',
        'PL' => 'POLAND',
        'PT' => 'PORTUGAL',
        'PR' => 'PUERTO RICO',
        'QA' => 'QATAR',
        'MD' => 'REPUBLIC OF MOLDOVA',
        'RE' => 'REUNION',
        'RO' => 'ROMANIA',
        'RU' => 'RUSSIAN FEDERATION',
        'RW' => 'RWANDA',
        'SH' => 'SAINT HELENA',
        'KN' => 'SAINT KITTS AND NEVIS',
        'LC' => 'SAINT LUCIA',
        'PM' => 'SAINT PIERRE AND MIQUELON',
        'VC' => 'SAINT VINCENT AND GRENADINES',
        'WS' => 'SAMOA',
        'SM' => 'SAN MARINO',
        'ST' => 'SAO TOME AND PRINCIPE',
        'SA' => 'SAUDI ARABIA',
        'SN' => 'SENEGAL',
        'RS' => 'SERBIA',
        'SC' => 'SEYCHELLES',
        'SL' => 'SIERRA LEONE',
        'SG' => 'SINGAPORE',
        'SK' => 'SLOVAKIA',
        'SI' => 'SLOVENIA',
        'SB' => 'SOLOMON ISLANDS',
        'SO' => 'SOMALIA',
        'ZA' => 'SOUTH AFRICA',
        'KR' => 'SOUTH KOREA',
        'ES' => 'SPAIN',
        'LK' => 'SRI LANKA',
        'SR' => 'SURINAME',
        'SJ' => 'SVALBARD AND JAN MAYEN ISLANDS',
        'SZ' => 'SWAZILAND',
        'SE' => 'SWEDEN',
        'CH' => 'SWITZERLAND',
        'TW' => 'TAIWAN PROVINCE OF CHINA',
        'TJ' => 'TAJIKISTAN',
        'YT' => 'TERRITORIAL OF MAYOTTE',
        'TH' => 'THAILAND',
        'TG' => 'TOGO',
        'TK' => 'TOKELAU',
        'TO' => 'TONGA',
        'TT' => 'TRINIDAD AND TOBAGO',
        'TN' => 'TUNISIA',
        'TR' => 'TURKEY',
        'TM' => 'TURKMENISTAN',
        'TC' => 'TURKS AND CAICOS ISLANDS',
        'TV' => 'TUVALU',
        'UG' => 'UGANDA',
        'UA' => 'UKRAINE',
        'AE' => 'UNITED ARAB EMIRATES',
        'GB' => 'UNITED KINGDOM',
        'TZ' => 'UNITED REPUBLIC OF TANZANIA',
        'VI' => 'UNITED STATES VIRGIN ISLANDS',
        'UY' => 'URUGUAY',
        'UZ' => 'UZBEKISTAN',
        'VU' => 'VANUATU',
        'VE' => 'VENEZUELA',
        'VN' => 'VIETNAM',
        'WF' => 'WALLIS AND FUTUNA ISLANDS',
        'EH' => 'WESTERN SAHARA',
        'YE' => 'YEMEN',
    ];

    public static $states = [
        'AB' => 'Alberta',
        'AK' => 'Alaska',
        'AL' => 'Alabama',
        'AR' => 'Arkansas',
        'AS' => 'American Samoa',
        'AZ' => 'Arizona',
        'BC' => 'British Columbia',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DC' => 'District of Columbia',
        'DE' => 'Delaware',
        'FL' => 'Florida',
        'FM' => 'Federated States of Micronesia',
        'GA' => 'Georgia',
        'GU' => 'Guam',
        'HI' => 'Hawaii',
        'IA' => 'Iowa',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'MA' => 'Massachusetts',
        'MB' => 'Manitoba',
        'MD' => 'Maryland',
        'ME' => 'Maine',
        'MH' => 'Marshall Islands',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MO' => 'Missouri',
        'MP' => 'Mariana Islands',
        'MS' => 'Mississippi',
        'MT' => 'Montana',
        'NB' => 'New Brunswick',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'NE' => 'Nebraska',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NL' => 'Newfoundland and Labrador',
        'NM' => 'New Mexico',
        'NS' => 'Nova Scotia',
        'NT' => 'Northwest Territories',
        'NU' => 'Nunavut',
        'NV' => 'Nevada',
        'NY' => 'New York',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'ON' => 'Ontario',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'PE' => 'Prince Edward Island',
        'PR' => 'Puerto Rico',
        'PW' => 'Palau',
        'QC' => 'Quebec',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'SK' => 'Saskatchewan',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VA' => 'Virginia',
        'VI' => 'Virgin Islands',
        'VT' => 'Vermont',
        'WA' => 'Washington',
        'WI' => 'Wisconsin',
        'WV' => 'West Virginia',
        'WY' => 'Wyoming',
        'YT' => 'Yukon',
        'ZZ' => 'Other',
    ];

    public static $earningPreferences = [
        '999'  => 'Coast Rewards',
        '1001' => 'Alaska Airlines',
        '1000' => 'Aeroplan',
        '1002' => 'More Rewards',
    ];

    public function registerAccount(array $fields)
    {
        $this->http->log('>>> ' . __METHOD__);
        //$this->ArchiveLogs = true;
        if (!in_array($fields['Country'], array_keys(self::$countries))) {
            throw new \UserInputError('Invalid country code');
        }

        if ($fields['Country'] == 'TL') {
            // Provider uses wrong country code for Timor-Leste (TI instead of standard TL)
            // Map from our standard ISO code to wrong code used by provider
            $fields['Country'] = 'TI';
            $this->logger->debug('Mapped standard country code "TL" to provider code "TI"');
        }

        $this->http->getURL('https://www.coasthotels.com/coast-rewards/signup/');

        $fields['BirthDay'] = sprintf('%02d', $fields['BirthDay']);
        $fields['EarningPreference'] = '999'; // gift asked to choose coast rewards

        $provFields = [];

        foreach (self::$fieldMap as $awkey => $keys) {
            if (!isset($fields[$awkey])) {
                continue;
            }

            if (!is_array($keys)) {
                $keys = [$keys];
            }

            foreach ($keys as $k) {
                $provFields[$k] = $fields[$awkey];
            }
        }

        $outsideParams = [
            'email',
            'membershipNumberForProgramToCollectPointsFor',
            'membershipNumberForProgramToCollectPointsFor',
            'password',
            'password2',
        ];
        $outsideParams = array_fill_keys($outsideParams, '');
        $this->http->log('outsideParams:');
        $this->http->log(print_r($outsideParams, true));
        $memberParams = array_diff_key($provFields, $outsideParams);
        $this->http->log('memberParams:');
        $this->http->log(print_r($memberParams, true));
        $params = array_intersect_key($provFields, $outsideParams);
        $params['listMember'] = $memberParams;
        $this->http->log('params:');
        $this->http->log(print_r($params, true));

        $signupURL = 'https://www.zdirect.com/portal/client/CoastRewards/en/account/json/extendedSignup';
        $headers = [
            'Content-Type' =>
                'application/json; charset=UTF-8',
        ];

        if (!$this->http->PostURL($signupURL, json_encode($params), $headers)) {
            $this->http->Log('post url failed');

            return false;
        }

        $response = json_decode($this->http->Response['body'], true);
        $this->http->Log('=response:');
        $this->http->Log(print_r($response, true));

        if (!empty($response['success'])) {
            $msg = 'Successful registration. Confirmation email sent.';
            $this->http->Log($msg);
            $this->ErrorMessage = $msg;

            return true;
        } elseif (isset($response['errorMessage'])) {
            throw new \UserInputError($response['errorMessage']);
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Title' => [
                'Type'     => 'string',
                'Caption'  => 'Title',
                'Required' => false,
                'Options'  => self::$titles,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'LastName',
                'Required' => true,
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country code',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State or Province',
                'Required' => true,
                'Options'  => self::$states,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City ',
                'Required' => true,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Address Line 1',
                'Required' => true,
            ],
            'AddressLine2' => [
                'Type'     => 'string',
                'Caption'  => 'Address Line 2',
                'Required' => false,
            ],
            'PhoneNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email (will be your username)',
                'Required' => true,
            ],
            'BirthMonth' => [
                'Type'     => 'integer',
                'Caption'  => 'Birth Month',
                'Required' => true,
            ],
            'BirthDay' => [
                'Type'     => 'integer',
                'Caption'  => 'Birth Day',
                'Required' => true,
            ],
            // set option
            // 'EarningPreference' => [
            // 	'Type' => 'string',
            // 	'Caption' => 'Earning Preference',
            // 	'Required' => true,
            // 	'Options' => self::$earningPreferences,
            // ],
            // 'PartnerMembershipNumber' => [
            // 	'Type' => 'string',
            // 	'Caption' => 'Partner Membership Number',
            // 	'Required' => false,
            // ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password - must be between 6 and 18 characters long and should also contain at least 1 number.',
                'Required' => true,
            ],
        ];
    }
}
