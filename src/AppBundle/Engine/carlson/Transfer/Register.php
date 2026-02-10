<?php

// case #10387

namespace AwardWallet\Engine\carlson\Transfer;

class Register extends \TAccountCheckerCarlson
{
    public static $preferredLanguages = [
        'en'      => 'English',
        'da'      => 'Dansk',
        'de'      => 'Deutsch',
        'fr'      => 'Français',
        'no'      => 'Norsk',
        'es'      => 'Español',
        'sv'      => 'Svenska',
        'zh-Hant' => '中文',
        'zh-Hans' => '简体中文',
        'fi'      => 'Suomi',
        'ru'      => 'Русский',
    ];

    public static $inputFieldsMap = [
        'Email'                   => 'email',
        'Title'                   => 'title',
        'FirstName'               => 'firstName',
        'LastName'                => 'lastName',
        'AddressLine1'            => 'address1',
        'AddressLine2'            => 'address2',
        'AddressLine3'            => 'address3',
        'City'                    => 'city',
        'StateOrProvince '        => 'state',
        'PostalCode'              => 'postal',
        'Country'                 => 'country',
        'PhoneCountryCodeNumeric' => false,
        'PhoneAreaCode'           => false,
        'PhoneLocalNumber'        => false,
        'PreferredLanguage'       => 'language',
        'Password'                =>
            [
                0 => 'password',
                1 => 'confirmPassword',
            ],
        'PromoCode'                                    => 'enrollmentCode',
        'ReceiveNewsAndOffers'                         => 'emailSpecialOffers',
        'ReceiveAPrintedMemberCardAndProgramMaterials' => 'sendPrintedCard',
    ];

    public static $states = [
        'US AL' => 'Alabama',
        'US AK' => 'Alaska',
        'US AZ' => 'Arizona',
        'US AR' => 'Arkansas',
        'US AS' => 'American Samoa',
        'US CA' => 'California',
        'US CO' => 'Colorado',
        'US CT' => 'Connecticut',
        'US DE' => 'Delaware',
        'US DC' => 'District of Columbia',
        'US FL' => 'Florida',
        'US GA' => 'Georgia',
        'US GU' => 'Guam',
        'US HI' => 'Hawaii',
        'US ID' => 'Idaho',
        'US IL' => 'Illinois',
        'US IN' => 'Indiana',
        'US IA' => 'Iowa',
        'US KS' => 'Kansas',
        'US KY' => 'Kentucky',
        'US LA' => 'Louisiana',
        'US ME' => 'Maine',
        'US MD' => 'Maryland',
        'US MA' => 'Massachusetts',
        'US MI' => 'Michigan',
        'US MN' => 'Minnesota',
        'US MS' => 'Mississippi',
        'US MO' => 'Missouri',
        'US MT' => 'Montana',
        'US NE' => 'Nebraska',
        'US NV' => 'Nevada',
        'US NH' => 'New Hampshire',
        'US NJ' => 'New Jersey',
        'US NM' => 'New Mexico',
        'US NY' => 'New York',
        'US NC' => 'North Carolina',
        'US ND' => 'North Dakota',
        'US MP' => 'Nrthn Mariana is',
        'US OH' => 'Ohio',
        'US OK' => 'Oklahoma',
        'US OR' => 'Oregon',
        'US PA' => 'Pennsylvania',
        'US PR' => 'Puerto Rico',
        'US RI' => 'Rhode Island',
        'US SC' => 'South Carolina',
        'US SD' => 'South Dakota',
        'US TN' => 'Tennessee',
        'US TX' => 'Texas',
        'US UM' => 'US Minor Outlying is',
        'US VI' => 'US Virgin Islands',
        'US UT' => 'Utah',
        'US VT' => 'Vermont',
        'US VA' => 'Virginia',
        'US WA' => 'Washington',
        'US WV' => 'West Virginia',
        'US WI' => 'Wisconsin',
        'US WY' => 'Wyoming',
    ];

