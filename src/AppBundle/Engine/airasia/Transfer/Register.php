<?php

// case #10599

namespace AwardWallet\Engine\airasia\Transfer;

class Register extends \TAccountCheckerAirasia
{
    public static $fieldMap = [
        'DoYouHaveABIGCardOrBIGShotID' => 'ctl00$body$rbnBigCard',
        'BIGShotID'                    => 'ctl00$body$txtBIGShotID',
        'Email'                        => 'ctl00$body$txtEmail',
        'Password'                     => [
            'ctl00$body$txtPassword',
            'ctl00$body$txtRePassword',
        ],
        'Title'                      => 'ctl00$body$rblTitle',
        'FirstName'                  => 'ctl00$body$txtFirstName',
        'LastName'                   => 'ctl00$body$txtLastName',
        'BirthDay'                   => 'ctl00$body$ddlDay',
        'BirthMonth'                 => 'ctl00$body$ddlMonth',
        'BirthYear'                  => 'ctl00$body$ddlYear',
        'Nationality'                => 'ctl00$body$ddlNationality',
        'Country'                    => 'ctl00$body$ddlCountry',
        'StateOrProvince'            => 'ctl00$body$ddlState',
        'City'                       => 'ctl00$body$txtCity',
        'PhoneCountryCodeAlphabetic' => 'ctl00$body$ddlDialing',
        'Phone'                      => 'ctl00$body$txtMobilePhone',
    ];

    public static $titles = [
        'MR' => 'Mr',
        'MS' => 'Ms',
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
        'CD' => 'Congo, Democratic Republic Of The',
        'CG' => 'Congo, Republic Of The',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D\'Ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands (Malvinas)',
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
        'LY' => 'Libyan Arab Jamahiriya',
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
        'KP' => 'North Korea',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PS' => 'Palestinian Territories',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthelemy',
        'KN' => 'Saint Kitts And Nevis',
        'LC' => 'Saint Lucia',
        'MF' => 'Saint Martin',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'KR' => 'South Korea',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SH' => 'St. Helena',
        'PM' => 'St. Pierre and Miquelon',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania, United Republic Of',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
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
        'UM' => 'United States Minor Outlying Islands',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VG' => 'Virgin Islands (British)',
        'VI' => 'Virgin Islands (U.S)',
        'WF' => 'Wallis and Futuna Islands',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public function registerAccount(array $fields)
    {
        $this->http->log('>>> ' . __METHOD__);
        //$this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        $fields['DoYouHaveABIGCardOrBIGShotID'] = false;
        $fields['BIGShotID'] = '';

        if ($fields['Country'] == 'TL') {
            // Provider uses wrong country codes for Timor-Leste (both TP and TL instead of standard TL).
            // Map from our standard ISO code to wrong code used by provider
            $fields['Country'] = 'TP';
            $this->logger->debug('Mapped standard country code "TL" to provider code "TP"');
        }

        $this->checkData($fields);

        $this->http->getUrl('https://member.airasia.com/register.aspx');

        if (!$this->parseForm()) {
            return false;
        }

        $fields['BirthDay'] = $fields['BirthDay'] < 10 ? '0' . $fields['BirthDay'] : $fields['BirthDay'];
        $fields['BirthMonth'] = $fields['BirthMonth'] < 10 ? '0' . $fields['BirthMonth'] : $fields['BirthMonth'];

        $this->setFormData($fields);
        $this->setIndirectFields();

        $this->http->postForm();
        $this->checkStatus();

        return true;
    }

    public function getRegisterFields()
    {
        return [
            // 'DoYouHaveABIGCardOrBIGShotID' => [
            // 	'Type' => 'boolean',
            // 	'Caption' => 'Do you have a BIG card or BIG Shot ID?',
            // 	'Required' => true,
            // ],
            // 'BIGShotID' => [
            // 	'Type' => 'string',
            // 	'Caption' => 'BIG Shot ID (you can find your BIG Shot ID on the front of your BIG card or the 10-digit number assigned to you)',
            // 	'Required' => false,
            // ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (must be 8-15 alphanumeric characters with number, lowercase letter and uppercase letter)',
                'Required' => true,
            ],
            'Title' => [
                'Type'     => 'string',
                'Caption'  => 'Title',
                'Required' => true,
                'Options'  => self::$titles,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'Given Name (enter name as per passport/IC in roman alphabets only)',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Family name/Surname (enter name as per passport/IC in roman alphabets only)',
                'Required' => true,
            ],
            'BirthDay' => [
                'Type'     => 'string',
                'Caption'  => 'Day of Birth',
                'Required' => true,
            ],
            'BirthMonth' => [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth',
                'Required' => true,
            ],
            'BirthYear' => [
                'Type'     => 'integer',
                'Caption'  => '1900 <= Year of Birth <= 2015',
                'Required' => true,
            ],
            'Nationality' => [
                'Type'     => 'string',
                'Caption'  => 'Nationality',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State/Province abbreviation, option "OTH" (Other) is available for all countries except US',
                'Required' => true,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'Town/City ',
                'Required' => true,
            ],
            'PhoneCountryCodeAlphabetic' => [
                'Type'     => 'string',
                'Caption'  => '2-letter Country Code',
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
                'Caption'  => 'Phone Local Number',
                'Required' => true,
            ],
        ];
    }

