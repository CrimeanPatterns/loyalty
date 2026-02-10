<?php

namespace AwardWallet\Engine\essoextra\Transfer;

class Register extends \TAccountCheckerEssoextra
{
    public static $languages = [
        'en' => 'English',
        'fr' => 'French',
    ];

    public static $titles = [
        'DR'   => 'Dr.',
        'M'    => 'M.',
        'MISS' => 'Miss',
        'MLLE' => 'Mlle',
        'MME'  => 'Mme',
        'MR'   => 'Mr.',
        'MRS'  => 'Mrs.',
        'MS'   => 'Ms',
        'PROF' => 'Prof.',
    ];

    public static $states = [
        'AB' => 'Alberta',
        'BC' => 'British Columbia',
        'MB' => 'Manitoba',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland / Labrador',
        'NT' => 'North-West Territories',
        'NS' => 'Nova Scotia',
        'NU' => 'Nunavut',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Quebec',
        'SK' => 'Saskatchewan',
        'YT' => 'Yukon Territory',
    ];

    protected $languageMap = [
        'en' => 'E',
        'fr' => 'F',
    ];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;
        $fields['PreferredLanguage'] = $this->languageMap[$fields['PreferredLanguage']];
        $this->http->Log('Registration fields:');
        $this->http->Log(var_export($fields, true));
        $secondaryChecker = new EssoextraAccountRegistratorSeleniumTAccountChecker();
        $secondaryChecker->primaryChecker = $this;
        $secondaryChecker->InitBrowser();

        return $secondaryChecker->register($fields);
    }

    // Form is loaded via JS, registration submit throws error without any explanation. So Selenium was choosed
    //	public function register(array $fields) {
    //		$this->checker->ArchiveLogs = true;
    //		$this->http->LogHeaders = true;
//
    //		$this->http->GetURL('https://www.essoextra.com/pages/enroll.aspx');
    //		$this->http->GetURL('https://www.essoextra.com/member/EnrollMember.page');
//
    //		$status = $this->http->ParseForm('memberDetails');
    //		if (!$status) {
    //			$this->http->Log('Failed to parse account registration form', LOG_LEVEL_ERROR);
    //			return false;
    //		}
//
    //		$this->http->FormURL = 'https://www.essoextra.com/member/EnrollMember,memberDetails.sdirect';
//
    //		$this->setInputFieldsValues($fields);
    //		$additionalFields = [
    //			'formids' => 'email,subscribe,Hidden,title,firstName,middleInitial,lastName,mailingAddressOne,mailingAddressTwo,mailingAddressThree,city,province,postalCode,homephone,If,onlineSetup,onlineEmailAddress,onlineEmailAddressConfirm,onlinePassword,onlineConfirmPassword,onlineSecurityQuestionCodeset,onlineSecurityAnswer,campaignId,origin,ppCard,If_1,If_2,If_3,ImageSubmit',
    //			'submitmode' => 'submit',
    //			'onlineSetup' => 'on',
    //			'Hidden' => 'SE',
    //			'If_1' => 'T',
    //			'If_2' => 'T',
    //			'If_3' => 'T',
    //		];
    //		foreach ($additionalFields as $key => $value)
    //			$this->http->SetInputValue($key, $value);
//
    //		$status = $this->http->PostForm('memberDetails');
    //		if (!$status) {
    //			$this->http->Log('Failed to post account registration form', LOG_LEVEL_ERROR);
    //			return false;
    //		}
//
    //		if ($successMessage = $this->http->FindPreg('#Thank\s+You\s+For\s+Joining\s+Esso\s+Extra#i')) {
    //			$this->checker->ErrorMessage = $successMessage;
    //			return true;
    //		}
    //		return false;
    //	}

    public function getRegisterFields()
    {
        return [
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email',
                    'Required' => true,
                ],
            'PreferredLanguage' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Preferred Language',
                    'Required' => true,
                    'Options'  => self::$languages,
                ],
            'Title' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Title',
                    'Required' => true,
                    'Options'  => self::$titles,
                ],
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
                    'Caption'  => 'Address',
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
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password (must be 8-10 characters long with at least one number and is case-sensitive )												 ',
                    'Required' => true,
                ],
            'ReceiveOffersAndPromotions' =>
                [
                    'Type'     => 'boolean',
                    'Caption'  => 'From time to time Imperial Oil sends out special offers and promotions to Esso Extra members that are not available to non-members.',
                    'Required' => true,
                ],
            //			'SecurityQuestionType' =>
            //				array (
            //					'Type' => 'string',
            //					'Caption' => 'Security Question',
            //					'Required' => true,
            //					'Options' => self::$securityQuestions,
            //				),
            //			'SecurityQuestionAnswer' =>
            //				array (
            //					'Type' => 'string',
            //					'Caption' => 'Security Answer (the answer you provide is case-sensitive)',
            //					'Required' => true,
            //				),
        ];
    }

    //	static $securityQuestions = array (
    //		'CITYBIRTH' => 'City of Birth',
    //		'FATHERMID' => 'Father\'s Middle Name',
    //		'FAVMOVIE' => 'Favourite Movie',
    //		'FAVORPET' => 'Favourite Pet\'s Name',
    //		'FIRSTCHLDMID' => 'First Child\'s Middle Name',
    //		'MOMMAID' => 'Mother\'s Maiden Name',
    //		'SPOUSEMID' => 'Spouses\'s Middle Name',
    //	);

    public static function inputFieldsMap()
    {
        return [
            'Email' =>
                [
                    0 => 'email', // "onlineEmailAddress" is filled automatically after filling "email"
                    1 => 'onlineEmailAddressConfirm',
                ],
            'PreferredLanguage' => 'languageRadio',
            'Title'             => 'title',
            'FirstName'         => 'firstName',
            'LastName'          => 'lastName',
            'AddressLine1'      => 'mailingAddressOne',
            'City'              => 'city',
            'StateOrProvince'   => 'province',
            'PostalCode'        => 'postalCode',
            'Password'          =>
                [
                    0 => 'onlinePassword',
                    1 => 'onlineConfirmPassword',
                ],
            'ReceiveOffersAndPromotions' => 'subscribeRadio',
            //			'SecurityQuestionType' => 'onlineSecurityQuestionCodeset',
            //			'SecurityQuestionAnswer' => 'onlineSecurityAnswer',
        ];
    }
}

