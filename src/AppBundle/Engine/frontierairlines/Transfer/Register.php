<?php

namespace AwardWallet\Engine\frontierairlines\Transfer;

class Register extends \TAccountChecker
{
    public static $statesUs = [
        'AK' => 'AK',
        'AL' => 'AL',
        'AR' => 'AR',
        'AZ' => 'AZ',
        'CA' => 'CA',
        'CO' => 'CO',
        'CT' => 'CT',
        'DC' => 'DC',
        'DE' => 'DE',
        'FL' => 'FL',
        'GA' => 'GA',
        'HI' => 'HI',
        'IA' => 'IA',
        'ID' => 'ID',
        'IL' => 'IL',
        'IN' => 'IN',
        'KS' => 'KS',
        'KY' => 'KY',
        'LA' => 'LA',
        'MA' => 'MA',
        'MD' => 'MD',
        'ME' => 'ME',
        'MI' => 'MI',
        'MN' => 'MN',
        'MO' => 'MO',
        'MS' => 'MS',
        'MT' => 'MT',
        'NC' => 'NC',
        'ND' => 'ND',
        'NE' => 'NE',
        'NH' => 'NH',
        'NJ' => 'NJ',
        'NM' => 'NM',
        'NV' => 'NV',
        'NY' => 'NY',
        'OH' => 'OH',
        'OK' => 'OK',
        'OR' => 'OR',
        'PA' => 'PA',
        'RI' => 'RI',
        'SC' => 'SC',
        'SD' => 'SD',
        'TN' => 'TN',
        'TX' => 'TX',
        'UT' => 'UT',
        'VA' => 'VA',
        'VT' => 'VT',
        'WA' => 'WA',
        'WI' => 'WI',
        'WV' => 'WV',
        'WY' => 'WY',
    ];

