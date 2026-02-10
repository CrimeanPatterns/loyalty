<?php

namespace AwardWallet\Engine\british\RewardAvailability;

use AwardWallet\Engine\british\RewardAvailability\Helpers\FormFieldsInformation;
use AwardWallet\Engine\Settings;

class Register extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \AwardWallet\Engine\ProxyList;

    private $timeout = 30;
    private $fields;
    private $registerInfo = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
        $this->disableImages();
        $this->http->saveScreenshots = true;

        $this->setProxyBrightData(true, Settings::RA_ZONE_STATIC);

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
        $this->useCache();
    }

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $this->fields = $fields;
        $this->logger->debug(var_export($fields, true), ['pre' => true]);

        $this->modifyFields($this->fields);
        $this->checkFields($this->fields);

        $this->register();

        return $this->checkLogIn();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->Question) {
            $this->logger->error("something went wrong");

            return false;
        }

        if (isset($this->Answers[$this->Question])) {
            $this->logger->info('Got verification link.');
            $verificationLink = $this->Answers[$this->Question];
            unset($this->Answers[$this->Question]);

            $this->getAction($verificationLink);

            $success = $this->waitForElement(\WebDriverBy::xpath("//p[@class='execlub-detail-list']"));

            if ($success) {
                $membershipNumber = str_replace(['.', 'Your Executive Club membership number is '], '',
                    $success->getText());

                $this->ErrorMessage = json_encode([
                    "status" => "success",
                    "message" => "Registration is successful!",
                    "login" => $membershipNumber,
                    "login2" => "US",
                    "active" => true,
                ], JSON_PRETTY_PRINT);

                return true;
            }
            $this->logger->info('Something wrong with activation.');
        } else {
            $this->logger->info('Answer is empty.');
        }

        $this->ErrorMessage = json_encode([
            "status" => "success",
            "message" => "Registration is successful! Activate account on email and login after.",
            "login" => $this->State['login'] ?? null,
            "login2" => "US",
            "active" => false,
        ], JSON_PRETTY_PRINT);

        return true;
    }

    public function getRegisterFields()
    {
        return FormFieldsInformation::getRegisterFields();
    }

    protected function checkFields(&$fields)
    {
        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/",
                $fields['FirstName']) || strpos($fields['FirstName'], ' ') !== false
            || strlen($fields['FirstName']) < 2 || strlen($fields['FirstName']) > 25) {
            throw new \UserInputError('FirstName contains an incorrect symbol');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/",
                $fields['LastName']) || strpos($fields['LastName'], ' ') !== false
            || strlen($fields['LastName']) < 2 || strlen($fields['LastName']) > 26) {
            throw new \UserInputError('LastName contains an incorrect symbol');
        }

        if (!preg_match("/[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/", $fields['Email'])) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (strlen($fields['Password']) < 8 || strlen($fields['Password']) > 16 || !preg_match("/[A-Z]/",
                $fields['Password'])
            || !preg_match("/[a-z]/", $fields['Password']) || !preg_match("/[0-9]/",
                $fields['Password']) || preg_match("/[*¡?¿<>\\ºª|\/\·@%&.,;=?¿())_+{}\-\[\]\"\^€£']/",
                $fields['Password'])
            || strpos($fields['Password'], ' ') !== false
        ) {
            throw new \UserInputError("Your password must be 8-16 characters and include at least 1 lowercase letter, 1 uppercase letter and 1 number");
        }

        if (strlen($fields['Address']) > 29 || preg_match("/[*¡!?¿<>ºª|\·@#$%&;=?¿())_+{}\-\[\]\"\^€\$£']/",
                $fields['Address'])) {
            throw new \UserInputError('Address (Must 0-29 characters or numbers long (include . , / \ and space) )');
        }

        if (strlen($fields['City']) > 29 || preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/",
                $fields['City'])) {
            throw new \UserInputError('City (0-29 characters)');
        }

        if (strlen($fields['PhoneNumber']) > 10 || preg_match("/[a-zA-Z*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/",
                $fields['PhoneNumber'])) {
            throw new \UserInputError('Phone Number contains an incorrect symbol');
        }

        if (!preg_match("/^(0[1-9]|1[012])\/(0[1-9]|[12][0-9]|3[01])\/(19[3-9][0-9]|20[01][0-9])$/",
            $fields['BirthdayDate'])) {
            throw new \UserInputError('BirthdayDate contains an incorrect number');
        }
    }

    protected function register()
    {
        $this->getAction('https://www.britishairways.com/travel/execenrol/public/en_us');

        $acceptCookies = $this->waitForElement(\WebDriverBy::xpath("//button[@id='ensCloseBanner']"), 5);

        if ($acceptCookies) {
            $acceptCookies->click();
        }

        //Register form
        $btnJoin = $this->waitForElement(\WebDriverBy::xpath("//input[@value='Join now']"), 0);
        $this->checkFieldExist(['btnJoin' => $btnJoin]);

        $this->searchAndFillRegistrationFields();
        sleep(5);

        $this->ErrorMessage = "form submitted";
        $btnJoin->click();
    }

    protected function checkLogIn()
    {
        $this->saveResponse();

        $this->waitForElement(\WebDriverBy::xpath("
            //p[contains(text(), 'Your membership number is:')]//strong
            | //div[@id='blsErrors']//li[
                contains(text(),'email address used must be unique') 
                or contains(text(),'nable to process request please retry later')
                or contains(text(),'Please check that you have entered a valid telephone number')
                ]
            "), 25, false);
        $error = $this->waitForElement(\WebDriverBy::xpath("
            //div[@id='blsErrors']//li[
                contains(text(),'email address used must be unique') 
                or contains(text(),'nable to process request please retry later')
                or contains(text(),'Please check that you have entered a valid telephone number')
                ]"), 0);
        $this->saveResponse();

        if ($error) {
            throw new \UserInputError($error->getText());
        }

        $success = $this->waitForElement(\WebDriverBy::xpath("//p[contains(text(), 'Your membership number is:')]//strong"),
            0);

        if ($success) {
            $this->State['login'] = str_replace(' ', '', trim($success->getText()));
            $this->ErrorMessage = json_encode([
                "status" => "success",
                "message" => "Registration is successful! Activate account on email and login after.",
                "login" => $this->State['login'],
                "login2" => "US",
                "active" => false,
                "registerInfo" => $this->registerInfo,
            ], JSON_PRETTY_PRINT);

            if ($question = $this->waitForElement(
                \WebDriverBy::xpath("//p[contains(text(),'To activate your account, you must open the email that has been sent to')]",
                    10)
            )) {
                $this->State['email'] = $this->fields['Email'];

                $question = substr($question->getText(), 0, 200);
                $this->holdSession();
                $this->AskQuestion($question, null, 'Question');

                return false;
            }

            $this->sendNotification("Activate account on email {$this->fields['Email']} and login after.");

            return true;
        }

        // TODO debug
        $btnJoin = $this->waitForElement(\WebDriverBy::xpath("//input[@value='Join now']"), 0);
        $this->checkFieldExist(['btnJoin' => $btnJoin]);

        try {
            $this->logger->debug('scroll Top throw script');
            $this->driver->executeScript("window.scrollTo(0, 0);");
        } catch (\UnexpectedJavascriptException $e) {
            $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());
        }
        $this->saveResponse();

        return false;
    }

    protected function searchAndFillRegistrationFields()
    {
        try {
            $this->driver->executeScript("document.querySelector('#title').value = '{$this->fields['Title']}'");

            $this->registerInfo = array_merge($this->registerInfo, [
                [
                    'key' => 'Title',
                    'value' => $this->driver->executeScript("return document.querySelector('#title').value;")
                ],
            ]);

            $firstName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='firstname']"), 0);
            $lastName = $this->waitForElement(\WebDriverBy::xpath("//input[@id='ecenrol-lastname']"), 0);
            $email = $this->waitForElement(\WebDriverBy::xpath("//input[@id='homeemail']"), 0);
            $emailConfirm = $this->waitForElement(\WebDriverBy::xpath("//input[@id='homeconfirm_email']"), 0);

            $this->checkFieldExist([
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'emailConfirm' => $emailConfirm,
            ]);

            $firstName->sendKeys($this->fields['FirstName']);
            $lastName->sendKeys($this->fields['LastName']);
            $email->sendKeys($this->fields['Email']);
            $emailConfirm->sendKeys($this->fields['Email']);

            $this->saveResponse();
            $this->registerInfo = array_merge($this->registerInfo, [
                [
                    'key' => 'FirstName',
                    'value' => $this->driver->executeScript("return document.querySelector('#firstname').value;")
                ],
                [
                    'key' => 'LastName',
                    'value' => $this->driver->executeScript("return document.querySelector('#ecenrol-lastname').value;")
                ],
            ]);

            $password = $this->waitForElement(\WebDriverBy::xpath("//input[@id='passwordEnrol']"), 0);
            $passwordConfirm = $this->waitForElement(\WebDriverBy::xpath("//input[@id='confirmpassword']"), 0);
            $this->checkFieldExist([
                'password' => $password,
                'passwordConfirm' => $passwordConfirm,
            ]);
            $password->sendKeys($this->fields['Password']);
            $passwordConfirm->sendKeys($this->fields['Password']);

            $this->saveResponse();

            $address = $this->waitForElement(\WebDriverBy::xpath("//input[@id='homeaddress1']"), 0);
            $city = $this->waitForElement(\WebDriverBy::xpath("//input[@id='homecity']"), 0);
            $state = $this->waitForElement(\WebDriverBy::xpath("//input[@id='homestate']"), 0);
            $zip = $this->waitForElement(\WebDriverBy::xpath("//input[@id='homepostalcode']"), 0);
            $phone = $this->waitForElement(\WebDriverBy::xpath("//input[@id='mobilephone']"), 0);
            $this->checkFieldExist([
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => $zip,
                'phone' => $phone,
            ]);
            $address->sendKeys($this->fields['Address']);
            $city->sendKeys($this->fields['City']);
            $state->sendKeys($this->fields['State']);
            $zip->sendKeys($this->fields['ZipCode']);
            $phone->sendKeys($this->fields['PhoneNumber']);
            $this->driver->executeScript("document.querySelector('#homecountry').value = 'US'");
            $this->driver->executeScript("document.querySelector('#mobilecountrycode').value = 'US';");

            $this->saveResponse();
            $this->registerInfo = array_merge($this->registerInfo, [
                [
                    'key' => 'Address',
                    'value' => $this->driver->executeScript("return document.querySelector('#homeaddress1').value;")
                ],
                [
                    'key' => 'City',
                    'value' => $this->driver->executeScript("return document.querySelector('#homecity').value;")
                ],
                [
                    'key' => 'State',
                    'value' => $this->driver->executeScript("return document.querySelector('#homestate').value;")
                ],
                [
                    'key' => 'ZipCode',
                    'value' => $this->driver->executeScript("return document.querySelector('#homepostalcode').value;")
                ],
                [
                    'key' => 'PhoneNumber',
                    'value' => $this->driver->executeScript("return document.querySelector('#mobilephone').value;")
                ],
                [
                    'key' => 'Country',
                    'value' => $this->driver->executeScript("return document.querySelector('#homecountry').value;")
                ],
                [
                    'key' => 'MobileCountryCode',
                    'value' => $this->driver->executeScript("return document.querySelector('#mobilecountrycode').value;")
                ],
            ]);

            $genderValue = ($this->fields['Gender'] == 'male') ? 'genderMale' : 'genderFemale';
            $gender = $this->waitForElement(\WebDriverBy::xpath("//label[@for='{$genderValue}']/span"), 0);
            $this->checkFieldExist(['gender' => $gender]);
            $this->driver->executeScript("document.querySelector('#birthday').value = '{$this->fields['BirthDay']}'");
            $this->driver->executeScript("document.querySelector('#birthmonth').value = '{$this->fields['BirthMonth']}'");
            $this->driver->executeScript("document.querySelector('#birthyear').value = '{$this->fields['BirthYear']}'");
            $gender->click();
            sleep(3);
            $gender->click();

            $this->saveResponse();
            $this->registerInfo = array_merge($this->registerInfo, [
                ['key' => 'Gender', 'value' => $this->fields['Gender']],
                [
                    'key' => 'BirthdDate',
                    'value' =>
                        implode('-', [
                            $this->driver->executeScript("return document.querySelector('#birthyear').value;"),
                            $this->driver->executeScript("return document.querySelector('#birthmonth').value;"),
                            $this->driver->executeScript("return document.querySelector('#birthday').value;"),
                        ]),
                ],
            ]);

            $checkbox1 = $this->waitForElement(\WebDriverBy::xpath("//label[@for='receive_infoExec']/span"), 0);
            $checkbox2 = $this->waitForElement(\WebDriverBy::xpath("//label[@for='receive_partner_info']/span"), 0);
            $checkbox3 = $this->waitForElement(\WebDriverBy::xpath("//label[@for='TermsAndConditions']/span/span[1]"),
                0);
            $this->checkFieldExist([
                'checkbox1' => $checkbox1,
                'checkbox2' => $checkbox2,
                'checkbox3' => $checkbox3,
            ]);
            sleep(1);
            $checkbox1->click();
            sleep(1);
            $checkbox2->click();
            sleep(1);
            $checkbox3->click();

            $this->saveResponse();
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Error: {$e->getMessage()}");

            throw new \EngineError("Error: {$e->getMessage()}");
        }
    }

    protected function modifyFields(array &$fields)
    {
        foreach ($fields as $key => $value) {
            if ($key == 'BirthdayDate') {
                $fields['BirthDay'] = \DateTime::createFromFormat("m/d/Y", $this->fields["BirthdayDate"])->format("d");
                $fields['BirthMonth'] = \DateTime::createFromFormat("m/d/Y",
                    $this->fields["BirthdayDate"])->format("m");
                $fields['BirthYear'] = \DateTime::createFromFormat("m/d/Y", $this->fields["BirthdayDate"])->format("Y");
            }
        }
    }

    protected function getAction($url)
    {
        try {
            $this->http->GetURL($url);
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("Site timed out, please try again later");

            throw new \UserInputError("Site timed out, please try again later");
        }
    }

    protected function checkFieldExist(array $fields)
    {
        foreach ($fields as $key => $field) {
            if (!$field) {
                $this->logger->error("{$key} field is not exist");

                throw new \EngineError("{$key} field is not exist");
            }
        }
    }
}
