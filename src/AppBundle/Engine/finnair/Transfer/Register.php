<?php

namespace AwardWallet\Engine\finnair\Transfer;

class Register extends \TAccountCheckerFinnair
{
    public static $genders = [
        "M" => "Male",
        "F" => "Female",
    ];
    public static $addressTypes = [
        "HOME" => "Home",
        "WORK" => "Work",
    ];
    public static $preferredLanguages = [
        "en" => "English",
        "fi" => "Finnish",
        "sv" => "Swedish",
    ];
    public static $seatPreferences = [
        "Aisle"  => "Aisle",
        "Window" => "Window",
    ];
    public static $mealPreferences = [
        "AVML" => "Veget. Hindu meal",
        "BLML" => "Bland meal",
        "DBML" => "Diabetic",
        "GFML" => "Gluten Free",
        "HNML" => "Hindu",
        "KSML" => "Kosher",
        "LCML" => "Low calorie",
        "LFML" => "Low cholesterol/fat",
        "LPML" => "Low protein",
        "LSML" => "Low sodium",
        "MOML" => "Moslem",
        "NLML" => "Low lactose",
        "PRML" => "Low purine",
        "RVML" => "Raw vegetarian",
        "VGML" => "Non-dairy veget.",
        "VLML" => "Lacto-ovo veget.",
    ];
    public static $countries = [
        'AF' => 'Afghanistan',
        'AX' => 'Åland Islands',
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
        'BA' => 'Bosnia-Herzegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory',
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
        'CX' => 'Christmas Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DO' => 'Dominican Republic',
        'TL' => 'East Timor',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands',
        'FO' => 'Faroe Islands',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Territories',
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
        'GW' => 'Guinea Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard and Mcdonald Islands',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IM' => 'Isle Of Man',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'CI' => 'Ivory Coast',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JE' => 'Jersey',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
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
        'MP' => 'Northern Mariana Islands',
        'KP' => 'North Korea',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
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
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'BL' => 'Saint Barthélemy',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'MF' => 'Saint Martin',
        'PM' => 'Saint Pierre and Miquelon',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
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
        'SH' => 'St Helena',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen Islands',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
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
        'US' => 'United States',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City State',
        'VE' => 'Venezuela',
        'VN' => 'Viet Nam',
        'WF' => 'Wallis and Futuna Islands',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'BV' => 'Bouvet Islands',
        'CC' => 'Cocos Keeling Island',
        'CD' => 'Congo Rep Dem',
        'FJ' => 'Fiji Islands',
        'LA' => 'Lao Democratic Republic',
        'NF' => 'Norfolk Islands',
        'PS' => 'Palestinian Occ Territories',
        'RS' => 'Serbia Montenegro',
        'UM' => 'United States Minor Outlaying Islands',
    ];
    protected $languageMap = [
        'en' => 'EN',
        'fi' => 'FI',
        'sv' => 'SV',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
    }

