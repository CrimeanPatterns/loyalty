<?php

namespace AwardWallet\Engine\aviancataca\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class Register extends \TAccountChecker
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    private $registerInfo = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();

        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
        $request = FingerprintRequest::chrome();

        $this->disableImages();
        $this->seleniumOptions->showImages = false;

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

        $this->http->saveScreenshots = true;
        $this->setProxyGoProxies();

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

    public function registerAccount(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug(var_export($fields, true), ['pre' => true]);

        $this->checkFields($fields);

        $this->http->GetURL('https://www.lifemiles.com/enrollment/step/1');

        $email = $this->waitForElement(\WebDriverBy::xpath("
        //h1[contains(.,'¡Estamos trabajando para ofrecerte más beneficios!')]/following-sibling::p[1][@id='description']
        | //input[@id='email']
        | //h1[contains(.,'Estaremos de regreso pronto')]
        "));

        if ($email && strpos($email->getText(), 'Estaremos de regreso pronto') !== false) {
            throw new \ProviderError('At this time our systems are unavailable as we are performing scheduled maintenance.');
        }
        $pass = $this->waitForElement(\WebDriverBy::xpath('//input[@id="password"]'), 0);
        $confPass = $this->waitForElement(\WebDriverBy::xpath('//input[@id="confirmPassword"]'), 0);

        if (!$email || !$pass || !$confPass) {
            $this->saveResponse();
            $this->logger->error('no register form or other format');

            return false;
        }

        if ($cookie = $this->waitForElement(\WebDriverBy::xpath('//button[@class="CookiesBrowserAlert_acceptButtonNO"]'), 10)) {
            $cookie->click();
        }

        $email->sendKeys($fields['Email']);
        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'Email',
                'value' => $this->driver->executeScript("return document.querySelector('#email').value;"),
            ],
        ]);
        $pass->sendKeys($fields['Password']);
        $confPass->sendKeys($fields['Password']);
        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'Password',
                'value' => $this->driver->executeScript("return document.querySelector('#confirmPassword').value;"),
            ],
        ]);
        $email->click();

        $nextBtn = $this->waitForElement(\WebDriverBy::xpath('//button[contains(@class,"Enrollment_nextButton")]'), 30);

        if (!$nextBtn) {
            $this->saveResponse();
            $this->logger->error('no register form or other format');

            return false;
        }
        $this->saveResponse();
        $nextBtn->click();
        $this->saveResponse();

        $firstName = $this->waitForElement(\WebDriverBy::xpath('//input[@id="firstname"]'), 30);
        $lastName = $this->waitForElement(\WebDriverBy::xpath('//input[@id="lastname"]'), 0);
        $month = $this->waitForElement(\WebDriverBy::xpath('//div[contains(@class,"DateSelectGroup_month")]//select'), 0);
        $day = $this->waitForElement(\WebDriverBy::xpath('//div[contains(@class,"DateSelectGroup_day")]//select'), 0);
        $year = $this->waitForElement(\WebDriverBy::xpath('//div[contains(@class,"DateSelectGroup_year")]//select'), 0);

        sleep(5);

        if (!$firstName || !$lastName || !$month || !$day || !$year) {
            $this->saveResponse();
            $this->logger->error('no register form or other format');

            return false;
        }

        $firstName->sendKeys($fields['FirstName']);
        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'FirstName',
                'value' => $this->driver->executeScript("return document.querySelector('#firstname').value;"),
            ],
        ]);

        $lastName->sendKeys($fields['LastName']);
        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'LastName',
                'value' => $this->driver->executeScript("return document.querySelector('#lastname').value;"),
            ],
        ]);

        $month->click();
        $monthOption = $this->waitForElement(\WebDriverBy::xpath(
            '//div[contains(@class,"DateSelectGroup_month")]//option[@value="' . random_int(0, 11) . '"]'), 10);

        if (!$monthOption) {
            $this->saveResponse();
            $this->logger->error('no register form or other format');

            return false;
        }
        $monthOption->click();

        $day->click();
        $dayOption = $this->waitForElement(\WebDriverBy::xpath(
            '//div[contains(@class,"DateSelectGroup_day")]//option[@value="' . random_int(0, 26) . '"]'), 10);

        if (!$dayOption) {
            $this->saveResponse();
            $this->logger->error('no register form or other format');

            return false;
        }
        $dayOption->click();

        $year->click();
        $yearOption = $this->waitForElement(\WebDriverBy::xpath(
            '//div[contains(@class,"DateSelectGroup_year")]//option[@value="' . random_int(20, 44) . '"]'), 10);

        if (!$yearOption) {
            $this->saveResponse();
            $this->logger->error('no register form or other format');

            return false;
        }
        $yearOption->click();
        $this->saveResponse();

        $year = $this->driver->executeScript("return document.querySelector('.enrollment-ui-DateSelectGroup_year select').value;");
        $year = $this->http->FindSingleNode('//div[contains(@class,"DateSelectGroup_year")]//option[@value="' . $year . '"]');
        $day = $this->driver->executeScript("return document.querySelector('.enrollment-ui-DateSelectGroup_day select').value;");
        $day = $this->http->FindSingleNode('//div[contains(@class,"DateSelectGroup_day")]//option[@value="' . $day . '"]');
        $month = $this->driver->executeScript("return document.querySelector('.enrollment-ui-DateSelectGroup_month select').value;");
        $month = $this->http->FindSingleNode('//div[contains(@class,"DateSelectGroup_month")]//option[@value="' . $month . '"]');
        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'Birthday date',
                'value' => implode(' ', [$day, $month, $year]),
            ],
        ]);

        $documentNumber = $this->waitForElement(\WebDriverBy::xpath('//input[@id="documentNumber"]'), 0);
        $country = $this->waitForElement(\WebDriverBy::xpath('//div[contains(@class,"idNumberInputWrapper")]//select[@id="country"]'), 0);
        $checkbox1 = $this->waitForElement(\WebDriverBy::xpath('//input[@id="normal_term0"]'), 5, false);
        $checkbox2 = $this->waitForElement(\WebDriverBy::xpath('//input[@id="normal_term1"]'), 5, false);
        $confirm = $this->waitForElement(\WebDriverBy::xpath('//button[@id="Enroll-confirm"]'), 0);

        if (!$documentNumber || !$country || !$checkbox1 || !$checkbox2 || !$confirm) {
            $this->saveResponse();
            $this->logger->error('no register form or other format');

            return false;
        }

        $country->click();
        $countryNames = $this->http->FindNodes('//div[contains(@class,"idNumberInputWrapper")]//select[@id="country"]//option[contains(.,"United States") or contains(.,"United Kingdom")]');
        $countryCodes = $this->http->FindNodes('//div[contains(@class,"idNumberInputWrapper")]//select[@id="country"]//option[contains(.,"United States") or contains(.,"United Kingdom")]/@value');

        $countries = array_combine($countryCodes, $countryNames);

        $country = array_rand($countries);
        $countryOption = $this->waitForElement(\WebDriverBy::xpath(
            '//div[contains(@class,"idNumberInputWrapper")]//select[@id="country"]//option[@value="' . $country . '"]'), 10);

        if (!$countryOption) {
            $this->saveResponse();
            $this->logger->error('no register form or other format');

            return false;
        }

        $countryOption->click();
        $countryCode = $this->driver->executeScript("return document.querySelector('.enrollment-ui-Step2_idNumberSelect #country').value;");

        if (!array_key_exists($countryCode, $countries)) {
            throw new \EngineError("failed checking country");
        }
        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'Issuing Country',
                'value' => $countries[$countryCode],
            ],
        ]);

        // Country of citizenship - default
        $countryNamesCitizenship = $this->http->FindNodes('//div[contains(@class,"enrollment-ui-Step2_selectItem")]//select[@id="country"]//option');
        $countryCodesCitizenship = $this->http->FindNodes('//div[contains(@class,"enrollment-ui-Step2_selectItem")]//select[@id="country"]//option/@value');
        $countriesCitizenship = array_combine($countryCodesCitizenship, $countryNamesCitizenship);
        $countryCodeCitizenship = $this->driver->executeScript("return document.querySelector('.enrollment-ui-Step2_selectItem #country').value;");
        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'Country of citizenship',
                'value' => $countriesCitizenship[$countryCodeCitizenship],
            ],
        ]);

        if ($checkbox1 && $checkbox2 && $documentNumber) {
            $this->driver->executeScript('
                document.querySelector(\'label[for="normal_term0"]\').click();
                document.querySelector(\'label[for="normal_term1"]\').click();
            ');
        }

        // 8 digits based on email, + random last one
        $national_id = substr(hexdec(substr(md5($fields['Email']), 0, 10)), 0, 8) . rand(0, 9);

        $documentNumber->click();
        $documentNumber->sendKeys(\WebDriverKeys::DELETE);
        $documentNumber->sendKeys($national_id);
        $this->registerInfo = array_merge($this->registerInfo, [
            [
                'key'   => 'National ID',
                'value' => $this->driver->executeScript("return document.querySelector('#documentNumber').value;"),
            ],
        ]);
        $lastName->click();
        $this->saveResponse();
        $this->logger->debug(var_export($this->registerInfo, true), ['pre'=>true]);

        $confirm->click();

        $this->logger->debug($this->http->currentUrl());

        if ($res = $this->waitForElement(\WebDriverBy::xpath('
                (//p[contains(text(),"This email is already associated")]
                | //*[self::div or self::p][contains(text(),"The email you entered is invalid. Please verify that the email is correct.")]
                | //h1[contains(text(),"An Error just ocurred")]/following-sibling::p[1][contains(@class,"enrollment-ui-GeneralErrorPage_description")]
                | //p[contains(text(),"nico se encuentra asociado")]
                | //p[contains(text(),"Lo sentimos, no pudimos realizar tu solicitud, por favor intenta nuevamente")]
                | //p[contains(text(),"We\'re sorry, we couldn\'t complete the operation at this time. For assistance, please visit our")]
                | //li[contains(text(),"que ingresaste ya pertenece a")])[1]
            '), 10)) {
            $this->saveResponse();
            $this->logger->debug($this->http->currentUrl());

            $msg = $res->getText();

            if (strpos($msg, "Lo sentimos, no pudimos realizar tu solicitud, por favor intenta nuevamente. Si el problema persiste contacta a nuestro Call Center") !== false) {
                throw new \EngineError($msg);
            }

            if (strpos($msg, "complete the operation at this time. For assistance") !== false) {
                throw new \EngineError($msg);
            }

            if (strpos($msg, "The email you entered is invalid") !== false) {
                throw new \UserInputError($msg);
            }

            if (strpos($msg, 'que ingresaste ya pertenece a') !== false) {
                throw new \UserInputError('National ID is already associated to a LifeMiles account. Try again.');
            }

            if (strpos($msg, 'se encuentra asociado a otra cuenta LifeMiles.') !== false
                || strpos($msg, 'This email is already associated to a LifeMiles account') !== false) {
                throw new \UserInputError('This email is already associated to a LifeMiles account. Try with a different email.');
            }

            throw new \ProviderError($msg);
        }
        $this->logger->debug($this->http->currentUrl());

        if ($username = $this->waitForElement(\WebDriverBy::xpath('//input[@id="username"]'), 50)) {
            $this->saveResponse();
            $this->ErrorMessage = json_encode([
                "status"       => "success",
                "message"      => "Registration is successful! Saved with email as login.",
                "active"       => true,
                "login"        => $username->getAttribute('value'),
                "registerInfo" => $this->registerInfo,
            ], JSON_PRETTY_PRINT);

            return true;
        }

        if ($username = $this->http->FindPreg("/ftnum=(\d+)/", false, $this->http->currentUrl())) {
            $this->saveResponse();
            $this->logger->debug($this->http->currentUrl());
            $this->sendNotification("ftnum url // ZM");
            $this->ErrorMessage = json_encode([
                "status"       => "success",
                "message"      => "Registration is successful! Saved with email as login.",
                "active"       => true,
                "login"        => $username,
                "registerInfo" => $this->registerInfo,
            ], JSON_PRETTY_PRINT);

            return true;
        }

        $ftnum = $this->driver->executeScript("
        function getCookieValue(name) {
                const regex = new RegExp(`(^| )\${name}=([^;]+)`)
                const match = document.cookie.match(regex)
                if (match) {
                    return match[2]
                }
        }
        return getCookieValue('ftnum');
        ");

        if (is_numeric($ftnum)) {
            $this->logger->debug(var_export($ftnum, true), ['pre' => true]);
            $this->sendNotification("ftnum (cookie) // ZM");
            $this->saveResponse();
            $this->ErrorMessage = json_encode([
                "status"       => "success",
                "message"      => "Registration is successful! Saved with email as login.",
                "active"       => true,
                "login"        => $ftnum,
                "registerInfo" => $this->registerInfo,
            ], JSON_PRETTY_PRINT);

            return true;
        }

        $this->saveResponse();

        if ($msg = $this->http->FindSingleNode("//div[contains(@class,'enrollment-ui-ModalFrame_contentWrapper')]//h1")) {
            if (strpos($msg, 'nico ya se encuentra asociado a una cuenta lifemiles') !== false
                || strpos($msg, 'email is already associated to') !== false) {
                throw new \UserInputError('This email is already associated to a LifeMiles account. Try with a different email.');
            }
            $this->logger->error($msg);
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
                "Note"     => "Between 8 and 15 characters. At least one uppercase. At least one lowercase. At least one number. Special characters like @ ? # $ % ( ) _ = * : ; ' . / + < > & ¿ , [.",
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
        ];
    }

    protected function checkFields(&$fields)
    {
        if (!preg_match("/[A-Z0-9a-z._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/", $fields['Email'])) {
            throw new \UserInputError('Email address contains an incorrect format');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['FirstName'])) {
            throw new \UserInputError('FirstName contains an incorrect symbol');
        }

        if (preg_match("/[\d*¡!?¿<>\\ºª|\/\·@#$%&.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['LastName'])) {
            throw new \UserInputError('LastName contains an incorrect symbol');
        }

        if ((strlen($fields['Password']) < 8 || strlen($fields['Password']) > 15) || !preg_match("/[A-Z]/", $fields['Password'])
            || !preg_match("/[a-z]/", $fields['Password']) || !preg_match("/[0-9]/", $fields['Password']) || strpos($fields['Password'], ' ') !== false
            || !preg_match("/[*?<>\\ºª|\/\·@#$.,;=?¿())_+{}\-\[\]\"\^€\$£']/", $fields['Password']) || preg_match("/[!%&¡¿¨]/", $fields['Password'])
        ) {
            throw new \UserInputError("Between 8 and 15 characters. At least one uppercase. At least one lowercase. At least one number. Special characters like @ ? # $ % ( ) _ = * : ; ' . / + < > & ¿ , [.");
        }
    }
}
