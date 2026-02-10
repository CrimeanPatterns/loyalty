<?php

namespace AwardWallet\Engine\asiana\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use ProxyList;

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];
    public static $nationalities = [
        "AF" => "AFGHANISTAN",
        "AL" => "ALBANIA",
        "DZ" => "ALGERIA",
        "AS" => "AMERICAN SAMOA",
        "AD" => "ANDORRA",
        "AO" => "ANGOLA",
        "AI" => "ANGUILLA",
        "AQ" => "ANTARCTICA",
        "AG" => "ANTIGUA AND BARBUDA",
        "AR" => "ARGENTINA",
        "AM" => "ARMENIA",
        "AW" => "ARUBA",
        "AU" => "AUSTRALIA",
        "AT" => "AUSTRIA",
        "AZ" => "AZERBAIJAN",
        "BS" => "BAHAMAS",
        "BH" => "BAHRAIN",
        "BD" => "BANGLADESH",
        "BB" => "BARBADOS",
        "BY" => "BELARUS",
        "BE" => "BELGIUM",
        "BZ" => "BELIZE",
        "BJ" => "BENIN",
        "BM" => "BERMUDA",
        "BT" => "BHUTAN",
        "BO" => "BOLIVIA",
        "BA" => "BOSNIA AND HERZEGOWINA",
        "BW" => "BOTSWANA",
        "BV" => "BOUVET ISLAND",
        "BR" => "BRAZIL",
        "IO" => "BRITISH INDIAN OCEAN TERRITORY",
        "BN" => "BRUNEI DARUSSALAM",
        "BG" => "BULGARIA",
        "BF" => "BURKINA FASO",
        "BI" => "BURUNDI",
        "KH" => "CAMBODIA",
        "CM" => "CAMEROON",
        "CA" => "CANADA",
        "CV" => "CAPE VERDE",
        "KY" => "CAYMAN ISLANDS",
        "CF" => "CENTRAL AFRICAN REPUBLIC",
        "TD" => "CHAD",
        "CL" => "CHILE",
        "CN" => "CHINA",
        "CX" => "CHRISTMAS ISLAND",
        "CC" => "COCOS (KEELING) ISLANDS",
        "CO" => "COLOMBIA",
        "KM" => "COMOROS",
        "CD" => "CONGO, Democratic Republic of (was Zaire)",
        "CG" => "CONGO, People's Republic of",
        "CK" => "COOK ISLANDS",
        "CR" => "COSTA RICA",
        "CI" => "COTE D'IVOIRE",
        "HR" => "CROATIA (local name: Hrvatska)",
        "CU" => "CUBA",
        "CY" => "CYPRUS",
        "CZ" => "CZECH REPUBLIC",
        "DK" => "DENMARK",
        "DJ" => "DJIBOUTI",
        "DM" => "DOMINICA",
        "DO" => "DOMINICAN REPUBLIC",
        "TL" => "EAST TIMOR",
        "EC" => "ECUADOR",
        "EG" => "EGYPT",
        "SV" => "EL SALVADOR",
        "GQ" => "EQUATORIAL GUINEA",
        "ER" => "ERITREA",
        "EE" => "ESTONIA",
        "ET" => "ETHIOPIA",
        "FK" => "FALKLAND ISLANDS (MALVINAS)",
        "FO" => "FAROE ISLANDS",
        "FJ" => "FIJI",
        "FI" => "FINLAND",
        "FR" => "FRANCE",
        "GF" => "FRENCH GUIANA",
        "PF" => "FRENCH POLYNESIA",
        "TF" => "FRENCH SOUTHERN TERRITORIES",
        "GA" => "GABON",
        "GM" => "GAMBIA",
        "GE" => "GEORGIA",
        "DE" => "GERMANY",
        "GH" => "GHANA",
        "GI" => "GIBRALTAR",
        "GR" => "GREECE",
        "GL" => "GREENLAND",
        "GD" => "GRENADA",
        "GP" => "GUADELOUPE",
        "GU" => "GUAM",
        "GT" => "GUATEMALA",
        "GN" => "GUINEA",
        "GW" => "GUINEA-BISSAU",
        "GY" => "GUYANA",
        "HT" => "HAITI",
        "HM" => "HEARD AND MC DONALD ISLANDS",
        "HN" => "HONDURAS",
        "HK" => "HONG KONG",
        "HU" => "HUNGARY",
        "IS" => "ICELAND",
        "IN" => "INDIA",
        "ID" => "INDONESIA",
        "IR" => "IRAN (ISLAMIC REPUBLIC OF)",
        "IQ" => "IRAQ",
        "IE" => "IRELAND",
        "IL" => "ISRAEL",
        "IT" => "ITALY",
        "JM" => "JAMAICA",
        "JP" => "JAPAN",
        "JO" => "JORDAN",
        "KZ" => "KAZAKHSTAN",
        "KE" => "KENYA",
        "KI" => "KIRIBATI",
        "KP" => "KOREA, DEMOCRATIC PEOPLE'S REPUBLIC OF",
        "KR" => "KOREA, REPUBLIC OF",
        "KW" => "KUWAIT",
        "KG" => "KYRGYZSTAN",
        "LA" => "LAO PEOPLE'S DEMOCRATIC REPUBLIC",
        "LV" => "LATVIA",
        "LB" => "LEBANON",
        "LS" => "LESOTHO",
        "LR" => "LIBERIA",
        "LY" => "LIBYAN ARAB JAMAHIRIYA",
        "LI" => "LIECHTENSTEIN",
        "LT" => "LITHUANIA",
        "LU" => "LUXEMBOURG",
        "MO" => "MACAU",
        "MK" => "MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF",
        "MG" => "MADAGASCAR",
        "MW" => "MALAWI",
        "MY" => "MALAYSIA",
        "MV" => "MALDIVES",
        "ML" => "MALI",
        "MT" => "MALTA",
        "MH" => "MARSHALL ISLANDS",
        "MQ" => "MARTINIQUE",
        "MR" => "MAURITANIA",
        "MU" => "MAURITIUS",
        "YT" => "MAYOTTE",
        "MX" => "MEXICO",
        "FM" => "MICRONESIA, FEDERATED STATES OF",
        "MD" => "MOLDOVA, REPUBLIC OF",
        "MC" => "MONACO",
        "MN" => "MONGOLIA",
        "MS" => "MONTSERRAT",
        "MA" => "MOROCCO",
        "MZ" => "MOZAMBIQUE",
        "MM" => "MYANMAR",
        "NA" => "NAMIBIA",
        "NR" => "NAURU",
        "NP" => "NEPAL",
        "NL" => "NETHERLANDS",
        "AN" => "NETHERLANDS ANTILLES",
        "NC" => "NEW CALEDONIA",
        "NZ" => "NEW ZEALAND",
        "NI" => "NICARAGUA",
        "NE" => "NIGER",
        "NG" => "NIGERIA",
        "NU" => "NIUE",
        "NF" => "NORFOLK ISLAND",
        "MP" => "NORTHERN MARIANA ISLANDS",
        "NO" => "NORWAY",
        "OM" => "OMAN",
        "PK" => "PAKISTAN",
        "PW" => "PALAU",
        "PS" => "PALESTINIAN TERRITORY, Occupied",
        "PA" => "PANAMA",
        "PG" => "PAPUA NEW GUINEA",
        "PY" => "PARAGUAY",
        "PE" => "PERU",
        "PH" => "PHILIPPINES",
        "PN" => "PITCAIRN",
        "PL" => "POLAND",
        "PT" => "PORTUGAL",
        "PR" => "PUERTO RICO",
        "QA" => "QATAR",
        "RE" => "REUNION",
        "RO" => "ROMANIA",
        "RU" => "RUSSIAN FEDERATION",
        "RW" => "RWANDA",
        "KN" => "SAINT KITTS AND NEVIS",
        "LC" => "SAINT LUCIA",
        "VC" => "SAINT VINCENT AND THE GRENADINES",
        "WS" => "SAMOA",
        "SM" => "SAN MARINO",
        "ST" => "SAO TOME AND PRINCIPE",
        "SA" => "SAUDI ARABIA",
        "SN" => "SENEGAL",
        "SC" => "SEYCHELLES",
        "SL" => "SIERRA LEONE",
        "SG" => "SINGAPORE",
        "SK" => "SLOVAKIA (Slovak Republic)",
        "SI" => "SLOVENIA",
        "SB" => "SOLOMON ISLANDS",
        "SO" => "SOMALIA",
        "ZA" => "SOUTH AFRICA",
        "GS" => "SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS",
        "ES" => "SPAIN",
        "LK" => "SRI LANKA",
        "SH" => "ST. HELENA",
        "PM" => "ST. PIERRE AND MIQUELON",
        "SD" => "SUDAN",
        "SR" => "SURINAME",
        "SJ" => "SVALBARD AND JAN MAYEN ISLANDS",
        "SZ" => "SWAZILAND",
        "SE" => "SWEDEN",
        "CH" => "SWITZERLAND",
        "SY" => "SYRIAN ARAB REPUBLIC",
        "TW" => "TAIWAN",
        "TJ" => "TAJIKISTAN",
        "TZ" => "TANZANIA, UNITED REPUBLIC OF",
        "TH" => "THAILAND",
        "TG" => "TOGO",
        "TK" => "TOKELAU",
        "TO" => "TONGA",
        "TT" => "TRINIDAD AND TOBAGO",
        "TN" => "TUNISIA",
        "TR" => "TURKEY",
        "TC" => "TURKS AND CAICOS ISLANDS",
        "TV" => "TUVALU",
        "US" => "U.S.A.",
        "UG" => "UGANDA",
        "UA" => "UKRAINE",
        "AE" => "UNITED ARAB EMIRATES",
        "GB" => "UNITED KINGDOM",
        "UM" => "UNITED STATES MINOR OUTLYING ISLANDS",
        "UY" => "URUGUAY",
        "UZ" => "UZBEKISTAN",
        "VU" => "VANUATU",
        "VA" => "VATICAN CITY STATE (HOLY SEE)",
        "VE" => "VENEZUELA",
        "VN" => "VIET NAM",
        "VG" => "VIRGIN ISLANDS (BRITISH)",
        "VI" => "VIRGIN ISLANDS (U.S.)",
        "WF" => "WALLIS AND FUTUNA ISLANDS",
        "EH" => "WESTERN SAHARA",
        "YE" => "YEMEN",
        "ZM" => "ZAMBIA",
        "ZW" => "ZIMBABWE",
    ];
    public static $countries = [
        "AF" => "AFGHANISTAN",
        "AL" => "ALBANIA",
        "DZ" => "ALGERIA",
        "AS" => "AMERICAN SAMOA",
        "AD" => "ANDORRA",
        "AO" => "ANGOLA",
        "AI" => "ANGUILLA",
        "AQ" => "ANTARCTICA",
        "AG" => "ANTIGUA AND BARBUDA",
        "AR" => "ARGENTINA",
        "AM" => "ARMENIA",
        "AW" => "ARUBA",
        "AU" => "AUSTRALIA",
        "AT" => "AUSTRIA",
        "AZ" => "AZERBAIJAN",
        "BS" => "BAHAMAS",
        "BH" => "BAHRAIN",
        "BD" => "BANGLADESH",
        "BB" => "BARBADOS",
        "BY" => "BELARUS",
        "BE" => "BELGIUM",
        "BZ" => "BELIZE",
        "BJ" => "BENIN",
        "BM" => "BERMUDA",
        "BT" => "BHUTAN",
        "BO" => "BOLIVIA",
        "BA" => "BOSNIA AND HERZEGOWINA",
        "BW" => "BOTSWANA",
        "BV" => "BOUVET ISLAND",
        "BR" => "BRAZIL",
        "IO" => "BRITISH INDIAN OCEAN TERRITORY",
        "BN" => "BRUNEI DARUSSALAM",
        "BG" => "BULGARIA",
        "BF" => "BURKINA FASO",
        "BI" => "BURUNDI",
        "KH" => "CAMBODIA",
        "CM" => "CAMEROON",
        "CA" => "CANADA",
        "CV" => "CAPE VERDE",
        "KY" => "CAYMAN ISLANDS",
        "CF" => "CENTRAL AFRICAN REPUBLIC",
        "TD" => "CHAD",
        "CL" => "CHILE",
        "CN" => "CHINA",
        "CX" => "CHRISTMAS ISLAND",
        "CC" => "COCOS (KEELING) ISLANDS",
        "CO" => "COLOMBIA",
        "KM" => "COMOROS",
        "CD" => "CONGO, Democratic Republic of (was Zaire)",
        "CG" => "CONGO, People's Republic of",
        "CK" => "COOK ISLANDS",
        "CR" => "COSTA RICA",
        "CI" => "COTE D'IVOIRE",
        "HR" => "CROATIA (local name: Hrvatska)",
        "CU" => "CUBA",
        "CY" => "CYPRUS",
        "CZ" => "CZECH REPUBLIC",
        "DK" => "DENMARK",
        "DJ" => "DJIBOUTI",
        "DM" => "DOMINICA",
        "DO" => "DOMINICAN REPUBLIC",
        "TL" => "EAST TIMOR",
        "EC" => "ECUADOR",
        "EG" => "EGYPT",
        "SV" => "EL SALVADOR",
        "GQ" => "EQUATORIAL GUINEA",
        "ER" => "ERITREA",
        "EE" => "ESTONIA",
        "ET" => "ETHIOPIA",
        "FK" => "FALKLAND ISLANDS (MALVINAS)",
        "FO" => "FAROE ISLANDS",
        "FJ" => "FIJI",
        "FI" => "FINLAND",
        "FR" => "FRANCE",
        "GF" => "FRENCH GUIANA",
        "PF" => "FRENCH POLYNESIA",
        "TF" => "FRENCH SOUTHERN TERRITORIES",
        "GA" => "GABON",
        "GM" => "GAMBIA",
        "GE" => "GEORGIA",
        "DE" => "GERMANY",
        "GH" => "GHANA",
        "GI" => "GIBRALTAR",
        "GR" => "GREECE",
        "GL" => "GREENLAND",
        "GD" => "GRENADA",
        "GP" => "GUADELOUPE",
        "GU" => "GUAM",
        "GT" => "GUATEMALA",
        "GN" => "GUINEA",
        "GW" => "GUINEA-BISSAU",
        "GY" => "GUYANA",
        "HT" => "HAITI",
        "HM" => "HEARD AND MC DONALD ISLANDS",
        "HN" => "HONDURAS",
        "HK" => "HONG KONG",
        "HU" => "HUNGARY",
        "IS" => "ICELAND",
        "IN" => "INDIA",
        "ID" => "INDONESIA",
        "IR" => "IRAN (ISLAMIC REPUBLIC OF)",
        "IQ" => "IRAQ",
        "IE" => "IRELAND",
        "IL" => "ISRAEL",
        "IT" => "ITALY",
        "JM" => "JAMAICA",
        "JP" => "JAPAN",
        "JO" => "JORDAN",
        "KZ" => "KAZAKHSTAN",
        "KE" => "KENYA",
        "KI" => "KIRIBATI",
        "KP" => "KOREA, DEMOCRATIC PEOPLE'S REPUBLIC OF",
        "KR" => "KOREA, REPUBLIC OF",
        "KW" => "KUWAIT",
        "KG" => "KYRGYZSTAN",
        "LA" => "LAO PEOPLE'S DEMOCRATIC REPUBLIC",
        "LV" => "LATVIA",
        "LB" => "LEBANON",
        "LS" => "LESOTHO",
        "LR" => "LIBERIA",
        "LY" => "LIBYAN ARAB JAMAHIRIYA",
        "LI" => "LIECHTENSTEIN",
        "LT" => "LITHUANIA",
        "LU" => "LUXEMBOURG",
        "MO" => "MACAU",
        "MK" => "MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF",
        "MG" => "MADAGASCAR",
        "MW" => "MALAWI",
        "MY" => "MALAYSIA",
        "MV" => "MALDIVES",
        "ML" => "MALI",
        "MT" => "MALTA",
        "MH" => "MARSHALL ISLANDS",
        "MQ" => "MARTINIQUE",
        "MR" => "MAURITANIA",
        "MU" => "MAURITIUS",
        "YT" => "MAYOTTE",
        "MX" => "MEXICO",
        "FM" => "MICRONESIA, FEDERATED STATES OF",
        "MD" => "MOLDOVA, REPUBLIC OF",
        "MC" => "MONACO",
        "MN" => "MONGOLIA",
        "MS" => "MONTSERRAT",
        "MA" => "MOROCCO",
        "MZ" => "MOZAMBIQUE",
        "MM" => "MYANMAR",
        "NA" => "NAMIBIA",
        "NR" => "NAURU",
        "NP" => "NEPAL",
        "NL" => "NETHERLANDS",
        "AN" => "NETHERLANDS ANTILLES",
        "NC" => "NEW CALEDONIA",
        "NZ" => "NEW ZEALAND",
        "NI" => "NICARAGUA",
        "NE" => "NIGER",
        "NG" => "NIGERIA",
        "NU" => "NIUE",
        "NF" => "NORFOLK ISLAND",
        "MP" => "NORTHERN MARIANA ISLANDS",
        "NO" => "NORWAY",
        "OM" => "OMAN",
        "PK" => "PAKISTAN",
        "PW" => "PALAU",
        "PS" => "PALESTINIAN TERRITORY, Occupied",
        "PA" => "PANAMA",
        "PG" => "PAPUA NEW GUINEA",
        "PY" => "PARAGUAY",
        "PE" => "PERU",
        "PH" => "PHILIPPINES",
        "PN" => "PITCAIRN",
        "PL" => "POLAND",
        "PT" => "PORTUGAL",
        "PR" => "PUERTO RICO",
        "QA" => "QATAR",
        "RE" => "REUNION",
        "RO" => "ROMANIA",
        "RU" => "RUSSIAN FEDERATION",
        "RW" => "RWANDA",
        "KN" => "SAINT KITTS AND NEVIS",
        "LC" => "SAINT LUCIA",
        "VC" => "SAINT VINCENT AND THE GRENADINES",
        "WS" => "SAMOA",
        "SM" => "SAN MARINO",
        "ST" => "SAO TOME AND PRINCIPE",
        "SA" => "SAUDI ARABIA",
        "SN" => "SENEGAL",
        "SC" => "SEYCHELLES",
        "SL" => "SIERRA LEONE",
        "SG" => "SINGAPORE",
        "SK" => "SLOVAKIA (Slovak Republic)",
        "SI" => "SLOVENIA",
        "SB" => "SOLOMON ISLANDS",
        "SO" => "SOMALIA",
        "ZA" => "SOUTH AFRICA",
        "GS" => "SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS",
        "ES" => "SPAIN",
        "LK" => "SRI LANKA",
        "SH" => "ST. HELENA",
        "PM" => "ST. PIERRE AND MIQUELON",
        "SD" => "SUDAN",
        "SR" => "SURINAME",
        "SJ" => "SVALBARD AND JAN MAYEN ISLANDS",
        "SZ" => "SWAZILAND",
        "SE" => "SWEDEN",
        "CH" => "SWITZERLAND",
        "SY" => "SYRIAN ARAB REPUBLIC",
        "TW" => "TAIWAN",
        "TJ" => "TAJIKISTAN",
        "TZ" => "TANZANIA, UNITED REPUBLIC OF",
        "TH" => "THAILAND",
        "TG" => "TOGO",
        "TK" => "TOKELAU",
        "TO" => "TONGA",
        "TT" => "TRINIDAD AND TOBAGO",
        "TN" => "TUNISIA",
        "TR" => "TURKEY",
        "TC" => "TURKS AND CAICOS ISLANDS",
        "TV" => "TUVALU",
        "US" => "U.S.A.",
        "UG" => "UGANDA",
        "UA" => "UKRAINE",
        "AE" => "UNITED ARAB EMIRATES",
        "GB" => "UNITED KINGDOM",
        "UM" => "UNITED STATES MINOR OUTLYING ISLANDS",
        "UY" => "URUGUAY",
        "UZ" => "UZBEKISTAN",
        "VU" => "VANUATU",
        "VA" => "VATICAN CITY STATE (HOLY SEE)",
        "VE" => "VENEZUELA",
        "VN" => "VIET NAM",
        "VG" => "VIRGIN ISLANDS (BRITISH)",
        "VI" => "VIRGIN ISLANDS (U.S.)",
        "WF" => "WALLIS AND FUTUNA ISLANDS",
        "EH" => "WESTERN SAHARA",
        "YE" => "YEMEN",
        "ZM" => "ZAMBIA",
        "ZW" => "ZIMBABWE",
    ];
    public static $phoneTypes = [
        'H' => 'Home',
        'M' => 'Mobile',
    ];

    public function InitBrowser()
    {
        $this->UseCurlBrowser();
        $this->http->LogHeaders = true;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        } else {
            $this->http->SetProxy('localhost:8000');
        }
    }

    public function registerAccount(array $fields)
    {
        $this->http->GetURL('https://eu.flyasiana.com/I/EN/BeforeRegisteredMemberCheck.do');

        // Check Username
        $this->http->FormURL = 'https://eu.flyasiana.com/I/EN/CheckDuplicateUserId.do';
        $this->http->setInputValue('userId', $fields['Username']);
        $status = $this->http->PostForm();

        if (!$status) {
            throw new \EngineError('Failed to post Username');
        }

        $response = $this->http->JsonLog();

        if (!isset($response->isDuplicate)) {
            throw new \EngineError('Username checking error');
        }

        if ($response->isDuplicate) {
            throw new \UserInputError('Unavailable Username. Can not dublicate');
        }

        // Step 1
        $this->http->GetURL('https://eu.flyasiana.com/I/EN/BeforeRegisteredMemberCheck.do');
        $status = $this->http->ParseForm('form');

        if (!$status) {
            throw new \EngineError('Failed to parse step1 form');
        }

        $fields['BirthDayStr'] = ($fields['BirthDay'] + 0) < 10 ? '0' . ($fields['BirthDay'] + 0) : $fields['BirthDay'];
        $fields['BirthMonthStr'] = ($fields['BirthMonth'] + 0) < 10 ? '0' . ($fields['BirthMonth'] + 0) : $fields['BirthMonth'];
        $inputData1 = [
            'birthDate'           => $fields['BirthYear'] . $fields['BirthMonthStr'] . $fields['BirthDayStr'],
            'sex'                 => $fields['Gender'],
            'isKoreanNationality' => 'false',
            'isRegisteredMember'  => 'true',
            'isSelfCertification' => 'false',
            'isParentAgree'       => 'false',
            'step'                => '1',
            'year'                => $fields['BirthYear'],
            'month'               => $fields['BirthMonth'],
            'day'                 => $fields['BirthDay'],
            'lastName'            => $fields['LastName'],
            'firstName'           => $fields['FirstName'],
            'gender'              => 'on',
            'mNationCode'         => 'kr',
            'mLangCode'           => '',
        ];

        foreach ($inputData1 as $fieldKey => $val) {
            $this->http->setInputValue($fieldKey, $val);
        }

        $this->http->FormURL = 'https://eu.flyasiana.com/I/EN/CheckRegisteredMember.do';
        $status = $this->http->PostForm();

        if (!$status) {
            throw new \EngineError('Failed to post step1 form');
        }

        // Step 2
        $status = $this->http->ParseForm('form');

        if (!$status) {
            throw new \EngineError('Failed to parse step2 form');
        }

        $inputData2 = [
            'registrationType'        => 'NEW',
            'isKoreanNationality'     => 'false',
            'isRegisteredMember'      => 'true',
            'isSelfCertification'     => 'false',
            'isParentAgree'           => 'false',
            'step'                    => '2',
            'clubNHomepageUseTerm'    => 'true',
            'personalCollectionTerm'  => 'true',
            'optionInfoFlagTerm'      => 'true',
            'personalProvideTerm'     => 'true',
            'identityCollectionTerm2' => 'true',
            'mNationCode'             => 'kr',
            'mLangCode'               => '',
        ];

        foreach ($inputData2 as $fieldKey => $val) {
            $this->http->setInputValue($fieldKey, $val);
        }

        $this->http->FormURL = 'https://eu.flyasiana.com/I/EN/GetMemberInformationInput.do';
        $status = $this->http->PostForm();

        if (!$status) {
            throw new \EngineError('Failed to post step2 form');
        }

        // Step 3
        $status = $this->http->ParseForm('form');

        if (!$status) {
            throw new \EngineError('Failed to parse step3 form');
        }

        $jsonData = $this->http->FindPreg('/selfCertification[\s]*=[\s]*eval\((.*)\)/');
        $inputData3 = [
            'homePhone'            => $fields['PhoneCountryCodeNumeric'] . '-' . $fields['PhoneAreaCode'] . $fields['PhoneLocalNumber'],
            'homePhone1'           => $fields['PhoneAreaCode'],
            'homePhone2'           => $fields['PhoneLocalNumber'],
            'zipCode'              => $fields['PostalCode'],
            'address1'             => $fields['AddressLine1'],
            'city'                 => $fields['City'],
            'email'                => $fields['Email'],
            'receiveSMSType'       => 'N',
            'countryCallNum'       => $fields['PhoneCountryCodeNumeric'],
            'nationality'          => $fields['Nationality'],
            'residentCountry'      => $fields['Country'],
            'chkEditBtn'           => 'Y',
            'userId'               => $fields['Username'],
            'compareUserId'        => $fields['Username'],
            'password'             => $fields['Password'],
            'confirmPassword'      => $fields['Password'],
            'rejectEmailType'      => '0',
            'SELF_CERTIFICATION'   => $jsonData,
            'PARENT_CERTIFICATION' => 'null',
        ];
        unset($this->http->Form['']);

        foreach ($inputData3 as $fieldKey => $val) {
            $this->http->setInputValue($fieldKey, $val);
        }

        $this->http->FormURL = 'https://eu.flyasiana.com/I/EN/SaveMemberInformation.do';
        $status = $this->http->PostForm();

        if (!$status) {
            throw new \EngineError('Failed to post step3 form');
        }

        $success = $this->http->FindPreg("/congratulations\s+on\s+your\s+subscription/");

        if ($success) {
            $memberKey = $this->http->FindSingleNode("//input[@id='acno']/@value");
            $this->ErrorMessage = 'Success Registration. Membership Number ' . $memberKey;
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        $errorMessage = $this->http->FindPreg("/Error\s+Message\s*\:([^<]+)</");

        if ($errorMessage) {
            throw new \UserInputError($errorMessage);
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'Given Name, as it appears on your Passport / ID Card, in English',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Family Name, as it appears on your Passport / ID Card, in English',
                'Required' => true,
            ],
            'Username' => [
                'Type'     => 'string',
                'Caption'  => 'User ID (enter your ID consisting of 6 to 15 characters, including both letters (case-sensitive) and numbers. No Korean letters, spaces or special characters allowed)',
                'Required' => true,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            'BirthDay' => [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth Date',
                'Required' => true,
            ],
            'BirthMonth' => [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth Date',
                'Required' => true,
            ],
            'BirthYear' => [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date',
                'Required' => true,
            ],
            'StateOrProvince' => [
                'Type'     => 'string',
                'Caption'  => 'State Or Province',
                'Required' => true,
            ],
            'Nationality' => [
                'Type'     => 'string',
                'Caption'  => 'Nationality, 2 letter country code',
                'Required' => true,
                'Options'  => self::$nationalities,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (use a combination of 8 to 15 digits with more than two numbers, letters, or special characters. Allowed special characters are ! \"" # $ % & \' ( ) * + , - . / : ; < = > ? @ [ ＼ ] ^ _ ` { | } ~ No Korean letters, spaces, date of birth, repeated or consecutive numbers allowed.)',
                'Required' => true,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country of Residence',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City',
                'Required' => true,
            ],
            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Address',
                'Required' => true,
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'PhoneCountryCodeNumeric' => [
                'Type'     => 'string',
                'Caption'  => 'Phone country code',
                'Required' => true,
            ],
            'PhoneAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Phone area code',
                'Required' => true,
            ],
            'PhoneLocalNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone number',
                'Required' => true,
            ],
        ];
    }
}
