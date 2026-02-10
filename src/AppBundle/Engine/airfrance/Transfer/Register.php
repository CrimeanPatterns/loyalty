<?php

// case #10318

namespace AwardWallet\Engine\airfrance\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountCheckerAirfrance
{
    use ProxyList;

    public const REGISTER_URL = 'http://www.airfrance.us/cgi-bin/AF/US/en/local/core/engine/myaccount/action/DisplayFBRegisterAction.do';

    public static $fieldMap = [
        'Title'     => 'civility',
        'LastName'  => 'lastName',
        'FirstName' => 'firstName',

        'PhoneCountryCodeAlphabetic' => 'mobileCountryCode',
        'PhoneCountryCodeNumeric'    => 'mobileCode',
        'Phone'                      => 'mobilePhone',

        'Email'     => 'emailAddress',
        'Email2'    => 'confEmailInput',
        'Password'  => 'password',
        'Password2' => 'confPassword',

        'SecurityQuestionType1'   => 'personalQuestion',
        'SecurityQuestionAnswer1' => 'personalAnswer',
        'PromoCode'               => 'promotionalCode',

        'BirthMonth' => 'monthDateTagLight',
        'BirthDay'   => 'dayDateTagLight',
        'BirthYear'  => 'yearDateTagLight',

        'Country'           => 'countryCode',
        'StateOrProvince'   => 'stateCode',
        'PreferredLanguage' => 'communicationLanguage',
    ];

    public static $titles = [
        'MR'  => 'Mr',
        'MRS' => 'Mrs',
    ];

    public static $securityQuestionTypes = [
        'Q1' => 'What\'s the name of your favourite pet?',
        'Q2' => 'What\'s the place of birth of your mother?',
        'Q3' => 'What\'s your favourite movie?',
        'Q4' => 'What\'s your favourite pop or rock band?',
        'Q5' => 'What\'s your favourite book?',
        'Q6' => 'What\'s your favourite destination?',
        'Q7' => 'What\'s your favourite recreation?',
    ];

    public static $preferredLanguages = [
        'EN' => 'English',
        'FR' => 'French',
    ];