    public static $countries = [
        'AF' => 'Afghanistan',
        'AX' => 'Aland Islands',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
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
        'BA' => 'Bosnia Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Terr',
        'VG' => 'British Virgin Islands',
        'BN' => 'Brunei',
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
        'CC' => 'Cocos (keeling) Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros Isl',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D\'ivoire',
        'HR' => 'Croatia',
        'CW' => 'Curacao',
        'CY' => 'Cyprus',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Rep',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji Islands',
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
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
        'GW' => 'Guinea Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'VA' => 'Holy See (vatican City State)',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IM' => 'Isle of Man',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JE' => 'Jersey',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KR' => 'Korea, Republic of',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Laos',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macao',
        'MK' => 'Macedonia',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia, Federated States',
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
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
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
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthelemy',
        'KN' => 'Saint Kitts and Nevis',
        'WS' => 'Samoa',
        'AS' => 'Samoa American',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SX' => 'Sint Maarten (dutch Part)',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'LC' => 'St Lucia',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'VI' => 'US Virgin Islands',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VE' => 'Venezuela',
        'VN' => 'Viet Nam',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'AG' => 'Antigua/Barbuda',
        'BQ' => 'Bonaire, St Eustatius, Saba',
        'CD' => 'Congo, Democratic Republic',
        'CG' => 'Congo-Brazzv',
        'CK' => 'Cook Isl',
        'CZ' => 'Czech Rep.',
        'HM' => 'Heard Island and Mcdonald Isls',
        'MH' => 'Marshall Isl/Mariana Isl',
        'MP' => 'Northern Mariana Isl',
        'PW' => 'Palau, Republic of',
        'PS' => 'Palestinian Territory Occupied',
        'RE' => 'Reunion Isl',
        'SC' => 'Seychelles Isl',
        'GS' => 'So. Georgia and So Sand Is.',
        'SB' => 'Solomon Isl',
        'SH' => 'St Helena, Ascension, Tristan',
        'MF' => 'St Martin',
        'PM' => 'St Pierre Et Miquelon',
        'VC' => 'St Vincent - The Grenadines',
        'TC' => 'Turks and Caicos Isl',
        'UM' => 'US Minor Outlying Islands',
        'WF' => 'Wallis and Futuna Isl',
    ];

