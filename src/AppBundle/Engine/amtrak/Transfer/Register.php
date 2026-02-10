<?php

// case #10649

namespace AwardWallet\Engine\amtrak\Transfer;

class Register extends \TAccountCheckerAmtrak
{
    use \AwardWallet\Engine\ProxyList;

    public static $fieldMap = [
        'Email'    => 'member[emailaddress]',
        'Password' => [
            'member[password]',
            'member[passwordConfirm]',
        ],
        'FirstName' => 'member[firstName]',
        'LastName'  => 'member[LastName]',

        'AddressLine1'    => 'member[addresses.address0.addressLine1]',
        'City'            => 'member[addresses.address0.city]',
        'StateOrProvince' => 'member[addresses.address0.state]',
        'PostalCode'      => 'member[addresses.address0.zip]',

        'BillingAddressIsTheSameAsMailing' => 'member[billingsame]',

        'Phone'                      => 'member[phones.phone0.phonenumber]',
        'PhoneCountryCodeAlphabetic' => 'member[phones.phone0.countryCode]',
        'PhoneType'                  => 'member[phones.phone0.phoneType]',

        'FavoriteDepartureStation' => 'member[departOrigin_1]',
    ];

    public static $phoneMap = [
        'H' => 'home',
        'B' => 'business',
        'M' => 'mobile',
        'I' => 'international',
    ];

    public static $states = [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming',
        'AB' => 'Alberta',
        'BC' => 'British Columbia',
        'MB' => 'Manitoba',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland',
        'NT' => 'Northwest Territories',
        'NS' => 'Nova Scotia',
        'NU' => 'Nunavut',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Quebec',
        'SK' => 'Saskatchewan',
        'YT' => 'Yukon',
        'AS' => 'American Samoa',
        'FM' => 'Federated States of Micronesia',
        'GU' => 'Guam',
        'MH' => 'Marshall Islands',
        'MP' => 'Northern Mariana Islands',
        'PW' => 'Palau',
        'PR' => 'Puerto Rico',
        'VI' => 'U.S. Virgin Islands',
        'AA' => 'Armed Forces Americas (except Canada)',
        'AE' => 'Armed Forces Africa, Canada, Europe, Middle East',
        'AP' => 'Armed Forces Pacific',
    ];

    public static $countries = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AG' => 'Antigua and Barbuda',
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
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde',
        'KY' => 'Cayman Islands',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote Divoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'CD' => 'Democratic Republic of (Zaire)',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'TL' => 'East Timor',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
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
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
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
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Laos',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macau',
        'MK' => 'Macedonia',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
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
        'KP' => 'North Korea',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PS' => 'Palistine',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn Islands',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion Island (French)',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'SH' => 'Saint Helena',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'PM' => 'Saint Pierre and Miquelon',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia and Montenegro',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'KR' => 'South Korea',
        'ES' => 'Spain and Canary Islands',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen Islands',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau Islands',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VG' => 'Virgin Islands (British)',
        'VI' => 'Virgin Islands (US)',
        'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public static $phoneTypes = [
        'H' => 'home',
        'B' => 'business',
        'M' => 'mobile',
        'I' => 'international',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
        } elseif (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        }
    }

    public function registerAccount(array $fields)
    {
        $this->http->Log('>>> ' . __METHOD__);

        $this->http->log('[INFO] initial fields:');
        $this->http->log(json_encode($fields, JSON_PRETTY_PRINT));

        $fields['Phone'] = $this->getPhone($fields);
        $fields['BillingAddressIsTheSameAsMailing'] = '1';

        $this->checkData($fields);

        $this->http->getUrl('https://www.amtrakguestrewards.com/members/new');

        if (!$this->http->parseForm(null, 1, true, '//form[@action = "/members/create"]')) {
            $this->http->log('>>> failed to parse reg form');

            return false;
        }

        $this->populateForm($fields);
        $this->http->postForm();

        $this->checkStatus();

        return true;
    }

    public function getRegisterFields()
    {
        return [
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (must be at least 8 characters and include at least 1 alpha characte, 1 number or special character)',
                'Required' => true,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Mailing US Address Line',
                'Required' => true,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'Mailing US Address City',
                'Required' => true,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'Mailing US Address State',
                'Required' => true,
                'Options'  => self::$states,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Mailing Country',
                'Required' => true,
                'Options'  => [
                    'US' => 'United States',
                    'CA' => 'Canada',
                ],
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Mailing Address ZIP/Postal Code',
                'Required' => true,
            ],
            'PhoneType' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Type (if you reside outside of the U.S. and Canada, please choose an international phone type)',
                'Required' => true,
                'Options'  => self::$phoneTypes,
            ],
            'PhoneCountryCodeAlphabetic' => [
                'Type'     => 'string',
                'Caption'  => '2-letter Phone Country Code, required only for international phone numbers',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'PhoneAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Area Code',
                'Required' => true,
            ],
            'PhoneLocalNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number',
                'Required' => true,
            ],
            'FavoriteDepartureStation' => [
                'Type'     => 'string',
                'Caption'  => 'Favorite departure station, for example: NYP, NRK, EWR. You can always change it later in your profile.',
                'Required' => true,
            ],
        ];
    }

    private function checkData($fields)
    {
        $password = $fields['Password'];

        if (strlen(trim($password)) < 8
                || !preg_match('/[a-z]/i', $password)
                || !preg_match('/\d/', $password)) {
            $msg = $this->getRegisterFields()['Password']['Caption'];

            throw new \UserInputError($msg);
        }
    }

    private function checkStatus()
    {
        $account = $this->http->findSingleNode('//*[contains(@class, "userName")]/span[1]');

        if ($account) {
            $msg = "Successfull registration, your member $account";
            $this->ErrorMessage = $msg;
            $this->http->log($msg);

            return true;
        }

        $errors = $this->http->findNodes('//div[
			not(contains(@class, "hidden")) and
			contains(@class, "error")]
		/p[1]');

        if ($errors) {
            $labels = $this->http->findNodes('//div[
				not(contains(@class, "hidden")) and
				contains(@class, "error")]
			/p[1]/following::label[1]');
            $msg = [];

            foreach ($labels as $i => $label) {
                $msg[] = sprintf('%s (%s)', $label, $errors[$i]);
            }
            $msg = implode(', ', $msg);
            $msg = 'Invalid values in fields: ' . $msg;

            $msg = preg_replace('/[*?]/', '', $msg);
            $msg = preg_replace('/\bExt\b/', 'Phone', $msg); // hack, missing label for phone
            $this->http->log($msg);

            throw new \UserInputError($msg); // Is it always user error
        }

        throw new \EngineError('Unexpected response on account registration request');
    }

    private function populateForm($fields)
    {
        foreach (self::$fieldMap as $awkey => $keys) {
            if (!arrayVal($fields, $awkey)) {
                continue;
            }

            if (!is_array($keys)) {
                $keys = [$keys];
            }

            foreach ($keys as $k) {
                $this->http->setInputValue($k, $fields[$awkey]);
            }
        }
    }

    private function getPhone(array $fields)
    {
        return sprintf('%s%s',
            $fields['PhoneAreaCode'],
            $fields['PhoneLocalNumber']
        );
    }
}