    public static $countries = [
        'AF' => 'AFGHANISTAN',
        'AL' => 'ALBANIA',
        'DZ' => 'ALGERIA',
        'AS' => 'AMERICAN SAMOA',
        'AD' => 'ANDORRA',
        'AO' => 'ANGOLA',
        'AI' => 'ANGUILLA',
        'AG' => 'ANTIGUA AND BARBADE',
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
        'BA' => 'BOSNIA HERZEGOVINA',
        'BW' => 'BOTSWANA',
        'BR' => 'BRAZIL',
        'VG' => 'BRITISH VIRGIN ISLANDS',
        'BN' => 'BRUNEI',
        'BG' => 'BULGARIA',
        'BF' => 'BURKINA FASO',
        'BI' => 'BURUNDI',
        'BQ' => 'Bonaire, Sint Eustatius, Saba',
        'KH' => 'CAMBODIA',
        'CM' => 'CAMEROON',
        'CA' => 'CANADA',
        'CV' => 'CAPE VERDE',
        'KY' => 'CAYMAN ISLANDS',
        'CF' => 'CENTRAL AFRICAN REP',
        'TD' => 'CHAD',
        'CL' => 'CHILE',
        'CN' => 'CHINA',
        'CX' => 'CHRISTMAS ISLANDS',
        'CC' => 'COCOS ISLANDS',
        'CO' => 'COLOMBIA',
        'KM' => 'COMOROS',
        'CG' => 'CONGO BRAZZAVILLE',
        'CD' => 'CONGO THE DEM REP',
        'CK' => 'COOK ISLANDS',
        'CR' => 'COSTA RICA',
        'HR' => 'CROATIA',
        'CU' => 'CUBA',
        'CW' => 'CURACAO',
        'CY' => 'CYPRUS',
        'CZ' => 'CZECH REP',
        'CI' => 'Côte d Ivoire',
        'DK' => 'DENMARK',
        'DJ' => 'DJIBOUTI',
        'DM' => 'DOMINICA',
        'DO' => 'DOMINICAN REP',
        'EC' => 'ECUADOR',
        'EG' => 'EGYPT',
        'SV' => 'EL SALVADOR',
        'GQ' => 'EQUATORIAL GUINEA',
        'ER' => 'ERITREA',
        'EE' => 'ESTONIA',
        'ET' => 'ETHIOPIA',
        'FK' => 'FALKLAND ISLANDS',
        'FO' => 'FAROE ISLANDS',
        'FJ' => 'FIJI',
        'FI' => 'FINLAND',
        'FR' => 'FRANCE',
        'GF' => 'FRENCH GUIANA',
        'PF' => 'FRENCH POLYNESIA',
        'GA' => 'GABON',
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
        'HT' => 'HAITI',
        'HN' => 'HONDURAS',
        'HK' => 'HONG KONG',
        'HU' => 'HUNGARY',
        'IS' => 'ICELAND',
        'IN' => 'INDIA',
        'ID' => 'INDONESIA',
        'IR' => 'IRAN',
        'IQ' => 'IRAQ',
        'IE' => 'IRELAND',
        'IL' => 'ISRAEL',
        'IT' => 'ITALY',
        'JM' => 'JAMAICA',
        'JP' => 'JAPAN',
        'JO' => 'JORDAN',
        'KZ' => 'KAZAKHSTAN',
        'KE' => 'KENYA',
        'KI' => 'KIRIBATI',
        'KR' => 'KOREA',
        'KW' => 'KUWAIT',
        'KG' => 'KYRGYZSTAN',
        'LA' => 'LAOS',
        'LV' => 'LATVIA',
        'LB' => 'LEBANON',
        'LS' => 'LESOTHO',
        'LR' => 'LIBERIA',
        'LI' => 'LIECHTENSTEIN',
        'LT' => 'LITHUANIA',
        'LU' => 'LUXEMBOURG',
        'LY' => 'LYBYAN ARAB JAMAHIRI',
        'MO' => 'MACAU',
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
        'YT' => 'MAYOTTE',
        'MX' => 'MEXICO',
        'UM' => 'MINOR OUTLYING ISL.',
        'MD' => 'MOLDOVA',
        'MC' => 'MONACO',
        'MN' => 'MONGOLIA',
        'ME' => 'MONTENEGRO',
        'MS' => 'MONTSERRAT',
        'MA' => 'MOROCCO',
        'MZ' => 'MOZAMBIQUE',
        'MM' => 'MYANMAR',
        'FM' => 'Micronesia',
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
        'KP' => 'NORTH KOREA',
        'NO' => 'NORWAY',
        'MP' => 'NOTHERN MARIANA ISLANDS',
        'OM' => 'OMAN',
        'PK' => 'PAKISTAN',
        'PW' => 'PALAU ISLANDS',
        'PS' => 'PALESTINA',
        'PA' => 'PANAMA',
        'PG' => 'PAPUA NEW GUINEA',
        'PY' => 'PARAGUAY',
        'PE' => 'PERU',
        'PH' => 'PHILIPPINES',
        'PL' => 'POLAND',
        'PT' => 'PORTUGAL',
        'PR' => 'PUERTO RICO',
        'QA' => 'QATAR',
        'RE' => 'REUNION',
        'RO' => 'ROMANIA',
        'RU' => 'RUSSIA',
        'RW' => 'RWANDA',
        'SH' => 'SAINT HELENA ISLANDS',
        'LC' => 'SAINT LUCIA',
        'WS' => 'SAMOA',
        'SM' => 'SAN MARINO',
        'ST' => 'SAO TOME AND PRINCIP',
        'SA' => 'SAUDI ARABIA',
        'SN' => 'SENEGAL',
        'RS' => 'SERBIA',
        'SC' => 'SEYCHELLES',
        'SL' => 'SIERRA LEONE',
        'SG' => 'SINGAPORE',
        'SX' => 'SINT MAARTEN',
        'SJ' => 'SJ',
        'SK' => 'SLOVAKIA',
        'SI' => 'SLOVENIA',
        'SB' => 'SOLOMON ISLANDS',
        'SO' => 'SOMALIA',
        'ZA' => 'SOUTH AFRICA',
        'ES' => 'SPAIN',
        'LK' => 'SRI LANKA',
        'PM' => 'ST PIERRE AND MIQUEL',
        'VC' => 'ST VINCENT GRENADINES MUSTIQUE',
        'SD' => 'SUDAN',
        'SR' => 'SURINAME',
        'SZ' => 'SWAZILAND',
        'SE' => 'SWEDEN',
        'CH' => 'SWITZERLAND',
        'SY' => 'SYRIAN ARAB REP',
        'MF' => 'Saint Martin',
        'SS' => 'South Sudan',
        'KN' => 'St Kitts Nevis',
        'TW' => 'TAIWAN',
        'TJ' => 'TAJIKISTAN',
        'TZ' => 'TANZANIA',
        'TH' => 'THAILAND',
        'TL' => 'TIMOR LESTE',
        'TG' => 'TOGO',
        'TK' => 'TOKELAU',
        'TO' => 'TONGA',
        'TT' => 'TRINIDAD AND TOBAGO',
        'TN' => 'TUNISIA',
        'TR' => 'TURKEY',
        'TM' => 'TURKMENISTAN',
        'TC' => 'TURKS AND CAICOS ISLANDS',
        'TV' => 'Tuvalu',
        'UG' => 'UGANDA',
        'UA' => 'UKRAINE',
        'AE' => 'UNITED ARAB EMIRATES',
        'GB' => 'UNITED KINGDOM',
        'UY' => 'URUGUAY',
        'VI' => 'US VIRGIN ISLANDS',
        'UZ' => 'UZBEKISTAN',
        'US' => 'United States of America',
        'VU' => 'VANUATU',
        'VA' => 'VATICAN',
        'VE' => 'VENEZUELA',
        'VN' => 'VIETNAM',
        'WF' => 'WALLIS AND FUTUNA',
        'YE' => 'YEMEN',
        'ZM' => 'ZAMBIA',
        'ZW' => 'ZIMBABWE',
    ];

