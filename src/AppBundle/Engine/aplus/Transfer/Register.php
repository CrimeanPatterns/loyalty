<?php

// case #10186

namespace AwardWallet\Engine\aplus\Transfer;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;

    public static $inputFieldsMap = [
        'Email'     => 'user.email',
        'Password'  => 'user.password',
        'Title'     => 'user.civility',
        'LastName'  => 'user.lastName',
        'FirstName' => 'user.firstName',
        //		'LCEnrollment' => 'user.lcEnrollment',
        //		'AddressLine1' => 'user.address1',
        //		'AddressLine2' => 'user.address2',
        //		'PostalCode' => 'user.zipCode',
        //		'City' => 'user.city',
        'Country'         => 'user.country',
        'StateOrProvince' => 'user.state',
        //		'ReceiveNewsletter' => 'user.newsletters[0].codeSubscription'
    ];
    public static $titles = [
        'MR'  => 'Mr.',
        'MS'  => 'Ms.',
        'MRS' => 'Mrs',
        'DOC' => 'Dr.',
        'PRO' => 'Prof.',
    ];
    public static $countries = [
        'AF' => 'Afghanistan',
        'AX' => 'Aland Islands',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
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
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory',
        'VG' => 'British Virgin Islands',
        'BN' => 'Brunei',
        'BG' => 'Bulgaria',
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
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
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
        'FK' => 'Falkland Islands/Malvinas',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji Islands',
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
        'GG' => 'Guernsey',
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
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao people\'s democratic republic',
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
        'FM' => 'Micronesia, Federated States Of',
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
        'PS' => 'Palestinian Territory',
        'PA' => 'Panama',
        'PG' => 'Papua new guinea',
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
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthelemy',
        'SH' => 'Saint Helena',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'MF' => 'Saint Martin',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'KR' => 'South Korea',
        'ES' => 'Spain',
        'LK' => 'Sri lanka',
        'VC' => 'St Vincent and The Grenadines',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor Leste',
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
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'WF' => 'Wallis and Futuna Islands',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'BA' => 'Bosnia And Hercegovina',
        'BF' => 'Burkina-Faso',
        'CC' => 'Cocos - Keeling Islands',
        'CG' => 'Congo-Brazzaville',
        'CD' => 'Democratic Repub. of Congo',
        'HM' => 'Heard Island McDonald Islands',
        'CI' => 'Ivory  Coast',
        'MH' => 'Marshall Island Republic',
        'AS' => 'Samoa American',
        'WS' => 'Samoa Western',
        'SC' => 'Seychelles Islands',
        'GS' => 'South Georgia South Sandwich Isl',
        'PM' => 'St Pierre and Miquelon',
        'TC' => 'Turks and Caicos',
        'UM' => 'United States Minor Outlying Isl',
        'VI' => 'Virgin Islands US',
    ];
    public static $countriesMap = [
        'AF' => 'PA7',
        'AX' => 'PA12',
        'AL' => 'PAL',
        'DZ' => 'PDZ',
        'AD' => 'PAD',
        'AO' => 'PAO',
        'AI' => 'PA8',
        'AG' => 'PAG',
        'AR' => 'PAR',
        'AM' => 'PAM',
        'AW' => 'PA11',
        'AU' => 'PAU',
        'AT' => 'PAT',
        'AZ' => 'PAZ',
        'BS' => 'PA2',
        'BH' => 'PBH',
        'BD' => 'PBD',
        'BB' => 'PBB',
        'BY' => 'PBY',
        'BE' => 'PBE',
        'BZ' => 'PBZ',
        'BJ' => 'PBJ',
        'BM' => 'PA13',
        'BT' => 'PA14',
        'BO' => 'PBO',
        'BW' => 'PBW',
        'BV' => 'PA27',
        'BR' => 'PBR',
        'IO' => 'PA32',
        'VG' => 'PA60',
        'BN' => 'PBN',
        'BG' => 'PBG',
        'BI' => 'PBI',
        'KH' => 'PKH',
        'CM' => 'PCM',
        'CA' => 'PCA',
        'CV' => 'PA17',
        'KY' => 'PA36',
        'CF' => 'PCF',
        'TD' => 'PTD',
        'CL' => 'PCL',
        'CN' => 'PCN',
        'CX' => 'PA18',
        'CO' => 'PCO',
        'KM' => 'PA35',
        'CK' => 'PA16',
        'CR' => 'PCR',
        'HR' => 'PHR',
        'CU' => 'PCU',
        'CY' => 'PCY',
        'CZ' => 'PCZ',
        'DK' => 'PDK',
        'DJ' => 'PDJ',
        'DM' => 'PDM',
        'DO' => 'PDO',
        'EC' => 'PEC',
        'EG' => 'PEG',
        'SV' => 'PSV',
        'GQ' => 'PGQ',
        'ER' => 'PER',
        'EE' => 'PEE',
        'ET' => 'PET',
        'FK' => 'PA20',
        'FO' => 'PA22',
        'FJ' => 'PA1',
        'FI' => 'PFI',
        'FR' => 'PFR',
        'GF' => 'PGF',
        'PF' => 'PPF',
        'GA' => 'PGA',
        'GM' => 'PGM',
        'GE' => 'PGE',
        'DE' => 'PDE',
        'GH' => 'PGH',
        'GI' => 'PGI',
        'GR' => 'PGR',
        'GL' => 'PA28',
        'GD' => 'PA23',
        'GP' => 'PGP',
        'GU' => 'PA26',
        'GT' => 'PGT',
        'GG' => 'PA24',
        'GN' => 'PGN',
        'GW' => 'PA29',
        'GY' => 'PGY',
        'HT' => 'PHT',
        'HN' => 'PHN',
        'HK' => 'PA69',
        'HU' => 'PHU',
        'IS' => 'PIS',
        'IN' => 'PIN',
        'ID' => 'PID',
        'IR' => 'PIR',
        'IQ' => 'PIQ',
        'IE' => 'PIE',
        'IM' => 'PA31',
        'IL' => 'PIL',
        'IT' => 'PIT',
        'JM' => 'PJM',
        'JP' => 'PJP',
        'JE' => 'PA33',
        'JO' => 'PJO',
        'KZ' => 'PKZ',
        'KE' => 'PKE',
        'KI' => 'PA34',
        'KW' => 'PKW',
        'KG' => 'PKG',
        'LA' => 'PLA',
        'LV' => 'PLV',
        'LB' => 'PLB',
        'LS' => 'PA40',
        'LR' => 'PA39',
        'LY' => 'PLY',
        'LI' => 'PA38',
        'LT' => 'PLT',
        'LU' => 'PLU',
        'MO' => 'PA70',
        'MK' => 'PMK',
        'MG' => 'PMG',
        'MW' => 'PMW',
        'MY' => 'PMY',
        'MV' => 'PA3',
        'ML' => 'PML',
        'MT' => 'PMT',
        'MQ' => 'PMQ',
        'MR' => 'PMR',
        'MU' => 'PMU',
        'YT' => 'PYT',
        'MX' => 'PMX',
        'FM' => 'PA21',
        'MD' => 'PMD',
        'MC' => 'PMC',
        'MN' => 'PMN',
        'ME' => 'PA68',
        'MS' => 'PA43',
        'MA' => 'PMA',
        'MZ' => 'PMZ',
        'MM' => 'PMM',
        'NA' => 'PNA',
        'NR' => 'PA44',
        'NP' => 'PNP',
        'NL' => 'PNL',
        'NC' => 'PNC',
        'NZ' => 'PNZ',
        'NI' => 'PNI',
        'NE' => 'PNE',
        'NG' => 'PNG',
        'NU' => 'PA45',
        'NF' => 'PA6',
        'KP' => 'PKP',
        'MP' => 'PA42',
        'NO' => 'PNO',
        'OM' => 'POM',
        'PK' => 'PPK',
        'PW' => 'PA48',
        'PS' => 'PA47',
        'PA' => 'PPA',
        'PG' => 'PPG',
        'PY' => 'PPY',
        'PE' => 'PPE',
        'PH' => 'PPH',
        'PN' => 'PA46',
        'PL' => 'PPL',
        'PT' => 'PPT',
        'PR' => 'PPR',
        'QA' => 'PQA',
        'RE' => 'PRE',
        'RO' => 'PRO',
        'RU' => 'PRU',
        'RW' => 'PRW',
        'BL' => 'PSF',
        'SH' => 'PA66',
        'KN' => 'PKN',
        'LC' => 'PA37',
        'MF' => 'PA65',
        'SM' => 'PSM',
        'ST' => 'PA52',
        'SA' => 'PSA',
        'SN' => 'PSN',
        'RS' => 'PA67',
        'SL' => 'PSL',
        'SG' => 'PSG',
        'SK' => 'PSK',
        'SI' => 'PSI',
        'SB' => 'PA49',
        'SO' => 'PSO',
        'ZA' => 'PZA',
        'KR' => 'PKR',
        'ES' => 'PES',
        'LK' => 'PLK',
        'VC' => 'PA59',
        'SD' => 'PSD',
        'SR' => 'PSR',
        'SJ' => 'PA51',
        'SZ' => 'PA53',
        'SE' => 'PSE',
        'CH' => 'PCH',
        'SY' => 'PSY',
        'TW' => 'PTW',
        'TJ' => 'PTJ',
        'TZ' => 'PTZ',
        'TH' => 'PTH',
        'TL' => 'PA55',
        'TG' => 'PTG',
        'TK' => 'PA54',
        'TO' => 'PA56',
        'TT' => 'PTT',
        'TN' => 'PTN',
        'TR' => 'PTR',
        'TM' => 'PTM',
        'TV' => 'PA57',
        'UG' => 'PUG',
        'UA' => 'PUA',
        'AE' => 'PAE',
        'GB' => 'PGB',
        'US' => 'PUS',
        'UY' => 'PUY',
        'UZ' => 'PUZ',
        'VU' => 'PA62',
        'VA' => 'PVA',
        'VE' => 'PVE',
        'VN' => 'PVN',
        'WF' => 'PA63',
        'EH' => 'PA19',
        'YE' => 'PYE',
        'ZM' => 'PZM',
        'ZW' => 'PZW',
        'BA' => 'PBA',
        'BF' => 'PBF',
        'CC' => 'PA15',
        'CG' => 'PCG',
        'CD' => 'PCD',
        'HM' => 'PA30',
        'CI' => 'PCI',
        'MH' => 'PA41',
        'AS' => 'PA10',
        'WS' => 'PA64',
        'SC' => 'PA50',
        'GS' => 'PA25',
        'PM' => 'PPM',
        'TC' => 'PA4',
        'UM' => 'PA58',
        'VI' => 'PA61',
    ];
    public static $countryStatesMap = [
        'AU' => [
            // Australia
            'ACT'  => 'Australian Capital Territory',
            'RE48' => 'Jervis Bay Territory',
            'ANS'  => 'New South Wales',
            'ANT'  => 'Northern Territory',
            'AQL'  => 'Queensland',
            'ASA'  => 'South Australia',
            'ATS'  => 'Tasmania',
            'AVI'  => 'Victoria',
            'AWA'  => 'Western Australia',
        ],
        'BR' => [
            // Brazil
            'BAC' => 'Acre',
            'BAL' => 'Alagoas',
            'BAP' => 'Amapá',
            'BAM' => 'Amazonas',
            'BBA' => 'Bahia',
            'BCE' => 'Ceará',
            'BDF' => 'Distrito Federal',
            'BES' => 'Espirito Santo',
            'BGO' => 'Goiás',
            'BMA' => 'Maranhão',
            'BMT' => 'Mato Grosso',
            'BMS' => 'Mato Grosso Do Sul',
            'BMG' => 'Minas Gerais',
            'BPA' => 'Pará',
            'BPB' => 'Paraíba',
            'BPR' => 'Paraná',
            'BPE' => 'Pernambuco',
            'BPI' => 'Piaui',
            'BRJ' => 'Rio De Janeiro',
            'BRN' => 'Rio Grande Do Norte',
            'BRS' => 'Rio Grande Do Sul',
            'BRO' => 'Rondônia',
            'BRR' => 'Roraima',
            'BSC' => 'Santa Catarina',
            'BSP' => 'São Paulo',
            'BSE' => 'Sergipe',
            'BTO' => 'Tocantins',
        ],
        'CA' => [
            // Canada
            'CAL'  => 'Alberta',
            'CBC'  => 'British Columbia',
            'CMB'  => 'Manitoba',
            'CNB'  => 'New Brunswick',
            'CNF'  => 'Newfounland And Labrador',
            'CNWT' => 'Northwest Territory',
            'CNS'  => 'Nova Scotia',
            'CNU'  => 'Nunavut',
            'CON'  => 'Ontario',
            'CPI'  => 'Prince Edward Island',
            'CQC'  => 'Quebec',
            'CSK'  => 'Saskatchewan',
            'CYT'  => 'Yukon',
        ],
        'MX' => [
            // Mexico
            'AGU' => 'Aguascalientes',
            'BCN' => 'Baja California',
            'BCS' => 'Baja California Sur',
            'CAM' => 'Campeche',
            'CHP' => 'Chiapas',
            'CHH' => 'Chihuahua',
            'COA' => 'Coahuila',
            'COL' => 'Colima',
            'DUR' => 'Durango',
            'DIF' => 'Federal District',
            'GUA' => 'Guanajuato',
            'GRO' => 'Guerrero',
            'HID' => 'Hidalgo',
            'JAL' => 'Jalisco',
            'MEX' => 'Mexico State',
            'MIC' => 'Michoacán',
            'MOR' => 'Morelos',
            'NAY' => 'Nayarit',
            'NLE' => 'Nuevo León',
            'OAX' => 'Oaxaca',
            'PUE' => 'Puebla',
            'QUE' => 'Querétaro',
            'ROO' => 'Quintana Roo',
            'SLP' => 'San Luis Potosí',
            'SIN' => 'Sinaloa',
            'SON' => 'Sonora',
            'TAB' => 'Tabasco',
            'TAM' => 'Tamaulipas',
            'TLA' => 'Tlaxcala',
            'VER' => 'Veracruz',
            'YUC' => 'Yucatán',
            'ZAC' => 'Zacatecas',
        ],
        'US' => [
            // USA
            'UAL' => 'Alabama',
            'UAK' => 'Alaska',
            'UAZ' => 'Arizona',
            'UAR' => 'Arkansas',
            'UCA' => 'California',
            'UCO' => 'Colorado',
            'UCT' => 'Connecticut',
            'UDE' => 'Delaware',
            'UDC' => 'District Of Columbia',
            'UFL' => 'Florida',
            'UGA' => 'Georgia',
            'UHI' => 'Hawaii',
            'UID' => 'Idaho',
            'UIL' => 'Illinois',
            'UIN' => 'Indiana',
            'UIA' => 'Iowa',
            'UKS' => 'Kansas',
            'UKY' => 'Kentucky',
            'ULA' => 'Louisiana',
            'UME' => 'Maine',
            'UMD' => 'Maryland',
            'UMA' => 'Massachusetts',
            'UMI' => 'Michigan',
            'UMN' => 'Minnesota',
            'UMS' => 'Mississippi',
            'UMO' => 'Missouri',
            'UMT' => 'Montana',
            'UNE' => 'Nebraska',
            'UNV' => 'Nevada',
            'UNH' => 'New Hampshire',
            'UNJ' => 'New Jersey',
            'UNM' => 'New Mexico',
            'UNY' => 'New York',
            'UNC' => 'North Carolina',
            'UND' => 'North Dakota',
            'UOH' => 'Ohio',
            'UOK' => 'Oklahoma',
            'UOR' => 'Oregon',
            'UPA' => 'Pennsylvania',
            'UPR' => 'Puerto Rico',
            'URI' => 'Rhode Island',
            'USC' => 'South Carolina',
            'USD' => 'South Dakota',
            'UTN' => 'Tennessee',
            'UTX' => 'Texas',
            'UUT' => 'Utah',
            'UVT' => 'Vermont',
            'UVA' => 'Virginia',
            'UWA' => 'Washington',
            'UWV' => 'West Virginia',
            'UWI' => 'Wisconsin',
            'UWY' => 'Wyoming',
        ],
    ];
    public $timeout = 20;
    protected $fields;
    private $loginUrl = 'https://secure.accorhotels.com/gb/profil/registration.shtml';

    public static function getStatesMap()
    {
        $arr = [];

        foreach (self::$countryStatesMap as $country => $states) {
            if (isset(self::$countries[$country])) {
                foreach ($states as $code => $state) {
                    $arr[$code] = '(' . self::$countries[$country] . ') ' . $state;
                }
            }
        }

        return $arr;
    }

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->useChromium();
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
            [800, 600],
        ];
        $this->setScreenResolution($resolutions[array_rand($resolutions)]);

        $this->AccountFields['BrowserState'] = null;
        $this->http->saveScreenshots = true;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->keepSession(false); //no need true
        } elseif (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->keepCookies(false);
            $this->keepSession(false);
        }
    }

    public function registerAccount(array $fields)
    {
        $this->fields = $fields;

        $this->http->log('[DEBUG] fields:');
        $this->http->log(json_encode($fields, JSON_PRETTY_PRINT));

        if (strlen($this->fields['Password']) < 6 || !preg_match('/\d/', $this->fields['Password']) || !preg_match('/[[:alpha:]]/iu', $this->fields['Password'])) {
            throw new \UserInputError('Password has to be minimum of 6 characters, including at least one number and one letter');
        }

        if ($this->emailExists($fields['Email'])) {
            throw new \UserInputError('This email address already exists');
        }

        try {
            $this->http->GetURL($this->loginUrl);
            $this->setUSACookies();
        } catch (\WebDriverCurlException $e) {
            throw new \ProviderError('Site unresponsive, please try again later');
        }

        $textInputFields = [
            'Email',
            'Password',
            'LastName',
            'FirstName',
            //			'AddressLine1',
            //			'AddressLine2',
            //			'PostalCode',
            //			'City',
        ];

        foreach ($textInputFields as $awKey) {
            if (!isset($this->fields[$awKey]) or $this->fields[$awKey] === '') {
                continue;
            }
            $keys = self::$inputFieldsMap[$awKey];

            if (!is_array($keys)) {
                $keys = [$keys];
            }

            foreach ($keys as $key) {
                $xpath = '//form[@name="registration"]//input[@name="' . $key . '"]';

                if ($elem = $this->waitForElement(\WebDriverBy::xpath($xpath), $this->timeout)) {
                    $elem->sendKeys($this->fields[$awKey]);
                } else {
                    $this->http->Log("Could not find input field for $awKey value", LOG_LEVEL_ERROR);

                    return false;
                }
            }
        }
        $this->saveResponse();

        $selectInputFields = [
            'Title',
            'Country',
            'StateOrProvince',
        ];
        $origCountryCode = $this->fields['Country'];
        $this->fields['Country'] = self::$countriesMap[$this->fields['Country']];
        $this->logger->debug('Mapped standard country code "' . $origCountryCode . '" to provider code "' . $this->fields['Country'] . '"');

        foreach ($selectInputFields as $awKey) {
            if (!isset($this->fields[$awKey]) or !$this->fields[$awKey]) {
                continue;
            }
            $key = self::$inputFieldsMap[$awKey];
            $value = $this->fields[$awKey];
            $this->logger->debug('try set select @name="' . $key . '" to value "' . $value . '"');
            $xpath = '//form[@name="registration"]//select[@name="' . $key . '"]';
            $select = new \WebDriverSelect($this->driver->findElement(\WebDriverBy::xpath($xpath)));
            $select->selectByValue($value);
        }
        $checkboxInputFields = [
            //			'ReceiveNewsletter',
            'LCEnrollment',
        ];

        foreach ($checkboxInputFields as $awKey) {
            if (!isset($this->fields[$awKey])) {
                continue;
            }

            if ($awKey == 'LCEnrollment') {
                $key = 'user.lcEnrollment';
                $value = true;
            } else {
                $key = self::$inputFieldsMap[$awKey];
                $value = $this->fields[$awKey];
            }
            $this->logger->debug('try set select @name="' . $key . '" to value "' . $value . '"');
            $xpath = '//form[@name="registration"]//input[@name="' . $key . '"]';

            if ($elem = $this->waitForElement(\WebDriverBy::xpath($xpath), $this->timeout)) {
                $alreadyChecked = $elem->getAttribute('checked');

                if ($alreadyChecked and !$value or !$alreadyChecked and $value) {
                    $elem->click();
                }
            } else {
                $this->http->Log("Could not find input field for $awKey value", LOG_LEVEL_ERROR);
                $this->saveResponse();

                return false;
            }
        }

        //		$this->waitForElement(\WebDriverBy::id('submitForm'))->click();
        //		jQuery("#submitForm > span").click() and jQuery('#submitForm > span').trigger('click') - doesn't work'
        //		$this->driver->executeScript("
        //			ProfilRegistration._processRegistration();
        //		");
        $this->driver->executeScript("
			var opt = document.querySelector('#submitForm>span');
			if (opt)
				opt.click();
		");
        $this->saveResponse();
        //		$successXpath = '//li[@class="welcome-box" and ./span[@class="username"]]';
        $successXpath = '//*[contains(@class, "pb-welcome") and ./span[@class="username"]]';
        //		$errorsXpath = '//*[@class="error_msg" and not(contains(@style, "none")) and string-length(normalize-space(.)) > 1]';
        $errorsXpath = '//*[@class=\'field error\']';

        $technicalErrorXpath = '//*[contains(@class, "popinProfil")]/*[contains(@class, "msgBox")]';
        $this->logger->debug('res successXpath: ' . var_export($this->http->FindNodes($successXpath), true));
        $this->logger->debug('res errorsXpath: ' . var_export($this->http->FindNodes($errorsXpath), true));
        $this->logger->debug('res technicalErrorXpath: ' . var_export($this->http->FindNodes($technicalErrorXpath), true));
        $allXpath = implode('|', [$successXpath, $errorsXpath, $technicalErrorXpath]);

        if ($this->waitForElement(\WebDriverBy::xpath($allXpath), $this->timeout)) {
            if ($elem = $this->waitForElement(\WebDriverBy::xpath($successXpath), $this->timeout)) {
                // Welcome USERNAME
                $successMsg = $elem->getText();

                if ($cardNumberMsgElem = $this->waitForElement(\WebDriverBy::xpath("//p[(contains(normalize-space(.), 'Card no') or contains(@class,'num-card')) and not(descendant::tr)]"), 5)) {
                    $successMsg = sprintf('%s, %s', $successMsg, $cardNumberMsgElem->getText());
                }
                $this->logger->info($successMsg);
                $this->ErrorMessage = $successMsg;

                return true;
            } elseif ($technicalErrorElements = $this->driver->findElements(\WebDriverBy::xpath($technicalErrorXpath))) {
                $error = sprintf('%s Please try again later.', $technicalErrorElements[0]->getText());
                $this->http->Log($error, LOG_LEVEL_ERROR);

                throw new \ProviderError($error);
            } else {
                $errorElements = $this->driver->findElements(\WebDriverBy::xpath($errorsXpath));
                $errors = [];

                foreach ($errorElements as $ee) {
                    $errors[] = $ee->getText();
                }
                $error = implode(' ', $errors);
                $this->http->Log($error, LOG_LEVEL_ERROR);

                throw new \UserInputError($error);
            }
        }

        // if site hangs on successful reg, that's how we know success
        if ($this->emailExists($fields['Email'])) {
            $msg = sprintf('Welcome, %s %s', $fields['FirstName'], $fields['LastName']);
            $this->http->log('[INFO] ' . $msg);
            $this->ErrorMessage = $msg;

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (Minimum of 6 characters, including at least one number and one letter)',
                'Required' => true,
            ],
            'Title' => [
                'Type'     => 'string',
                'Caption'  => 'Title',
                'Required' => true,
                'Options'  => self::$titles,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            //			'AddressLine1' => [
            //				'Type' => 'string',
            //				'Caption' => 'Address',
            //				'Required' => true,
            //			],
            //			'AddressLine2' => [
            //				'Type' => 'string',
            //				'Caption' => 'Additional information',
            //				'Required' => false,
            //			],
            //			'PostalCode' => [
            //				'Type' => 'string',
            //				'Caption' => 'Postcode',
            //				'Required' => true,
            //			],
            //			'City' => [
            //				'Type' => 'string',
            //				'Caption' => 'City',
            //				'Required' => true,
            //			],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State/Province (required for USA, Australia, Brazil, Canada, Mexico)',
                'Required' => false,
                'Options'  => self::getStatesMap(), //$countryStatesMap
            ],
            //			'ReceiveNewsletter' => [
            //				'Type' => 'boolean',
            //				'Caption' => 'I would like to receive the Le Club Accorhotels exclusive newsletter',
            //				'Required' => false,
            //			]
        ];
    }

    protected function emailExists($email)
    {
        $br = new \HttpBrowser('none', new \CurlDriver());
        $url = 'https://secure.accorhotels.com/user/isProfileCheck.action';
        $params = ['user.email' => $email];
        $res = $br->postUrl($url, $params);

        $body = $br->Response['body'];
        $this->http->log('[DEBUG] email exists body:');
        $this->http->log($body);

        if ($br->findPreg('/error.email.already.exists.login/')) {
            return true;
        }

        return false;
    }

    private function setUSACookies()
    {
        $this->driver->manage()->addCookie([
            'name'   => 'userBrowsingZoneLocalization',
            'value'  => 'usa',
            'domain' => '.accorhotels.com',
        ]);
        $this->driver->manage()->addCookie([
            'name'   => 'userCurrency',
            'value'  => 'USD',
            'domain' => '.accorhotels.com',
        ]);
        $this->driver->manage()->addCookie([
            'name'   => 'userLang',
            'value'  => 'en',
            'domain' => '.accorhotels.com',
        ]);
        $this->driver->manage()->addCookie([
            'name'   => 'userLocalization',
            'value'  => 'us',
            'domain' => '.accorhotels.com',
        ]);
        $this->driver->manage()->addCookie([
            'name'   => 'userLocalizationInitial',
            'value'  => 'ru',
            'domain' => '.accorhotels.com',
        ]);
        $this->driver->manage()->addCookie([
            'name'   => 'userPrefLocalization',
            'value'  => 'en',
            'domain' => '.accorhotels.com',
        ]);
        $this->http->GetURL($this->loginUrl);
    }
}
