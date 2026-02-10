<?php

namespace AwardWallet\Engine\airmilesca\Transfer;

use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;
    public $timeout = 10;

    public static $states = [
        'AB' => 'Alberta',
        'BC' => 'British Columbia',
        'MB' => 'Manitoba',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland',
        'NT' => 'Northwest Territories',
        'NS' => 'Nova Scotia',
        'NU' => 'Nunavut',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Quebec',
        'SK' => 'Saskatchewan',
        'YT' => 'Yukon Territory',
    ];

    public static $phoneTypes = [
        'H' => 'Home',
        'B' => 'Business',
        'M' => 'Mobile',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $preferredLanguages = [
        'en' => 'English',
        'fr' => 'French',
    ];

    public static $securityQuestionTypes = [
        1 => "What is your mother's maiden name?",
        2 => "In what city were you born?",
        3 => "What is your favorite color?",
        4 => "What is your favourite sports team?",
        5 => "What is your first child's birthdate?",
        6 => "What is your dog's name?",
    ];
    protected $fields;
    protected $startTime;

    protected $languageMap = [
        'en' => 'E',
        'fr' => 'F',
    ];

    /** @var \TAccountChecker */
//    private $registrUrl = 'https://whatismyipaddress.com';
    private $registrUrl = 'https://www.airmiles.ca/arrow/Enrollment';

    public function initBrowser()
    {
        $this->useSelenium();
//        $this->useChrome();
        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->http->SetProxy($this->proxyDOP());
        } else {
            $this->http->SetProxy('localhost:8000');
        }
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
                'Caption'  => 'Province',
                'Required' => true,
                'Options'  => self::$states,
            ],
            'PostalCode' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
            ],
            'PhoneType' =>
            [
                'Type'     => 'string',
                'Caption'  => 'PhoneType',
                'Required' => false,
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
            'Gender' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            'PreferredLanguage' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Language',
                'Required' => true,
                'Options'  => self::$preferredLanguages,
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
            'Password' =>
            [
                'Type'     => 'string',
                'Caption'  => 'PIN (4 digit number)',
                'Required' => true,
            ],
            'SecurityQuestionType1' =>
            [
                'Type'     => 'integer',
                'Caption'  => 'Security Question',
                'Required' => true,
                'Options'  => self::$securityQuestionTypes,
            ],
            'SecurityQuestionAnswer1' =>
            [
                'Type'     => 'string',
                'Caption'  => 'Answer',
                'Required' => true,
            ],
        ];
    }

    public function registerAccount(array $fields)
    {
        $fields['PreferredLanguage'] = $this->languageMap[$fields['PreferredLanguage']];
        $this->fields = $fields;
        $this->checkValues();

        $status = false;

        try {
            $this->startTime = time();
            $status = $this->registerInternal();
        } catch (\CheckException $e) {
            $this->saveResponse();

            throw $e;
        } catch (\Exception $e) {
            $this->log($e->getMessage(), LOG_LEVEL_ERROR);
//            $this->log(print_r($e));
            $this->saveResponse();

            return false;
        }
        $this->saveResponse();

        return $status;
    }

    protected function checkValues()
    {
        if (0 === preg_match('/^[\d]{4}$/', $this->fields['Password'])) {
            throw new \UserInputError('Password needs to be 4 digit number');
        }  /*review*/

        if (0 === preg_match('/^[\d]{10}$/', $this->fields['PhoneAreaCode'] . $this->fields['PhoneLocalNumber'])) {
            throw new \UserInputError('PhoneAreaCode + PhoneLocalNumber needs to be 10 digit number');
        }  /*review*/
    }

    protected function checkErrors()
    {
        if ($this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'alert-danger')]"), $this->timeout, false)) {
            $errors = $this->driver->findElements(\WebDriverBy::xpath("//div[contains(@class,'error-alert')]"));

            foreach ($errors as $error) {
                if ($error->isDisplayed()) {
                    throw new \UserInputError($error->getText());
                }
            } // Is it always user error?
        }

        if ($this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'error-alert')]"), $this->timeout, false)) {
            $errors = $this->driver->findElements(\WebDriverBy::xpath("//div[contains(@class,'error-alert')]"));

            foreach ($errors as $error) {
                if ($error->isDisplayed()) {
                    throw new \UserInputError($error->getText());
                }
            } // Is it always user error?
        }

        $errors = $this->driver->findElements(\WebDriverBy::className("validation-message"));
        $errList = [];

        foreach ($errors as $error) {
            if ($error->isDisplayed()) {
                $errList[] = $error->getText();
            }
        }

        if (!empty($errList)) {
            throw new \UserInputError("Invalid values: " . implode("\n", $errList));
        }
    }

    protected function registerInternal()
    {
        $this->http->GetURL($this->registrUrl);

        $this->stepYourInfo();
        $this->checkErrors();
        $this->stepYourAddr();
        $this->checkErrors();
        $this->stepYourPin();

        if (!$elem = $this->waitForElement(\WebDriverBy::id("confirm-button"), $this->timeout)) {
            $this->checkErrors();

            throw new \EngineError('error load Step Confirm');
        }
        $elem->click();

        if ($cardNum = $this->waitForElement(\WebDriverBy::xpath("//div[@id='sample-card-front-5']"))) {
//        if ($cardNum = $this->waitForElement(\WebDriverBy::xpath("(//div[@id='sample-card-front-5']/text())[1]"))){
            $this->ErrorMessage = 'Your Card is ' . $cardNum->getText();
            $this->log($this->ErrorMessage);

            return true;
        }

        return false;
    }

    protected function stepYourInfo()
    {
        // fields
        if (!$elem = $this->waitForElement(\WebDriverBy::xpath("(//section[@id='enroll-step0']//li)[1]/button"), $this->timeout, true)) {
            throw new \EngineError('error load Step 0');
        }

        $elem->click();

        if (!$this->waitForElement(\WebDriverBy::id('firstName'), $this->timeout, true)) {
            throw new \EngineError('error load stepYourInfo');
        }

        $stepFields = [
            'FirstName'  => 'firstName',
            'LastName'   => 'lastName',
            'Email'      => 'emailAddress',
            'BirthMonth' => 'month',
            'BirthDay'   => 'day',
            'BirthYear'  => 'year',
        ];

        $this->fields['BirthMonth'] = ($this->fields['BirthMonth'] + 0) < 10 ? '0' . $this->fields['BirthMonth'] : $this->fields['BirthMonth'];

        foreach ($stepFields as $awKey => $provKeys) {
            if (!isset($this->fields[$awKey])) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $this->driver->executeScript("document.getElementById('{$provKey}').value='{$this->fields[$awKey]}';");
            }
        }

        // confirm checkboxes
        $elem = $this->waitForElement(\WebDriverBy::xpath("//label[input[@id='readTerms']]"), $this->timeout, true);
        $elem->click();