    public static $phoneCountryCodesAlphabetic = [
        'AF' => 'Afghanistan (+ 93)',
        'AL' => 'Albania (+ 355)',
        'DZ' => 'Algeria (+ 213)',
        'AS' => 'American Samoa (+ 684)',
        'AD' => 'Andorra (+ 376)',
        'AO' => 'Angola (+ 244)',
        'AI' => 'Anguilla (+ 1264)',
        'AG' => 'Antigua And Barbuda (+ 1268)',
        'AR' => 'Argentina (+ 54)',
        'AM' => 'Armenia (+ 374)',
        'AW' => 'Aruba (+ 297)',
        'AU' => 'Australia (+ 61)',
        'AT' => 'Austria (+ 43)',
        'AZ' => 'Azerbaijan (+ 994)',
        'BS' => 'Bahamas (+ 1242)',
        'BH' => 'Bahrain (+ 973)',
        'BD' => 'Bangladesh (+ 880)',
        'BB' => 'Barbados (+ 1246)',
        'BY' => 'Belarus (+ 375)',
        'BE' => 'Belgium (+ 32)',
        'BZ' => 'Belize (+ 501)',
        'BJ' => 'Benin (+ 229)',
        'BM' => 'Bermuda (+ 1441)',
        'BT' => 'Bhutan (+ 975)',
        'BO' => 'Bolivia (+ 591)',
        'BA' => 'Bosnia Herzegovina (+ 387)',
        'BW' => 'Botswana (+ 267)',
        'BR' => 'Brazil (+ 55)',
        'VG' => 'British Virgin Islands (+ 1)',
        'BN' => 'Brunei (+ 673)',
        'BG' => 'Bulgaria (+ 359)',
        'BF' => 'Burkina Faso (+ 226)',
        'BI' => 'Burundi (+ 257)',
        'KH' => 'Cambodia (+ 855)',
        'CM' => 'Cameroon (+ 237)',
        'CA' => 'Canada (+ 1)',
        'CV' => 'Cape Verde (+ 238)',
        'KY' => 'Cayman Islands (+ 1345)',
        'CF' => 'Central African Rep (+ 236)',
        'TD' => 'Chad (+ 235)',
        'CL' => 'Chile (+ 56)',
        'CN' => 'China (+ 86)',
        'CX' => 'Christmas Islands (+ 672)',
        'CC' => 'Cocos Islands (+ 672)',
        'CO' => 'Colombia (+ 57)',
        'KM' => 'Comoros (+ 269)',
        'CG' => 'Congo Brazzaville (+ 242)',
        'CD' => 'Congo The Dem Rep (+ 243)',
        'CK' => 'Cook Islands (+ 682)',
        'CR' => 'Costa Rica (+ 506)',
        'HR' => 'Croatia (+ 385)',
        'CU' => 'Cuba (+ 53)',
        'CW' => 'Curacao (+ 599)',
        'CY' => 'Cyprus (+ 357)',
        'CZ' => 'Czech Rep (+ 420)',
        'DK' => 'Denmark (+ 45)',
        'DJ' => 'Djibouti (+ 253)',
        'DM' => 'Dominica (+ 1767)',
        'DO' => 'Dominican Rep (+ 1)',
        'EC' => 'Ecuador (+ 593)',
        'EG' => 'Egypt (+ 20)',
        'GQ' => 'Equatorial Guinea (+ 240)',
        'ER' => 'Eritrea (+ 291)',
        'EE' => 'Estonia (+ 372)',
        'ET' => 'Ethiopia (+ 251)',
        'FK' => 'Falkland Islands (+ 500)',
        'FO' => 'Faroe Islands (+ 298)',
        'FJ' => 'Fiji (+ 679)',
        'FI' => 'Finland (+ 358)',
        'FR' => 'France (+ 33)',
        'GF' => 'French Guiana (+ 594)',
        'PF' => 'French Polynesia (+ 689)',
        'GA' => 'Gabon (+ 241)',
        'GM' => 'Gambia (+ 220)',
        'GE' => 'Georgia (+ 995)',
        'DE' => 'Germany (+ 49)',
        'GH' => 'Ghana (+ 233)',
        'GI' => 'Gibraltar (+ 350)',
        'GR' => 'Greece (+ 30)',
        'GL' => 'Greenland (+ 299)',
        'GD' => 'Grenada (+ 1473)',
        'GP' => 'Guadeloupe (+ 590)',
        'GU' => 'Guam (+ 671)',
        'GT' => 'Guatemala (+ 502)',
        'GN' => 'Guinea (+ 224)',
        'GW' => 'Guinea Bissau (+ 245)',
        'GY' => 'Guyana (+ 592)',
        'HT' => 'Haiti (+ 509)',
        'HN' => 'Honduras (+ 504)',
        'HK' => 'Hong Kong (+ 852)',
        'HU' => 'Hungary (+ 36)',
        'IS' => 'Iceland (+ 354)',
        'IN' => 'India (+ 91)',
        'ID' => 'Indonesia (+ 62)',
        'IR' => 'Iran (+ 98)',
        'IQ' => 'Iraq (+ 964)',
        'IE' => 'Ireland (+ 353)',
        'IL' => 'Israel (+ 972)',
        'IT' => 'Italy (+ 39)',
        'CI' => 'Ivory Coast (+ 225)',
        'JM' => 'Jamaica (+ 1876)',
        'JP' => 'Japan (+ 81)',
        'JO' => 'Jordan (+ 962)',
        'KZ' => 'Kazakhstan (+ 7)',
        'KE' => 'Kenya (+ 254)',
        'KI' => 'Kiribati (+ 686)',
        'KR' => 'Korea (+ 82)',
        'KW' => 'Kuwait (+ 965)',
        'KG' => 'Kyrgyzstan (+ 996)',
        'LA' => 'Laos (+ 856)',
        'LV' => 'Latvia (+ 371)',
        'LB' => 'Lebanon (+ 961)',
        'LS' => 'Lesotho (+ 266)',
        'LR' => 'Liberia (+ 231)',
        'LI' => 'Liechtenstein (+ 423)',
        'LT' => 'Lithuania (+ 370)',
        'LU' => 'Luxembourg (+ 352)',
        'LY' => 'Lybyan Arab Jamahiri (+ 218)',
        'MO' => 'Macau (+ 853)',
        'MK' => 'Macedonia (+ 389)',
        'MG' => 'Madagascar (+ 261)',
        'MW' => 'Malawi (+ 265)',
        'MY' => 'Malaysia (+ 60)',
        'MV' => 'Maldives (+ 960)',
        'ML' => 'Mali (+ 223)',
        'MT' => 'Malta (+ 356)',
        'MH' => 'Marshall Islands (+ 692)',
        'MQ' => 'Martinique (+ 596)',
        'MR' => 'Mauritania (+ 222)',
        'MU' => 'Mauritius (+ 230)',
        'YT' => 'Mayotte (+ 269)',
        'MX' => 'Mexico (+ 52)',
        'FM' => 'Micronesia (+ 691)',
        'UM' => 'Minor Outlying Isl. (+ 1)',
        'MD' => 'Moldova (+ 373)',
        'MC' => 'Monaco (+ 377)',
        'MN' => 'Mongolia (+ 976)',
        'ME' => 'Montenegro (+ 382)',
        'MS' => 'Montserrat (+ 1664)',
        'MA' => 'Morocco (+ 212)',
        'MZ' => 'Mozambique (+ 258)',
        'MM' => 'Myanmar (+ 95)',
        'NA' => 'Namibia (+ 264)',
        'NR' => 'Nauru (+ 674)',
        'NP' => 'Nepal (+ 977)',
        'AN' => 'Neth Antilles (+ 599)',
        'NL' => 'Netherlands (+ 31)',
        'NC' => 'New Caledonia (+ 687)',
        'NZ' => 'New Zealand (+ 64)',
        'NI' => 'Nicaragua (+ 505)',
        'NE' => 'Niger (+ 227)',
        'NG' => 'Nigeria (+ 234)',
        'NU' => 'Niue (+ 683)',
        'NF' => 'Norfolk Island (+ 6723)',
        'KP' => 'North Korea (+ 850)',
        'NO' => 'Norway (+ 47)',
        'MP' => 'Nothern Mariana Islands (+ 1)',
        'OM' => 'Oman (+ 968)',
        'PK' => 'Pakistan (+ 92)',
        'PW' => 'Palau Islands (+ 680)',
        'PS' => 'Palestina (+ 970)',
        'PA' => 'Panama (+ 507)',
        'PG' => 'Papua New Guinea (+ 675)',
        'PY' => 'Paraguay (+ 595)',
        'PE' => 'Peru (+ 51)',
        'PH' => 'Philippines (+ 63)',
        'PL' => 'Poland (+ 48)',
        'PT' => 'Portugal (+ 351)',
        'PR' => 'Puerto Rico (+ 1787)',
        'QA' => 'Qatar (+ 974)',
        'RE' => 'Reunion (+ 262)',
        'RO' => 'Romania (+ 40)',
        'RU' => 'Russia (+ 7)',
        'RW' => 'Rwanda (+ 250)',
        'SH' => 'Saint Helena Islands (+ 290)',
        'LC' => 'Saint Lucia (+ 1)',
        'SX' => 'Saint Maarten (+ 599)',
        'MF' => 'Saint Martin (+ 590)',
        'WS' => 'Samoa (+ 685)',
        'SM' => 'San Marino (+ 378)',
        'ST' => 'Sao Tome And Princip (+ 239)',
        'SA' => 'Saudi Arabia (+ 966)',
        'SN' => 'Senegal (+ 221)',
        'RS' => 'Serbia (+ 381)',
        'SC' => 'Seychelles (+ 248)',
        'SL' => 'Sierra Leone (+ 232)',
        'SG' => 'Singapore (+ 65)',
        'SK' => 'Slovakia (+ 421)',
        'SI' => 'Slovenia (+ 386)',
        'SB' => 'Solomon Islands (+ 677)',
        'SO' => 'Somalia (+ 252)',
        'ZA' => 'South Africa (+ 27)',
        'ES' => 'Spain (+ 34)',
        'LK' => 'Sri Lanka (+ 94)',
        'SD' => 'Sudan (+ 249)',
        'SR' => 'Suriname (+ 597)',
        'SZ' => 'Swaziland (+ 268)',
        'SE' => 'Sweden (+ 46)',
        'CH' => 'Switzerland (+ 41)',
        'SY' => 'Syrian Arab Rep (+ 963)',
        'TW' => 'Taiwan (+ 886)',
        'TJ' => 'Tajikistan (+ 992)',
        'TZ' => 'Tanzania (+ 255)',
        'TH' => 'Thailand (+ 66)',
        'TL' => 'Timor Leste (+ 670)',
        'TG' => 'Togo (+ 228)',
        'TK' => 'Tokelau (+ 690)',
        'TO' => 'Tonga (+ 676)',
        'TT' => 'Trinidad And Tobago (+ 1868)',
        'TN' => 'Tunisia (+ 216)',
        'TR' => 'Turkey (+ 90)',
        'TM' => 'Turkmenistan (+ 993)',
        'TC' => 'Turks And Caicos Islands (+ 1649)',
        'TV' => 'Tuvalu (+ 688)',
        'UG' => 'Uganda (+ 256)',
        'UA' => 'Ukraine (+ 380)',
        'AE' => 'United Arab Emirates (+ 971)',
        'GB' => 'United Kingdom (+ 44)',
        'US' => 'United States of America (+ 1)',
        'UY' => 'Uruguay (+ 598)',
        'UZ' => 'Uzbekistan (+ 998)',
        'VU' => 'Vanuatu (+ 678)',
        'VA' => 'Vatican (+ 379)',
        'VE' => 'Venezuela (+ 58)',
        'VN' => 'Vietnam (+ 84)',
        'WF' => 'Wallis And Futuna (+ 681)',
        'YE' => 'Yemen (+ 967)',
        'ZM' => 'Zambia (+ 260)',
        'ZW' => 'Zimbabwe (+ 263)',
        'AC' => 'ac (+ 54)',
        'BQ' => 'bq (+ 599)',
        'SV' => 'el Salvador (+ 503)',
        'KN' => 'st Kitts Nevis (+ 1)',
        'PM' => 'st Pierre And Miquel (+ 508)',
        'VC' => 'st Vincent Grenadines Mustique (+ 1784)',
        'VI' => 'us Virgin Islands (+ 1)',
        'XU' => 'xu (+ 7)',
    ];

