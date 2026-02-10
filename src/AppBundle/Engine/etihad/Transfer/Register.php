<?php

namespace AwardWallet\Engine\etihad\Transfer;

class Register extends \TAccountChecker
{
    public static $fieldMap = [
        'Title' => [
            'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$ddlTitle',
            'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$hfTitle',
        ],
        'Gender'    => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$rblGender',
        'FirstName' => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$txtFirstName',
        'LastName'  => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$txtLastName',
        'BirthDate' => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$txtDateOfBirth',
        'Email'     => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$txtEmail',
        'Password'  => [
            'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$txtPassword',
            'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$txtPassWordConfirm',
        ],
        // the site just needs country code
        'Country'                 => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$hfSelectedCountry',
        'AddressLine1'            => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$txtAddressLine1',
        'City'                    => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$txtCity',
        'POBox'                   => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$txtPoBoxNo',
        'ContactNumber'           => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$hfContactNumber',
        'PhoneType'               => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$uc_ContactNumbers$ddlNumberTypeFirst',
        'PhoneCountryCodeNumeric' => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$uc_ContactNumbers$txtCountryCodeFirst',
        'PhoneAreaCode'           => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$uc_ContactNumbers$txtAreaCodeFirst',
        'PhoneLocalNumber'        => 'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$uc_ContactNumbers$txtNumberFirst',
    ];