    public static $countries = [
        'US' => 'United States of America',
        'CA' => 'Canada',
        'AD' => 'Andorra, Principality of',
        'AE' => 'United Arab Emirates',
        'AF' => 'Afghanistan',
        'AG' => 'Antigua and Barbuda',
        'AI' => 'Anguilla',
        'AL' => 'Albania, People\'s Socialist Republic of',
        'AM' => 'Armenia',
        'AO' => 'Angola, Republic of',
        'AR' => 'Argentina, Argentine Republic',
        'AS' => 'American Samoa',
        'AT' => 'Austria, Republic of',
        'AU' => 'Australia, Commonwealth of',
        'AW' => 'Aruba',
        'AX' => 'Aland Islands',
        'AZ' => 'Azerbaijan, Republic of',
        'BA' => 'Bosnia and Herzegovina',
        'BB' => 'Barbados',
        'BD' => 'Bangladesh, People\'s Republic of',
        'BE' => 'Belgium, Kingdom of',
        'BF' => 'Burkina Faso',
        'BG' => 'Bulgaria, People\'s Republic of',
        'BH' => 'Bahrain, Kingdom of',
        'BI' => 'Burundi, Republic of',
        'BJ' => 'Benin, People\'s Republic of',
        'BL' => 'Saint Barthelemy',
        'BM' => 'Bermuda',
        'BN' => 'Brunei Darussalam',
        'BO' => 'Bolivia, Republic of',
        'BQ' => 'Bonaire, Saint Eustatius and Saba',
        'BR' => 'Brazil, Federative Republic of',
        'BS' => 'Bahamas, Commonwealth of the',
        'BT' => 'Bhutan, Kingdom of',
        'BW' => 'Botswana, Republic of',
        'BY' => 'Belarus',
        'BZ' => 'Belize',
        'CC' => 'Cocos (Keeling) Islands',
        'CD' => 'Congo, Democratic Republic of',
        'CF' => 'Central African Republic',
        'CG' => 'Congo, People\'s Republic of',
        'CH' => 'Switzerland, Swiss Confederation',
        'CI' => 'Cote D\'Ivoire, Ivory Coast, Republic of the',
        'CK' => 'Cook Islands',
        'CL' => 'Chile, Republic of',
        'CM' => 'Cameroon, United Republic of',
        'CN' => 'China, People\'s Republic of',
        'CO' => 'Colombia, Republic of',
        'CR' => 'Costa Rica, Republic of',
        'CU' => 'Cuba, Republic of',
        'CV' => 'Cape Verde, Republic of',
        'CW' => 'Curacao',
        'CX' => 'Christmas Island',
        'CY' => 'Cyprus, Republic of',
        'CZ' => 'Czech Republic',
        'DE' => 'Germany',
        'DJ' => 'Djibouti, Republic of',
        'DK' => 'Denmark, Kingdom of',
        'DM' => 'Dominica, Commonwealth of',
        'DO' => 'Dominican Republic',
        'DZ' => 'Algeria, People\'s Democratic Republic of',
        'EC' => 'Ecuador, Republic of',
        'EE' => 'Estonia',
        'EG' => 'Egypt, Arab Republic of',
        'EH' => 'Western Saraha',
        'ER' => 'Eritrea',
        'ES' => 'Spain, Spanish State',
        'ET' => 'Ethiopia',
        'FI' => 'Finland, Republic of',
        'FJ' => 'Fiji, Republic of the Fiji Islands',
        'FK' => 'Falkland Islands (Malvinas)',
        'FM' => 'Micronesia, Federated States of',
        'FO' => 'Faeroe Islands',
        'FR' => 'France, French Republic',
        'GA' => 'Gabon, Gabonese Republic',
        'GB' => 'United Kingdom of Great Britain &amp; N. Ireland',
        'GD' => 'Grenada',
        'GE' => 'Georgia',
        'GF' => 'French Guiana',
        'GG' => 'Guernsey',
        'GH' => 'Ghana, Republic of',
        'GI' => 'Gibraltar',
        'GL' => 'Greenland',
        'GM' => 'Gambia, Republic of the',
        'GN' => 'Guinea, Revolutionary People\'s Rep\'c of',
        'GP' => 'Guadeloupe',
        'GQ' => 'Equatorial Guinea, Republic of',
        'GR' => 'Greece, Hellenic Republic',
        'GT' => 'Guatemala, Republic of',
        'GU' => 'Guam',
        'GW' => 'Guinea-Bissau, Republic of',
        'GY' => 'Guyana, Republic of',
        'HK' => 'Hong Kong, Special Administrative Region of China',
        'HN' => 'Honduras, Republic of',
        'HR' => 'Hrvatska (Croatia)',
        'HT' => 'Haiti, Republic of',
        'HU' => 'Hungary, Hungarian People\'s Republic',
        'ID' => 'Indonesia, Republic of',
        'IE' => 'Ireland',
        'IL' => 'Israel, State of',
        'IM' => 'Isle Of Man',
        'IN' => 'India, Republic of',
        'IQ' => 'Iraq, Republic of',
        'IR' => 'Iran, Islamic Republic of',
        'IS' => 'Iceland, Republic of',
        'IT' => 'Italy, Italian Republic',
        'JE' => 'Jersey',
        'JM' => 'Jamaica',
        'JO' => 'Jordan, Hashemite Kingdom of',
        'JP' => 'Japan',
        'KE' => 'Kenya, Republic of',
        'KG' => 'Kyrgyz Republic',
        'KH' => 'Cambodia, Kingdom of',
        'KI' => 'Kiribati, Republic of',
        'KM' => 'Comoros, Union of the',
        'KN' => 'St. Kitts and Nevis',
        'KP' => 'Korea, Democratic People\'s Republic of',
        'KR' => 'Korea, Republic of',
        'KW' => 'Kuwait, State of',
        'KY' => 'Cayman Islands',
        'KZ' => 'Kazakhstan, Republic of',
        'LA' => 'Laos, People\'s Democratic Republic of',
        'LB' => 'Lebanon, Lebanese Republic',
        'LC' => 'St. Lucia',
        'LI' => 'Liechtenstein, Principality of',
        'LK' => 'Sri Lanka, Democratic Socialist Republic of',
        'LR' => 'Liberia, Republic of',
        'LS' => 'Lesotho, Kingdom of',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg, Grand Duchy of',
        'LV' => 'Latvia',
        'LY' => 'Libyan Arab Jamahiriya',
        'MA' => 'Morocco, Kingdom of',
        'MC' => 'Monaco, Principality of',
        'MD' => 'Moldova, Republic of',
        'ME' => 'Montenegro',
        'MF' => 'Saint Martin',
        'MG' => 'Madagascar, Republic of',
        'MH' => 'Marshall Islands',
        'MK' => 'Macedonia, the former Yugoslav Republic of',
        'ML' => 'Mali, Republic of',
        'MM' => 'Myanmar',
        'MN' => 'Mongolia, Mongolian People\'s Republic',
        'MO' => 'Macao, Special Administrative Region of China',
        'MP' => 'Northern Mariana Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania, Islamic Republic of',
        'MS' => 'Montserrat',
        'MT' => 'Malta, Republic of',
        'MU' => 'Mauritius',
        'MV' => 'Maldives, Republic of',
        'MW' => 'Malawi, Republic of',
        'MX' => 'Mexico, United Mexican States',
        'MY' => 'Malaysia',
        'MZ' => 'Mozambique, People\'s Republic of',
        'NA' => 'Namibia',
        'NC' => 'New Caledonia',
        'NE' => 'Niger, Republic of the',
        'NF' => 'Norfolk Island',
        'NG' => 'Nigeria, Federal Republic of',
        'NI' => 'Nicaragua, Republic of',
        'NL' => 'Netherlands, Kingdom of the',
        'NO' => 'Norway, Kingdom of',
        'NP' => 'Nepal, Kingdom of',
        'NR' => 'Nauru, Republic of',
        'NU' => 'Niue, Republic of',
        'NZ' => 'New Zealand',
        'OM' => 'Oman, Sultanate of',
        'PA' => 'Panama, Republic of',
        'PE' => 'Peru, Republic of',
        'PF' => 'French Polynesia',
        'PG' => 'Papua New Guinea',
        'PH' => 'Philippines, Republic of the',
        'PK' => 'Pakistan, Islamic Republic of',
        'PL' => 'Poland, Polish People\'s Republic',
        'PM' => 'St. Pierre and Miquelon',
        'PR' => 'Puerto Rico',
        'PS' => 'Palestinian Territory, Occupied',
        'PT' => 'Portugal, Portuguese Republic',
        'PW' => 'Palau',
        'PY' => 'Paraguay, Republic of',
        'QA' => 'Qatar, State of',
        'RE' => 'Reunion',
        'RO' => 'Romania, Socialist Republic of',
        'RS' => 'Serbia',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda, Rwandese Republic',
        'SA' => 'Saudi Arabia, Kingdom of',
        'SB' => 'Solomon Islands',
        'SC' => 'Seychelles, Republic of',
        'SD' => 'Sudan, Democratic Republic of the',
        'SE' => 'Sweden, Kingdom of',
        'SG' => 'Singapore, Republic of',
        'SH' => 'St. Helena',
        'SI' => 'Slovenia',
        'SJ' => 'Svalbard &amp; Jan Mayen Islands',
        'SK' => 'Slovakia (Slovak Republic)',
        'SL' => 'Sierra Leone, Republic of',
        'SM' => 'San Marino, Republic of',
        'SN' => 'Senegal, Republic of',
        'SO' => 'Somalia, Somali Republic',
        'SR' => 'Suriname, Republic of',
        'SS' => 'South Sudan',
        'ST' => 'Sao Tome and Principe, Democratic Republic of',
        'SV' => 'El Salvador, Republic of',
        'SX' => 'Sint Maarten',
        'SY' => 'Syrian Arab Republic',
        'SZ' => 'Swaziland, Kingdom of',
        'TC' => 'Turks and Caicos Islands',
        'TD' => 'Chad, Republic of',
        'TG' => 'Togo, Togolese Republic',
        'TH' => 'Thailand, Kingdom of',
        'TJ' => 'Tajikistan',
        'TK' => 'Tokelau',
        'TL' => 'Timor-Leste, Democratic Republic of',
        'TM' => 'Turkmenistan',
        'TN' => 'Tunisia, Republic of',
        'TO' => 'Tonga, Kingdom of',
        'TR' => 'Turkey, Republic of',
        'TT' => 'Trinidad and Tobago, Republic of',
        'TV' => 'Tuvalu',
        'TW' => 'Taiwan, Province of China',
        'TZ' => 'Tanzania, United Republic of',
        'UA' => 'Ukraine',
        'UG' => 'Uganda, Republic of',
        'UM' => 'United States Minor Outlying Islands',
        'UY' => 'Uruguay, Eastern Republic of',
        'UZ' => 'Uzbekistan',
        'VA' => 'Vatican,Holy See',
        'VC' => 'St. Vincent and the Grenadines',
        'VE' => 'Venezuela, Bolivarian Republic of',
        'VG' => 'British Virgin Islands',
        'VI' => 'US Virgin Islands',
        'VN' => 'Vietnam',
        'VU' => 'Vanuatu',
        'WF' => 'Wallis and Futuna Islands',
        'WS' => 'Samoa, Independent State of',
        'YE' => 'Yemen',
        'YT' => 'Mayotte',
        'ZA' => 'South Africa, Republic of',
        'ZM' => 'Zambia, Republic of',
        'ZW' => 'Zimbabwe',
    ];

