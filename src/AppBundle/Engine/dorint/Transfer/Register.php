<?php

namespace AwardWallet\Engine\dorint\Transfer;

class Register extends \TAccountCheckerDorint
{
    public static $preferredLanguages = [
        'en' => 'English',
        'de' => 'German',
    ];

    public static $titlesEnglish = [
        0  => 'Mr./Mrs',
        1  => 'Mr.',
        2  => 'Mrs.',
        3  => 'Ms.',
        5  => 'Family',
        6  => '.',
        7  => 'Mr. and Mrs.',
        8  => 'Professor',
        9  => 'Professor Dr.',
        10 => 'Dr.',
        11 => 'Dr.',
        12 => 'Prof.',
        20 => 'Ambassador',
        21 => 'Comte',
        22 => 'Countess',
    ];

    public static $titlesGerman = [
        0  => 'Herr/Frau',
        1  => 'Herr',
        2  => 'Frau',
        3  => 'Frl',
        4  => 'Familie',
        5  => 'Familie',
        6  => '.',
        7  => 'Herr und Frau',
        8  => 'Herrn Professor',
        9  => 'Herrn Professor Dr.',
        10 => 'Frau Dr.',
        11 => 'Herr Dr.',
        12 => 'Herr Prof.',
        13 => 'Herr Prof. Dr.',
        21 => 'Frau Dr.',
        22 => 'Frau Prof.',
        23 => 'Frau Prof. Dr.',
        30 => 'Botschafter',
        31 => 'Baron',
        32 => 'Baronin',
        33 => 'Fuerst',
        34 => 'Fuerstin',
        35 => 'Graf',
        36 => 'Graefin',
        37 => 'Herzog',
        38 => 'Herzogin',
        39 => 'Kaiserliche Hoheit',
        40 => 'Koenigliche Hoheit',
        41 => 'Konsul',
        42 => 'Prinz',
        43 => 'Prinzessin',
    ];

    public static $phoneTypes = [
        'Mobile' => 'Mobile',
        'Work'   => 'Work',
        'Home'   => 'Home',
    ];