    public static $countries = [
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
        'BR' => 'Brazil',
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
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
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
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'UM' => 'Minor Island',
        'MD' => 'Moldova',
        'MC' => 'Monaco',
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
        'KP' => 'Peoples Rep Korea',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'CM' => 'Republic Cameroon',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
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
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor - Leste',
        'TG' => 'Togo',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks Caicos',
        'TV' => 'Tuvalu',
        'VI' => 'US Virgin Islands',
        'US' => 'USA',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'WS' => 'Western Samoa',
        'YE' => 'Yemen Republic',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    private $registerUrl = 'https://www.etihadguest.com/en/join/';
    private $fields = [];

    private static $genderMap = [
        'M' => 'male',
        'F' => 'female',
    ];

    private static $titles = [
        'Miss'      => 'Miss',
        'Mr'        => 'Mr',
        'Mrs'       => 'Mrs',
        'Ms'        => 'Ms',
        'Baron'     => 'Baron',
        'Baroness'  => 'Baroness',
        'Brigadier' => 'Brigadier',
        'Captain'   => 'Captain',
        'Colonel'   => 'Colonel',
        'Dr'        => 'Dr',
        'General'   => 'General',
        'HE'        => 'HE',
        'HH'        => 'HH',
        'HRH'       => 'HRH',
        'Lady'      => 'Lady',
        'Lord'      => 'Lord',
        'Master'    => 'Master',
        'Professor' => 'Professor',
        'Sheikh'    => 'Sheikh',
        'Sheikha'   => 'Sheikha',
        'Sir'       => 'Sir',
    ];

    public function initBrowser()
    {
        $this->UseCurlBrowser();
        $this->http->LogHeaders = true;
        $this->ArchiveLogs = true;
    }

    public function registerAccount(array $fields)
    {
        $this->http->log('>>> ' . __METHOD__);
        $this->fields = $fields;

        $this->http->log('[DEBUG] fields:');
        $this->http->log(json_encode($fields, JSON_PRETTY_PRINT));

        $this->checkFields();
        $this->modifyFields();

        $this->http->getUrl($this->registerUrl);
        $this->populateForm();
        $this->modifyFormData();

        $this->http->postForm();
        $this->checkStatus();

        return true;
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
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => [
                    'M' => 'Male',
                    'F' => 'Female',
                ],
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name (as in passport)',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name (as in passport)',
                'Required' => true,
            ],
            'BirthMonth' => [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth Date',
                'Required' => true,
            ],
            'BirthDay' => [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth Date',
                'Required' => true,
            ],
            'BirthYear' => [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (must be 8-10 characters, begin with a letter and end with a number OR begin with a number and end with a letter)',
                'Required' => true,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'AddressLine1',
                'Required' => true,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City',
                'Required' => true,
            ],
            'POBox' => [
                'Type'     => 'string',
                'Caption'  => 'PO Box Number (required for UAE)',
                'Required' => false,
            ],
            'PhoneType' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Type',
                'Required' => true,
                'Options'  => [
                    'M' => 'Mobile',
                    // 'B' => 'Business',
                    // 'H' => 'Home',
                ],
            ],
            'PhoneCountryCodeNumeric' => [
                'Type'     => 'string',
                'Caption'  => '1-3-number Phone Country Code',
                'Required' => true,
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
        ];
    }

    private function checkFields()
    {
        if ($this->fields['Country'] === 'AE' and !arrayVal($this->fields, 'POBox')) {
            throw new \UserInputError('POBox is required if you live in the United Arab Emirates');
        }

        $currentYear = date('Y');

        if ($this->fields['BirthYear'] < $currentYear - 100 // yes magic number
              || $this->fields['BirthYear'] > $currentYear) {
            throw new \UserInputError('Invalid Birth Year');
        }

        if ($this->fields['BirthDay'] < 1
              || $this->fields['BirthDay'] > 31) {
            throw new \UserInputError('Invalid Birth Day');
        }

        if ($this->fields['BirthMonth'] < 1
              || $this->fields['BirthMonth'] > 12) {
            throw new \UserInputError('Invalid Birth Month');
        }

        return true;
    }

    private function modifyFormData()
    {
        $this->http->Form['ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$btnEnrollment'] = 'Join Now';

        $this->http->Form['ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$hfBirthDate'] = (
            $this->fields['BirthDate']
        );

        $genderLetter = $this->fields['Gender'] === 'male' ? 'M' : 'F';
        $this->http->Form['ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$hfGender'] = $genderLetter;

        $this->http->Form['ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$uc_ContactNumbers$ddlNumberTypeSecond'] = $this->fields['PhoneType'];
        $this->http->Form['ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$uc_ContactNumbers$ddlNumberTypeThird'] = $this->fields['PhoneType'];

        $toUnset = [
            'dontshow',
            '',

            'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$commPrefRpt$ctl00$chkCommPrefItem',
            'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$commPrefRpt$ctl01$chkCommPrefItem',
            'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$commPrefRpt$ctl02$chkCommPrefItem',
            'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$commPrefRpt$ctl03$chkCommPrefItem',
            'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$commPrefRpt$ctl04$chkCommPrefItem',
            'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$commPrefRpt$ctl05$chkCommPrefItem',
            'ctl00$ctl00$ctl00$cphMain$cphContent$cphPrimary$ModuleList1$ctl00$commPrefRpt$ctl06$chkCommPrefItem',
        ];

        foreach ($toUnset as $key) {
            unset($this->http->Form[$key]);
        }
    }

    private function modifyFields()
    {
        $this->http->log('>>> ' . __METHOD__);
        $this->fields['Gender'] = self::$genderMap[$this->fields['Gender']];

        $day = $this->fields['BirthDay'];
        $day = strval(intval($day)); // remove leading zeros if present (unlikely)
        $day = str_pad($day, 2, '0', STR_PAD_LEFT); // pad to exactly 2

        $month = $this->fields['BirthMonth'];
        $month = strval(intval($month));
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);

        $this->fields['BirthDay'] = $day;
        $this->fields['BirthMonth'] = $month;
        $this->fields['BirthDate'] = $this->getBirthDate();

        $this->fields['PhoneCountryCodeNumeric'] = sprintf('%02s', $this->fields['PhoneCountryCodeNumeric']);
        $this->fields['ContactNumber'] = $this->getContactNumber();
    }

    private function getContactNumber()
    {
        return sprintf('%s,%s,%s,%s::',
            $this->fields['PhoneType'],
            $this->fields['PhoneCountryCodeNumeric'],
            $this->fields['PhoneAreaCode'],
            $this->fields['PhoneLocalNumber']
        );
    }

    private function getBirthDate()
    {
        $this->http->log('>>> ' . __METHOD__);

        return sprintf('%s/%s/%s',
            $this->fields['BirthDay'],
            $this->fields['BirthMonth'],
            $this->fields['BirthYear']
        );
    }

    private function populateForm()
    {
        $this->http->log('>>> ' . __METHOD__);

        foreach (self::$fieldMap as $awkey => $keys) {
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

    private function checkStatus()
    {
        $this->http->log('>>> ' . __METHOD__);

        $account = $this->http->findPreg('/(Your membership number is : \d+)/i');

        if ($account) {
            $msg = sprintf('Successfull registration. %s. %s.',
                $account,
                'Activation link has been sent to your email'
            );
            $this->http->log($msg);
            $this->ErrorMessage = $msg;

            return true;
        }

        $emailExists = $this->http->findPreg('/Email exists/i');

        if ($emailExists) {
            throw new \ProviderError($emailExists);
        }

        if ($this->http->findPreg('/Your enrollment has not been successfully completed/')) {
            throw new \ProviderError('Site error, please try again');
        }

        throw new \EngineError('Unexpected response on account registration request');
    }
}