class EssoextraAccountRegistratorSeleniumTAccountChecker extends \TAccountChecker
{
    use \SeleniumCheckerHelper;

    public $timeout = 30;

    /** @var \TAccountChecker */
    public $primaryChecker;

    protected $fields;

    private $captchaRetriesLimit = 3;

    private static $loginUrl = 'https://www.essoextra.com/pages/enroll.aspx';

    private static $formName = 'memberDetails';

    public function InitBrowser()
    {
        $this->InitSeleniumBrowser();
        $this->primaryChecker->http->brotherBrowser($this->http);
    }

    public function register(array $fields)
    {
        $this->fields = $fields;
        $this->http->driver->start();
        $this->Start();

        try {
            $this->registerInternal();
            $this->http->cleanup();

            return true;
        } catch (\CheckException $e) {
            $this->http->cleanup();

            throw $e;
        } catch (\Exception $e) {
            $this->log($e->getMessage(), LOG_LEVEL_ERROR);
            //			$this->saveResponse();
            $this->http->cleanup();

            return false;
        }
    }

    private function registerInternal()
    {
        $this->driver->get(self::$loginUrl);
        $this->fillTextInputs();
        $this->fillRadiobuttons();
        $this->fillSelects();
        $this->fillCheckboxes();

        for ($retry = 1; $retry <= $this->captchaRetriesLimit; $retry++) {
            try {
                $this->fillCaptcha();
                $this->submit();

                break;
            } catch (\Exception $e) {
                if ($e->getMessage() == 'The text you entered is incorrect, please try again' and $retry < $this->captchaRetriesLimit) {
                    continue;
                } else {
                    throw $e;
                }
            }
        }
    }

    private function submit()
    {
        $registerButton = $this->waitForElement(\WebDriverBy::id('enrollToday'));

        if (!$registerButton) {
            throw new \EngineError('Failed to find "register" button');
        }
        $registerButton->click();

        $successXpath = '//h1[contains(., "Thank You For Joining Esso Extra")]';
        $errorsXpath = '//*[@class="errorinstructions"]';

        if ($this->waitForElement(\WebDriverBy::xpath($successXpath . ' | ' . $errorsXpath), $this->timeout)) {
            if ($elems = $this->driver->findElements(\WebDriverBy::xpath($successXpath))) {
                $successMsg = $elems[0]->getText();
                $this->http->Log($successMsg);
                $this->primaryChecker->ErrorMessage = $successMsg;
            } elseif ($errorElements = $this->driver->findElements(\WebDriverBy::xpath($errorsXpath))) {
                $this->http->Log('Errors found', LOG_LEVEL_ERROR);
                $errors = [];

                foreach ($errorElements as $ee) {
                    if ($ee->getText() == 'The text you entered is incorrect, please try again') {
                        // Captcha recognition error is internal error, other is user input errors
                        throw new \EngineError('Captcha recognition error: ' . $ee->getText());
                    }
                    $errors[] = str_replace("\n", '. ', $ee->getText());
                }
                $error = implode('. ', $errors);

                throw new \UserInputError($error); // Is it always user input error?
            }
        } else {
            throw new \EngineError('Unexpected response');
        }
    }

    private function fillCaptcha()
    {
        $elem = $this->driver->findElement(\WebDriverBy::xpath("//input[@name='captcha_response' and @type='text']"));
        $elem->sendKeys($this->parseCaptcha());
    }