    public static $countries = [
        'AF' => 'AFGHANISTAN',
        'AL' => 'ALBANIA',
        'DZ' => 'ALGERIA',
        'AD' => 'ANDORRA',
        'AO' => 'ANGOLA',
        'AI' => 'ANGUILLA',
        'AQ' => 'ANTARCTICA',
        'AG' => 'ANTIGUA AND BARBUDA',
        'AR' => 'ARGENTINA',
        'AM' => 'ARMENIA',
        'AW' => 'ARUBA',
        'AU' => 'AUSTRALIA',
        'AT' => 'AUSTRIA',
        'AZ' => 'AZERBAIJAN',
        'AX' => 'Aaland Islands',
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
        'BA' => 'BOSNIA',
        'BW' => 'BOTSWANA',
        'BV' => 'BOUVET ISLAND',
        'BR' => 'BRAZIL',
        'IO' => 'BRITISH INDIAN OCEAN',
        'VG' => 'BRITISH VIRGIN ISLAN',
        'BN' => 'BRUNEI',
        'BG' => 'BULGARIA',
        'BF' => 'BURKINA FASO',
        'BI' => 'BURUNDI',
        'KH' => 'CAMBODIA',
        'CM' => 'CAMEROON',
        'CA' => 'CANADA',
        'CV' => 'CAP VERDE',
        'KY' => 'CAYMAN ISLANDS',
        'CF' => 'CENTRAL AFRICAN REP.',
        'TD' => 'CHAD',
        'CL' => 'CHILE',
        'CN' => 'CHINA',
        'CX' => 'CHRISTMAS ISLAND',
        'CC' => 'COCOS (KEELING) ISLA',
        'CO' => 'COLOMBIA',
        'KM' => 'COMOROS',
        'CG' => 'CONGO',
        'CD' => 'CONGO, THE DEMOCRATI',
        'CK' => 'COOK ISLAND',
        'CR' => 'COSTA RICA',
        'HR' => 'CROATIA',
        'CU' => 'CUBA',
        'CY' => 'CYPRUS',
        'CZ' => 'CZECH REPUBLIC',
        'DK' => 'DENMARK',
        'DJ' => 'DJIBOUTI',
        'DM' => 'DOMINICA',
        'DO' => 'DOMINICAN REP.',
        'EC' => 'ECUADOR',
        'EG' => 'EGYPT',
        'SV' => 'EL SALVADOR',
        'GQ' => 'EQUATORIAL GUINEA',
        'ER' => 'ERITREA',
        'EE' => 'ESTONIA',
        'ET' => 'ETHIOPIA',
        'FK' => 'FALKLAND/MALVINAS',
        'FO' => 'FAROE ISLANDS',
        'FJ' => 'FIJI ISLANDS',
        'FI' => 'FINLAND',
        'FR' => 'FRANCE',
        'GF' => 'FRENCH GUIANA',
        'PF' => 'FRENCH POLYNESIA',
        'TF' => 'FRENCH SOUTHERN TERR',
        'GA' => 'GABOON',
        'GM' => 'GAMBIA',
        'GE' => 'GEORGIA',
        'DE' => 'GERMANY',
        'GH' => 'GHANA',
        'GI' => 'GIBRALTAR',
        'GR' => 'GREECE',
        'GL' => 'GREENLAND',
        'GD' => 'GRENADA',
        'GP' => 'GUADELOUPE',
        'GU' => 'GUAM',
        'GT' => 'GUATEMALA',
        'GN' => 'GUINEA',
        'GW' => 'GUINEA BISSAU',
        'GY' => 'GUYANA',
        'GG' => 'Guernsey',
        'HT' => 'HAITI',
        'HM' => 'HEARD AND MC DONALD',
        'VA' => 'HOLY SEE (VATICAN CI',
        'HN' => 'HONDURAS',
        'HK' => 'HONG KONG',
        'HU' => 'HUNGARY',
        'IS' => 'ICELAND',
        'IN' => 'INDIA',
        'ID' => 'INDONESIA',
        'IQ' => 'IRAK',
        'IR' => 'IRAN',
        'IE' => 'IRELAND',
        'IL' => 'ISRAEL',
        'IT' => 'ITALY',
        'CI' => 'IVORY COAST',
        'JM' => 'JAMAICA',
        'JP' => 'JAPAN',
        'JO' => 'JORDAN',
        'KZ' => 'KAZAKHSTAN',
        'KE' => 'KENYA',
        'KI' => 'KIRIBATI',
        'KW' => 'KUWAIT',
        'KG' => 'KYRGYZTAN',
        'LA' => 'LAOS',
        'LV' => 'LATVIA',
        'LB' => 'LEBANON',
        'LS' => 'LESOTHO',
        'LR' => 'LIBERIA',
        'LY' => 'LIBYA',
        'LI' => 'LIECHENSTEIN',
        'LT' => 'LITHUANIA',
        'LU' => 'LUXEMBOURG',
        'MO' => 'MACAU',
        'MK' => 'MACEDONIA',
        'MG' => 'MADAGASCAR',
        'MW' => 'MALAWI',
        'MY' => 'MALAYSIA',
        'MV' => 'MALDIVES',
        'ML' => 'MALI',
        'MT' => 'MALTA',
        'MH' => 'MARSHALL ISLAND',
        'MQ' => 'MARTINIQUE',
        'MR' => 'MAURITANIA',
        'MU' => 'MAURITIUS',
        'YT' => 'MAYOTTE',
        'MX' => 'MEXICO',
        'FM' => 'MICRONESIA',
        'MD' => 'MOLDOVA',
        'MC' => 'MONACO',
        'MN' => 'MONGOLIA',
        'MS' => 'MONTSERRAT',
        'MA' => 'MOROCCO',
        'MZ' => 'MOZAMBIQUE',
        'MM' => 'MYANMAR',
        'ME' => 'Montenegro',
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
        'KP' => 'NORTH KOREA ',
        'NO' => 'NORWAY',
        'MP' => 'NOTHERN MARIANA',
        'OM' => 'OMAN',
        'PK' => 'PAKISTAN',
        'PW' => 'PALAU',
        'PS' => 'PALESTINIAN TERRITOR',
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
        'RE' => 'REUNION',
        'RO' => 'ROMANIA',
        'RU' => 'RUSSIA',
        'RW' => 'RWANDA',
        'KN' => 'SAINT KITTS AND NEVI',
        'LC' => 'SAINT LUCIA',
        'AS' => 'SAMOA AMERICAN',
        'WS' => 'SAMOA WESTERN',
        'SM' => 'SAN MARINO',
        'ST' => 'SAO TOME AND PRINCIP',
        'SA' => 'SAUDI ARABIA',
        'SN' => 'SENEGAL',
        'RS' => 'SERBIA',
        'SC' => 'SEYCHELLES',
        'SL' => 'SIERRA LEONE',
        'SG' => 'SINGAPORE',
        'SK' => 'SLOVAKIA',
        'SI' => 'SLOVENIA',
        'SB' => 'SOLOMON',
        'SO' => 'SOMALIA',
        'ZA' => 'SOUTH AFRICA',
        'GS' => 'SOUTH GEORGIA AND TH',
        'KR' => 'SOUTH KOREA',
        'ES' => 'SPAIN',
        'LK' => 'SRI LANKA',
        'BL' => 'ST BARTHELEMY',
        'PM' => 'ST PIERRE ET MIQUELO',
        'VC' => 'ST VINCENT',
        'SD' => 'SUDAN',
        'SR' => 'SURINAME',
        'SJ' => 'SVALBARD AND JAN MAY',
        'SZ' => 'SWAZILAND',
        'SE' => 'SWEDEN',
        'CH' => 'SWITZERLAND',
        'SY' => 'SYRIA',
        'MF' => 'Saint Martin',
        'TW' => 'TAIWAN',
        'TJ' => 'TAJKISTAN',
        'TZ' => 'TANZANIA',
        'TH' => 'THAILAND',
        'TG' => 'TOGO',
        'TK' => 'TOKELAU',
        'TO' => 'TONGA',
        'TT' => 'TRINIDAD TOBACCO',
        'TN' => 'TUNISIA',
        'TR' => 'TURKEY',
        'TM' => 'TURKMENISTAN',
        'TC' => 'TURKS AND CAICOS',
        'TV' => 'TUVALU',
        'TL' => 'Timor Leste',
        'UG' => 'UGANDA',
        'UA' => 'UKRAINE',
        'AE' => 'UNITED ARABIAN EMIRA',
        'GB' => 'UNITED KINGDOM',
        'US' => 'UNITED STATES',
        'UM' => 'UNITED STATES MINOR',
        'UY' => 'URUGUAY',
        'UZ' => 'UZBEKISTAN',
        'VU' => 'VANUATU',
        'VE' => 'VENEZUELA',
        'VN' => 'VIETNAM',
        'VI' => 'VIRGIN ISLANDS (U.S.',
        'WF' => 'WALLIS AND FUTUNA',
        'EH' => 'WESTERN SAHARA',
        'YE' => 'YEMEN',
        'ZM' => 'ZAMBIA',
        'ZW' => 'ZIMBABWE',
    ];