    public static $inputFieldsMap = [
        'Email' => [
            'frontierRegisterMember.Member.PersonalEmailAddress.EmailAddress',
            'frontierRegisterMember.personalEmailAddressConfirmation',
            'frontierRegisterMember.Member.Username',
        ],
        'Password'        => ['frontierRegisterMember.Member.Password', 'frontierRegisterMember.Member.NewPasswordConfirmation'],
        'FirstName'       => 'frontierRegisterMember.Member.Name.First',
        'LastName'        => 'frontierRegisterMember.Member.Name.Last',
        'Gender'          => 'frontierRegisterMember.Member.Gender',
        'BirthDay'        => 'frontierRegisterMember.day',
        'BirthMonth'      => 'frontierRegisterMember.month',
        'BirthYear'       => 'frontierRegisterMember.year',
        'AddressLine1'    => 'frontierRegisterMember.Member.HomeAddress.AddressLine1',
        'Country'         => 'frontierRegisterMember.Member.HomeAddress.CountryCode',
        'StateOrProvince' => 'frontierRegisterMember.Member.HomeAddress.ProvinceState',
        'City'            => 'frontierRegisterMember.Member.HomeAddress.City',
        'PostalCode'      => 'frontierRegisterMember.Member.HomeAddress.PostalCode',
        'Phone'           => 'frontierRegisterMember.Member.MobilePhoneNumber.Number',
    ];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        if ($fields['Country'] === 'US' && (!isset($fields['StateOrProvince']) || !in_array($fields['StateOrProvince'], self::$statesUs))) {
            throw new \UserInputError('Unavailable State for US');
        }