    public static $phoneCountryCodes = [
        'AD' => '376',
        'AE' => '971',
        'AF' => '93',
        'AG' => '1',
        'AI' => '1',
        'AL' => '355',
        'AM' => '374',
        'AN' => '599',
        'AO' => '244',
        'AQ' => '672',
        'AR' => '54',
        'AS' => '1',
        'AT' => '43',
        'AU' => '61',
        'AW' => '297',
        'AX' => '358',
        'AZ' => '994',
        'BA' => '387',
        'BB' => '1',
        'BD' => '880',
        'BE' => '32',
        'BF' => '226',
        'BG' => '359',
        'BH' => '973',
        'BI' => '257',
        'BJ' => '229',
        'BL' => '590',
        'BM' => '1',
        'BN' => '673',
        'BO' => '591',
        'BQ' => '599',
        'BR' => '55',
        'BS' => '1',
        'BT' => '975',
        'BV' => '47',
        'BW' => '267',
        'BY' => '375',
        'BZ' => '501',
        'CA' => '1',
        'CC' => '61',
        'CD' => '243',
        'CF' => '236',
        'CG' => '242',
        'CH' => '41',
        'CI' => '225',
        'CK' => '682',
        'CL' => '56',
        'CM' => '237',
        'CN' => '86',
        'CO' => '57',
        'CR' => '506',
        'CU' => '53',
        'CV' => '238',
        'CW' => '599',
        'CX' => '61',
        'CY' => '357',
        'CZ' => '420',
        'DE' => '49',
        'DJ' => '253',
        'DK' => '45',
        'DM' => '1',
        'DO' => '1',
        'DZ' => '213',
        'EC' => '593',
        'EE' => '372',
        'EG' => '20',
        'EH' => '212',
        'ER' => '291',
        'ES' => '34',
        'ET' => '251',
        'FI' => '358',
        'FJ' => '679',
        'FK' => '500',
        'FM' => '691',
        'FO' => '298',
        'FR' => '33',
        'GA' => '241',
        'GB' => '44',
        'GD' => '1',
        'GE' => '995',
        'GF' => '594',
        'GG' => '44',
        'GH' => '233',
        'GI' => '350',
        'GL' => '299',
        'GM' => '220',
        'GN' => '224',
        'GP' => '590',
        'GQ' => '240',
        'GR' => '30',
        'GS' => '870',
        'GT' => '502',
        'GU' => '1',
        'GW' => '245',
        'GY' => '592',
        'HK' => '852',
        'HN' => '504',
        'HR' => '385',
        'HT' => '509',
        'HU' => '36',
        'ID' => '62',
        'IE' => '353',
        'IL' => '972',
        'IM' => '44',
        'IN' => '91',
        'IO' => '246',
        'IQ' => '964',
        'IR' => '98',
        'IS' => '354',
        'IT' => '39',
        'JE' => '44',
        'JM' => '1',
        'JO' => '962',
        'JP' => '81',
        'KE' => '254',
        'KG' => '996',
        'KH' => '855',
        'KI' => '686',
        'KM' => '269',
        'KN' => '869',
        'KP' => '850',
        'KR' => '82',
        'KW' => '965',
        'KY' => '1',
        'KZ' => '7',
        'LA' => '856',
        'LB' => '961',
        'LC' => '1',
        'LI' => '423',
        'LK' => '94',
        'LR' => '231',
        'LS' => '266',
        'LT' => '370',
        'LU' => '352',
        'LV' => '371',
        'LY' => '218',
        'MA' => '212',
        'MC' => '377',
        'MD' => '373',
        'ME' => '382',
        'MF' => '1',
        'MG' => '261',
        'MH' => '692',
        'MK' => '389',
        'ML' => '223',
        'MM' => '95',
        'MN' => '976',
        'MO' => '853',
        'MP' => '1',
        'MQ' => '596',
        'MR' => '222',
        'MS' => '1',
        'MT' => '356',
        'MU' => '230',
        'MV' => '960',
        'MW' => '265',
        'MX' => '52',
        'MY' => '60',
        'MZ' => '258',
        'NA' => '264',
        'NC' => '687',
        'NE' => '227',
        'NF' => '672',
        'NG' => '234',
        'NI' => '505',
        'NL' => '31',
        'NO' => '47',
        'NP' => '977',
        'NR' => '674',
        'NU' => '683',
        'NZ' => '64',
        'OM' => '968',
        'PA' => '507',
        'PE' => '51',
        'PF' => '689',
        'PG' => '675',
        'PH' => '63',
        'PK' => '92',
        'PL' => '48',
        'PM' => '508',
        'PN' => '870',
        'PR' => '1',
        'PS' => '970',
        'PT' => '351',
        'PW' => '680',
        'PY' => '595',
        'QA' => '974',
        'RE' => '262',
        'RO' => '40',
        'RS' => '38',
        'RU' => '7',
        'RW' => '250',
        'SA' => '966',
        'SB' => '677',
        'SC' => '248',
        'SD' => '249',
        'SE' => '46',
        'SG' => '65',
        'SH' => '290',
        'SI' => '386',
        'SJ' => '47',
        'SK' => '421',
        'SL' => '232',
        'SM' => '378',
        'SN' => '221',
        'SO' => '252',
        'SR' => '597',
        'SS' => '211',
        'ST' => '239',
        'SV' => '503',
        'SX' => '599',
        'SY' => '963',
        'SZ' => '268',
        'TC' => '1',
        'TD' => '235',
        'TG' => '228',
        'TH' => '66',
        'TJ' => '992',
        'TK' => '690',
        'TL' => '670',
        'TM' => '993',
        'TN' => '216',
        'TO' => '676',
        'TR' => '90',
        'TT' => '1',
        'TV' => '688',
        'TW' => '886',
        'TZ' => '255',
        'UA' => '380',
        'UG' => '256',
        'UM' => '1',
        'US' => '1',
        'UY' => '598',
        'UZ' => '998',
        'VA' => '39',
        'VC' => '1',
        'VE' => '58',
        'VG' => '1',
        'VI' => '1',
        'VN' => '84',
        'VU' => '678',
        'WF' => '681',
        'WS' => '685',
        'YE' => '967',
        'YT' => '262',
        'ZA' => '27',
        'ZM' => '260',
        'ZW' => '263',
    ];

    protected $registerUrl = 'https://www.clubcarlson.com/profiles/secure/joinRouter.do';

    protected $languageMap = [
        'en'      => 'en',
        'da'      => 'da',
        'de'      => 'de',
        'fr'      => 'fr',
        'no'      => 'no',
        'es'      => 'es',
        'sv'      => 'sv',
        'zh-Hant' => 'zh_TW',
        'zh-Hans' => 'zh_CN',
        'fi'      => 'fi',
        'ru'      => 'ru',
    ];

    // InitBrowser from TAccountCheckerCarlson
    /*
    public function InitBrowser() {
        $this->UseCurlBrowser();
        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $proxy = $this->http->getLiveProxy($this->registerUrl);
            $this->http->SetProxy($proxy);
        }
    }
    */

    public function registerAccount(array $fields)
    {
        //$this->ArchiveLogs = true;

        $this->http->log('[INFO] initial fields:');
        $this->http->log(json_encode($fields, JSON_PRETTY_PRINT));

        $fields['PreferredLanguage'] = $this->languageMap[$fields['PreferredLanguage']];
        $fields['PhoneCountryCodeNumeric'] = self::$phoneCountryCodes[$fields['Country']];

        if (!in_array($fields['Country'], array_keys(self::$countries))) {
            throw new \UserInputError('Invalid country code');
        }

        $this->http->GetURL($this->registerUrl);

        if ($this->http->Response['code'] === 403) {
            throw new \UserInputError('Site error, please try again later');
        }

        $status = $this->http->ParseForm('createAccountForm');

        if (!$status) {
            $this->http->Log('Failed to parse create account form (step 1)');

            return false;
        }

        $this->http->SetInputValue('email', $fields['Email']);

        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post create account form (step 1)');

            return false;
        }