    public static $inputFieldsMap = [
        'PreferredLanguage' => 'language',
        'LastName'          => 'lastname',
        'FirstName'         => 'firstname',
        'Email'             => 'email',
        'AddressLine1'      => 'street',
        'AddressLine2'      => 'building',
        'PostalCode'        => 'zip',
        'City'              => 'city',
        'Country'           => 'country',
    ];

    protected $languageMap = [
        'en' => 'E',
        'de' => 'G',
    ];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;

        $fields['PreferredLanguage'] = $this->languageMap[$fields['PreferredLanguage']];
        $this->http->GetURL('https://dorintservice.dorint.com/centralmember/register.aspx');
        $status = $this->http->ParseForm('aspnetForm');

        if (!$status) {
            $this->http->Log('Failed to parse create account form');

            return false;
        }

        // Provider uses wrong country codes for:
        // - SERBIA (YU instead of standard RS)
        // - ST BARTHELEMY (SH instead of standard BL)
        // Map from our standard ISO code to wrong code used by provider
        $wrongCountryCodesFixingMap = [
            'RS' => 'YU',
            'BL' => 'SH',
        ];

        if (isset($wrongCountryCodesFixingMap[$fields['Country']])) {
            $origCountryCode = $fields['Country'];
            $fields['Country'] = $wrongCountryCodesFixingMap[$fields['Country']];
            $this->logger->debug('Mapped standard country code "' . $origCountryCode . '" to provider code "' . $fields['Country'] . '"');
        }

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey])) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $diff = ['language', 'country'];
                $sep = in_array($provKey, $diff) ? '_' : '$';
                $this->http->SetInputValue("ctl00{$sep}Content{$sep}" . $provKey, $fields[$awKey]);
            }
        }

        //choose greeting field
        $greetField = 'Title' . $fields['PreferredLanguage'];

        if (!isset($fields[$greetField]) or trim($fields[$greetField]) === '') {
            throw new \UserInputError($greetField . ' is required field');
        }

        //phone logic
        $phoneTypes = [
            'Mobile' => 'mob',
            'Work'   => 'bus',
            'Home'   => 'home',
        ];
        $phoneFieldName = $phoneTypes[$fields['PhoneType']];
        $phone = '+' . $fields['PhoneCountryCodeNumeric'] . $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'];

        //set values
        $this->http->SetInputValue('ctl00_Content_greetingid', $fields[$greetField]);
        $this->http->SetInputValue('ctl00$Content$phone_' . $phoneFieldName, $phone);
        $this->http->SetInputValue('ctl00$Content$donotmail', $fields['ReceiveNewsViaRegularMail'] ? 'on' : '');
        $this->http->SetInputValue('ctl00$Content$donotemail', 'on');
        $this->http->SetInputValue('ctl00$Content$register_tc', 'on');
        $this->http->SetInputValue('ctl00$ActionRequest', 'Register');

        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post create account form');

            return false;
        }

        if ($statusMessage = $this->http->FindSingleNode("//div[contains(@class,'statusmessage-info')]/label")) {
            throw new \UserInputError($statusMessage);
        } // Is it always user input error?

        if ($successMessage = $this->http->FindSingleNode("//p[contains(text(), 'you for registering')]/following-sibling::text()[1]")) {
            $this->ErrorMessage = $successMessage;

            return true;
        }

        throw new \EngineError('Unexpected response on account registration submit');
        //		return false;
    }

    public function getRegisterFields()
    {
        return [
            'PreferredLanguage' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Language',
                    'Required' => true,
                    'Options'  => self::$preferredLanguages,
                ],
            'TitleE' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Salutation in English',
                    'Required' => false,
                    'Options'  => self::$titlesEnglish,
                ],
            'TitleG' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Salutation in German',
                    'Required' => false,
                    'Options'  => self::$titlesGerman,
                ],
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Last Name',
                    'Required' => true,
                ],
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Firs Name',
                    'Required' => true,
                ],
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email',
                    'Required' => true,
                ],
            'PhoneCountryCodeNumeric' =>
                [
                    'Type'     => 'string',
                    'Caption'  => '1-3 numbers Phone Country Code',
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
            'PhoneType' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Type',
                    'Required' => true,
                    'Options'  => self::$phoneTypes,
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
            'PostalCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Postal Code',
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
                    'Caption'  => 'Country',
                    'Required' => true,
                    'Options'  => self::$countries,
                ],
            'ReceiveNewsViaRegularMail' =>
                [
                    'Type'     => 'boolean',
                    'Caption'  => 'Please send me news via regular mail',
                    'Required' => false,
                ],
        ];
    }
}
