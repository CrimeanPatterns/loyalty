<?php

namespace AwardWallet\Engine\fiesta\Transfer;

class Register extends \TAccountChecker
{
    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $maritalStatuses = [
        'Single'  => 'Single',
        'Married' => 'Married',
    ];

    public static $addressTypes = [
        'Home'   => 'Home',
        'Office' => 'Office',
    ];

    public static $phoneTypes = [
        'Home'      => 'Home',
        'Office'    => 'Office',
        'Cellphone' => 'Celular',
    ];

    public static $mxStates = [
        'AG' => 'Aguascalientes',
        'BN' => 'Baja California',
        'BS' => 'Baja California Sur',
        'CM' => 'Campeche',
        'CP' => 'Chiapas',
        'CH' => 'Chihuahua',
        'CA' => 'Coahuila De Zaragoza',
        'CL' => 'Colima',
        'DF' => 'Distrito Federal',
        'DU' => 'Durango',
        'GT' => 'Guanajuato',
        'GR' => 'Guerrero',
        'HI' => 'Hidalgo',
        'JA' => 'Jalisco',
        'MC' => 'Michoacán de Ocampo',
        'MR' => 'Morelos',
        'NA' => 'Nayarit',
        'NL' => 'Nuevo León',
        'OA' => 'Oaxaca',
        'PU' => 'Puebla',
        'QE' => 'Querétaro De Arteaga',
        'QR' => 'Quintana Roo',
        'SL' => 'San Luis Potosí',
        'SI' => 'Sinaloa',
        'SO' => 'Sonora',
        'TB' => 'Tabasco',
        'TM' => 'Tamaulipas',
        'TL' => 'Tlaxcala',
        'VE' => 'Veracruz',
        'YU' => 'Yucatán',
        'ZA' => 'Zacatecas',
    ];

    protected $fields;

    protected $birthDate;