        $status = $this->http->ParseForm('createAccountForm');

        if (!$status) {
            $this->http->Log('Failed to parse create account form (step 2)');

            if ($error = $this->http->FindSingleNode("//div[contains(text(), 'account already exists')]")) {
                throw new \UserInputError($error);
            }

            return false;
        }

        //		$sessionId = $this->http->getCookieByName('JSESSIONID');
        //		if (!$sessionId)
        //			throw new \EngineError('Couldn\'t get session id');
        //		$this->http->FormURL = "https://www.clubcarlson.com/profiles/secure/createaccount.do;jsessionid=$sessionId";

        foreach (['ReceiveNewsAndOffers' => 'true', 'ReceiveAPrintedMemberCardAndProgramMaterials' => 'on'] as $key => $provValue) {
            // TODO: Check flags
            $awValue = $fields[$key];
            unset($fields[$key]);
            $provKey = self::$inputFieldsMap[$key];

            if ($awValue === true) {
                $this->http->SetInputValue($provKey, $provValue);
            } elseif ($awValue === false) {
                unset($this->http->Form[$provKey]);
            } else {
                throw new \UserInputError("Invalid value, $key field should be only true or false");
            }
        }

        $captcha = $this->recognizeCaptcha();

        if ($captcha === false) {
            $this->http->Log('Failed to recognize captcha', LOG_LEVEL_ERROR);

            return false;
        }
        $this->http->SetInputValue('captcha', $captcha);

