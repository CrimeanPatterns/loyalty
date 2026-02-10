<?php

namespace AwardWallet\Engine\delta\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use ProxyList;

    /* @var \HttpBrowser $http */
    public $http;

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $addressTypes = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $securityQuestionTypes = [
        1  => 'What is the name of the first school you attended?',
        2  => 'What is the name of your first pet?',
        4  => 'What is your father\'s middle name?',
        5  => 'What is your paternal grandmother\'s given name?',
        6  => 'What is the phone number you remember most from your childhood?',
        7  => 'What was your favorite place to visit as a child?',
        8  => 'What is the name of your childhood best friend?',
        9  => 'Where did you meet your spouse/partner?',
        10 => 'What city is your ultimate dream destination?',
        11 => 'What is one travel item you can\'t do without?',
        12 => 'What is the most distant city you\'ve ever visited?',
        13 => 'What is your favorite summer vacation activity?',
        14 => 'What city is your favorite winter vacation spot?',
        15 => 'Where is the first place you ever flew?',
        16 => 'What year did you take your first flight?',
        17 => 'What is the strangest food you\'ve ever eaten while traveling?',
        18 => 'What is your favorite foreign city?',
        19 => 'What is the coolest landmark you\'ve ever visited?',
    ];

    public static $notificationPreferences = [
        'E' => 'Email',
        'P' => 'Phone',
    ];

    public static $inputFieldsMap = [
        'FirstName'                  => ['customerDo.name.firstName', 'basicInfoFirstName'],
        'LastName'                   => ['customerDo.name.lastName', 'basicInfoLastName'],
        'Gender'                     => ['customerDo.gender', 'basicInfoGender1'],
        'BirthMonth'                 => ['dobMonth', 'month'],
        'BirthDay'                   => ['dobDay', 'day'],
        'BirthYear'                  => ['dobYear', 'year'],
        'Country'                    => 'customerDo.addresses[0].addressLine8',
        'AddressType'                => 'customerDo.addresses[0].type',
        'AddressLine1'               => 'customerDo.addresses[0].addressLine1',
        'City'                       => 'customerDo.addresses[0].addressLine4',
        'StateOrProvince'            => 'customerDo.addresses[0].addressLine7',
        'PostalCode'                 => 'customerDo.addresses[0].addressLine9',
        'PhoneCountryCodeAlphabetic' => 'customerDo.phones[0].countryCd',
        //		'PhoneCountryCodeNumeric' => '',
        'PhoneAreaCode'           => 'phoneAreaCode',
        'PhoneLocalNumber'        => 'phoneNumber',
        'Email'                   => ['customerDo.emails[0].emailAddress', 'basicInfoEmailAddress', 'requiredEmail2'],
        'Username'                => ['customerDo.username', 'basicInfoUserName'],
        'Password'                => ['password', 'basicInfoPassword', 'requiredEqualTo'],
        'SecurityQuestionType1'   => ['customerDo.securityQuestionsAndAnswers[0].questionId', 'basicInfoQuestionId11'],
        'SecurityQuestionAnswer1' => ['customerDo.securityQuestionsAndAnswers[0].answer', 'basicInfoAnswer1'],
        'SecurityQuestionType2'   => ['customerDo.securityQuestionsAndAnswers[1].questionId', 'basicInfoQuestionId22'],
        'SecurityQuestionAnswer2' => ['customerDo.securityQuestionsAndAnswers[1].answer', 'basicInfoAnswer2'],
        'NotificationPreferences' => ['phoneEmailDropdown1', 'phoneEmailDropdown2'],
    ];

    public static $phoneCountries = [
        "1_US"   => "United States 1",
        "93_AF"  => "Afghanistan 93",
        "355_AL" => "Albania 355",
        "213_DZ" => "Algeria 213",
        "376_AD" => "Andorra 376",
        "244_AO" => "Angola 244",
        "1_AI"   => "Anguilla 1",
        "1_AG"   => "Antigua 1",
        "54_AR"  => "Argentina 54",
        "374_AM" => "Armenia 374",
        "297_AW" => "Aruba 297",
        "61_AU"  => "Australia 61",
        "43_AT"  => "Austria 43",
        "994_AZ" => "Azerbaijan 994",
        "1_BS"   => "Bahamas 1",
        "973_BH" => "Bahrain 973",
        "880_BD" => "Bangladesh 880",
        "1_BB"   => "Barbados 1",
        "375_BY" => "Belarus 375",
        "32_BE"  => "Belgium 32",
        "501_BZ" => "Belize 501",
        "229_BJ" => "Benin 229",
        "1_BM"   => "Bermuda 1",
        "975_BT" => "Bhutan 975",
        "591_BO" => "Bolivia 591",
        "387_BA" => "Bosnia and Herzegovina 387",
        "267_BW" => "Botswana 267",
        "55_BR"  => "Brazil 55",
        "1_VG"   => "British Virgin Islands 1",
        "673_BN" => "Brunei 673",
        "359_BG" => "Bulgaria 359",
        "226_BF" => "Burkina Faso 226",
        "257_BI" => "Burundi 257",
        "855_KH" => "Cambodia 855",
        "237_CM" => "Cameroon 237",
        "1_CA"   => "Canada 1",
        "238_CV" => "Cape Verde 238",
        "1_KY"   => "Cayman Islands 1",
        "236_CF" => "Central African Republic 236",
        "235_TD" => "Chad 235",
        "56_CL"  => "Chile 56",
        "86_CN"  => "China 86",
        "61_CC"  => "Cocos (Keeling) Islands 61",
        "57_CO"  => "Colombia 57",
        "269_KM" => "Comoros 269",
        "242_CG" => "Congo 242",
        "682_CK" => "Cook Islands 682",
        "506_CR" => "Costa Rica 506",
        "385_HR" => "Croatia 385",
        "357_CY" => "Cyprus 357",
        "420_CZ" => "Czech Republic 420",
        "243_CD" => "Dem Rep of the Congo 243",
        "45_DK"  => "Denmark 45",
        "253_DJ" => "Djibouti 253",
        "1_DM"   => "Dominica 1",
        "1_DO"   => "Dominican Republic 1",
        "593_EC" => "Ecuador 593",
        "20_EG"  => "Egypt 20",
        "503_SV" => "El Salvador 503",
        "240_GQ" => "Equatorial Guinea 240",
        "291_ER" => "Eritrea 291",
        "372_EE" => "Estonia 372",
        "251_ET" => "Ethiopia 251",
        "500_FK" => "Falkland Islands 500",
        "298_FO" => "Faroe Islands 298",
        "679_FJ" => "Fiji 679",
        "358_FI" => "Finland 358",
        "33_FR"  => "France 33",
        "594_GF" => "French Guiana 594",
        "689_PF" => "French Polynesia 689",
        "241_GA" => "Gabon 241",
        "220_GM" => "Gambia 220",
        "995_GE" => "Georgia 995",
        "49_DE"  => "Germany 49",
        "233_GH" => "Ghana 233",
        "350_GI" => "Gibraltar 350",
        "30_GR"  => "Greece 30",
        "299_GL" => "Greenland 299",
        "1_GD"   => "Grenada 1",
        "590_GP" => "Guadeloupe 590",
        "502_GT" => "Guatemala 502",
        "224_GN" => "Guinea 224",
        "245_GW" => "Guinea-Bissau 245",
        "592_GY" => "Guyana 592",
        "509_HT" => "Haiti 509",
        "39_VA"  => "Holy See (Vatican City) 39",
        "504_HN" => "Honduras 504",
        "852_HK" => "Hong Kong 852",
        "36_HU"  => "Hungary 36",
        "354_IS" => "Iceland 354",
        "91_IN"  => "India 91",
        "62_ID"  => "Indonesia 62",
        "964_IQ" => "Iraq 964",
        "353_IE" => "Ireland 353",
        "972_IL" => "Israel 972",
        "39_IT"  => "Italy 39",
        "225_CI" => "Ivory Coast 225",
        "1_JM"   => "Jamaica 1",
        "81_JP"  => "Japan 81",
        "962_JO" => "Jordan 962",
        "7_KZ"   => "Kazakhstan 7",
        "254_KE" => "Kenya 254",
        "686_KI" => "Kiribati 686",
        "965_KW" => "Kuwait 965",
        "996_KG" => "Kyrgyzstan 996",
        "856_LA" => "Laos 856",
        "371_LV" => "Latvia 371",
        "961_LB" => "Lebanon 961",
        "266_LS" => "Lesotho 266",
        "231_LR" => "Liberia 231",
        "218_LY" => "Libya 218",
        "423_LI" => "Liechtenstein 423",
        "370_LT" => "Lithuania 370",
        "352_LU" => "Luxembourg 352",
        "853_MO" => "Macau 853",
        "389_MK" => "Macedonia 389",
        "261_MG" => "Madagascar 261",
        "265_MW" => "Malawi 265",
        "60_MY"  => "Malaysia 60",
        "960_MV" => "Maldives 960",
        "223_ML" => "Mali 223",
        "356_MT" => "Malta 356",
        "596_MQ" => "Martinique 596",
        "222_MR" => "Mauritania 222",
        "230_MU" => "Mauritius 230",
        "262_YT" => "Mayotte 262",
        "52_MX"  => "Mexico 52",
        "373_MD" => "Moldova 373",
        "377_MC" => "Monaco 377",
        "976_MN" => "Mongolia 976",
        "382_ME" => "Montenegro 382",
        "1_MS"   => "Montserrat 1",
        "212_MA" => "Morocco 212",
        "258_MZ" => "Mozambique 258",
        "95_MM"  => "Myanmar 95",
        "264_NA" => "Namibia 264",
        "674_NR" => "Nauru 674",
        "977_NP" => "Nepal 977",
        "31_NL"  => "Netherlands 31",
        "1_AN"   => "Netherlands Antilles 1",
        "687_NC" => "New Caledonia 687",
        "64_NZ"  => "New Zealand 64",
        "505_NI" => "Nicaragua 505",
        "227_NE" => "Niger 227",
        "234_NG" => "Nigeria 234",
        "683_NU" => "Niue 683",
        "672_NF" => "Norfolk Island 672",
        "850_KP" => "North Korea 850",
        "47_NO"  => "Norway 47",
        "968_OM" => "Oman 968",
        "92_PK"  => "Pakistan 92",
        "507_PA" => "Panama 507",
        "675_PG" => "Papua New Guinea 675",
        "595_PY" => "Paraguay 595",
        "51_PE"  => "Peru 51",
        "63_PH"  => "Philippines 63",
        "48_PL"  => "Poland 48",
        "351_PT" => "Portugal 351",
        "974_QA" => "Qatar 974",
        "262_RE" => "Reunion Islands 262",
        "40_RO"  => "Romania 40",
        "7_RU"   => "Russia 7",
        "250_RW" => "Rwanda 250",
        "1_KN"   => "Saint Kitts and Nevis 1",
        "1_LC"   => "Saint Lucia 1",
        "1_VC"   => "Saint Vincent and the Grenadines 1",
        "685_WS" => "Samoa 685",
        "378_SM" => "San Marino 378",
        "239_ST" => "Sao Tome and Principe 239",
        "966_SA" => "Saudi Arabia 966",
        "221_SN" => "Senegal 221",
        "381_RS" => "Serbia 381",
        "248_SC" => "Seychelles 248",
        "232_SL" => "Sierra Leone 232",
        "65_SG"  => "Singapore 65",
        "421_SK" => "Slovakia 421",
        "386_SI" => "Slovenia 386",
        "677_SB" => "Soloman Islands 677",
        "252_SO" => "Somalia 252",
        "27_ZA"  => "South Africa 27",
        "82_KR"  => "South Korea 82",
        "34_ES"  => "Spain 34",
        "94_LK"  => "Sri Lanka 94",
        "290_SH" => "St. Helena 290",
        "508_PM" => "St. Pierre and Miquelon 508",
        "597_SR" => "Suriname 597",
        "268_SZ" => "Swaziland 268",
        "46_SE"  => "Sweden 46",
        "41_CH"  => "Switzerland 41",
        "886_TW" => "Taiwan 886",
        "992_TJ" => "Tajikistan 992",
        "255_TZ" => "Tanzania 255",
        "66_TH"  => "Thailand 66",
        "670_TL" => "Timor-Leste 670",
        "228_TG" => "Togo 228",
        "690_TK" => "Tokelau 690",
        "676_TO" => "Tonga 676",
        "1_TT"   => "Trinidad and Tobago 1",
        "216_TN" => "Tunisia 216",
        "90_TR"  => "Turkey 90",
        "993_TM" => "Turkmenistan 993",
        "1_TC"   => "Turks and Caicos Islands 1",
        "688_TV" => "Tuvalu 688",
        "256_UG" => "Uganda 256",
        "380_UA" => "Ukraine 380",
        "971_AE" => "United Arab Emirates 971",
        "44_GB"  => "United Kingdom 44",
        "598_UY" => "Uruguay 598",
        "998_UZ" => "Uzbekistan 998",
        "678_VU" => "Vanuatu 678",
        "58_VE"  => "Venezuela 58",
        "84_VN"  => "Vietnam 84",
        "681_WF" => "Wallis and Futuna 681",
        "212_EH" => "Western Sahara 212",
        "967_YE" => "Yemen 967",
        "260_ZM" => "Zambia 260",
        "263_ZW" => "Zimbabwe 263",
    ];
    public static $countries = [
        'US' => 'United States',
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
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
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
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
        'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'CD' => 'Dem Rep of the Congo',
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
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'VA' => 'Holy See (Vatican City)',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
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
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
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
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'QA' => 'Qatar',
        'RE' => 'Reunion Islands',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
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
        'SB' => 'Soloman Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'KR' => 'South Korea',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SH' => 'St. Helena',
        'PM' => 'St. Pierre and Miquelon',
        'SR' => 'Suriname',
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
        'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public static $statesByCountry = [
        'US' =>
            [
                'AL' => 'ALABAMA',
                'AK' => 'ALASKA',
                'AZ' => 'ARIZONA',
                'AR' => 'ARKANSAS',
                'AA' => 'ARMED FORCES AMERICAS (NOT CA)',
                'AP' => 'ARMED FORCES PACIFIC',
                'AE' => 'ARMED FORCES US,CA,EURO,AFRICA',
                'AS' => 'American Samoa',
                'CA' => 'CALIFORNIA',
                'CO' => 'COLORADO',
                'CT' => 'CONNECTICUT',
                'DE' => 'DELAWARE',
                'DC' => 'DISTRICT OF COLUMBIA',
                'FL' => 'FLORIDA',
                'GA' => 'GEORGIA',
                'GU' => 'Guam',
                'HI' => 'HAWAII',
                'ID' => 'IDAHO',
                'IL' => 'ILLINOIS',
                'IN' => 'INDIANA',
                'IA' => 'IOWA',
                'KS' => 'KANSAS',
                'KY' => 'KENTUCKY',
                'LA' => 'LOUISIANA',
                'ME' => 'MAINE',
                'MD' => 'MARYLAND',
                'MA' => 'MASSACHUSETTS',
                'MI' => 'MICHIGAN',
                'MN' => 'MINNESOTA',
                'MS' => 'MISSISSIPPI',
                'MO' => 'MISSOURI',
                'MT' => 'MONTANA',
                'MH' => 'Marshall Islands',
                'FM' => 'Micronesia, Federated States o',
                'NE' => 'NEBRASKA',
                'NV' => 'NEVADA',
                'NH' => 'NEW HAMPSHIRE',
                'NJ' => 'NEW JERSEY',
                'NM' => 'NEW MEXICO',
                'NY' => 'NEW YORK',
                'NC' => 'NORTH CAROLINA',
                'ND' => 'NORTH DAKOTA',
                'MP' => 'NORTHERN MARIANA ISLANDS',
                'OH' => 'OHIO',
                'OK' => 'OKLAHOMA',
                'OR' => 'OREGON',
                'PA' => 'PENNSYLVANIA',
                'PR' => 'PUERTO RICO',
                'PW' => 'Palau',
                'RI' => 'RHODE ISLAND',
                'SC' => 'SOUTH CAROLINA',
                'SD' => 'SOUTH DAKOTA',
                'TN' => 'TENNESSEE',
                'TX' => 'TEXAS',
                'UT' => 'UTAH',
                'VT' => 'VERMONT',
                'VI' => 'VIRGIN ISLANDS',
                'VA' => 'VIRGINIA',
                'WA' => 'WASHINGTON',
                'WV' => 'WEST VIRGINIA',
                'WI' => 'WISCONSIN',
                'WY' => 'WYOMING',
            ],
        'AG' =>
            [
                'BB' => 'Barbuda',
                'RD' => 'Redonda',
                'GE' => 'Saint George',
                'JO' => 'Saint John',
                'MA' => 'Saint Mary',
                'PA' => 'Saint Paul',
                'PE' => 'Saint Peter',
                'PH' => 'Saint Philip',
            ],
        'AR' =>
            [
                'BA' => 'Buenos Aires',
                'CT' => 'Catamarca',
                'CC' => 'Chaco',
                'CH' => 'Chubut',
                'DF' => 'Ciudad de Buenos Aires',
                'CB' => 'Cordoba',
                'CN' => 'Corrientes',
                'ER' => 'Entre Rios',
                'FM' => 'Formosa',
                'JY' => 'Jujuy',
                'LP' => 'La Pampa',
                'LR' => 'La Rioja',
                'MZ' => 'Mendoza',
                'MN' => 'Misiones',
                'NQ' => 'Neuquen',
                'RN' => 'Rio Negro',
                'SA' => 'Salta',
                'SJ' => 'San Juan',
                'SL' => 'San Luis',
                'SC' => 'Santa Cruz',
                'SF' => 'Santa Fe',
                'SE' => 'Santiago del Estero',
                'TF' => 'Tierra del Fuego',
                'TM' => 'Tucuman',
            ],
        'AU' =>
            [
                'XAA' => 'Australian Antarctic Territory',
                'ACT' => 'Australian Capital Territory',
                'CXR' => 'Christms Island',
                'CCK' => 'Cocos (Keeling) Islands',
                'HMD' => 'Heard and McDonald Islands',
                'NSW' => 'New South Wales',
                'NFK' => 'Norfolk Island',
                'NT'  => 'Northern Territory',
                'QLD' => 'Queensland',
                'SA'  => 'South Australia',
                'TAS' => 'Tasmania',
                'VIC' => 'Victoria',
                'WA'  => 'Western Australia',
            ],
        'AT' =>
            [
                'BU' => 'Burgenland',
                'KA' => 'Carinthia',
                'NO' => 'Lower Austria',
                'SZ' => 'Salzburg',
                'ST' => 'Styria',
                'TR' => 'Tyrol',
                'OO' => 'Upper Austria',
                'WI' => 'Vienna',
                'VO' => 'Vorarlberg',
            ],
        'BH' =>
            [
                'CA' => 'Capital',
                'CE' => 'Central',
                'MU' => 'Muharraq',
                'NO' => 'Northern',
                'SO' => 'Southern',
            ],
        'BE' =>
            [
                'AN' => 'Antwerp',
                'BU' => 'Brussels',
                'OV' => 'East Flanders',
                'VB' => 'Flemish Brabant',
                'HT' => 'Hainaut',
                'LG' => 'Liege',
                'LI' => 'Limburg',
                'LX' => 'Luxembourg',
                'NA' => 'Namur',
                'BW' => 'Walloon Brabant',
                'WV' => 'West Flanders',
            ],
        'BM' =>
            [
                'DE' => 'Devonshire',
                'HA' => 'Hamilton',
                'HC' => 'Hamilton Municipality',
                'PA' => 'Paget',
                'PE' => 'Pembroke',
                'SG' => 'Saint George Municipality',
                'SC' => 'Saint George\'s',
                'SA' => 'Sandys',
                'SM' => 'Smiths',
                'SO' => 'Southampton',
                'WA' => 'Warwick',
            ],
        'BO' =>
            [
                'CQ' => 'Chuquisaca',
                'CB' => 'Cochabamba',
                'EB' => 'El Beni',
                'LP' => 'La Paz',
                'OR' => 'Oruro',
                'PA' => 'Pando',
                'PO' => 'Potosi',
                'SC' => 'Santa Cruz',
                'TR' => 'Tarija',
            ],
        'BR' =>
            [
                'AC' => 'Acre',
                'AL' => 'Alagoas',
                'AP' => 'Amapa',
                'AM' => 'Amazonas',
                'BA' => 'Bahia',
                'CE' => 'Ceara',
                'DF' => 'Distrito Federal',
                'ES' => 'Espirito Santo',
                'GO' => 'Goias',
                'MA' => 'Maranhao',
                'MT' => 'Mato Grosso',
                'MS' => 'Mato Grosso do Sul',
                'MG' => 'Minas Gerais',
                'PA' => 'Para',
                'PB' => 'Paraiba',
                'PR' => 'Parana',
                'PE' => 'Pernambuco',
                'PI' => 'Piaui',
                'RN' => 'Rio Grande do Norte',
                'RS' => 'Rio Grande do Sul',
                'RJ' => 'Rio de Janeiro',
                'RO' => 'Rondonia',
                'RR' => 'Roraima',
                'SC' => 'Santa Catarina',
                'SP' => 'Sao Paulo',
                'SE' => 'Sergipe',
                'TO' => 'Tocantins',
            ],
        'CA' =>
            [
                'AB' => 'ALBERTA',
                'BC' => 'BRITISH COLUMBIA',
                'MB' => 'MANITOBA',
                'NB' => 'NEW BRUNSWICK',
                'NL' => 'NEWFOUNDLAND/LABRADOR',
                'NT' => 'NORTHWEST TERRITORIES',
                'NS' => 'NOVA SCOTIA',
                'NU' => 'NUNAVUT',
                'ON' => 'ONTARIO',
                'PE' => 'PRINCE EDWARD ISLAND',
                'QC' => 'QUEBEC',
                'SK' => 'SASKATCHEWAN',
                'YT' => 'YUKON TERRITORIES',
            ],
        'CL' =>
            [
                'AI' => 'Aisen',
                'AN' => 'Antofagasta',
                'AR' => 'Araucania',
                'AP' => 'Arica and Parinacota',
                'AT' => 'Atacama',
                'BI' => 'Bio-Bio',
                'CO' => 'Coquimbo',
                'LI' => 'Libertador',
                'LG' => 'Los Lagos',
                'LR' => 'Los Rios',
                'MA' => 'Magallanes y Antartica Chilena',
                'ML' => 'Maule',
                'RM' => 'Region Metro de Santiago',
                'TP' => 'Tarapaca',
                'VS' => 'Valparaiso',
            ],
        'CN' =>
            [
                'AH' => 'Anhui',
                'BJ' => 'Beijing',
                'CQ' => 'Chongqing',
                'FJ' => 'Fujian',
                'GS' => 'Gansu',
                'GD' => 'Guangdong',
                'GX' => 'Guangxi Zhuang',
                'GZ' => 'Guizhou',
                'HA' => 'Hainan',
                'HB' => 'Hebei',
                'HL' => 'Heilongjiang',
                'HE' => 'Henam',
                'HU' => 'Hubei',
                'HN' => 'Hunan',
                'JS' => 'Jiangsu',
                'JX' => 'Jiangxi',
                'JL' => 'Jilin',
                'LN' => 'Liaoning',
                'NM' => 'Nei Mongol',
                'NX' => 'Ningxia Hui',
                'QH' => 'Qinghai',
                'SA' => 'Shaanxi',
                'SD' => 'Shandong',
                'SH' => 'Shanghai',
                'SX' => 'Shanxi',
                'SC' => 'Sichuan',
                'TJ' => 'Tianjin',
                'XJ' => 'Xinjiang Uygur',
                'XZ' => 'Xizang',
                'YN' => 'Yunnan',
                'ZJ' => 'Zhejiang',
            ],
        'CO' =>
            [
                'AM' => 'Amazonas',
                'AN' => 'Antioquia',
                'AR' => 'Arauca',
                'AT' => 'Atlantico',
                'BL' => 'Bolivar',
                'BY' => 'Boyaca',
                'CL' => 'Caldas',
                'CQ' => 'Caqueta',
                'CS' => 'Casanare',
                'CA' => 'Cauca',
                'CE' => 'Cesar',
                'CH' => 'Choco',
                'CO' => 'Cordoba',
                'CU' => 'Cundinamarca',
                'DC' => 'Distrito Capital',
                'GN' => 'Guainia',
                'GV' => 'Guaviare',
                'HU' => 'Huila',
                'LG' => 'La Guajira',
                'MA' => 'Magdalena',
                'ME' => 'Meta',
                'NA' => 'Narino',
                'NS' => 'Norte de Santander',
                'PU' => 'Putumayo',
                'QD' => 'Quindio',
                'RI' => 'Risaralda',
                'SA' => 'San Andres y Providencia',
                'ST' => 'Santander',
                'SU' => 'Sucre',
                'TO' => 'Tolima',
                'VC' => 'Valle del Cauca',
                'VP' => 'Vaupes',
                'VD' => 'Vichada',
            ],
    ];

    protected $retries = 5;
    protected $retriesCount = 1;

    public function InitBrowser()
    {
        $this->UseCurlBrowser();
        //		$this->http->saveScreenshots = true;

        $this->http->maxRequests = 500;
        $this->http->TimeLimit = 290;
    }

    public function registerAccount(array $fields)
    {
        try {
            return $this->registerTry($fields);
        } catch (\UserInputError $e) {
            if ($e->getMessage() == 'We are unable to process your request. Please try again later.' && $this->retriesCount < $this->retries) {
                $this->http = null;
                $this->InitBrowser();
                ++$this->retriesCount;

                return $this->registerAccount($fields);
            } else {
                throw $e;
            }
        }
    }

    public function registerTry(array $fields)
    {
        if (!in_array($fields['Country'], array_keys(self::$countries))) {
            throw new \UserInputError('Invalid country code');
        }

        $this->http->LogHeaders = true;

        $this->http->FilterHTML = false;
        $this->checkValues($fields);
        $this->http->Log('Try #' . $this->retriesCount);

        // This provider should be tested via proxy even locally
        $this->http->SetProxy($this->proxyReCaptcha());
        //$this->setProxyBrightData();

        $this->http->removeCookies();
        $this->http->GetURL('https://www.delta.com/profile/enrolllanding.action');
        sleep(5);

        if ($this->checkUsername($fields['Username'])) {
            throw new \UserInputError('A unique username is required for each account');
        }

        $status = $this->http->ParseForm('basicForm');

        if (!$status) {
            $this->http->Log('Failed to parse create account form');

            return false;
        }

        $fields['Gender'] = $fields['Gender'] === 'F' ? 'Female' : 'Male';
        $fields['NotificationPreferences'] = $fields['NotificationPreferences'] === 'E' ? 'email' : 'phone';

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey])) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $this->http->SetInputValue($provKey, $fields[$awKey]);
            }
        }

        $const = [
            'selectedSQ1Text'                        => self::getRegisterFields()['SecurityQuestionType1']['Options'][$fields['SecurityQuestionType1']],
            'selectedSQ2Text'                        => self::getRegisterFields()['SecurityQuestionType2']['Options'][$fields['SecurityQuestionType2']],
            'customerDo.preferences.preferredLangCd' => 'en',
            'next'                                   => 'COMPLETE',
            'beforeFlyEventCode'                     => 'AN',
            'lastMinuteEventCode'                    => 'LM',
            'newsOffersEventCode'                    => 'NS',
            'fltRemainderEventCode'                  => 'TP',
            '__checkbox_crimeaChkBox'                => 'true',
            '__checkbox_customerDo.isBusinessOwner'  => 'true',
            'crimeaChkBoxChecked'                    => 'true',
            'crimeaChkBox'                           => 'true', //this field is necessary in RU and UA, in other countries could be or not could be (not critical)
        ];

        foreach ($const as $key => $val) {
            $this->http->SetInputValue($key, $val);
        }

        unset($this->http->Form['amex_cc_promo']);
        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post create account form');

            return false;
        }
        sleep(15);
        $this->http->SaveResponse();

        $errMsg = $this->http->FindSingleNode("//div[@id='error'][normalize-space(.)!='']");

        if (!empty($errMsg)) {
            $errMsgN = $this->http->FindPreg('#The ZIP/postal code format you’ve entered is not valid based on your Primary Address Country \(i.e. invalid length and/or characters\).#i', false, $errMsg);

            if (!empty($errMsgN)) {
                $errMsg = $errMsgN;
            }

            throw new \UserInputError($errMsg);
        }

        $urlError = $this->http->FindSingleNode("//input[@id='backendErrCode']/@value");

        if (!empty($urlError)) {
            $this->http->NormalizeURL($urlError);
            $this->http->GetURL($urlError);
            $err = $this->http->FindPreg("#^(?:<strong>)?(.+?)\s*(?:To\s+complete.+)?(?:<\/strong>)?$#si");

            if (empty($err)) {
                throw new \EngineError($this->http->Response['body']);
            }

            throw new \UserInputError($err);
        }
        $errMsg = $this->http->FindSingleNode("//div[@class='toolTipErrorMessageContainer' and normalize-space(.)!='']");

        if (!empty($errMsg)) {
            throw new \UserInputError($errMsg);
        }

        //sometimes if above not worked
        /*		if ($this->http->XPath->query("//div[@class='error']/ancestor::div[@class='dispNone']")->length === 0) {
                    $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Basic Info')]/ancestor::div[contains(normalize-space(.),'English characters only')][1]");
                    $errMsg = $this->http->FindPreg('#English characters only\s*(.+)\s*Basic Info#s', false, $node);
                    $errMsgN = $this->http->FindPreg('#The ZIP/postal code format you’ve entered is not valid based on your Primary Address Country \(i.e. invalid length and/or characters\).#i', false, $errMsg);
                    if (!empty($errMsgN))
                        $errMsg = $errMsgN;
                    if (!empty($errMsg))
                        throw new \UserInputError($errMsg);
                    else
                        throw new \EngineError('There was error. But can\'t determinate it');
                }
        */

        $this->logger->notice($this->http->currentUrl());

        $errMsgs = $this->http->FindNodes("//*[contains(@class,'errIcon')]");

        if (count($errMsgs) > 0) {
            $errMsg = "Check the items: " . implode(',', $errMsgs);

            throw new \UserInputError($errMsg);
        }

        $success = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Congratulations')]/ancestor::div[1]");

        if (!empty($success)) {
            $this->ErrorMessage = $success;
            $this->http->Log($this->ErrorMessage);

            return true;
        } elseif (!empty($success = $this->http->FindSingleNode("//title[starts-with(normalize-space(.),'Login after successful Delta account creation')]", null, true, "#Login after (successful Delta account creation)#"))) {
            $this->ErrorMessage = $success;
            $this->http->Log($this->ErrorMessage);

            return true;
        } else {
            $success = $this->http->FindSingleNode("//div[@id='welcomeText']");

            if (!empty($success)) {
                $this->ErrorMessage = $success;
                $this->http->Log($this->ErrorMessage);

                return true;
            }
        }

        $status = $this->http->ParseForm('toLoginAppln');

        if (!$status) {
            $this->http->Log('Failed to parse redirect login form');

            return false;
        }

        if (isset($this->http->Form['username'])) {
            $this->ErrorMessage = 'CardNumber: ' . $this->http->Form['username'];
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
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
            'Gender' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Gender',
                    'Required' => true,
                    'Options'  => self::$genders,
                ],
            'BirthMonth' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Month of Birth Date',
                    'Required' => true,
                ],
            'BirthDay' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Day of Birth Date',
                    'Required' => true,
                ],
            'BirthYear' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Year of Birth Date',
                    'Required' => true,
                ],
            'Country' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Country code',
                    'Required' => true,
                    'Options'  => self::$countries,
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
            'StateOrProvince' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'State or Province (required for US, AG, AR, AU, AT, BH, BE, BM, BO, BR, CA, CL, CN, CO)',
                    'Required' => false,
                    //					'Options' => self::getStatesMap(),
                ],
            'PostalCode' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Postal Code',
                    'Required' => true,
                ],
            'Company' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Company, required if Address Type is Business',
                    'Required' => false,
                ],
            'PhoneCountryCodeAlphabetic' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Phone Country Code',
                    'Required' => true,
                    'Options'  => self::$phoneCountries,
                ],
            //			'PhoneCountryCodeNumeric' =>
            //				array (
            //					'Type' => 'string',
            //					'Caption' => '1-3-number Phone Country Code',
            //					'Required' => true,
            //				),
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
            'Username' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Username (username cannot contain special characters and cannot be your email	address or contain only numbers, must be unique and at least 6	characters long)',
                    'Required' => true,
                ],
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password (must be 8-20 characters long, not the same as your SkyMiles number, email address or username and contain no special characters or non-English characters)',
                    'Required' => true,
                ],
            'SecurityQuestionType1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Security Question 1',
                    'Required' => true,
                    'Options'  => self::$securityQuestionTypes,
                ],
            'SecurityQuestionAnswer1' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Security Answer 1',
                    'Required' => true,
                ],
            'SecurityQuestionType2' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Security Question 2',
                    'Required' => true,
                    'Options'  => self::$securityQuestionTypes,
                ],
            'SecurityQuestionAnswer2' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Security Answer 2',
                    'Required' => true,
                ],
            'NotificationPreferences' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Notification Preferences ',
                    'Required' => true,
                    'Options'  => self::$notificationPreferences,
                ],
        ];
    }

    public static function states()
    {
        $statesPlain = [];

        foreach (self::$statesByCountry as $s) {
            $statesPlain = array_merge($statesPlain, $s);
        }

        return $statesPlain;
    }

    public static function getStatesMap()
    {
        $arr = [];

        foreach (self::$statesByCountry as $country => $states) {
            if (isset(self::$countries[$country])) {
                foreach ($states as $code => $state) {
                    $arr[$code] = '(' . self::$countries[$country] . ') ' . $state;
                }
            }
        }

        return $arr;
    }

    protected function checkValues($fields)
    {
        if (strlen($fields['Password']) < 8 || strlen($fields['Password']) > 20 || !preg_match("#\d#", $fields['Password']) || !preg_match("#[A-Z]#", $fields['Password']) || !preg_match("#[a-z]#", $fields['Password'])
            || preg_match("#[\@\<]#", $fields['Password']) || preg_match("#" . strstr($fields['Email'], '@', true) . "#", $fields['Password']) || preg_match("#" . $fields['Username'] . "#", $fields['Password'])
        ) {
            throw new \UserInputError('Password Must contains:  8-20 characters, 1 number, 1 uppercase letter, 1 lowercase letter and Cannot contain: "@" or "<" symbols, your email or username');
        } /*review*/

        if ($fields['SecurityQuestionType1'] == $fields['SecurityQuestionType2']) {
            throw new \UserInputError('SecurityQuestionType1 and SecurityQuestionType2 can not be the same');
        } /*review*/

        if (iconv_strlen($fields["SecurityQuestionAnswer1"]) < 4 || iconv_strlen($fields["SecurityQuestionAnswer2"]) < 4) {
            throw new \UserInputError('Answer to security question should contain at least 4 characters');
        } /*review*/

        if ($fields['AddressType'] === 'B' and !isset($fields['Company'])) {
            throw new \UserInputError('Company is required field then select Business Address Type');
        } /*review*/
    }

    protected function checkUsername($username)
    {
        $this->logger->notice(__METHOD__);

        $val = $this->http->FindSingleNode("//input[@id='postValidationId']/@value");

        if (!$val) {
            throw new \EngineError('Failed to find postValidationId for post checkUserNameExist.action');
        }

        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);

        $headers = [
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];

        $data = [];
        $data[] = urlencode('userName') . "=" . urlencode($username);
        $data[] = urlencode('postValidationId') . "=" . urlencode($val);
        $status = $http2->PostURL("https://www.delta.com/profile/profile_checkUserNameExist.action", implode("&", $data), $headers);

        if (!$status) {
            throw new \EngineError('Failed to post checkUserNameExist.action');
        }

        $response = $http2->JsonLog(null, true, true);

        if (!($response == true || $response == false)) {
            throw new \EngineError('Failed to check Response checkUserNameExist.action');
        }

        return (bool) $response;
    }
}
