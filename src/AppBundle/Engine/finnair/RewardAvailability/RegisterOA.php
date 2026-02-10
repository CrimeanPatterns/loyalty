<?php

namespace AwardWallet\Engine\finnair\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class RegisterOA extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        //$this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);

        //$randVal = random_int(0, 5);
        //$randVal = 1;
        $randVal = random_int(0, 1);

        switch ($randVal) {
            case 5:
                $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);
                $request = FingerprintRequest::chrome();

                break;

            case 0:
                $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
                $request = FingerprintRequest::chrome();

                break;

            case 1:
                $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
                $request = FingerprintRequest::chrome();

                break;

            case 2:
                $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
                $request = FingerprintRequest::firefox();

                break;

            case 3:
                $this->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
                $request = FingerprintRequest::firefox();

                break;

            case 4:
                $this->useFirefoxPlaywright();
                $request = FingerprintRequest::firefox();

                break;
        }

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $chosenResolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($chosenResolution);

        $this->setProxyNetNut();
        $this->http->saveScreenshots = true;

        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN - 5;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';

        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($fingerprint)) {
            $this->http->setUserAgent($fingerprint->getUseragent());
            $this->seleniumOptions->userAgent = $fingerprint->getUseragent();
            $this->seleniumOptions->fingerprintOptions = $fingerprint->getFingerprint();
        } else {
            $this->http->setRandomUserAgent(null, false, true, false, true, false);
        }
        $this->KeepState = false;
    }

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug(var_export($fields, true), ['pre' => true]);

        $this->checkFields($fields);

        $this->http->GetURL('https://auth.finnair.com/content/en/join/finnair-plus');

        $internal_err = $this->waitForElement(\WebDriverBy::xpath('//*[contains(text(),"request could not be processed")]'), 5);

        if ($internal_err) {
            throw new \ProviderError($internal_err->getText());
        }

        $email = $this->waitForElement(\WebDriverBy::xpath('//input[@type="email"]'), 10);
        $pass = $this->waitForElement(\WebDriverBy::xpath('//input[@type="password"]'), 0);

        $firstName = $this->waitForElement(\WebDriverBy::xpath('//input[@type="text"][@formcontrolname="firstName"]'), 0);
        $lastName = $this->waitForElement(\WebDriverBy::xpath('//input[@type="text"][@formcontrolname="lastName"]'), 0);

        if (!$email || !$pass || !$firstName || !$lastName) {
            //$this->saveResponse();
            $this->logger->error('no register form or other format 1');

            return false;
        }

        if ($fields['Gender'] === 'female') {
            $gender = $this->waitForElement(\WebDriverBy::xpath('//span[contains(text(),"Female")]'), 10);
            $fields['PickedGender'] = 'Female';
        } else {
            $gender = $this->waitForElement(\WebDriverBy::xpath('//span[contains(text(),"Male")]'), 10);
            $fields['PickedGender'] = 'Male';
        }

        $month = $this->waitForElement(\WebDriverBy::xpath('//input[@type="number"][@formcontrolname="month"]'), 0);
        $day = $this->waitForElement(\WebDriverBy::xpath('//input[@type="number"][@formcontrolname="day"]'), 0);
        $year = $this->waitForElement(\WebDriverBy::xpath('//input[@type="number"][@formcontrolname="year"]'), 0);

        $mover = new \MouseMover($this->driver);
        $mover->logger = $this->logger;

        $email->click();
        $mover->sendKeys($email, $fields['Email'], 5);
        /*
                $email_invalid = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="email"]/ancestor::div[contains(@class,"form__group invalid" )]//div[@id="input-invalid"]/p'), 5);
                if ($email_invalid) {
                    throw new \UserInputError($email_invalid->getText());
                }
                $this->logger->debug("-email ok-");
        */
        $this->logger->debug("-email entered-");
        $this->saveResponse();
        //$email->sendKeys($fields['Email']);

        $pass->click();
        $mover->sendKeys($pass, $fields['Password'], 5);
        $this->saveResponse();
        //$pass->sendKeys($fields['Password']);

        $firstName->click();
        $mover->sendKeys($firstName, $fields['FirstName'], 5);
        //$firstName->sendKeys($fields['FirstName']);

        $lastName->click();
        $mover->sendKeys($lastName, $fields['LastName'], 5);
        //$lastName->sendKeys($fields['LastName']);

        $gender->click();

        $fields['BirthDay'] = \DateTime::createFromFormat("m/d/Y", $fields["BirthdayDate"])->format("j");
        $fields['BirthMonth'] = \DateTime::createFromFormat("m/d/Y", $fields["BirthdayDate"])->format("n");
        $fields['BirthYear'] = \DateTime::createFromFormat("m/d/Y", $fields["BirthdayDate"])->format("Y");

        $month->click();
        $mover->sendKeys($month, $fields['BirthMonth'], 5);
        //$month->sendKeys($fields['BirthMonth']);

        $day->click();
        $mover->sendKeys($day, $fields['BirthDay'], 5);
        //$day->sendKeys($fields['BirthDay']);

        $year->click();
        $mover->sendKeys($year, $fields['BirthYear'], 5);
        //$year->sendKeys($fields['BirthYear']);
        $this->saveResponse();

        $this->driver->executeScript('
            function doEvent( obj, event ) {
                var event = new Event( event, {target: obj, bubbles: true} );
                    return obj ? obj.dispatchEvent(event) : false;
            };
                      
            let countryCode = document.querySelector(\'select[formcontrolname="countryCode"]\');
            countryCode.value ="' . $fields['Country'] . '";
            
            doEvent(document.querySelector(\'select[formcontrolname="countryCode"]\'), "change");
            
            //return countryCode.value;
        ');

        $phone = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="phone"]'), 0);
        $mover->sendKeys($phone, $fields['PhoneNumber'], 5);
        $this->saveResponse();

        /*
                $phone_invalid = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="phone"]/ancestor::div[contains(@class,"form__group invalid" )]//div[@id="input-invalid"]/p'), 10);
                if ($phone_invalid) {
                    throw new \UserInputError($phone_invalid->getText());
                }
                $this->logger->debug("-phone ok-");
        */
        $this->logger->debug("-phone entered-");

        $savedFields = $this->driver->executeScript('
            let name = document.querySelector(\'input[formcontrolname="firstName"]\').value;
            let lastName =document.querySelector(\'input[formcontrolname="lastName"]\').value;
            let bDay = document.querySelector(\'input[formcontrolname="day"]\').value;
            let bMonth = document.querySelector(\'input[formcontrolname="month"]\').value;
            let bYear = document.querySelector(\'input[formcontrolname="year"]\').value; 
            let el = document.querySelector(\'input[formcontrolname="gender"]:checked\');
            let gender = el.parentNode.childNodes[2].innerText;   
            let phone = document.querySelector(\'input[formcontrolname="phone"]\').value;
            let email = document.querySelector(\'input[formcontrolname="email"]\').value;
            let password = document.querySelector(\'input[formcontrolname="password"]\').value;
            let countryCode = document.querySelector(\'select[formcontrolname="countryCode"]\').value;
            
            
            let user = {
                name: name,
                lastName: lastName,
                bDay: bDay,
                bMonth: bMonth,
                bYear: bYear,
                gender: gender,
                phone: phone,
                email: email,
                password: password,
                countryCode: countryCode                          
            }
            
            return JSON.stringify(user);            
        ');

        $this->driver->executeScript("document.querySelector('button.button').scrollIntoView({block: \"end\"});");
        $this->driver->executeScript('
            document.querySelector(\'input[type="checkbox"]\').click();
        ');

        $this->saveResponse();

        $inputErrors = $this->http->FindNodes("//form//p[@class='ng-star-inserted']");

        if (!empty($inputErrors)) {
            throw new \UserInputError(implode('; ', $inputErrors));
        }

        $nextBtn = $this->waitForElement(\WebDriverBy::xpath('//button[@type="submit" and not(@disabled)]'), 10);
        $this->saveResponse();

        if (!$nextBtn) {
            throw new \EngineError('no Continue button placed on resource');
        }

        $this->logger->debug("-Ready to click Continue btn -");
        $nextBtn->click();

        $confirm = $this->waitForElement(\WebDriverBy::xpath('//span[contains(text(),"Save and continue")]/parent::button[@type="submit"]'), 10);
        $this->saveResponse();

        if (!$confirm) {
            throw new \EngineError('-Save and Continue-  button not found');
        }
        $this->logger->debug("Save and Continue btn found!");

        $confirm->click();
        $this->saveResponse();

        $result_first = $this->waitForElement(\WebDriverBy::xpath('//*[contains(text(),"something went wrong")]
                                    | //*[contains(text(),"membership number")]'), 30);

        $status_msg = $result_first->getText();

        $membershipNumber = $this->http->FindPreg('/^.*?(\d+)$/', false, $status_msg);
        $sorry_msg = $this->http->FindPreg('/^.*?(something went wrong)/', false, $status_msg);

        if (!$membershipNumber) {
            if ($sorry_msg) {
                $this->logger->debug("Sorry message found 1");
                $confirm = $this->waitForElement(\WebDriverBy::xpath('//span[contains(text(),"Save and continue")]/parent::button[@type="submit"]'), 10);
                $this->saveResponse();
                $confirm->click();          // вторая попытка получить member ID
                $this->saveResponse();

                $result_second = $this->waitForElement(\WebDriverBy::xpath('//*[contains(text(),"something went wrong")]
                                    | //*[contains(text(),"membership number")]'), 30);
                $this->logger->debug("---Trace 1--- ");
                $this->saveResponse();
                $status_msg = $result_second->getText();

                $membershipNumber = $this->http->FindPreg('/^.*?(\d+)$/', false, $status_msg);

                if (!$membershipNumber) {
                    //throw new \EngineError('Membership is not found./ Last step');
                    throw new \ProviderError($status_msg);
                }
            } else {
                throw new \EngineError('Neither Membership nor Sorry msg is found');
            }
        }
        $this->saveResponse();

        // здесь оказыаемся когда $membershipNumber существует

        if ($membershipNumber) {  //$welcome_msg
            $this->logger->debug("--saveRegisterFields-");

            $savedFields = json_decode($savedFields, true);

            $fullBirthdayDate = $savedFields['bDay'] . "/" . $savedFields['bMonth'] . "/" . $savedFields['bYear'];

            $this->ErrorMessage = json_encode([
                "status"          => "success",
                "message"         => "Membership number: {$membershipNumber}",
                "login"           => $membershipNumber,
                "login2"          => $fields['LastName'],
                "login3"          => "",
                "password"        => $fields['Password'],
                "email"           => $fields['Email'],
                "registerInfo"    => [
                    [
                        "key"      => "First Name",
                        "value"    => $savedFields['name'],
                    ],
                    [
                        "key"      => "Last Name",
                        "value"    => $savedFields['lastName'],
                    ],
                    [
                        "key"      => "Gender",
                        "value"    => $savedFields['gender'],
                    ],
                    [
                        "key"      => "PhoneNumber",
                        "value"    => $savedFields['phone'],
                    ],
                    [
                        "key"      => "BirthdayDate",
                        "value"    => $fullBirthdayDate,
                    ],
                    [
                        "key"      => "country",
                        "value"    => $savedFields['countryCode'],
                    ],
                ],

                "active"    => true,
            ], JSON_PRETTY_PRINT);

            return true;
        }

        $this->ErrorMessage = 'Something is wrong';

        return false;
    }

    public function getRegisterFields()
    {
        return [
            "Email"     => [
                "Type"     => "string",
                "Caption"  => "Email address",
                "Required" => true,
            ],
            "Password"  => [
                "Type"     => "string",
                "Caption"  => "Password",
                "Note"     => "Between 8 and 32 characters. At least one uppercase. At least one lowercase. At least one number. At least one special character. Special characters like @ ? # $ % ( ) _ = * : ; ' . / + < > & ¿ , [. ! % & ¡",
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Caption"  => "First Name",
                "Required" => true,
            ],
            "LastName"  => [
                "Type"     => "string",
                "Caption"  => "LastName",
                "Required" => true,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => ['male' => 'Male', 'female' => 'Femail'],
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => ['US' => 'United States Of America (+1)', 'UZ' => 'Uzbekistan (+998)'],
            ],
            'PhoneNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone number length should be 5-11 symbols long. Only digits are allowed.',
                'Required' => true,
            ],
            'BirthdayDate' => [
                'Type'     => 'date',
                'Caption'  => 'Your date of birth, older than 18 (MM/DD/YYYY)',
                'Required' => true,
            ],
        ];
    }

    protected function checkFields(&$fields)
    {
        if (!filter_var($fields['Email'], FILTER_VALIDATE_EMAIL)) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['FirstName'])) {
            throw new \UserInputError('FirstName contains an incorrect symbol');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['LastName'])) {
            throw new \UserInputError('LastName contains an incorrect symbol');
        }

        if ((strlen($fields['Password']) < 8 || strlen($fields['Password']) > 32) || !preg_match("/[A-Z]/", $fields['Password'])
            || !preg_match("/[a-z]/", $fields['Password']) || !preg_match("/[0-9]/", $fields['Password']) || strpos($fields['Password'], ' ') !== false
            || !preg_match("/[*?!%&¡<>\\ºª|\/\·@#$.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['Password'])
        ) {
            throw new \UserInputError("Between 8 and 32 characters. At least one uppercase. At least one lowercase. At least one number. At least one special character. Special characters like @ ? # $ % ( ) _ = * : ; ' . / + < > & ¿ , [. ! % & ¡");
        }

        if (!preg_match("/^[0-9]{5,11}$/", $fields['PhoneNumber'])
        ) {
            // preg_match("/\D/", $fields['PhoneNumber']
            throw new \UserInputError("Phone number length should be 5-11 symbols long. Only digits are allowed. Given incorrect symbol or length is wrong.");
        }
    }
}
