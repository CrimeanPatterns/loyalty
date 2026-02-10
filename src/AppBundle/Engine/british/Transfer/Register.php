<?php

namespace AwardWallet\Engine\british\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use ProxyList;

    public static $titles = [
        'Mr'          => 'Mr',
        'Mrs'         => 'Mrs',
        'Miss'        => 'Miss',
        'Ms'          => 'Ms',
        'Mstr'        => 'Mstr',
        'Capt'        => 'Capt',
        'Prof'        => 'Prof',
        'Dr'          => 'Dr',
        'Dame'        => 'Dame',
        'Lady'        => 'Lady',
        'Lord'        => 'Lord',
        'The Rt Hon'  => 'The Rt Hon',
        'Rabbi'       => 'Rabbi',
        'Rev'         => 'Rev',
        'Sir'         => 'Sir',
        'Baroness'    => 'Baroness',
        'Baron'       => 'Baron',
        'Viscount'    => 'Viscount',
        'Viscountess' => 'Viscountess',
    ];

    public static $addressTypes = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $gender = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $countries = [
        'GB' => 'United Kingdom',
        'US' => 'USA',
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AG' => 'Antigua',
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
        'BJ' => 'Benin Republic',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BA' => 'Bosnia-Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory',
        'VG' => 'British Virgin Islands',
        'BN' => 'Brunei',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CA' => 'Canada',
        'CV' => 'Cape Verde',
        'KY' => 'Cayman Islands',
        'CF' => 'Central African Rep',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'CD' => 'Democratic Republic of Congo',
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
        'FO' => 'Faeroe Is',
        'FK' => 'Falkland Is',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guyana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Territories',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar (UK)',
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
        'HM' => 'Heard Island & McDonald Islands',
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
        'CI' => 'Ivory Coast',
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
        'MP' => 'Mariana Islands',
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'UM' => 'Minor Island',
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
        'AN' => 'Netherland Antilles',
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
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'KP' => 'Peoples Rep Korea',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'CM' => 'Republic Cameroon',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'SH' => 'Saint Helena',
        'PM' => 'Saint Pierre & Miguelon',
        'SM' => 'San Marino',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Island',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'KR' => 'South Korea',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'KN' => 'St Kitts and Nevis',
        'LC' => 'St Lucia',
        'VC' => 'St Vincent',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'ST' => 'São Tomé and Principe',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor - Leste',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks Caicos',
        'TV' => 'Tuvalu',
        'VI' => 'US Virgin Islands',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'WF' => 'Wallis and Futuna Islands',
        'EH' => 'Western Sahara',
        'WS' => 'Western Samoa',
        'YE' => 'Yemen Republic',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public static $preferredLanguages = [
        'de' => 'Deutsch',
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'it' => 'Italiano',
        'pl' => 'Polski',
        'pt' => 'Português',
        'sv' => 'Svenska',
        'zh' => '中文',
        'ja' => '日本語',
        'ru' => 'Русский',
        'ko' => '한국어',
    ];

    public static $inputFieldsMap = [
        'Title'             => 'title',
        'FirstName'         => 'firstname',
        'LastName'          => 'lastname',
        'Email'             => ['emailaddress', 'confirmemailaddress', 'username'],
        'Password'          => ['password', 'confirmpassword'],
        'AddressType'       => 'mail_pref',
        'AddressLine1'      => '%saddress1',
        'City'              => '%scity',
        'Country'           => '%scountry',
        'PreferredLanguage' => 'pref_language',
        'BirthDay'          => 'birthday',
        'BirthMonth'        => 'birthmonth',
        'BirthYear'         => 'birthyear',
        'Gender'            => 'gender',
        'PostalCode'        => '%spostalcode',
        'StateOrProvince'   => '%sstate',
        'Company'           => 'company',
    ];

    protected $languageMap = [
        'de' => 'DE',
        'en' => 'EN',
        'es' => 'ES',
        'fr' => 'FR',
        'it' => 'IT',
        'pl' => 'PL',
        'pt' => 'PT',
        'sv' => 'SV',
        'zh' => 'ZH',
        'ja' => 'JA',
        'ru' => 'RU',
        'ko' => 'KO',
    ];

    public function registerAccount(array $fields)
    {
        $fields['PreferredLanguage'] = $this->languageMap[$fields['PreferredLanguage']];
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        } else {
            $this->http->SetProxy('localhost:8000');
        }

        $this->http->GetURL('https://www.britishairways.com/travel/execenrol/public/en_us');

        $status = $this->http->ParseForm('passenger');

        if (!$status) {
            $message = 'Failed to parse create account form';
            $this->http->Log($message);

            throw new \EngineError($message);
        }

        $fields['BirthDay'] = ($fields['BirthDay'] + 0) < 10 ? '0' . ($fields['BirthDay'] + 0) : $fields['BirthDay'];
        $fields['BirthMonth'] = ($fields['BirthMonth'] + 0) < 10 ? '0' . ($fields['BirthMonth'] + 0) : $fields['BirthMonth'];
        $fields['Gender'] = self::$gender[$fields['Gender']];
        $addrType = self::$addressTypes[$fields['AddressType']];

        if ($fields['AddressType'] === 'H' && isset($fields['Company'])) {
            unset($fields['Company']);
        }

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey]) or $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $this->http->SetInputValue(sprintf($provKey, strtolower($addrType)), $fields[$awKey]);
            }
        }

        $addValues = [
            'useEmailAddress' => 'true',
            'mobilephone'     => '',
        ];

        foreach ($addValues as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        unset($this->http->Form['flexPreferenceQuestionId45_EXEC']);

        $status = $this->http->PostForm();

        if (!$status) {
            $message = 'Failed to post create account form';
            $this->http->Log($message);

            throw new \EngineError($message);
        }

        if ($successMessage = $this->http->FindSingleNode("//p[contains(text(),'membership number')]")) {
            $this->ErrorMessage = $successMessage . ' ' . $this->http->FindSingleNode("//p[contains(text(),'activate your')]");
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        $errors = $this->http->FindNodes("//div[@id='blsErrors']//li");

        if (!empty($errors)) {
            throw new \UserInputError(implode('; ', $errors));
        }

        throw new \EngineError('No errors, no success');
    }

    public function getRegisterFields()
    {
        return [
            'Title' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Title',
                    'Required' => true,
                    'Options'  => self::$titles,
                ],
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'First Name (as on your passport)',
                    'Required' => true,
                ],
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Family Name (as on your passport)',
                    'Required' => true,
                ],
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email',
                    'Required' => true,
                ],
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password (At least 6 characters using a mix of letters (English A-Z) and numbers)',
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
            'City' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'City',
                    'Required' => true,
                ],
            'Country' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Country of Residence',
                    'Required' => true,
                    'Options'  => self::$countries,
                ],
            'StateOrProvince' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'State Or Province (required for US, Canada)',
                    'Required' => false,
                ],
            'PostalCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Postal Code (required for US, Canada)',
                    'Required' => false,
                ],
            'Company' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Company name (required for business address type)',
                    'Required' => false,
                ],
            'PreferredLanguage' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Preferred Language',
                    'Required' => true,
                    'Options'  => self::$preferredLanguages,
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
                    'Caption'  => 'Year of Birth Date (You must be at least 18 years of age to apply for Executive Club membership. If you are under 18, you may join a household account.)',
                    'Required' => true,
                ],
            'Gender' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Gender',
                    'Required' => true,
                    'Options'  => self::$gender,
                ],
        ];
    }
}
