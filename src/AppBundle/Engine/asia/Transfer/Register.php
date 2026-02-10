<?php

namespace AwardWallet\Engine\asia\Transfer;

class Register extends \TAccountCheckerAsia
{
    public static $titles = [
        'MR'   => 'Mr',
        'MISS' => 'Miss',
        'MS'   => 'Ms',
        'MRS'  => 'Mrs',
        'DR'   => 'Dr',
        'PROF' => 'Professor',
    ];

    public static $nameFormats = [
        'FG' => 'LastName FirstName',
        'GF' => 'FirstName LastName',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $nationalities = [
        'US' => 'American',
        'AU' => 'Australian',
        'AT' => 'Austrian',
        'BH' => 'Bahraini',
        'BD' => 'Bangladeshi',
        'BE' => 'Belgian',
        'GB' => 'British',
        'BN' => 'Bruneian',
        'KH' => 'Cambodian',
        'CA' => 'Canadian',
        'CN' => 'Chinese',
        'DK' => 'Danish',
        'NL' => 'Dutch',
        'PH' => 'Filipino',
        'FI' => 'Finnish',
        'FR' => 'French',
        'DE' => 'German',
        'GR' => 'Greek',
        'HK' => 'Hong Kong',
        'HU' => 'Hungarian',
        'IN' => 'Indian',
        'ID' => 'Indonesian',
        'IE' => 'Irish',
        'IT' => 'Italian',
        'JP' => 'Japanese',
        'KR' => 'Korean',
        'LU' => 'Luxembourger',
        'MY' => 'Malaysian',
        'MU' => 'Mauritius',
        'MC' => 'Monaco',
        'AE' => 'National Of Uae',
        'NP' => 'Nepalese',
        'PG' => 'New Guinean',
        'NZ' => 'New Zealander',
        'NO' => 'Norwegian',
        'PK' => 'Pakistani',
        'PL' => 'Polish',
        'PT' => 'Portuguese',
        'RU' => 'Russian',
        'SA' => 'Saudi Arabian',
        'SG' => 'Singaporean',
        'ZA' => 'South African',
        'ES' => 'Spanish',
        'LK' => 'Sri Lankan',
        'SE' => 'Swedish',
        'CH' => 'Swiss',
        'TW' => 'Taiwanese',
        'TH' => 'Thai',
        'TR' => 'Turkish',
        'VN' => 'Vietnamese',
        'XY' => 'Others',
    ];

    public static $preferredLanguages = [
        'en'      => 'English',
        'ja'      => 'Japanese',
        'ko'      => 'Korean',
        'zh-Hans' => 'Simplified Chinese',
        'zh-Hant' => 'Traditional Chinese',
    ];

    public static $addressTypes = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $countries = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antartica',
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
        'BA' => 'Bosnia Hercegovina',
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
        'KY' => 'Caynan Islands',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Chrismas Island (Indian Ocean)',
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
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'TL' => 'Timor Leste',
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
        'TF' => 'French Southern Territories',
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
        'HK' => 'Hong Kong,SAR',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran (Islamic Republic Of)',
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
        'KP' => 'Korea, Democratic People\'s Republic',
        'KR' => 'Korea, Republic Of',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgstan',
        'LA' => 'Lao People\'s Democratic Republic',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macau, SAR',
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
        'MU' => 'Mauritus',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova, Republic Of',
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
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'GS' => 'S Seorgia & S Sandwich Island',
        'KN' => 'Saint Kitts And Nevis',
        'LC' => 'Saint Lucia',
        'VC' => 'Saint Vincent And The Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome And Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia & Montenegro',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'ES' => 'Spain And Canary Islands',
        'LK' => 'Sri Lanka',
        'SH' => 'St. Helena',
        'PM' => 'St. Pierre And Miquelon',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard And Jan Mayen Islands',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TJ' => 'Tadjikistan',
        'TW' => 'Taiwan',
        'TZ' => 'Tanzania, United Republic',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad And Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks And Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UM' => 'United States Minor Outlying Island',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City State (Holy See)',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VG' => 'Virgin Islands, (British)',
        'VI' => 'Virgin Islands, (U.S.)',
        'WF' => 'Wallis And Futuna Islands',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen, Republic Of',
        'CD' => 'Zaire',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'HM' => 'Heard & Mc Donald Islands',
    ];

