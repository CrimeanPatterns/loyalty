<?php

namespace AwardWallet\Engine\alitalia\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    public static $addrTypes = [
        'H' => 'Home',
        'B' => 'Business',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $preferredLanguages = [
        "Italiano"   => "Italian",
        "Francese"   => "French",
        "Tedesco"    => "German",
        "Inglese"    => "English",
        "Giapponese" => "Japanese",
        "Portoghese" => "Portuguese",
        "Spagnolo"   => "Spanish",
    ];

    public static $countries = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AO' => 'Angola',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BJ' => 'Benin',
        'BA' => 'Bosnia',
        'BR' => 'Brazil',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'KY' => 'Cayman Islands',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CG' => 'Congo',
        'CD' => 'Democratic Republic of the Congo',
        'CR' => 'Costa Rica',
        'CI' => 'Ivory Coast',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'GA' => 'Gabon',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GR' => 'Greece',
        'GP' => 'Guadeloupe',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GQ' => 'Equatorial Guinea',
        'HT' => 'Haiti',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KR' => 'Rep South Korea',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LY' => 'Libya',
        'LU' => 'Luxembourg',
        'MK' => 'Macedonia/FYROM',
        'MG' => 'Madagascar',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'MX' => 'Mexico',
        'MD' => 'Moldova',
        'ME' => 'Montenegro',
        'MA' => 'Morocco',
        'AN' => 'Netherlands Antilles',
        'NL' => 'Netherlands',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PA' => 'Panama',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RE' => 'Réunion',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'SX' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovak Republic',
        'SI' => 'Slovenia',
        'ZA' => 'South Africa',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'Great Britain',
        'US' => 'USA',
        'UZ' => 'Uzbekistan',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'VI' => 'Virgin Islands (US)',
        'ZM' => 'Zambia',
    ];

    public static $inputFieldsMap = [
        'FirstName'       => 'name',
        'LastName'        => 'surname',
        'BirthDay'        => 'day',
        'BirthMonth'      => 'month',
        'BirthYear'       => 'year',
        'Country'         => 'nation',
        'AddressLine1'    => 'address',
        'PostalCode'      => 'cap',
        'City'            => 'city',
        'StateOrProvince' => 'province',
        'Email'           => 'email',
    ];

    protected $loadTimeout = 20;

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->useChromium();
        $this->http->driver->showImages = false;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy('localhost:8000');
        } else {
            $this->http->SetProxy($this->proxyDOP());
        }
    }

    public function registerAccount(array $fields)
    {
        // Step 1: PERSONAL INFORMATION
        $this->http->GetURL("https://www.alitalia.com/en_us/special-pages/subscribe_mm_program.html");
        //inputs

        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey]) or $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                if (!$elem = $this->waitForElement(\WebDriverBy::xpath("//*[@name='{$provKey}']"), $this->loadTimeout)) {
                    throw new \ProviderError("Can not find $provKey field");
                }
                $elem->sendKeys($fields[$awKey]);
            }
        }

        //radio
        $ids = [
            'gender' . strtoupper($fields['Gender']),
            ['H' => 'home', 'B' => 'office'][$fields['AddressType']],
        ];

        foreach ($ids as $id) {
            $this->driver->executeScript("
                $('#{$id}').click();
            ");
        }

        if ($fields['AddressType'] === 'B') {
            if (!$elem = $this->waitForElement(\WebDriverBy::xpath("//*[@name='company']"), $this->loadTimeout)) {
                throw new \ProviderError("Can not find company field");
            }
            $elem->sendKeys($fields['Company']);
        }

        // submit step1
        if (!$elem = $this->waitForElement(\WebDriverBy::id('registratiSubmit'), $this->loadTimeout)) {
            throw new \ProviderError("Can not find registratiSubmit btn");
        }
        $elem->click();

        // Step 2: COMMUNICATIONS AND PREFERENCES
        //input
        if (!$elem = $this->waitForElement(\WebDriverBy::xpath("//*[@name='language']"), $this->loadTimeout)) {
            $this->findErrors();

            throw new \ProviderError("Can not find prferedLanguage field");
        }

        if ($fields['Country'] !== 'IT') {
            $elem->sendKeys($fields['PreferredLanguage']);
        }

        //radio
        $ids = [
            'newsletter3',
            'email',
            'trattamento2',
            'check_millemiglia',
        ];

        foreach ($ids as $id) {
            $this->driver->executeScript("
                $('#{$id}').click();
            ");
        }

        // submit step2
        if (!$elem = $this->waitForElement(\WebDriverBy::id('completaSubmit'), $this->loadTimeout)) {
            throw new \ProviderError("Can not find completaSubmit btn");
        }
        $elem->click();

        if ($elem = $this->waitForElement(\WebDriverBy::xpath("//p[contains(text(), 'your MilleMiglia code')]"), $this->loadTimeout)) {
            $this->ErrorMessage = $elem->getText();
            $this->http->Log($this->ErrorMessage);

            return true;
        }

        $this->findErrors();

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
                'Caption'  => 'Year of Birth Date',
                'Required' => true,
            ],
            'Gender' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            'AddressType' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Address Type',
                'Required' => true,
                'Options'  => self::$addrTypes,
            ],
            'Company' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Company (required for busuness address)',
                'Required' => false,
            ],
            'AddressLine1' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Address Line',
                'Required' => true,
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
            'StateOrProvince' =>
            [
                'Type'     => 'string',
                'Caption'  => 'State or Province (required for Canada, Italy, US)',
                'Required' => false,
            ],
            'Email' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'PreferredLanguage' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Preferred Language (for residents in Italy, only Italian)',
                'Required' => true,
                'Options'  => self::$preferredLanguages,
            ],
        ];
    }

    protected function findErrors()
    {
        if ($elem = $this->waitForElement(\WebDriverBy::className("form__errorField"), $this->loadTimeout)) {
            $field = explode('_', $elem->getAttribute('id'));

            throw new \UserInputError("Error value in {$field[0]} field");
        }
    }
}