//        if($elem = $this->waitForElement(\WebDriverBy::xpath("//div[@class='modal-header']/button[contains(@class,'close-button')]"), $this->timeout))
//            $elem->click();
//        $elem = $this->waitForElement(\WebDriverBy::xpath("//label[input[@id='readTerms']]"), $this->timeout, true);
//        $elem->click();

        $this->nextStep(1);

//        $this->nextStep(1);
    }

    protected function stepYourAddr()
    {
        if (!$elem = $this->waitForElement(\WebDriverBy::xpath("//input[@id='streetAddress1']"), $this->timeout, true)) {
            throw new \EngineError('error load stepYourPref');
        }

        $stepFields = [
            'AddressLine1'    => 'streetAddress1',
            'City'            => 'city',
            'StateOrProvince' => 'province',
            'PostalCode'      => 'postalCode',
        ];

        $this->fields['StateOrProvince'] = self::$states[$this->fields['StateOrProvince']];
        $this->fields['City'] = strtoupper($this->fields['City']);

        foreach ($stepFields as $awKey => $provKeys) {
            if (!isset($this->fields[$awKey])) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $elem = $this->waitForElement(\WebDriverBy::id($provKey), $this->timeout, true);
                sleep(1);
                $elem->click(2);
                $elem->sendKeys($this->fields[$awKey]);
//                $this->driver->executeScript("document.getElementById('{$provKey}').value='{$this->fields[$awKey]}';");
            }
        }

        $this->nextStep(2);

        if ($elem = $this->waitForElement(\WebDriverBy::xpath("//div[@id='address-modal']//button[@type='button' and contains(@class, 'btn-primary')]"), $this->timeout, true)) {
            $elem->click();
        }
    }

    protected function stepYourPin()
    {
        // gender
        $radioId = $this->fields['Gender'] === 'M' ? 'genderM' : 'genderF';

        if (!$elem = $this->waitForElement(\WebDriverBy::id($radioId), $this->timeout, true)) {
            throw new \EngineError('error load stepYourPin');
        }

        $elem->click();

        // prefered language
        if ($this->fields['PreferredLanguage'] === 'F') {
            $elem = $this->waitForElement(\WebDriverBy::id('languageCodeF'));
            $elem->click();
        }

        $this->fields['PhoneNum'] = $this->fields['PhoneAreaCode'] . $this->fields['PhoneLocalNumber'];
//        $phoneRow = strtolower(self::$phoneTypes[$this->fields['PhoneType']]).'PhoneNumber';
//        $elem = $this->waitForElement(\WebDriverBy::xpath("//select[@name='phoneType']"), $this->timeout, true);
//        $elem->click();
//        sleep(1);
//        $elem->sendKeys($phoneRow);

        $values = [
            'mobilePhoneNumber' => $this->fields['PhoneNum'],
            //            $phoneRow => $this->fields['PhoneNum'],
            'pin'                => $this->fields['Password'],
            'confirmPin'         => $this->fields['Password'],
            'reminderQuestionId' => $this->fields['SecurityQuestionType1'] . '',
            'answer'             => $this->fields['SecurityQuestionAnswer1'],
        ];

        foreach ($values as $key => $value) {
            $elem = $this->waitForElement(\WebDriverBy::id($key), $this->timeout, true);

            if (is_object($elem)) {
                $elem->click();
                sleep(1);
                $elem->sendKeys($value);
            } else {
                $this->driver->executeScript("document.getElementById('{$key}').value='{$value}';");
            }
        }

        $this->driver->executeScript("document.getElementById('reminderQuestionId').value='{$this->fields['SecurityQuestionType1']}';");
        $elem = $this->waitForElement(\WebDriverBy::id('reminderQuestionId'), $this->timeout, true);
        $elem->click();
        sleep(1);
        $elem->sendKeys($this->fields['SecurityQuestionType1']);
        $elem->click();

        //-----------CAPTCHA
        $iframe = $this->waitForElement(\WebDriverBy::xpath("//div[@id='googleRecaptcha']//iframe"), $this->timeout, false);

        if (!$iframe) {
//            $this->waitForElement(\WebDriverBy::id('summaryEnrollmentSubmitButton'), $this->timeout)->click();
//            $iframe = $this->waitForElement(\WebDriverBy::xpath("//div[@class = 'g-recaptcha']//iframe"), $this->timeout, false);
            throw new \EngineError('No reCaptcha frame');
        }

        $result = false;
        //		for($retry=0;$retry<3;$retry++ )
        if ($iframe) {
            $this->driver->switchTo()->frame($iframe);
            //			$recaptchaAnchor = $this->waitForElement(\WebDriverBy::className("rc-anchor"), 20);
            $recaptchaAnchor = $this->waitForElement(\WebDriverBy::id("recaptcha-anchor"), 10);

            if (!$recaptchaAnchor) {
                $this->http->Log('Failed to find reCaptcha "I am not a robot" button');

                throw new \CheckRetryNeededException(3, 7);
            }
            $recaptchaAnchor->click();

            $this->http->Log("wait captcha iframe");
            $this->driver->switchTo()->defaultContent();
            $iframe2 = $this->waitForElement(\WebDriverBy::xpath("//iframe[@title = 'recaptcha challenge']"), 10, true);
            $this->saveResponse();

            if ($iframe2) {
                $status = '';

                if (!$status) {
                    $this->http->Log('Failed to pass captcha');

                    throw new \CheckRetryNeededException(3, 2, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }
        }// if ($iframe)
        else {
            $this->http->Log('Could not find iFrame with captcha, trying to do normal login', LOG_LEVEL_ERROR);

            throw new \CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
        }
        //-----------END CAPTCHA

        $this->nextStep(3);
    }

    protected function nextStep($currentStep)
    {
        $btn = $this->waitForElement(\WebDriverBy::xpath("//section[@id='enroll-step{$currentStep}']//button[@type='submit']"), $this->timeout, true);
        $btn->click();
    }

    protected function logPageSource($logLevel = null)
    {
        $this->log($this->driver->executeScript('return document.documentElement.innerHTML'), $logLevel);
    }

    private function log($msg, $loglevel = null)
    {
        $this->http->Log($msg, $loglevel);
    }
}