    public static $emailFormats = [
        'H' => 'HTML',
        'T' => 'Text',
    ];

    public static $phoneTypes = [
        'H' => 'Home',
        'B' => 'Business',
        'M' => 'Mobile',
    ];

    protected $languageMap = [
        'en'      => 'EN',
        'ja'      => 'JA',
        'ko'      => 'KO',
        'zh-Hans' => 'ZH',
        'zh-Hant' => 'CT',
    ];

    public function registerAccount(array $fields)
    {
        $fields['PreferredLanguage'] = $this->languageMap[$fields['PreferredLanguage']];
        $this->ArchiveLogs = true;
        $this->http->GetURL('https://www.asiamiles.com/am/en/about/join?WAright=JoinNow');

        if (!$this->http->ParseForm('enrolForm')) {
            return false;
        }

        foreach ([
            'LastName',
            'FirstName',
            'Company',
            'JobTitle',
            'AddressLine1',
            'AddressLine2',
            'AddressLine3',
            'City',
            'StateOrProvince',
        ] as $f) {
            $fields[$f] = strtoupper($fields[$f]);
        }
        $nat = $this->http->FindNodes('//select[@id="nationality"]/option[@value!=""]/@value');

        if (empty($nat)) {
            return false;
        }

        if (!in_array($fields['Nationality'], $nat)) {
            throw new \UserInputError('Invalid nationality');
        }
        $ctr = $this->http->FindNodes('//select[@id="CountryCode"]/option[@value!=""]/@value');

        if (empty($ctr)) {
            return false;
        }

        if (!in_array($fields['Country'], $ctr)) {
            throw new \UserInputError('Invalid address country');
        }

        // Provider uses wrong country codes for:
        // - Serbia & Montenegro (CS instead of standard RS)
        // - Zaire (ZR instead of standard CD)
        // Map from our standard ISO code to wrong code used by provider
        $wrongCountryCodesFixingMap = [
            'RS' => 'CS',
            'CD' => 'ZR',
        ];

        if (isset($wrongCountryCodesFixingMap[$fields['Country']])) {
            $origCountryCode = $fields['Country'];
            $fields['Country'] = $wrongCountryCodesFixingMap[$fields['Country']];
            $this->logger->debug('Mapped standard country code "' . $origCountryCode . '" to provider code "' . $fields['Country'] . '"');
        }

        foreach (['BirthDay', 'BirthMonth'] as $f) {
            $v = (string) intval($fields[$f]);

            if (strlen($v) < 2) {
                $v = '0' . $v;
            }
            $fields[$f] = $v;
        }

        if ($fields['PhoneType'] == 'B') {
            $fields['PhoneType'] = 'BUS';
        }

        foreach ([
            'username' => 'Username',
            'TITLE' => 'Title',
            'fName' => 'LastName',
            'gName' => 'FirstName',
            'nameOnCard' => 'NameFormat',
            'gender' => 'Gender',
            'birthYear' => 'BirthYear',
            'birthMonth' => 'BirthMonth',
            'birthDay' => 'BirthDay',
            'nationality' => 'Nationality',
            'pin' => 'Password',
            'pin2' => 'Password',
            'prefWLang' => 'PreferredLanguage',
            'addrType' => 'AddressType',
            'company' => 'Company',
            'co' => 'JobTitle',
            'CountryCode' => 'Country',
            'Addr1' => 'AddressLine1',
            'Addr2' => 'AddressLine2',
            'Addr3' => 'AddressLine3',
            'City' => 'City',
            'State' => 'StateOrProvince',
            'Postal' => 'PostalCode',
            'email' => 'Email',
            'emailFormat' => 'EmailFormat',
        ] as $n => $f) {
            $this->http->Form[$n] = $fields[$f];
        }

        switch ($fields['PhoneType']) {
            case 'H':
            case 'BUS':
                $phone = [
                    'phonePref' => 'PhoneType',
                    'phoneCtry' => 'PhoneCountryCodeNumeric',
                    'phoneArea' => 'PhoneAreaCode',
                    'phoneNum'  => 'PhoneLocalNumber',
                ];

                break;

            case 'M':
                $phone = [
                    'mobileCtry' => 'PhoneCountryCodeNumeric',
                    'mobileArea' => 'PhoneAreaCode',
                    'mobileNum'  => 'PhoneLocalNumber',
                ];

                break;

            default:
                throw new \UserInputError('Invalid phone type');
        }

        foreach ($phone as $n => $f) {
            $this->http->Form[$n] = $fields[$f];
        }
        unset($this->http->Form['OptOne']);
        unset($this->http->Form['OptTwo']);
        $this->http->Form['isLL'] = 'false';
        $this->http->Form['IS_ADULT'] = 'true';

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($errors = $this->http->FindNodes('//div[@id="error_box"]/ul/li[contains(., "marked red below is incorrect")]/following-sibling::li')) {
            throw new \UserInputError(implode($errors));
        }

        if (!$this->http->ParseForm('enrolForm') || !$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Our records show that you may already be an Asia Miles member.")]')) {
            throw new \ProviderError('Our records show that you may already be an Asia Miles member.');
        }

        if ($number = $this->http->FindSingleNode('//input[@name="memID"]/@value')) {
            $this->http->Log('found autologin form and acc number');
            $this->ErrorMessage = 'Congratulations, your account has been created! Your membership number: ' . $number;

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Title' => [
                'Type'     => 'string',
                'Caption'  => 'Title',
                'Required' => true,
                'Options'  => self::$titles,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'Given Name, as it appears on your Passport / ID Card, in English',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Family Name, as it appears on your Passport / ID Card, in English',
                'Required' => true,
            ],
            'Username' => [
                'Type'     => 'string',
                'Caption'  => 'Username, 6 to 25 characters long',
                'Required' => true,
            ],
            'NameFormat' => [
                'Type'     => 'string',
                'Caption'  => 'Preferred name format on membership card',
                'Required' => true,
                'Options'  => self::$nameFormats,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            'BirthDay' => [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth Date',
                'Required' => true,
            ],
            'BirthMonth' => [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth Date',
                'Required' => true,
            ],
            'BirthYear' => [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date',
                'Required' => true,
            ],
            'Nationality' => [
                'Type'     => 'string',
                'Caption'  => 'Nationality, 2 letter country code',
                'Required' => true,
                'Options'  => self::$nationalities,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'PIN, 6 to 8 digits',
                'Required' => true,
            ],
            'PreferredLanguage' => [
                'Type'     => 'string',
                'Caption'  => 'Preferred language',
                'Required' => true,
                'Options'  => self::$preferredLanguages,
            ],
            'AddressType' => [
                'Type'     => 'string',
                'Caption'  => 'Mailing Address Type',
                'Required' => true,
                'Options'  => self::$addressTypes,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => '2 letter country code',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State / Province / Territory',
                'Required' => false,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City',
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
            'AddressLine3' => [
                'Type'     => 'string',
                'Caption'  => 'Address Line 3',
                'Required' => false,
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => false,
            ],
            'Company' => [
                'Type'     => 'string',
                'Caption'  => 'Company name, required for work address',
                'Required' => false,
            ],
            'JobTitle' => [
                'Type'     => 'string',
                'Caption'  => 'Job Title, for work address',
                'Required' => false,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'EmailFormat' => [
                'Type'     => 'string',
                'Caption'  => 'Preferred Email Format',
                'Required' => true,
                'Options'  => self::$emailFormats,
            ],
            'PhoneType' => [
                'Type'     => 'string',
                'Caption'  => 'Primary phone type',
                'Required' => true,
                'Options'  => self::$phoneTypes,
            ],
            'PhoneCountryCodeNumeric' => [
                'Type'     => 'string',
                'Caption'  => 'Phone country code',
                'Required' => true,
            ],
            'PhoneAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Phone area code',
                'Required' => true,
            ],
            'PhoneLocalNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone number',
                'Required' => true,
            ],
        ];
    }
}
