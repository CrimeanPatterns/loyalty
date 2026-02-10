<?php

namespace AwardWallet\Engine\finnair\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\finnair\RewardAvailability\Helpers\FormFieldsInformation;
use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    private $fields;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();

        switch (random_int(0, 6)) {
            case 6:
                $this->useChromium(\SeleniumFinderRequest::CHROMIUM_80);
                $request = FingerprintRequest::chrome();

                break;

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

            /*case 2:
                $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
                $request = FingerprintRequest::firefox();

                break;*/

            /*case 3:
                $this->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
                $request = FingerprintRequest::firefox();

                break;*/

            /*case 4:
                $this->useFirefoxPlaywright();
                $request = FingerprintRequest::firefox();

                break;*/

            default:
                $this->useChromePuppeteer(\SeleniumFinderRequest::CHROME_PUPPETEER_103);
                $request = FingerprintRequest::chrome();
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
        $this->disableImages();

        $this->setProxyBrightData();

        $request->browserVersionMin = 100;
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

    public function getRegisterFields()
    {
        return FormFieldsInformation::getRegisterFields();
    }

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug(var_export($fields, true), ['pre' => true]);

        $this->modifyFields($fields);
        $this->checkFields($fields);

        try {
            $this->http->GetURL('https://auth.finnair.com/content/en/join/finnair-plus');

            $firstName = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="firstName"]'), 20);
            $familyName = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="lastName"]'), 0);
            $dayOfBirth = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="day"]'), 0);
            $monthOOfBirth = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="month"]'), 0);
            $yearOfBirth = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="year"]'), 0);
            $genderBlock = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="gender"]/../..'), 0);
            $phoneCountry = $this->waitForElement(\WebDriverBy::xpath('//select[@formcontrolname="phoneCode"]'), 0);
            $phone = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="phone"]'), 0);
            $country = $this->waitForElement(\WebDriverBy::xpath('//select[@formcontrolname="countryCode"]'), 0);
            $email = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="email"]'), 0);
            $password = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="password"]'), 0);

            if (!$firstName || !$familyName || !$dayOfBirth || !$monthOOfBirth || !$yearOfBirth || !$genderBlock || !$phoneCountry || !$phone || !$country || !$email || !$password) {
                $this->saveResponse();

                throw new \EngineError('no register form or other format');
            }

            $firstName->sendKeys($fields['FirstName']);
            $familyName->sendKeys($fields['LastName']);
            $dayOfBirth->sendKeys($fields['BirthDay']);
            $monthOOfBirth->sendKeys($fields['BirthMonth']);
            $yearOfBirth->sendKeys($fields['BirthYear']);

            if ($fields['Gender'] === 'male') {
                $this->driver->executeScript("document.querySelectorAll('input[formcontrolname=\"gender\"]')[0].click();");
            } else {
                $this->driver->executeScript("document.querySelectorAll('input[formcontrolname=\"gender\"]')[1].click();");
            }

            $phoneCountry->sendKeys('United States of America (+1)');

            $country->click();

            $countryOpt = $this->waitForElement(\WebDriverBy::xpath('//select[@formcontrolname="countryCode"]/option[@value="US"]'), 15);

            if (!$countryOpt) {
                $country->sendKeys('United States of America');
            } else {
                $countryOpt->click();
            }

            $phone->sendKeys($fields['PhoneNumber']);
            $this->driver->executeScript("document.querySelector('select[formcontrolname=\"countryCode\"]').value = 'US';");
            $email->sendKeys($fields['Email']);
            $password->sendKeys($fields['Password']);
            $this->driver->executeScript("document.querySelector('input[formcontrolname=\"agreements\"]').click();");
            $this->driver->executeScript("document.querySelector('button[type=\"submit\"]').disabled = false;");

            $finalFirstName = $firstName->getAttribute('value');
            $finalLastName = $familyName->getAttribute('value');
            $finalBirthDate = sprintf('%s-%s-%s', $monthOOfBirth->getAttribute('value'), $dayOfBirth->getAttribute('value'), $yearOfBirth->getAttribute('value'));
            $finalPhone = "+1" . $phoneCountry->getAttribute('value');
            $finalEmail = $email->getAttribute('value');
            $finalPass = $password->getAttribute('value');
            $finalCountry = $this->driver->executeScript("document.querySelector('select[formcontrolname=\"countryCode\"]').value;");

            $nextButton = $this->waitForElement(\WebDriverBy::xpath('//button[@type="submit"]'), 10);
            $nextButton->click();
            $this->saveResponse();

            if ($res = $this->http->FindNodes('
            //p[contains(text(),"Phone number is required.")]
                | //p[contains(text(),"Invalid date")]
                | //p[contains(text(),"Invalid format")]
                | //p[contains(text(),"can include maximum of 56 characters")]
                | //p[contains(text(),"The email address you entered is invalid.")]
                | //p[contains(text(),"The password must be 8–32 characters long and contain at least 1 uppercase letter, 1 lowercase letter, 1 digit and 1 special character.")]
                | //p[contains(text(),"Password must include at least 8 characters.")]
                | //p[contains(text(),"Please select country code and insert valid phone number.")]
            ')) {
                $msg = implode('. ', $res);

                throw new \UserInputError($msg);
            }

            if ($nextButton = $this->waitForElement(\WebDriverBy::xpath('//button[@type="submit"]'), 15)) {
                $nextButton->click();
            }

            $this->saveResponse();

            if ($userMembershipTextBlock = $this->waitForElement(\WebDriverBy::xpath('//p[@class="lead"]'), 5)) {
                if (!$userMembershipTextBlock) {
                    $this->saveResponse();

                    throw new \EngineError('no register form or other format');
                }
                preg_match('/\d+/', $userMembershipTextBlock->getText(), $m);
                $userMembershipNumber = $m[0];

                $this->ErrorMessage = json_encode([
                    "status"       => "success",
                    "message"      => "Registration is successful! Membership number: $userMembershipNumber",
                    "active"       => true,
                    "login"        => $userMembershipNumber,
                    "password"     => $finalPass,
                    "email"        => $finalEmail,
                    "registerInfo" => [
                        [
                            "key"   => "First Name",
                            "value" => $finalFirstName,
                        ],
                        [
                            "key"   => "Last Name",
                            "value" => $finalLastName,
                        ],
                        [
                            "key"   => "Birth Date",
                            "value" => $finalBirthDate,
                        ],
                        [
                            "key"   => "Phone Number",
                            "value" => $finalPhone,
                        ],
                        [
                            "key"   => "Country",
                            "value" => $finalCountry,
                        ],
                    ],
                ], JSON_PRETTY_PRINT);

                return true;
            }
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        } catch (\Facebook\WebDriver\Exception\WebDriverException | \WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new \EngineError('Forms were not loaded. Try to register account again');
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'New session attempts retry count exceeded') === false) {
                throw $e;
            }
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "New session attempts retry count exceeded";

            throw new \EngineError('no register form or other format');
        }

        $this->ErrorMessage = 'Something is wrong';
        $this->saveResponse();

        return false;
    }

    protected function checkFields(&$fields)
    {
        if (preg_match("/[\d*¡!?¿<>ºª|\·#$%&.,;=?¿_+{}\-\[\]\^€\$£]/", $fields['FirstName']) || strpos($fields['FirstName'], ' ') !== false
            || strlen($fields['FirstName']) > 56) {
            throw new \UserInputError('FirstName contains an incorrect symbol');
        }

        if (strpos($fields['LastName'], ' ') !== false
            || strlen($fields['LastName']) > 56) {
            throw new \UserInputError('FamilyName contains an incorrect symbol');
        }

        if (
            !preg_match("/^(0[1-9]|1[012])\/(0[1-9]|[12][0-9]|3[01])\/(19[3-9][0-9]|20[01][0-9])$/", $fields['BirthdayDate'])
            || ((new \DateTimeImmutable($fields['BirthdayDate']))->diff(new \DateTimeImmutable()))->format('%y') < 18
        ) {
            throw new \UserInputError('BirthdayDate contains an incorrect number');
        }

        if (!preg_match("/[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/", $fields['Email'])) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (strlen($fields['Password']) < 8 || strlen($fields['Password']) > 32 || !preg_match("/[A-Z]/", $fields['Password'])
            || !preg_match("/[a-z]/", $fields['Password']) || !preg_match("/[0-9]/", $fields['Password']) || !preg_match("/[^A-Za-z0-9]/", $fields['Password'])
        ) {
            throw new \UserInputError("Your password must be 8-32 characters and include at least 1 lowercase letter, 1 uppercase letter, 1 number and 1 special character");
        }

        if (strlen($fields['PhoneNumber']) != 10
            || preg_match("/[a-zA-Z*¡!?¿<>ºª|\·@#$%&;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['PhoneNumber'])
            || !in_array(substr($fields['PhoneNumber'], 0, 3), FormFieldsInformation::$americanAreaCodes)
        ) {
            throw new \UserInputError('Phone Number contains an incorrect number or invalid area code');
        }
    }

    protected function modifyFields(array &$fields)
    {
        foreach ($fields as $key => $value) {
            if ($key == 'BirthdayDate') {
                $fields['BirthDay'] = \DateTime::createFromFormat("m/d/Y", $fields["BirthdayDate"])->format("j");
                $fields['BirthMonth'] = \DateTime::createFromFormat("m/d/Y", $fields["BirthdayDate"])->format("n");
                $fields['BirthYear'] = \DateTime::createFromFormat("m/d/Y", $fields["BirthdayDate"])->format("Y");
            }

            $fields[$key] = $value;
        }
    }
}