        $this->http->GetURL('https://booking.flyfrontier.com/Member/Register');

        $status = $this->http->ParseForm('memberRegisterForm');

        if (!$status) {
            $message = 'Failed to parse create account form';
            $this->http->Log($message);

            throw new \EngineError($message);
        }

        $fields['Phone'] = $fields['PhoneCountryCodeNumeric'] . $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];
        $fields['Gender'] = $fields['Gender'] === 'M' ? '1' : '2';

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

        $birthDate = $fields['BirthYear'] . '-' .
                     \DateTime::createFromFormat('!m', intval($fields['BirthMonth']))->format('m') . '-' .
                     \DateTime::createFromFormat('!m', intval($fields['BirthDay']))->format('d');
        $additional = [
            'frontierRegisterMember_Submit'             => 'Join',
            'frontierRegisterMember.Member.DateOfBirth' => $birthDate,
        ];

        foreach ($additional as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        $status = $this->http->PostForm();

        if (!$status) {
            $message = 'Failed to post create account form';
            $this->http->Log($message);

            throw new \EngineError($message);
        }

        if ($success = $this->http->FindSingleNode("//a[contains(@href, 'Logout')]")) {
            $this->http->GetURL('https://booking.flyfrontier.com/Member/Profile');
            $this->ErrorMessage = $this->http->FindSingleNode("//span[contains(text(), 'Member #')]");
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        if ($error = $this->http->FindSingleNode("//div[contains(@class,'alert-error')]//li[1]")) {
            throw new \UserInputError($error);
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Password (must be at least 8 characters, no more than 16 characters, include at least one upper case letter, one lower case letter, one numeric digit, and one special character)',
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
            'Gender' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  =>
                [
                    'M' => 'Male',
                    'F' => 'Female',
                ],
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
                'Caption'  => 'Year of Birth Date ',
                'Required' => true,
            ],
            'AddressLine1' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Address Line',
                'Required' => true,
            ],
            'Country' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'StateOrProvince' =>
            [
                'Type'     => 'string',
                'Caption'  => 'State (required for US)',
                'Required' => false,
                'Options'  => self::$statesUs,
            ],
            'City' =>
            [
                'Type'     => 'string',
                'Caption'  => 'City',
                'Required' => true,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
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
        ];
    }
}
