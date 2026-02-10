<?php

// case #11558

namespace AwardWallet\Engine\eurobonus\Transfer;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper {
        waitForElement as traitWaitForElement;
    }
    use \AwardWallet\Engine\ProxyList;

    public static $fieldMap = [
        'Email'                   => 'email',
        'Username'                => 'username',
        'Password'                => 'password',
        'FirstName'               => 'firstname',
        'LastName'                => 'lastname',
        'BirthDay'                => 'day',
        'BirthMonth'              => 'month',
        'BirthYear'               => 'year',
        'Gender'                  => 'gender',
        'PhoneCountryCodeNumeric' => 'mobilephone+',
        'Phone'                   => 'mobilephone',
        'Country'                 => 'country',
        'AddressLine1'            => 'addressLine1',
        'PostalCode'              => 'postcode',
        'City'                    => 'city',
        'StateOrProvince'         => 'state',
        'Captcha'                 => 'captcha',
    ];
    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
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
        'AG' => 'Antigua And Barbuda',
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
        'BA' => 'Bosnia Herzegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
        'VG' => 'British Virgin Islands',
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
        'CG' => 'Congo',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'CI' => 'Cote D\'Ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'CD' => 'Democratic Republic Of The Co',
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
        'KP' => 'Korea, Democratic Peoples Rep',
        'KR' => 'Korea, Republic Of',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao Peoples Democratic Republ',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libyan Arab Jamahiriyia',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macau',
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
        'MD' => 'Moldovo',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'MS' => 'Monserrat',
        'ME' => 'Montenegro',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar, Union Of',
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
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'MK' => 'Republic Of Macedonia',
        'RE' => 'Reunion',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome And Principe',
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
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'KN' => 'St. Christopher (St. Kitts) N',
        'SH' => 'St. Helena',
        'LC' => 'St. Lucia',
        'PM' => 'St. Pierre And Miquelon',
        'VC' => 'St. Vincent And The Grenadine',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SZ' => 'Swaziland',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TG' => 'Togo',
        'TO' => 'Tonga',
        'TT' => 'Trinidad And Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks And Caicos Islands',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UM' => 'United States Minor Outlying',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VE' => 'Venezuela',
        'VN' => 'Viet Nam',
        'VI' => 'Virgin Islands, United States',
        'WF' => 'Wallis And Futuna Islands',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];
    public $retryLimit = 5;
    public $skipCaptcha = false;
    protected $timeout = 10;
    protected $registerUrl = 'https://www.sas.se/en/#/profile?userAction=Register';
    protected $fields;

    public function initBrowser()
    {
        $this->UseSelenium();
        //		$this->http->saveScreenshots = true;
        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            //			$this->http->SetProxy('localhost:8000');
            $this->keepSession(false); //no need true
        } elseif (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->keepSession(false);
            //			$this->http->SetProxy($this->proxyDOP());
//			$this->http->SetProxy($this->proxyUK());
        }
    }

    public function registerAccount(array $fields)
    {
        $this->logger->info('[INFO] ' . __METHOD__);

        $this->logger->info('[INFO] initial fields:');
        $this->logger->info(json_encode($fields, JSON_PRETTY_PRINT));

        $this->http->LogHeaders = true;
        $this->ArchiveLogs = true;
        $this->fields = $fields;

        $this->checkFields();

        $this->registerPrepare();

        if ($this->fields['BirthMonth'] < 10) {
            $this->fields['BirthMonth'] = '0' . $this->fields['BirthMonth'];
        }

        if ($this->fields['BirthDay'] < 10) {
            $this->fields['BirthDay'] = '0' . $this->fields['BirthDay'];
        }

        $gender = strtolower(self::$genders[$this->fields['Gender']]);

        $arrayRegFields = [
            'registerEmail'     => $this->fields['Email'],
            'registerPassword'  => $this->fields['Password'],
            'registerDob'       => $this->fields['BirthYear'] . '-' . $this->fields['BirthMonth'] . '-' . $this->fields['BirthDay'],
            'registerPhone'     => $this->getPhone(),
            'registerFirstname' => $this->fields['FirstName'],
            'registerLastname'  => $this->fields['LastName'],
        ];

        foreach ($arrayRegFields as $id => $value) {
            if (!$el = $this->waitForElement(\WebDriverBy::id($id), $this->timeout)) {
                throw new \EngineError('Failed to find input field with name "' . $id . '"');
            }
            $el->clear();
            $el->sendKeys($value);
        }

        $this->driver->executeScript('
            var scope = angular.element(document.querySelector("form[name = registerForm]")).scope();
            
            scope.registerForm.registerEmail.$render();
            
            scope.registerForm.registerPassword.$render();
            
            scope.registerForm.registerDob.$render();
            
            scope.registerForm.registerPhone.$render();
            
            scope.registerForm.registerFirstname.$render();
            
            scope.registerForm.registerLastname.$render();
            
            scope.registerForm.gender.$setViewValue("' . $gender . '");
            scope.registerForm.gender.$render();
            
            scope.submit();
        ');

        if ($err = $this->driver->findElements(\WebDriverBy::xpath("//div[contains(@class, 'errorInfoText ng-binding')]"))) {
            $err = array_map(function ($e) {
                return trim($e->getText());
            }, $err);
            $err = array_filter($err);
            $msg = implode("\n", $err);

            if (!empty($msg)) {
                throw new \UserInputError(trim($msg));
            }
        }

        if ($err = $this->waitForElement(\WebDriverBy::xpath("//div[@class = 'col-xs-12 api_Err_response']//div[1]"), $this->timeout)) {
            throw new \UserInputError($err->getText());
        }

        if ($this->waitForElement(\WebDriverBy::xpath("//*[contains(., 'Welcome') and contains(., '" . $this->fields['FirstName'] . "')]"), $this->timeout)) {
            $this->logger->info('Successfull registration');
            $this->ErrorMessage = 'Successfull registration';

            return true;
        }

        //		$this->retry = 1;
        //		return $this->register();
        return false;
    }

    public function getRegisterFields()
    {
        return [
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email (will be your username)',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (must be 6-12 characters, minimum one digit and one letter)',
                'Required' => true,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
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
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            //			'PhoneCountryCodeNumeric' => [
            //				'Type' => 'string',
            //			    'Caption' => '1-3-number Phone Country Code',
            //			    'Required' => true,
            //			],
            'PhoneAreaCode' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Area Code',
                'Required' => true,
            ],
            'PhoneLocalNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number',
                'Required' => true,
            ],
            //			'Country' => [
            //				'Type' => 'string',
            //			    'Caption' => 'Country',
            //			    'Required' => true,
            //			    'Options' => self::$countries,
            //			],
            //			'AddressLine1' => [
            //				'Type' => 'string',
            //			    'Caption' => 'Address (this information is needed in order for you to receive your EuroBonus Card)',
            //			    'Required' => false,
            //			],
            //			'PostalCode' => [
            //				'Type' => 'string',
            //			    'Caption' => 'Postal Code (this information is needed in order for you to receive your EuroBonus Card)',
            //			    'Required' => false,
            //			],
            //			'City' => [
            //				'Type' => 'string',
            //			    'Caption' => 'City (this information is needed in order for you to receive your EuroBonus Card)',
            //			    'Required' => false,
            //			],
            //			'StateOrProvince' => [
            //				'Type' => 'string',
            //			    'Caption' => 'State (required for Australia, Canada and United States, this information is needed in order for you to receive your EuroBonus Card)',
            //			    'Required' => false,
            //			],
        ];
    }

    protected function checkFields()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        //		if (!in_array($this->fields['Country'], array_keys(self::$countries)))
        //			throw new \UserInputError('Invalid country code');

        $pass = $this->fields['Password'];

        if (strlen($pass) < 6 || strlen($pass) > 12) {
            throw new \UserInputError('Password must be 6-12 characters');
        }

        if (empty($this->fields['FirstName'])) {
            throw new \UserInputError('Please enter your first name using 2 to 30 characters.');
        }
    }

    protected function modifyFields()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $this->fields['Username'] = $this->fields['Email'];

        $this->fields['BirthDay'] = sprintf("%02d", trim($this->fields['BirthDay']));
        $this->fields['BirthMonth'] = ltrim(trim($this->fields['BirthMonth']), '0');

        $this->fields['PhoneCountryCodeNumeric'] = sprintf('+%s', $this->fields['PhoneCountryCodeNumeric']);
        $this->fields['Phone'] = $this->getPhone();
    }

    protected function getPhone()
    {
        return sprintf('%s%s', $this->fields['PhoneAreaCode'], $this->fields['PhoneLocalNumber']);
    }

    protected function register()
    {
        $this->http->log(sprintf('[INFO] %s, try #%s', __METHOD__, $this->retry));

        $this->registerPrepare(); // here for now, should be outside

        try {
            if (!$this->skipCaptcha) {
                $this->fields['Captcha'] = $this->parseCaptcha();
            } else {
                $this->fields['Captcha'] = '999';
            }
        } catch (\CaptchaException $e) {
            $this->http->log('[INFO] captcha recognizing failed with exception');

            throw new \CheckException('Captcha issue, please try again');
        }

        $submit = $this->waitForElement(\WebDriverBy::cssSelector('div#submitbutton'));
        $this->fillInputs(); // fast enough, can be in retry
        $this->fillState();
        $rules = $this->driver->findElement(\WebDriverBy::xpath('//*[@name = "confirm_rules"]'));
        $rules->click(); // always click, repeat from the top
        $submit->click();
        $status = $this->checkStatus();

        if ($status) {
            return true;
        } else {
            $this->retry++;

            return $this->register();
        }
    }

    protected function registerPrepare()
    {
        $this->logger->info('[INFO] ' . __METHOD__);

        try {
            $this->http->GetURL($this->registerUrl);
        } catch (\WebDriverCurlException $e) {
            throw new \ProviderError('Site timeout, please try again later', ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg('/504 - Gateway Timeout/i')) {
            throw new \CheckException('Site error, please try again later', ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg('/Error 404/i')) {
            throw new \CheckException('Site error, please try again later', ACCOUNT_PROVIDER_ERROR);
        }

        //		$this->driver->switchTo()->frame('EB-signUp');
        $this->saveResponse();
    }

    protected function parseCaptcha()
    {
        $captcha = $this->driver->executeScript(sprintf("

        var captchaDiv = document.createElement('div');
        captchaDiv.id = 'captchaDiv';
        document.body.appendChild(captchaDiv);

        var canvas = document.createElement('CANVAS'),
            ctx = canvas.getContext('2d'),
            img = document.getElementsByClassName('%s')[0];

        canvas.height = img.height;
        canvas.width = img.width;
        ctx.drawImage(img, 0, 0);
        dataURL = canvas.toDataURL('image/png');

        return dataURL;

        ", 'captcha_img'));
        $this->http->Log("[INFO] captcha: " . $captcha);
        $marker = "data:image/png;base64,";

        if (strpos($captcha, $marker) !== 0) {
            $this->http->Log("no marker");

            return false;
        }
        $captcha = substr($captcha, strlen($marker));
        $file = tempnam(sys_get_temp_dir(), "captcha") . ".png";
        $this->http->Log("[INFO] captcha file: " . $file);
        file_put_contents($file, base64_decode($captcha));

        $code = $this->recognizeFile($file);
        unlink($file);

        return $code;
    }

    protected function recognizeFile($file)
    {
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 100;

        try {
            // parameters https://rucaptcha.com/api-rucaptcha
            $code = trim($this->recognizer->recognizeFile($file, ["calc" => 1]));
        } catch (\CaptchaException $e) {
            $this->http->Log("exception: " . $e->getMessage());
            // Notifications
            if (strstr($e->getMessage(), "ERROR_ZERO_BALANCE")) {
                $this->sendNotification("WARNING! " . $this->recognizer->domain . " - balance is null");

                throw new \EngineError(self::CAPTCHA_ERROR_MSG);
            }

            if ($e->getMessage() == 'server returned error: ERROR_CAPTCHA_UNSOLVABLE'
                || $e->getMessage() == 'timelimit (60) hit'
                || $e->getMessage() == 'slot not available') {
                $this->http->Log("parseCaptcha", LOG_LEVEL_ERROR);
                // retries
                throw new \CheckRetryNeededException(3, 7);
            }

            return false;
        }

        return $code;
    }

    protected function waitForElement($selector, $timeout = null, $visible = true)
    {
        if ($timeout === null) {
            $timeout = $this->timeout;
        }

        return $this->traitWaitForElement($selector, $timeout, $visible);
    }

    protected function fillInputs()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        // In theory StateOrProvince should be removed from this loop,
        // since separate function for it. But left for simplicity.
        foreach (self::$fieldMap as $awkey => $key) {
            if (!ArrayVal($this->fields, $awkey)) {
                continue;
            }
            $this->setInputValue($key, $this->fields[$awkey]);
        }
    }

    protected function setInputValue($key, $value)
    {
        $this->driver->executeScript("
			var elem = document.getElementById('$key');
			if (elem) {
				$(elem).val('$value').change();
			} else {
				elem = document.getElementsByName('$key')[0];
				$(elem).val('$value').change();
			}
		");
    }

    protected function fillState()
    {
        if (!ArrayVal($this->fields, 'StateOrProvince')) {
            return;
        }

        $awkey = 'StateOrProvince';
        $key = self::$fieldMap[$awkey];
        $value = ArrayVal($this->fields, $awkey);

        if (!$value) {
            return;
        }

        $optionSel = sprintf('//select[@name = "%s"]/option[@value = "%s"]', $key, $value);
        $this->waitForElement(\WebDriverBy::xpath($optionSel));

        $elem = $this->waitForElement(\WebDriverBy::name($key));
        $select = new \WebDriverSelect($elem);
        $select->selectByValue($value);
    }

    protected function checkStatus()
    {
        $this->http->log('[INFO] ' . __METHOD__);

        $this->waitForElement(\WebDriverBy::cssSelector('div#submitbutton'));
        $this->saveResponse();

        $elem = $this->waitForElement(\WebDriverBy::xpath('//div[@id = "ansok"]/iframe[1]'));

        if ($elem) {
            $this->driver->switchTo()->frame($elem);
            $body = $this->driver->executeScript('return document.body.innerHTML');
            $this->http->log('[INFO] body:');
            $this->http->log($body);

            if (preg_match('/Your personal membership number is: (\w+)/i', $body, $m)) {
                $acc = $m[1];
                $msg = sprintf('Successfull registration, your account number is %s', $acc);
                $this->http->log(sprintf('[INFO] %s', $msg));
                $this->ErrorMessage = $msg;

                return true;
            }
        }

        $errors = $this->driver->findElements(\WebDriverBy::cssSelector('div.error,span.error,div.toperror'), $this->timeout, true);
        $errors = array_map(function ($el) { return trim($el->getText()); }, $errors);
        $errors = array_filter($errors);
        $this->http->log('[INFO] errors:');
        $this->http->log(print_r($errors, true));

        if ($errors) {
            $message = trim(implode(', ', $errors));
            $newMessage = trim(preg_replace('/Please add the numbers\s*/i', '', $message));
            $captchaIssue = $newMessage !== $message;
            $message = $newMessage;

            if ($message) {
                $this->http->log(sprintf('[ERROR] %s', $message));

                throw new \UserInputError($message);
            } elseif ($captchaIssue) { // just captcha problem
                if ($this->retry < $this->retryLimit) {
                    $this->http->log('[INFO] captcha issue');

                    return false;
                } else {
                    $this->http->log('[INFO] retry limit of %s exceeded');
                    $message = 'Captcha issue, please try again later';
                    $this->http->log('[ERROR] ' . $message);

                    throw new \CheckException($message);
                }
            }
        }

        throw new \ProviderError('unexpected situation');
    }
}