    private function setIndirectFields()
    {
        if (!arrayVal($this->http->Form, 'ctl00$body$rbnBigCard')) {
            $this->http->Form['ctl00$body$rbnBigCard'] = '0';
        }
        // if (!arrayVal($this->http->Form, 'ctl00$body$txtBIGShotID'))
        // 	unset($this->http->Form['ctl00$body$txtBIGShotID']);

        unset($this->http->Form['ctl00$body$ckAgreement']);
        unset($this->http->Form['ctl00$body$ddlCity']);
        unset($this->http->Form['ctl00$body$ckMinorAgreement']);

        $fixed = [
            'ctl00$body$btnSignUp'            => 'Sign up now!',
            'ctl00$body$ckTnC1'               => 'on',
            'ctl00$body$ckTnC2'               => 'on',
            'ctl00$body$ddlPreferredLanguage' => '-1',
        ];

        foreach ($fixed as $key => $value) {
            $this->http->setInputValue($key, $value);
        }

        $this->http->Form['ctl00$body$hdfCountry'] = (
        arrayVal($this->http->Form, 'ctl00$body$ddlCountry'));
        $this->http->Form['ctl00$body$hdfState'] = (
        arrayVal($this->http->Form, 'ctl00$body$ddlState'));
    }

    private function checkData($fields)
    {
        if ($fields['BirthYear'] < 1900 || $fields['BirthYear'] > 2015) {
            throw new \UserInputError(self::getRegisterFields()['BirthYear']['Caption']);
        }

        if ($fields['DoYouHaveABIGCardOrBIGShotID'] && !$fields['BIGShotID']) {
            throw new \UserInputError('Please enter Big Shot ID, if you have it.');
        }
    }

    private function checkStatus()
    {
        $success = $this->http->findSingleNode("//*[
            contains(text(), 'Your account has been successfully created and an') or
			contains(text(), 'Account created successfully!')
		]");

        if ($success) {
            $this->http->log($success);
            $this->ErrorMessage = $success;

            return true;
        }

        $errorPW = $this->http->findSingleNode('//*[@id = "body_lblPwdError"]');

        if ($errorPW) {
            $msg = sprintf('Password %s', strtolower($errorPW));

            throw new \UserInputError($msg);
        }
        $errorEmail = $this->http->findSingleNode('//*[@id = "body_lblUserError"]');

        if ($errorEmail) {
            throw new \UserInputError($errorEmail);
        }

        throw new \EngineError('Unexpected response on account registration request');
    }

    private function parseForm()
    {
        $this->http->log('>>> ' . __METHOD__);

        if (!$this->http->parseForm('form1')) {
            $this->http->log('failed to find sign up form');

            return false;
        }
        $this->http->log('>>> Register form:');
        $this->http->log(print_r($this->http->Form, true));

        return true;
    }

    private function setFormData($fields)
    {
        $this->http->log('>>> ' . __METHOD__);

        $fields['Phone'] = $this->getPhone($fields);

        foreach (self::$fieldMap as $awkey => $keys) {
            if (!isset($fields[$awkey])) {
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