    protected $fields;

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;

        $this->fields = $fields;
        $this->http->log('[INFO] initial fields:');
        $this->http->log(json_encode($this->fields, JSON_PRETTY_PRINT));

        $this->checkFields();
        $this->modifyFields();

        $this->http->GetURL(self::REGISTER_URL);
        $status = $this->http->ParseForm('globalForm');

        if (!$status) {
            throw new \EngineError('Failed to parse registration form');
        }

        $this->populateForm();
        $this->modifyForm();
        $status = $this->http->PostForm();

        if (!$status) {
            throw new \EngineError('Failed to post register form');
        }

        $status = $this->checkStatus();

        return $status;
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
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Last name, >= 2 letters',
                    'Required' => true,
                ],
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'First name, >= 2 letters',
                    'Required' => true,
                ],

            'PhoneCountryCodeAlphabetic' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone country code (alphabetic)',
                    'Required' => true,
                    'Options'  => self::$phoneCountryCodesAlphabetic,
                ],
            'PhoneAreaCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone area code',
                    'Required' => true,
                ],
            'PhoneLocalNumber' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Phone local number',
                    'Required' => true,
                ],

            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'E-mail address',
                    'Required' => true,
                ],
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password, 4 digit pin',
                    'Required' => true,
                ],

            'SecurityQuestionType1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Security Question Type',
                    'Required' => true,
                    'Options'  => self::$securityQuestionTypes,
                ],
            'SecurityQuestionAnswer1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Answer',
                    'Required' => true,
                ],
            'PromoCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'If you do not have a promo code, please leave this field blank.',
                    'Required' => false,
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

            'Country' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Country, 2 letter code',
                    'Required' => true,
                    'Options'  => self::$countries,
                ],
            'StateOrProvince' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'State',
                    'Required' => false,
                ],
            'PreferredLanguage' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Language preference',
                    'Required' => true,
                    'Options'  => self::$preferredLanguages,
                ],
        ];
    }

    protected function getPhone()
    {
        return sprintf('%s%s', $this->fields['PhoneAreaCode'], $this->fields['PhoneLocalNumber']);
    }

    protected function checkFields()
    {
        if (strlen($this->fields['Password']) !== 4) {
            throw new \UserInputError('Password has to be 4-digit pin');
        }
    }

    protected function modifyFields()
    {
        $this->fields['Password2'] = $this->fields['Password'];
        $this->fields['Email2'] = $this->fields['Email'];

        $this->fields['BirthDay'] = sprintf('%02d', $this->fields['BirthDay']);
        $this->fields['BirthMonth'] = sprintf('%02d', $this->fields['BirthMonth']);

        $this->fields['PhoneCountryCodeNumeric'] = sprintf('+ %s',
            $this->phoneAlphabeticToNumeric($this->fields['PhoneCountryCodeAlphabetic'])
        );
        $this->fields['Phone'] = $this->getPhone();

        $this->fields['SecurityQuestionType1'] =
            self::$securityQuestionTypes[$this->fields['SecurityQuestionType1']];
    }

    protected function modifyForm()
    {
        $data = [
            'isPrivateSales' => 'false',
            'webId'          => 'null',
            'birthDate'      => '',
            'acceptInfo'     => 'on',
            'acceptCond'     => 'on',
        ];

        foreach ($data as $key => $value) {
            $this->http->SetInputValue($key, $value);
        }
    }

    protected function getAccountNumber()
    {
        $this->AccountFields['Login'] = $this->fields['Email'];
        $this->AccountFields['Pass'] = $this->fields['Password'];

        try {
            $this->LoadLoginForm() && $this->Login() && $this->Parse();
        } catch (\CheckException $e) {
            $this->http->Log('[INFO] failed to login: ' . $e->getMessage());

            return false;
        }

        $this->http->log('[INFO] properties:');
        $this->http->log(print_r($this->Properties, true));

        return arrayVal($this->Properties, 'Number');
    }

    protected function checkStatus()
    {
        if ($this->http->FindPreg('/FBNumberEnroll/i')) {
            $acc = $this->getAccountNumber();

            if ($acc) {
                $msg = "Successfull registration, airfrance number: $acc";
            } else {
                $msg = 'Successfull registration, but failed to obtain airfrance number';
            }

            $this->http->Log('[INFO] ' . $msg);
            $this->ErrorMessage = $msg;

            return true;
        }

        if ($this->http->FindPreg('/unauthorizedCustomer|An error occured/i')) {
            throw new \EngineError('Some field is invalid');
        }

        if ($this->http->FindPreg('/emailAlreadyUsedByFBMembers/i')) {
            throw new \UserInputError('Email already used');
        }

        if ($this->http->FindPreg('/alreadyFBMember/i')) {
            throw new \UserInputError('Already member');
        }

        if ($this->http->FindPreg('/invalidPhoneNumber/i')) {
            throw new \UserInputError('Invalid phone number');
        }

        if ($this->http->FindPreg('/technicalError/i')) {
            throw new \ProviderError('Technical error, try again later');
        }

        return false;
    }

    protected function phoneAlphabeticToNumeric($alphabetic)
    {
        $info = self::$phoneCountryCodesAlphabetic[$alphabetic];

        if (preg_match('/[(][+]\s*(\d+)\s*[)]/', $info, $m)) {
            return $m[1];
        }

        throw new \EngineError('Cannot convert country alphabetic code to numeric');
    }

    private function populateForm()
    {
        foreach (self::$fieldMap as $awkey => $key) {
            if (!arrayVal($this->fields, $awkey)) {
                continue;
            }
            $this->http->SetInputValue($key, $this->fields[$awkey]);
        }
    }
}
