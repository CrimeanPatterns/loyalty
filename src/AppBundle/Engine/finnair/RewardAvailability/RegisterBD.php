<?php

namespace AwardWallet\Engine\finnair\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\finnair\RewardAvailability\Helpers\FormFieldsInformation;
use AwardWallet\Engine\ProxyList;

class RegisterBD extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->UseSelenium();
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
        $this->disableImages();
        $this->http->saveScreenshots = true;

        switch (rand(1, 2)) {
            case 0:
                $this->setProxyBrightData(null, 'static', 'fi');

                break;

            case 1:
                $this->setProxyGoProxies(null, 'in');

                break;

            case 2:
                $this->setProxyGoProxies(null, 'ca');

                break;
        }

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

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = 100;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $this->fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
        $this->http->setRandomUserAgent(null, false, true, false, true, false);

        if ($this->fingerprint) {
            $this->seleniumOptions->userAgent = $this->fingerprint->getUseragent();
            $this->http->setUserAgent($this->fingerprint->getUseragent());
        }
    }

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug(var_export($fields, true), ['pre' => true]);
        $fields['MobileAreaCode'] = 'US';
        $this->checkFields($fields);

        $this->http->GetURL('https://www.finnair.com/us-en');

        if (!$this->waitForElement(\WebDriverBy::xpath("//h1[normalize-space()='Your journey starts here']"), 20)) {
            $this->logger->error('element not found');

            throw new \CheckException('not load', ACCOUNT_ENGINE_ERROR);
        }

        if ($button = $this->waitForElement(\WebDriverBy::xpath("//button[contains(.,'Allow all cookies')]"), 3)) {
            $button->click();
        }

        if ($language = $this->waitForElement(\WebDriverBy::xpath("//a[@aria-label='Language: United States - en']"), 20)) {
            $language->click();
        }

        if ($language = $this->waitForElement(\WebDriverBy::xpath("//a[@data-test-lang-selection-link='us-en']"), 20)) {
            $language->click();
        }

        sleep(1);

        $this->http->GetURL('https://auth.finnair.com/content/en/join/finnair-plus');

        $first_name = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='firstName']"), 5);
        $last_name = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='lastName']"), 5);
        $day = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='day']"), 5);
        $month = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='month']"), 5);
        $year = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='year']"), 5);
        $gender_male = $this->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='Male']"), 10, false);
        $gender_female = $this->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='Female']"), 10, false);
        $country_code = $this->waitForElement(\WebDriverBy::xpath("//select[@formcontrolname='countryCode']"), 5); // Rx3 = Russia
        $phone = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='phone']"), 5);
        $email = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='email']"), 5);
        $pass = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='password']"), 5);
        $agreements = $this->waitForElement(\WebDriverBy::xpath("//input[@formcontrolname='agreements']"), 5, false); // agreements checkbox
        $submit = $this->waitForElement(\WebDriverBy::xpath('//button[@type="submit"]'), 5);

        $this->saveResponse();

        if (
            !$first_name || !$last_name || !$day || !$month || !$year
            || !$gender_male || !$gender_female || !$country_code || !$phone
            || !$email || !$pass || !$agreements || !$submit
        ) {
            $this->logger->error('no register form or other format (form 1)');

            return false;
        }

        sleep(1);
        // fill fields

        $first_name->sendKeys($fields['FirstName']);
        $last_name->sendKeys($fields['LastName']);

        [$fields_month, $fields_day, $fields_year] = explode('/', $fields['BirthdayDate']);

        $day->sendKeys($fields_day);
        $month->sendKeys($fields_month);
        $year->sendKeys($fields_year);

        if ($fields['Gender'] == 'male') {
            $gender_male->click();
        } else {
            $gender_female->click();
        }

        $this->logger->debug('gender from fields ' . $fields['Gender']);

        $keys = $this->getKeysForCountryCode($fields['MobileAreaCode']);

        $country_code->sendKeys($keys);
        $phone->sendKeys($fields['PhoneNumber']);
        $email->sendKeys($fields['Email']);
        $pass->sendKeys($fields['Password']);

        $this->driver->executeScript('document.querySelector(\'label[class="checkbox"]\').click()');

        $this->saveResponse();
        $submit->click();
        sleep(1);
        $submit = $this->waitForElement(\WebDriverBy::xpath('//button[@type="submit"]'), 10);

        if (!$submit) {
            $this->logger->error('no register form or other format (form 2)');

            return false;
        }

        $submit->click();

        if (!$this->waitForElement(\WebDriverBy::xpath("//h1[normalize-space()='Welcome to a world of points!']"), 45)) {
            $this->logger->error('Unknown registration error!');
            $this->saveResponse();

            if ($error = $this->waitForElement(\WebDriverBy::xpath("//p[contains(@class,'error')]"), 10)) {
                $msg = $error->getText();

                if (strpos($msg,
                        'Sorry, something went wrong. Please check the details you provided or try again later.') !== false) {
                    throw new \UserInputError($msg);
                }
            }

            throw new \EngineError('Unknown registration error!');
        }

        $this->saveResponse();
        $url = $this->http->currentUrl(); // https://auth.finnair.com/content/en/join/plus-success?memberNumber=725019665
        $parts = parse_url($url);
        parse_str($parts['query'], $query);

        $this->ErrorMessage = json_encode([
            "status"  => "success",
            "message" => "Registration is successful! Saved with email as login.",
            "active"  => true,
            "login"   => $query['memberNumber'],
        ], JSON_PRETTY_PRINT);

        return true;
    }

    public function getRegisterFields()
    {
        return [
            "Email"          => [
                "Type"     => "string",
                "Caption"  => "Email address",
                "Required" => true,
            ],
            "Password"       => [
                "Type"     => "string",
                "Caption"  => "Password must be at least 8 characters and contain 1 upper case, 1 lower case, 1 digit and 1 special character + Password must include at least 8 characters.",
                "Required" => true,
            ],
            "FirstName"      => [
                "Type"     => "string",
                "Caption"  => "First Name",
                "Required" => true,
            ],
            "LastName"       => [
                "Type"     => "string",
                "Caption"  => "Last Name",
                "Required" => true,
            ],
            'BirthdayDate'   => [
                'Type'     => 'date',
                'Caption'  => 'Your date of birth, older than 18 (MM/DD/YYYY)',
                'Required' => true,
            ],
            'Gender'         => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => ['male' => 'Male', 'female' => 'Female'],
            ],
            'PhoneNumber'    => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number without Mobile Area Code',
                'Required' => true,
            ],
        ];
    }

    protected function checkFields(&$fields)
    {
        $this->phoneLengthValidation($fields);

        if (!preg_match("/[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/", $fields['Email'])) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['FirstName'])) {
            throw new \UserInputError('FirstName contains an incorrect symbol');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['LastName'])) {
            throw new \UserInputError('LastName contains an incorrect symbol');
        }

        if ((strlen($fields['Password']) < 8 || strlen($fields['Password']) > 20) || !preg_match("/[A-Z]/", $fields['Password'])
            || !preg_match("/[a-z]/", $fields['Password']) || !preg_match("/[0-9]/", $fields['Password']) || strpos($fields['Password'], ' ') !== false
            || !preg_match("/[*!?<>\\ºª|\/\·@#$.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['Password']) || preg_match("/[%&¡¿¨]/", $fields['Password'])
        ) {
            throw new \UserInputError("Your password must contain at least 8 characters and meet requirements: uppercase letter, lowercase letter, number and special character (except %, &, ¡, ¿ and ¨). And it shouldn't include any spaces or have been used previously.");
        }
    }

    private function getKeysForCountryCode($input_code): string
    {
        $pos = 0;
        $all_codes = FormFieldsInformation::$country_codes;
        $first_letter = $all_codes[$input_code][0];

        foreach ($all_codes as $code => $country) {
            if ($country[0] == $first_letter) {
                break;
            }
            $pos++;
        }
        $country_codes = array_slice($all_codes, $pos);
        $country_codes = array_keys($country_codes);
        $country_codes = array_flip($country_codes);
        $tap_counts = $country_codes[$input_code] + 1;

        return str_repeat($first_letter, $tap_counts);
    }

    private function phoneLengthValidation($fields)
    {
        $acceptable_length = '';
        $i = 1;
        $valid_number = FormFieldsInformation::$countries_phone_number_length[$fields['MobileAreaCode']];
        $input_number_length = strlen($fields['PhoneNumber']);

        if (is_array($valid_number['length'])) {
            if (!in_array($input_number_length, $valid_number['length'])) {
                $count = count($valid_number['length']);

                foreach ($valid_number['length'] as $lenghtt) {
                    if ($i == $count) {
                        $acceptable_length .= $lenghtt;
                    } else {
                        $acceptable_length .= $lenghtt . ' or ';
                    }
                    $i++;
                }

                throw new \UserInputError('PhoneNumber must contain ' . $acceptable_length . ' digits with MobileAreaCode [' . $fields['MobileAreaCode'] . '] current length is ' . $input_number_length);
            }
        } else {
            if ($input_number_length != $valid_number['length']) {
                throw new \UserInputError('PhoneNumber must contain ' . $valid_number['length'] . ' digits with MobileAreaCode [' . $fields['MobileAreaCode'] . '] current length is ' . $input_number_length);
            }
        }
    }
}