    // TODO: Move to some trait from here and from AirmilescaAccountRegistrator
    private function parseCaptcha()
    {
        if (!$elem = $this->waitForElement(\WebDriverBy::id('challengeImage'), $this->timeout, false)) {
            throw new \EngineError('Failed to get captcha image');
        }

        $captcha = $this->driver->executeScript("
            var captchaDiv = document.createElement('div');
            captchaDiv.id = 'captchaDiv';
            document.body.appendChild(captchaDiv);

            var canvas = document.createElement('CANVAS'),
                ctx = canvas.getContext('2d'),
                img = document.getElementById('challengeImage');

            canvas.height = img.height;
            canvas.width = img.width;
            ctx.drawImage(img, 0, 0);
            dataURL = canvas.toDataURL('image/png');

            return dataURL;
		");

        $this->http->Log("captcha: " . $captcha);
        $marker = "data:image/png;base64,";

        if (strpos($captcha, $marker) !== 0) {
            $this->http->Log("no marker");

            return false;
        }
        $captcha = substr($captcha, strlen($marker));
        $file = tempnam(sys_get_temp_dir(), "captcha") . ".png";
        $this->http->Log("captcha file: " . $file);
        file_put_contents($file, base64_decode($captcha));

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);

        try {
            $captcha = str_replace(' ', '', $recognizer->recognizeFile($file));
        } catch (\CaptchaException $e) {
            $this->http->Log("exception: " . $e->getMessage());
            // Notifications
            if (strstr($e->getMessage(), "ERROR_ZERO_BALANCE")) {
                $this->sendNotification("WARNING! " . $recognizer->domain . " - balance is null");

                throw new \EngineError(self::CAPTCHA_ERROR_MSG);
            }

            if ($e->getMessage() == 'server returned error: ERROR_CAPTCHA_UNSOLVABLE'
                || $e->getMessage() == 'timelimit (60) hit'
                || $e->getMessage() == 'slot not available') {
                $this->http->Log("parseCaptcha", LOG_LEVEL_ERROR);

                throw new \EngineError(self::CAPTCHA_ERROR_MSG);
            }

            return false;
        }
        unlink($file);

        return $captcha;
    }

    private function fillTextInputs()
    {
        $textInputFields = [
            'Email',
            'FirstName',
            'LastName',
            'AddressLine1',
            'City',
            'PostalCode',
            'Password',
            //			'SecurityQuestionAnswer',
        ];

        foreach ($textInputFields as $awKey) {
            if (!isset($this->fields[$awKey]) or $this->fields[$awKey] === '') {
                continue;
            }
            $keys = \AwardWallet\Engine\essoextra\Transfer\Register::inputFieldsMap()[$awKey];

            if (!is_array($keys)) {
                $keys = [$keys];
            }

            foreach ($keys as $key) {
                $xpath = '//form[@name="' . self::$formName . '"]//input[@name="' . $key . '"]';

                if ($elem = $this->waitForElement(\WebDriverBy::xpath($xpath), $this->timeout)) {
                    $elem->sendKeys($this->fields[$awKey]);
                } else {
                    throw new \EngineError("Could not find input field for $awKey value");
                }
            }
            //			if ($awKey == 'Email')
//				sleep(1);
        }
    }

    private function fillRadiobuttons()
    {
        $radiobuttonInputFields = [
            'PreferredLanguage',
            'ReceiveOffersAndPromotions',
        ];

        foreach ($radiobuttonInputFields as $awKey) {
            if (!isset($this->fields[$awKey])) {
                continue;
            }
            $key = \AwardWallet\Engine\essoextra\Transfer\Register::inputFieldsMap()[$awKey];
            $value = $this->fields[$awKey];

            if ($awKey == 'ReceiveOffersAndPromotions') {
                $value = $value ? 'true' : 'false';
            }
            $xpath = '//form[@name="' . self::$formName . '"]//input[@name="' . $key . '" and @value="' . $value . '"]';

            if ($elem = $this->waitForElement(\WebDriverBy::xpath($xpath), $this->timeout)) {
                $elem->click();
            } else {
                throw new \EngineError("Could not find input field for $awKey value");
            }
        }
    }

    private function fillSelects()
    {
        $selectInputFields = [
            'Title',
            'StateOrProvince',
            //			'SecurityQuestionType',
        ];

        foreach ($selectInputFields as $awKey) {
            if (!isset($this->fields[$awKey])) {
                continue;
            }
            $key = \AwardWallet\Engine\essoextra\Transfer\Register::inputFieldsMap()[@$awKey];
            $value = $this->fields[$awKey];
            $xpath = '//form[@name="' . self::$formName . '"]//select[@name="' . $key . '"]';
            $select = new \WebDriverSelect($this->driver->findElement(\WebDriverBy::xpath($xpath)));
            $select->selectByValue($value);
        }
    }

    private function fillCheckboxes()
    {
        $checkboxInputFields = [
            'onlineSetup',
        ];

        foreach ($checkboxInputFields as $key) {
            $value = true;
            $xpath = '//form[@name="' . self::$formName . '"]//input[@name="' . $key . '"]';

            if ($elem = $this->waitForElement(\WebDriverBy::xpath($xpath), $this->timeout)) {
                $alreadyChecked = $elem->getAttribute('checked');

                if ($alreadyChecked and !$value or !$alreadyChecked and $value) {
                    $elem->click();
                }
            } else {
                throw new \EngineError("Could not find input field for creating online account");
            }
        }
    }

    private function log($msg, $loglevel = null)
    {
        $this->http->Log($msg, $loglevel);
    }
}