    protected $phoneNumber;
    /*
        static $countries = array (
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'BR' => 'Brasil',
            'CA' => 'Canada',
            'US' => 'United States of America',
            'CO' => 'Colombia',
            'CR' => 'Costa Rica',
            'IT' => 'Italia',
            'DE' => 'Germany',
            'GT' => 'Guatemala',
            'PR' => 'Puerto Rico',
            'GB' => 'United Kingdom',
            'CL' => 'Chile',
            'UY' => 'Uruguay',
            'VE' => 'Venezuela',
        );
        */
    protected static $countries = [
        'MX' =>
            [
                'num'  => '1',
                'name' => 'Mexico',
            ],
        'US' =>
            [
                'num'  => '2',
                'name' => 'United States of America',
            ],
        'AF' =>
            [
                'num'  => '3',
                'name' => 'Afghanistan',
            ],
        'AL' =>
            [
                'num'  => '4',
                'name' => 'Albania',
            ],
        'DZ' =>
            [
                'num'  => '5',
                'name' => 'Algeria',
            ],
        'AD' =>
            [
                'num'  => '6',
                'name' => 'Andorra',
            ],
        'AO' =>
            [
                'num'  => '7',
                'name' => 'Angola',
            ],
        'AG' =>
            [
                'num'  => '8',
                'name' => 'Antigua and Barbuda',
            ],
        'AR' =>
            [
                'num'  => '9',
                'name' => 'Argentina',
            ],
        'AM' =>
            [
                'num'  => '10',
                'name' => 'Armenia',
            ],
        'AW' =>
            [
                'num'  => '11',
                'name' => 'Aruba',
            ],
        'AU' =>
            [
                'num'  => '12',
                'name' => 'Australia',
            ],
        'AT' =>
            [
                'num'  => '13',
                'name' => 'Austria',
            ],
        'AZ' =>
            [
                'num'  => '14',
                'name' => 'Azerbaijan',
            ],
        'BS' =>
            [
                'num'  => '15',
                'name' => 'Bahamas',
            ],
        'BH' =>
            [
                'num'  => '16',
                'name' => 'Bahrain',
            ],
        'BD' =>
            [
                'num'  => '17',
                'name' => 'Bangladesh',
            ],
        'BB' =>
            [
                'num'  => '18',
                'name' => 'Barbados',
            ],
        'BY' =>
            [
                'num'  => '19',
                'name' => 'Belarus',
            ],
        'BE' =>
            [
                'num'  => '20',
                'name' => 'Belgium',
            ],
        'BZ' =>
            [
                'num'  => '21',
                'name' => 'Belize',
            ],
        'BJ' =>
            [
                'num'  => '22',
                'name' => 'Benin',
            ],
        'BT' =>
            [
                'num'  => '23',
                'name' => 'Bhutan',
            ],
        'BO' =>
            [
                'num'  => '24',
                'name' => 'Bolivia',
            ],
        'BA' =>
            [
                'num'  => '25',
                'name' => 'Bosnia and Herzegovina',
            ],
        'BW' =>
            [
                'num'  => '26',
                'name' => 'Botswana',
            ],
        'BR' =>
            [
                'num'  => '27',
                'name' => 'Brazil',
            ],
        'BN' =>
            [
                'num'  => '28',
                'name' => 'Brunei Darussalam',
            ],
        'BG' =>
            [
                'num'  => '29',
                'name' => 'Bulgaria',
            ],
        'BF' =>
            [
                'num'  => '30',
                'name' => 'Burkina Faso',
            ],
        'BI' =>
            [
                'num'  => '32',
                'name' => 'Burundi',
            ],
        'KH' =>
            [
                'num'  => '33',
                'name' => 'Cambodia',
            ],
        'CM' =>
            [
                'num'  => '34',
                'name' => 'Cameroon',
            ],
        'CA' =>
            [
                'num'  => '35',
                'name' => 'Canada',
            ],
        'CV' =>
            [
                'num'  => '36',
                'name' => 'Cape Verde',
            ],
        'CF' =>
            [
                'num'  => '37',
                'name' => 'Central African Republic',
            ],
        'TD' =>
            [
                'num'  => '38',
                'name' => 'Chad',
            ],
        'CL' =>
            [
                'num'  => '39',
                'name' => 'Chile',
            ],
        'CN' =>
            [
                'num'  => '40',
                'name' => 'China',
            ],
        'CO' =>
            [
                'num'  => '41',
                'name' => 'Colombia',
            ],
        'KM' =>
            [
                'num'  => '43',
                'name' => 'Comoros',
            ],
        'CG' =>
            [
                'num'  => '45',
                'name' => 'Congo',
            ],
        'CR' =>
            [
                'num'  => '46',
                'name' => 'Costa Rica',
            ],
        'HR' =>
            [
                'num'  => '48',
                'name' => 'Croatia',
            ],
        'CU' =>
            [
                'num'  => '49',
                'name' => 'Cuba, Republic of',
            ],
        'CZ' =>
            [
                'num'  => '52',
                'name' => 'Czech Republic',
            ],
        'DK' =>
            [
                'num'  => '53',
                'name' => 'Denmark',
            ],
        'DO' =>
            [
                'num'  => '55',
                'name' => 'Dominican Republic',
            ],
        'TL' =>
            [
                'num'  => '56',
                'name' => 'East Timor',
            ],
        'EE' =>
            [
                'num'  => '61',
                'name' => 'Estonia, Republic of',
            ],
        'ET' =>
            [
                'num'  => '62',
                'name' => 'Ethiopia',
            ],
        'FI' =>
            [
                'num'  => '64',
                'name' => 'Finland, Republic of',
            ],
        'FR' =>
            [
                'num'  => '65',
                'name' => 'France',
            ],
        'GA' =>
            [
                'num'  => '66',
                'name' => 'Gabon',
            ],
        'GM' =>
            [
                'num'  => '67',
                'name' => 'Gambia',
            ],
        'GE' =>
            [
                'num'  => '68',
                'name' => 'Georgia',
            ],
        'DE' =>
            [
                'num'  => '69',
                'name' => 'Germany',
            ],
        'GH' =>
            [
                'num'  => '70',
                'name' => 'Ghana',
            ],
        'GR' =>
            [
                'num'  => '71',
                'name' => 'Greece',
            ],
        'GD' =>
            [
                'num'  => '72',
                'name' => 'Grenada',
            ],
        'GT' =>
            [
                'num'  => '73',
                'name' => 'Guatemala',
            ],
        'GN' =>
            [
                'num'  => '74',
                'name' => 'Guinea',
            ],
        'GY' =>
            [
                'num'  => '76',
                'name' => 'Guyana',
            ],
        'HT' =>
            [
                'num'  => '77',
                'name' => 'Haiti',
            ],
        'HN' =>
            [
                'num'  => '79',
                'name' => 'Honduras',
            ],
        'HK' =>
            [
                'num'  => '80',
                'name' => 'Hong Kong',
            ],
        'HU' =>
            [
                'num'  => '81',
                'name' => 'Hungary',
            ],
        'IS' =>
            [
                'num'  => '82',
                'name' => 'Iceland',
            ],
        'IN' =>
            [
                'num'  => '83',
                'name' => 'India',
            ],
        'ID' =>
            [
                'num'  => '84',
                'name' => 'Indonesia',
            ],
        'IR' =>
            [
                'num'  => '85',
                'name' => 'Iran',
            ],
        'IQ' =>
            [
                'num'  => '86',
                'name' => 'Iraq',
            ],
        'IE' =>
            [
                'num'  => '87',
                'name' => 'Ireland',
            ],
        'IL' =>
            [
                'num'  => '88',
                'name' => 'Israel',
            ],
        'IT' =>
            [
                'num'  => '89',
                'name' => 'Italy',
            ],
        'JM' =>
            [
                'num'  => '90',
                'name' => 'Jamaica',
            ],
        'JP' =>
            [
                'num'  => '91',
                'name' => 'Japan',
            ],
        'JO' =>
            [
                'num'  => '92',
                'name' => 'Jordan',
            ],
        'KZ' =>
            [
                'num'  => '93',
                'name' => 'Kazakhstan',
            ],
        'KE' =>
            [
                'num'  => '94',
                'name' => 'Kenya',
            ],
        'KI' =>
            [
                'num'  => '95',
                'name' => 'Kiribati',
            ],
        'KP' =>
            [
                'num'  => '96',
                'name' => 'Korea, Democratic People\'s Rep',
            ],
        'KW' =>
            [
                'num'  => '99',
                'name' => 'Kuwait',
            ],
        'KG' =>
            [
                'num'  => '100',
                'name' => 'Kyrgyzstan',
            ],
        'LV' =>
            [
                'num'  => '102',
                'name' => 'Latvia',
            ],
        'LB' =>
            [
                'num'  => '103',
                'name' => 'Lebanon',
            ],
        'LS' =>
            [
                'num'  => '104',
                'name' => 'Lesotho',
            ],
        'LR' =>
            [
                'num'  => '105',
                'name' => 'Liberia',
            ],
        'LI' =>
            [
                'num'  => '107',
                'name' => 'Liechtenstein',
            ],
        'LT' =>
            [
                'num'  => '108',
                'name' => 'Lithuania',
            ],
        'LU' =>
            [
                'num'  => '109',
                'name' => 'Luxembourg',
            ],
        'MO' =>
            [
                'num'  => '110',
                'name' => 'Macau',
            ],
        'MG' =>
            [
                'num'  => '112',
                'name' => 'Madagascar',
            ],
        'MW' =>
            [
                'num'  => '113',
                'name' => 'Malawi',
            ],
        'MY' =>
            [
                'num'  => '114',
                'name' => 'Malaysia',
            ],
        'MV' =>
            [
                'num'  => '115',
                'name' => 'Maldives',
            ],
        'ML' =>
            [
                'num'  => '116',
                'name' => 'Mali',
            ],
        'MT' =>
            [
                'num'  => '117',
                'name' => 'Malta',
            ],
        'MH' =>
            [
                'num'  => '118',
                'name' => 'Marshall Islands',
            ],
        'MR' =>
            [
                'num'  => '119',
                'name' => 'Mauritania',
            ],
        'MU' =>
            [
                'num'  => '120',
                'name' => 'Mauritius',
            ],
        'MD' =>
            [
                'num'  => '122',
                'name' => 'Moldova',
            ],
        'MC' =>
            [
                'num'  => '123',
                'name' => 'Monaco',
            ],
        'MN' =>
            [
                'num'  => '124',
                'name' => 'Mongolia',
            ],
        'ME' =>
            [
                'num'  => '125',
                'name' => 'Montenegro',
            ],
        'MA' =>
            [
                'num'  => '126',
                'name' => 'Morocco',
            ],
        'MZ' =>
            [
                'num'  => '127',
                'name' => 'Mozambique',
            ],
        'NA' =>
            [
                'num'  => '128',
                'name' => 'Namibia',
            ],
        'NR' =>
            [
                'num'  => '129',
                'name' => 'Nauru',
            ],
        'NP' =>
            [
                'num'  => '130',
                'name' => 'Nepal',
            ],
        'NL' =>
            [
                'num'  => '131',
                'name' => 'Netherlands',
            ],
        'NZ' =>
            [
                'num'  => '133',
                'name' => 'New Zealand',
            ],
        'NI' =>
            [
                'num'  => '134',
                'name' => 'Nicaragua',
            ],
        'NG' =>
            [
                'num'  => '136',
                'name' => 'Nigeria',
            ],
        'NO' =>
            [
                'num'  => '137',
                'name' => 'Norway',
            ],
        'OM' =>
            [
                'num'  => '138',
                'name' => 'Oman',
            ],
        'PK' =>
            [
                'num'  => '139',
                'name' => 'Pakistan',
            ],
        'PW' =>
            [
                'num'  => '140',
                'name' => 'Palau',
            ],
        'PA' =>
            [
                'num'  => '142',
                'name' => 'Panama',
            ],
        'PG' =>
            [
                'num'  => '143',
                'name' => 'Papua New Guinea',
            ],
        'PY' =>
            [
                'num'  => '144',
                'name' => 'Paraguay',
            ],
        'PE' =>
            [
                'num'  => '145',
                'name' => 'Peru',
            ],
        'PH' =>
            [
                'num'  => '146',
                'name' => 'Philippines',
            ],
        'PL' =>
            [
                'num'  => '147',
                'name' => 'Poland',
            ],
        'PT' =>
            [
                'num'  => '148',
                'name' => 'Portugal',
            ],
        'QA' =>
            [
                'num'  => '149',
                'name' => 'Qatar',
            ],
        'RO' =>
            [
                'num'  => '150',
                'name' => 'Romania',
            ],
        'RU' =>
            [
                'num'  => '151',
                'name' => 'Russia',
            ],
        'RW' =>
            [
                'num'  => '152',
                'name' => 'Rwanda',
            ],
        'KN' =>
            [
                'num'  => '153',
                'name' => 'Saint Kitts and Nevis',
            ],
        'LC' =>
            [
                'num'  => '154',
                'name' => 'Saint Lucia',
            ],
        'WS' =>
            [
                'num'  => '156',
                'name' => 'Samoa',
            ],
        'SM' =>
            [
                'num'  => '157',
                'name' => 'San Marino',
            ],
        'ST' =>
            [
                'num'  => '158',
                'name' => 'Sao Tome and Principe',
            ],
        'SA' =>
            [
                'num'  => '159',
                'name' => 'Saudi Arabia',
            ],
        'SN' =>
            [
                'num'  => '160',
                'name' => 'Senegal',
            ],
        'SC' =>
            [
                'num'  => '162',
                'name' => 'Seychelles',
            ],
        'SL' =>
            [
                'num'  => '163',
                'name' => 'Sierra Leone',
            ],
        'SG' =>
            [
                'num'  => '164',
                'name' => 'Singapore',
            ],
        'SK' =>
            [
                'num'  => '166',
                'name' => 'Slovakia',
            ],
        'SI' =>
            [
                'num'  => '167',
                'name' => 'Slovenia',
            ],
        'SB' =>
            [
                'num'  => '168',
                'name' => 'Solomon Islands',
            ],
        'SO' =>
            [
                'num'  => '169',
                'name' => 'Somalia',
            ],
        'ZA' =>
            [
                'num'  => '170',
                'name' => 'South Africa',
            ],
        'KR' =>
            [
                'num'  => '172',
                'name' => 'South Korea',
            ],
        'ES' =>
            [
                'num'  => '173',
                'name' => 'Spain',
            ],
        'LK' =>
            [
                'num'  => '174',
                'name' => 'Sri Lanka',
            ],
        'SD' =>
            [
                'num'  => '177',
                'name' => 'Sudan',
            ],
        'SR' =>
            [
                'num'  => '178',
                'name' => 'Suriname',
            ],
        'SZ' =>
            [
                'num'  => '179',
                'name' => 'Swaziland',
            ],
        'SE' =>
            [
                'num'  => '180',
                'name' => 'Sweden',
            ],
        'CH' =>
            [
                'num'  => '181',
                'name' => 'Switzerland',
            ],
        'TJ' =>
            [
                'num'  => '184',
                'name' => 'Tajikistan',
            ],
        'TZ' =>
            [
                'num'  => '185',
                'name' => 'Tanzania',
            ],
        'TH' =>
            [
                'num'  => '186',
                'name' => 'Thailand',
            ],
        'TG' =>
            [
                'num'  => '188',
                'name' => 'Togo',
            ],
        'TO' =>
            [
                'num'  => '189',
                'name' => 'Tonga',
            ],
        'TT' =>
            [
                'num'  => '190',
                'name' => 'Trinidad and Tobago',
            ],
        'TN' =>
            [
                'num'  => '191',
                'name' => 'Tunisia',
            ],
        'TR' =>
            [
                'num'  => '192',
                'name' => 'Turkey',
            ],
        'TM' =>
            [
                'num'  => '193',
                'name' => 'Turkmenistan',
            ],
        'TV' =>
            [
                'num'  => '194',
                'name' => 'Tuvalu',
            ],
        'UG' =>
            [
                'num'  => '195',
                'name' => 'Uganda',
            ],
        'UA' =>
            [
                'num'  => '196',
                'name' => 'Ukraine',
            ],
        'AE' =>
            [
                'num'  => '197',
                'name' => 'United Arab Emirates',
            ],
        'GB' =>
            [
                'num'  => '198',
                'name' => 'United Kingdom',
            ],
        'UY' =>
            [
                'num'  => '199',
                'name' => 'Uruguay',
            ],
        'UZ' =>
            [
                'num'  => '200',
                'name' => 'Uzbekistan',
            ],
        'VU' =>
            [
                'num'  => '201',
                'name' => 'Vanuatu',
            ],
        'VE' =>
            [
                'num'  => '202',
                'name' => 'Venezuela',
            ],
        'VN' =>
            [
                'num'  => '203',
                'name' => 'Vietnam',
            ],
        'YE' =>
            [
                'num'  => '204',
                'name' => 'Yemen',
            ],
        'ZM' =>
            [
                'num'  => '205',
                'name' => 'Zambia',
            ],
        'ZW' =>
            [
                'num'  => '206',
                'name' => 'Zimbabwe',
            ],
    ];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        if ('MX' === $fields['Country']) {
            if (!isset(self::$mxStates[$fields['StateOrProvince']])) {
                throw new \UserInputError('Invalid state');
            }
            $fields['StateOrProvince'] = self::$mxStates[$fields['StateOrProvince']];
        }
        $this->fields = $fields;