    public function registerAccount(array $fields)
    {
        $this->http->Log('finnair - start registration');
        $fields['PreferredLanguage'] = $this->languageMap[$fields['PreferredLanguage']];

        if (strlen($fields["Password"]) < 8 || strlen($fields["Password"]) > 32) {
            throw new \UserInputError("Password should be 8 to 32 characters long");
        }

        foreach (['Nationality', 'Country', 'PassportCountry'] as $k) {
            if (isset($fields[$k]) and !in_array($fields[$k], array_keys(self::$countries))) {
                throw new \UserInputError("$k: invalid country code");
            }
        }

        // Provider uses wrong country codes for Serbia Montenegro (CS instead of standard RS)
        // Map from our standard ISO code to wrong code used by provider
        if ($fields['Country'] == 'RS') {
            $fields['Country'] = 'CS';
            $this->logger->debug('Mapped standard country code "RS" to provider code "CS"');
        }

        $form = [
            "callback"             => "jQuery",
            ""                     => "",
            "token"                => "dummy",
            "status"               => "A",
            "c_token"              => "",
            "enc"                  => "",
            "membernumber"         => "",
            "gender"               => "",
            "nationality"          => "",
            "firstname"            => "",
            "middlename"           => "",
            "lastname"             => "",
            "birthdate"            => "",
            "guardianName"         => "",
            "guardianMobile"       => "",
            "guardianReference"    => "",
            "guardianEmail"        => "",
            "mobile"               => "",
            "email"                => "",
            "password"             => "",
            "passwordVerification" => "",
            "addressType"          => "",
            "homeCountry"          => "",
            "homeStreet"           => "",
            "homeZip"              => "",
            "homeCity"             => "",
            "homeBuildingName"     => "",
            "homeState"            => "",
            "homeProvince"         => "",
            "homeTerritory"        => "",
            "company"              => "",
            "title"                => "",
            "workCountry"          => "",
            "workStreet"           => "",
            "workZip"              => "",
            "workCity"             => "",
            "workBuildingName"     => "",
            "workState"            => "",
            "workProvince"         => "",
            "workTerritory"        => "",
            "language"             => "EN",
            "agreements"           => "on",
            "originCity"           => "",
            "seatPreferences"      => "",
            "mealPreferences"      => "",
            "businessDest"         => "",
            "leisureDest"          => "",
            "passportNumber"       => "",
            "passportCountry"      => "",
            "passportIssued"       => "",
            "passportExpires"      => "",
            "interests"            => "",
            "vacationTypes"        => "",
            "household"            => "",
            "birthyears1"          => "",
            "birthyears2"          => "",
            "birthyears3"          => "",
            "birthyears4"          => "",
            "birthyears5"          => "",
            "_"                    => time() . "123",
        ];

        foreach ([
            "nationality" => "Nationality",
            "firstname" => "FirstName",
            "lastname" => "LastName",
            "middlename" => "MiddleName",
            "mobile" => "PhoneNumber",
            "email" => "Email",
            "password" => "Password",
            "passwordVerification" => "Password",
            "company" => "Company",
            "title" => "JobTitle",
            "language" => "PreferredLanguage",
            "passportNumber" => "PassportNumber",
            "passportCountry" => "PassportCountry",
        ] as $n => $f) {
            if (!empty($fields[$f])) {
                $form[$n] = $fields[$f];
            }
        }
        $genders = ["F" => "Female", "M" => "Male"];
        $form["gender"] = ArrayVal($genders, $fields["Gender"], "");
        $addressFields = [
            "Country" => "Country",
            "Street"  => "AddressLine1",
            "Zip"     => "PostalCode",
            "City"    => "City",
        ];

        if (in_array($fields["Country"], ["US", "CA", "AU", "JA"])) {
            if (trim($fields["StateOrProvince"]) == "") {
                throw new \UserInputError("State/Province is required for US/CA/AU/JA");
            }

            switch ($fields["Country"]) {
                case "US":
                    $addressFields["State"] = "StateOrProvince";

                    break;

                case "CA":
                    $addressFields["Province"] = "StateOrProvince";

                    break;

                case "JA":
                    $addressFields["Prefecture"] = "StateOrProvince";

                    break;

                case "AU":
                    $addressFields["Territory"] = "StateOrProvince";

                    break;
            }
        }
        $prefix = "home";

        switch ($fields["AddressType"]) {
            case "WORK":
                if (empty($fields["Company"])) {
                    throw new \UserInputError("Company name is required for work addresses");
                }
                $prefix = "work";
                $form["addressType"] = 14;

                break;

            case "HOME":
                $form["addressType"] = 13;

                break;
        }

        foreach ($addressFields as $n => $f) {
            $form[$prefix . $n] = $fields[$f];
        }

        foreach ([
            "customfield9" => "OffersSubscribers",
            "customfield10" => "OffersMembers",
            "customfield11" => "OffersPersonalized",
            "customfield8" => "OffersPartners",
            "customfield7" => "OffersSMS",
        ] as $n => $f) {
            if (isset($fields[$f]) and intval($fields[$f])) {
                $form[$n] = "TRUE";
            }
        }

        foreach (["flightInfoSMS", "flightInfoEmail"] as $n) {
            if (isset($fields[ucfirst($n)]) and intval($fields[ucfirst($n)])) {
                $form[$n] = "TRUE";
            }
        }

        if (isset($fields['SeatPreference'])) {
            switch ($fields["SeatPreference"]) {
                case "Aisle":
                    $form["seatPreferences"] = 3;

                    break;

                case "Window":
                    $form["seatPreferences"] = 5;

                    break;
            }
        }

        if (!empty($fields["MealPreference"])) {
            $form["mealPreferences"] = $fields["MealPreference"];
        }

        foreach ([
            "businessDest" => "BusinessDestination",
            "leisureDest" => "LeisureDestination",
            "originCity" => "OriginAirport",
        ] as $n => $f) {
            if (empty($fields[$f])) {
                continue;
            }

            if (!preg_match("/^[A-Z]{3}$/", $fields[$f])) {
                throw new \UserInputError("Invalid airport code");
            }
            $form[$n] = $fields[$f];
        }

        foreach ([
            "birthdate" => "Birth",
            "passportIssued" => "PassportIssue",
            "passportExpires" => "PassportExpiration",
        ] as $n => $pre) {
            $day = ArrayVal($fields, $pre . "Day", "");

            if (strlen($day) == 1) {
                $day = "0$day";
            }
            $month = ArrayVal($fields, $pre . "Month", "");

            if (strlen($month) == 1) {
                $month = "0$month";
            }
            $year = ArrayVal($fields, $pre . "Year", "");

            if (!empty($day) && !empty($month) && !empty($year)) {
                $form[$n] = "$year-$month-$day";
            }
        }

        $this->http->Log(var_export($form, true));
        $url = "https://partners.finnair.fi/profile/put?" . ImplodeAssoc("=", "&", $form, true);
        $this->http->GetURL($url);

        if ($this->http->FindPreg("/\{[\"']error[\"']:[\"']500[\"']\}/")) {
            // validation is performed by JS
            throw new \ProviderError("Error occurred trying to register new account");
        }
        $number = $this->http->FindPreg("/[\"']membernumber[\"']:[\"'](\d+)[\"']\}\);/");

        if (isset($number)) {
            $this->http->Log("found number: " . $number);
            $this->ErrorMessage = "Member Number: $number";

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            "Email" => [
                "Type"     => "string",
                "Caption"  => "Email",
                "Required" => true,
            ],
            "Password" => [
                "Type"     => "string",
                "Caption"  => "Password, 8 to 32 characters",
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Caption"  => "First Name",
                "Required" => true,
            ],
            "MiddleName" => [
                "Type"     => "string",
                "Caption"  => "Middle Name",
                "Required" => false,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "Last Name",
                "Required" => true,
            ],
            "Gender" => [
                "Type"     => "string",
                "Caption"  => "Gender",
                "Required" => true,
                "Options"  => self::$genders,
            ],
            "Nationality" => [
                "Type"     => "string",
                "Caption"  => "Nationality, country code",
                "Required" => true,
                "Options"  => self::$countries,
            ],
            "BirthDay" => [
                "Type"     => "integer",
                "Caption"  => "Day of Birth Date",
                "Required" => true,
            ],
            "BirthMonth" => [
                "Type"     => "integer",
                "Caption"  => "Month of Birth Date",
                "Required" => true,
            ],
            "BirthYear" => [
                "Type"     => "integer",
                "Caption"  => "Year of Birth Date",
                "Required" => true,
            ],
            "AddressType" => [
                "Type"     => "string",
                "Caption"  => "Mailing Address Type",
                "Required" => true,
                "Options"  => self::$addressTypes,
            ],
            "Country" => [
                "Type"     => "string",
                "Caption"  => "Country Code",
                "Required" => true,
                "Options"  => self::$countries,
            ],
            "StateOrProvince" => [
                "Type"     => "string",
                "Caption"  => "State or Province (please choose state/province for US and Canada or type state/territory/prefecture name for Australia and Japan)",
                "Required" => false,
            ],
            "City" => [
                "Type"     => "string",
                "Caption"  => "City",
                "Required" => true,
            ],
            "AddressLine1" => [
                "Type"     => "string",
                "Caption"  => "Address Line",
                "Required" => true,
            ],
            "PostalCode" => [
                "Type"     => "string",
                "Caption"  => "Postal Code",
                "Required" => true,
            ],
            "Company" => [
                "Type"     => "string",
                "Caption"  => "Company name, required for work address",
                "Required" => false,
            ],
            "JobTitle" => [
                "Type"     => "string",
                "Caption"  => "Job Title, for work address",
                "Required" => false,
            ],
            "PhoneNumber" => [
                "Type"     => "string",
                "Caption"  => "Full Phone Number with Areal Code",
                "Required" => true,
            ],
            "PreferredLanguage" => [
                "Type"     => "string",
                "Caption"  => "Preferred Language",
                "Required" => true,
                "Options"  => self::$preferredLanguages,
            ],
            "OffersSubscribers" => [
                "Type"     => "boolean",
                "Caption"  => "Receive Finnair flight offers and best deals available exclusively to email subscribers",
                "Required" => false,
            ],
            "OffersMembers" => [
                "Type"     => "boolean",
                "Caption"  => "Receive offers exclusive for Finnair Plus members",
                "Required" => false,
            ],
            "OffersPersonalized" => [
                "Type"     => "boolean",
                "Caption"  => "Receive personalized offers",
                "Required" => false,
            ],
            "OffersPartners" => [
                "Type"     => "boolean",
                "Caption"  => "Receive offers from partners",
                "Required" => false,
            ],
            "OffersSMS" => [
                "Type"     => "boolean",
                "Caption"  => "Receive offers and news via SMS",
                "Required" => false,
            ],
            "FlightInfoSMS" => [
                "Type"     => "boolean",
                "Caption"  => "Receive flight information via SMS",
                "Required" => false,
            ],
            "FlightInfoEmail" => [
                "Type"     => "boolean",
                "Caption"  => "Receive flight information via email",
                "Required" => false,
            ],
            "SeatPreference" => [
                "Type"     => "string",
                "Caption"  => "Seat Preference",
                "Options"  => self::$seatPreferences,
                "Required" => false,
            ],
            "MealPreference" => [
                "Type"     => "string",
                "Caption"  => "Meal preference",
                "Options"  => self::$mealPreferences,
                "Required" => false,
            ],
            "OriginAirport" => [
                "Type"     => "string",
                "Caption"  => "IATA code of default origin airport",
                "Required" => false,
            ],
            "BusinessDestination" => [
                "Type"     => "string",
                "Caption"  => "IATA code of favourite business destination airport",
                "Required" => false,
            ],
            "LeisureDestination" => [
                "Type"     => "string",
                "Caption"  => "IATA code of favourite leisure destination airport",
                "Required" => false,
            ],
            "PassportNumber" => [
                "Type"     => "string",
                "Caption"  => "Passport Number",
                "Required" => false,
            ],
            "PassportCountry" => [
                "Type"     => "string",
                "Caption"  => "Passport issuing country code",
                "Options"  => self::$countries,
                "Required" => false,
            ],
            "PassportIssueDay" => [
                "Type"     => "integer",
                "Caption"  => "Day of date when passport issued",
                "Required" => false,
            ],
            "PassportIssueMonth" => [
                "Type"     => "integer",
                "Caption"  => "Month of date when passport issued",
                "Required" => false,
            ],
            "PassportIssueYear" => [
                "Type"     => "integer",
                "Caption"  => "Year of date when passport issued",
                "Required" => false,
            ],
            "PassportExpirationDay" => [
                "Type"     => "integer",
                "Caption"  => "Day of passport expiration date",
                "Required" => false,
            ],
            "PassportExpirationMonth" => [
                "Type"     => "integer",
                "Caption"  => "Month of passport expiration date",
                "Required" => false,
            ],
            "PassportExpirationYear" => [
                "Type"     => "integer",
                "Caption"  => "Year of passport expiration date",
                "Required" => false,
            ],
        ];
    }
}