        // TODO: Move this loop to some generic method
        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey]) or $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $this->http->SetInputValue($provKey, $fields[$awKey]);
            }
        }

        $this->http->SetInputValue('countryPrefix', $fields['PhoneCountryCodeNumeric']);
        $this->http->SetInputValue('phoneNumber', $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber']);
        //		$this->http->SetInputValue('areaCode', ...);
        //		$this->http->SetInputValue('centralOfficeNumber', ...);
        //		$this->http->SetInputValue('subscriberNumber', ...);

        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post create account form (step 2)');

            return false;
        }

        if ($err = $this->http->FindPreg('#The\s+website\s+was\s+unable\s+to\s+complete\s+your\s+task\.\s+Please\s+try\s+again\.#i')) {
            throw new \ProviderError($err);
        }

        if ($err = $this->http->FindPreg('#We\s+are\s+sorry\s+but\s+we\s+have\s+encountered\s+an\s+unexpected\s+error\s+processing\s+your\s+request\.\s+Please\s+contact\s+customer\s+support\s+to\s+fulfill\s+your\s+request\.#i')) {
            throw new \ProviderError($err);
        }

        if ($errors = $this->http->FindNodes('//div[@class="errors"]')) {
            throw new \UserInputError(implode($errors));
        } // Is it always user error?

        $successMessageVariants = [
            'en' => 'account\s+has\s+been\s+created',
            'da' => 'konto\s+er\s+oprettet',
            'de' => 'Konto\s+wurde\s+erstellt',
            'fr' => 'Votre\s+compte\s+Club\s+Carlson\s+a\s+\S+?t\S+?\s+cr\S+',
            'no' => 'Din\s+konto\s+i\s+Club\s+Carlson\S*?\s*er\s+opprettet',
            'es' => 'Se\s+ha\s+creado\s+su\s+cuenta\s+del\s+Club\s+Carlson',
            'sv' => 'Ditt\s+Club\s+Carlson-konto\s+har\s+skapats',
            // 'zh_TW' same as 'en'
            // 您的卡尔森俱乐部帐户已创建。
            'zh_CN' => preg_quote('&#24744;&#30340;&#21345;&#23572;&#26862;&#20465;&#20048;&#37096;&#24080;&#25143;&#24050;&#21019;&#24314;&#12290;', '#'),
            'fi'    => 'tilisi\s+on\s+luotu',
            // Ваш счёт участника программы Club Carlson  уже создан
            'ru' => preg_quote('&#1042;&#1072;&#1096; &#1089;&#1095;&#1105;&#1090; &#1091;&#1095;&#1072;&#1089;&#1090;&#1085;&#1080;&#1082;&#1072; &#1087;&#1088;&#1086;&#1075;&#1088;&#1072;&#1084;&#1084;&#1099;', '#') . '\s+Club\s+Carlson' . preg_quote('&#160; &#1091;&#1078;&#1077; &#1089;&#1086;&#1079;&#1076;&#1072;&#1085;', '#'),
        ];
        $memberNumberVariants = [
            'en' => 'member\s+number\s+is',
            'da' => 'medlemsnummer\s+er',
            'de' => 'Mitgliedsnummer\s+lautet',
            'fr' => 'Votre\s+num\S+?ro\s+de\s+membre\s+Club\s+Carlson\s*.*?\s+est',
            'no' => 'Ditt\s+medlemsnummer\s+i\s+Club\s+Carlson.*?\s+er',
            'es' => 'Su\s+n\S+?mero\s+de\s+socio\s+del\s+Club\s+Carlson.*?\s+es',
            'sv' => 'medlemsnummer\s+\S+?r',
            // 'zh_TW' same as 'en'
            // 您的卡尔森俱乐部SM会员编号为
            'zh_CN' => preg_quote('&#x60A8;&#x7684;&#x5361;&#x5C14;&#x68EE;&#x4FF1;&#x4E50;&#x90E8;', '#') . '.*?' . preg_quote('&#x4F1A;&#x5458;&#x7F16;&#x53F7;&#x4E3A;', '#'),
            'fi'    => 'j\S+?sennumerosi\s+on',
            // Ваш номер участника программы Club CarlsonSM —
            'ru' => preg_quote('&#x412;&#x430;&#x448; &#x43D;&#x43E;&#x43C;&#x435;&#x440; &#x443;&#x447;&#x430;&#x441;&#x442;&#x43D;&#x438;&#x43A;&#x430; &#x43F;&#x440;&#x43E;&#x433;&#x440;&#x430;&#x43C;&#x43C;&#x44B;', '#') . '\s+Club\s+Carlson.*?' . preg_quote('&#xA0;&#x2014;', '#'),
        ];

        if ($successMessage = $this->http->FindPreg('#' . implode('|', array_values($successMessageVariants)) . '#ui')) {
            $this->http->Log('Registration succeeded');
            $successMessage = ucfirst(trim($successMessage, '.'));

            if ($memberNumber = $this->http->FindPreg('#(?:' . implode('|', array_values($memberNumberVariants)) . ')\s+\d+#ui')) {
                $successMessage .= '. ' . ucfirst($memberNumber);
            }
            $successMessage = strip_tags($successMessage);
            $this->http->Log($successMessage);
            $this->ErrorMessage = $successMessage;

            return true;
        }

        return false;
    }

    public function recognizeCaptcha()
    {
        $file = $this->http->DownloadFile('https://www.clubcarlson.com/captcha/capjpg', 'jpeg');
        $this->http->Log("Captcha: " . $file);
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);

        try {
            $captcha = str_replace(' ', '', $recognizer->recognizeFile($file));
        } catch (\CaptchaException $e) {
            $this->http->Log("Captcha recognition exception: " . $e->getMessage(), LOG_LEVEL_ERROR);

            return false;
        }
        unlink($file);

        return $captcha;
    }

    // TODO: Fill states for non-US countries
    public function getRegisterFields()
    {
        return [
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email',
                    'Required' => true,
                ],
            'Title' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Title',
                    'Required' => true,
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
            'AddressLine1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Line 1',
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
            'PostalCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Postal Code',
                    'Required' => true,
                ],
            'Country' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Country, 2 letter code',
                    'Required' => true,
                    'Options'  => self::$countries,
                ],
            'PhoneAreaCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Area Code, has to correspond to the Country',
                    'Required' => true,
                ],
            'PhoneLocalNumber' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Local Number',
                    'Required' => true,
                ],
            'PreferredLanguage' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Preferred Language',
                    'Required' => true,
                    'Options'  => self::$preferredLanguages,
                ],
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password. It must be 8 to 20 characters long, contain at least one number, one lowercase letter, one uppercase letter and one of these special symbols: ! @ # $ % ^ & * ( ) + ?.',
                    'Required' => true,
                ],
            'PromoCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Promotional Code',
                    'Required' => false,
                ],
            'ReceiveNewsAndOffers' =>
                [
                    'Type'     => 'boolean',
                    'Caption'  => 'Receive updates on news and special offers from Club Carlson and Carlson Rezidor hotels',
                    'Required' => true,
                ],
            'ReceiveAPrintedMemberCardAndProgramMaterials' =>
                [
                    'Type'     => 'boolean',
                    'Caption'  => 'Receive a printed member card and program materials',
                    'Required' => true,
                ],
        ];
    }
}