        $this->validate();
        $this->http->GetURL('https://www.fiestarewards.com/en/inscripcion');
        $this->step1();
        $this->step2();

        return true;
    }

    public function getRegisterFields()
    {
        $countries = [];

        foreach (self::$countries as $code => $arr) {
            $countries[$code] = $arr['name'];
        }

        return [
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
                    'Caption'  => 'Birth Day',
                    'Required' => true,
                ],
            'BirthMonth' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Birth Month',
                    'Required' => true,
                ],
            'BirthYear' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Birth Year',
                    'Required' => true,
                ],
            'Gender' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Gender',
                    'Required' => true,
                    'Options'  => self::$genders,
                ],
            'MaritalStatus' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Marital Status',
                    'Required' => true,
                    'Options'  => self::$maritalStatuses,
                ],
            'AddressType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Address Type',
                    'Required' => true,
                    'Options'  => self::$addressTypes,
                ],
            'Country' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Country',
                    'Required' => true,
                    'Options'  => $countries,
                ],
            'PostalCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Zip',
                    'Required' => true,
                ],
            'ExteriorNumber' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Exterior Number',
                    'Required' => true,
                ],
            'InteriorNumber' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Interior Number',
                    'Required' => false,
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
                    'Caption'  => 'Address Line 2 (not used for Mexico, required for all other countries)',
                    'Required' => false,
                ],
            'StateOrProvince' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'State',
                    'Required' => true,
                ],
            'City' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'City (required for Mexico)',
                    'Required' => false,
                ],
            'NeighborhoodOrQuarter' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Neighborhood/Quarter (required for Mexico)',
                    'Required' => false,
                ],
            'PhoneType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Type',
                    'Required' => true,
                    'Options'  => self::$phoneTypes,
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
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email',
                    'Required' => true,
                ],
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password',
                    'Required' => true,
                ],
        ];
    }

    protected function validate()
    {
        $fields = $this->fields;

        $this->birthDate =
            str_pad($fields['BirthDay'], 2, 0, STR_PAD_LEFT)
            . '/' . str_pad($fields['BirthMonth'], 2, 0, STR_PAD_LEFT)
            . '/' . $fields['BirthYear'];

        if (!strtotime(str_replace('/', '.', $this->birthDate))) {
            throw new \UserInputError('Invalid combination of birth day/month/year values');
        }

        if ($fields['Country'] == 'MX') {
            // Fiesta site validates postal codes only for Mexico
            $this->http->setCookie('GUEST_LANGUAGE_ID', 'en_US', 'www.fiestarewards.com');
            $status = $this->http->PostURL('https://www.fiestarewards.com/c/portal/colonia', ['cp' => $fields['PostalCode']]);

            if (!$status) {
                throw new \EngineError('Failed to POST postal code validation request');
            }

            if ($this->http->FindPreg('#The Zip Code doesn\'t exist#')) {
                throw new \UserInputError('The Zip Code doesn\'t exist.');
            }
        }

        if ($fields['Country'] == 'MX' and $fields['AddressLine2'] != '') {
            throw new \UserInputError('AddressLine2 field is not supported for Mexico country');
        }

        if ($fields['Country'] != 'MX' and $fields['AddressLine2'] == '') {
            throw new \UserInputError('AddressLine2 field is required for non-Mexico country and should not be empty');
        }

        $this->phoneNumber = $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];

        if (!preg_match('#^\d{10}$#', $this->phoneNumber)) {
            throw new \UserInputError('The phone number (PhoneAreaCode + PhoneLocalNumber) must be 10 digits without hyphens or spaces.');
        }

        $password = $fields['Password'];

        if (strlen($password) < 4 or strlen($password) > 12 or !preg_match('#^\w+$#', $password)) {
            throw new \UserInputError('The password must be alphanumeric, no special characters, and 4 to 12 characters.');
        }
    }

    protected function step1()
    {
        $fields = $this->fields;

        $status = $this->http->ParseForm('fm');

        if (!$status) {
            throw new \EngineError('Failed to parse registration form step 1');
        }

        $step1Fields = [
            'contactId'                                                         => '',
            '_registrogenericos_WAR_ampersandportlet_terminos-y-avisos'         => 'true',
            '_registrogenericos_WAR_ampersandportlet_terminos-y-avisosCheckbox' => 'true',
            'promoCode'                                                         => '',
            'name'                                                              => $fields['FirstName'],
            'middleName'                                                        => '',
            'lastName'                                                          => $fields['LastName'],
            'secondLastName'                                                    => '',
            'birthDate'                                                         => $this->birthDate,
            'gender'                                                            => $fields['Gender'],
            'maritalStatus'                                                     => $fields['MaritalStatus'],
        ];

        foreach ($step1Fields as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        $status = $this->http->PostForm();

        if (!$status) {
            throw new \EngineError('Failed to POST registration form step 1');
        }

        $step1SuccessXpath = '//label[contains(normalize-space(.), "Address Type")]';

        if ($this->http->FindSingleNode($step1SuccessXpath)) {
            $this->logger->info('First step passed successfully');
        } elseif ($errors = $this->http->FindNodes('//div[@class="portlet-msg-error"]')) {
            throw new \UserInputError(implode(' ', $errors));
        } else {
            throw new \EngineError('Unexpected response after first step submit');
        }
    }

    protected function step2()
    {
        $fields = $this->fields;

        $status = $this->http->ParseForm('fm');

        if (!$status) {
            throw new \EngineError('Failed to parse registration form step 2');
        }

        $countryCode = self::$countries[$fields['Country']]['num'];
        $this->logger->debug('Mapped standard country code ' . $fields['Country'] . ' to provider code ' . $countryCode);

        $step2Fields = [
            'name'           => $fields['FirstName'],
            'middleName'     => '',
            'lastName'       => $fields['LastName'],
            'secondLastName' => '',
            'birthDate'      => $this->birthDate,
            'maritalStatus'  => $fields['MaritalStatus'],
            'gender'         => $fields['Gender'],
            'nameCompany'    => 'null',
            'promoCode'      => '',
            'contactId'      => '',
            'useType'        => $fields['AddressType'],
            'country'        => $countryCode,
            'indexTemp'      => '15', // seems that it is some magic constant
            'zipCode'        => $fields['PostalCode'],
            'state'          => $fields['Country'] == 'MX' ? $fields['StateOrProvince'] : '', // For Mexico country state is placed here due to site logic, for other - in addressLine2 (yes, it is stupid logic)
            'city'           => $fields['City'],
            //			'county' => $fields['County'],
            'colony'         => $fields['NeighborhoodOrQuarter'],
            'streetAddress'  => $fields['AddressLine1'],
            'exteriorNumber' => $fields['ExteriorNumber'],
            'interiorNumber' => $fields['InteriorNumber'],
            'addressLine1'   => $fields['AddressLine2'],
            'addressLine2'   => $fields['Country'] != 'MX' ? $fields['StateOrProvince'] : '', // For non-Mexico country state is placed here due to site logic, for other - in addressLine2 (yes, it is stupid logic)
            'useTypePhone'   => $fields['PhoneType'],
            'phoneNumber'    => $this->phoneNumber,
            'email'          => $fields['Email'],
            'email2'         => $fields['Email'],
            'password'       => $fields['Password'],
            'password2'      => $fields['Password'],
        ];

        foreach ($step2Fields as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }

        $status = $this->http->PostForm();

        if (!$status) {
            throw new \EngineError('Failed to POST registration form step 2');
        }

        $step2SuccessRegexp = '#<h1>\s*Welcome\s+to\s+<b>\s*FIESTA\s+REWARDS\s*</b>#i';

        if ($this->http->FindPreg($step2SuccessRegexp)) {
            $this->logger->info('Second step passed successfully');

            if ($membershipNumber = $this->http->FindSingleNode('//p[contains(normalize-space(.), "Your member number is:")]/following-sibling::h3')) {
                $this->ErrorMessage = 'Welcome to FIESTA REWARDS. Your member number is: ' . $membershipNumber;
                $this->logger->info($this->ErrorMessage);

                return;
            } else {
                throw new \EngineError('Seems that registration succeeded, but could not find member number');
            }
        } elseif ($errors = $this->http->FindNodes('//div[@class="portlet-msg-error"]')) {
            throw new \UserInputError(implode(' ', $errors));
        } else {
            throw new \EngineError('Unexpected response after second step submit');
        }
    }

    //	static $inputFieldsMap = array (
//		'FirstName' => '',
//		'LastName' => '',
//		'BirthDate' => '',
//		'Gender' => '',
//		'MaritalStatus' => '',
//		'AddressType' => '',
//		'Country' => '',
//		'PostalCode' => '',
//		'ExteriorNumber' => '',
//		'AddressLine' => '',
//		'StateOrProvince' => '',
//		'City' => '',
//		'NeighborhoodOrQuarter' => '',
//		'PhoneType' => '',
//		'PhoneAreaCode' => '',
//		'PhoneLocalNumber' => '',
//		'Email' => '',
//		'Password' => '',
//	);
}
