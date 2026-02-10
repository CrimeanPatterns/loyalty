<?php

namespace AwardWallet\Engine\amtrak\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    protected $timeout = 5;
    protected $loadTimeout = 20;

    public function InitBrowser()
    {
        $this->useChromium();
        $this->UseSelenium();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyPurchase());
        }
        $this->http->saveScreenshots = true;
        $this->ArchiveLogs = true;
    }

    public function LoadLoginForm()
    {
        // if you dont need to sign in for purchase
        // you still have to implement LoadLoginForm() and Login()
        // just return true
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function getPurchaseMilesFields()
    {
        return [
            "Email" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Email",
            ],
            "FirstName" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "First Name",
            ],
            "LastName" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Last Name",
            ],
            "AccountNumber" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Amtrak Number",
            ],
            "Password" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Password",
            ],
        ];
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $numberOfMiles = intval($numberOfMiles);

        if ($numberOfMiles < 500 || $numberOfMiles > 30000 || $numberOfMiles % 500 !== 0) {
            throw new \UserInputError("Number of purchased points should be less than 30,000 and divisible by 500");
        }
        $this->http->GetURL("https://buy.points.com/PointsPartnerFrames/partners/amtrak/container.html?language=en&product=BUY");

        //step 1
        if (!$this->waitForElement(\WebDriverBy::xpath('//form[@name="loginForm"]'), $this->loadTimeout)) {
            return $this->fail('no form for membership info');
        }

        if (!$this->fillTextInputs([
            'firstName' => $fields['FirstName'],
            'lastName' => $fields['LastName'],
            'memberId' => $fields['AccountNumber'],
            'password' => $fields['Password'],
            'email' => $fields['Email'],
        ])) {
            return $this->fail();
        }

        $this->saveResponse();

        if (!($button = $this->waitForElement(\WebDriverBy::xpath('//form[@name="loginForm"]//button[@type="submit"]'), $this->timeout))) {
            return $this->fail();
        }
        $button->click();

        if (!$this->waitForElement(\WebDriverBy::xpath('//form[@data-name="transactionForm"]//select[@id="bgt-offer-dropdown"]'), $this->loadTimeout)) {
            $this->checkLoginFormErrors();

            return $this->fail('Buy form not loaded');
        }
        //step 2
        if (!($elem = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout))) {
            return $this->fail('Select "bgt-offer-dropdown" not found.');
        }
        $optionXpath = sprintf('//select[@id = "bgt-offer-dropdown"]//option[@value = "%s"]', $numberOfMiles);

        if ($option = $this->waitForElement(\WebDriverBy::xpath($optionXpath), $this->timeout)) {
            $option->click();
            $this->logger->info('Select ' . $numberOfMiles . ' points done.');
        } else {
            return $this->fail('Didn\'t find option for ' . $numberOfMiles . ' points.');
        }
        //step 3
        if (!$this->waitForElement(\WebDriverBy::id('cardName'), $this->loadTimeout)) {
            $this->checkLoginFormErrors();

            return $this->fail('Credit Card form not loaded.');
        }
        $inputSelectFieldsMap = [
            'Type' => [
                'cardName',
                'string:transaction.payment.form.creditCard.' . $creditCard['Type'],
            ],
            'ExpirationMonth' => [
                'expirationMonth',
                'number:' . (int) $creditCard['ExpirationMonth'],
            ],
            'ExpirationYear' => [
                'expirationYear',
                'number:' . (strlen($creditCard['ExpirationYear']) === 2 ? '20' . $creditCard['ExpirationYear'] : $creditCard['ExpirationYear']),
            ],
            'Country' => [
                'country',
                'string:' . $creditCard['CountryCode'],
            ],
            'StateOrProvince' => [
                'state',
                'string:' . $creditCard['StateCode'],
            ],
        ];

        foreach ($inputSelectFieldsMap as $key => $selectField) {
            [$selectFieldId, $value] = $selectField;
            $this->logger->info("Selecting '$key' to '$value' ...");

            try {
                (new \WebDriverSelect($this->waitForElement(\WebDriverBy::id($selectFieldId), $this->timeout)))->selectByValue($value);
            } catch (\NoSuchElementException $e) {
                return $this->fail($e->getMessage());
            }
            $this->logger->info('Select "' . $value . '" done.');
        }
        $inputTextFieldsMap = [
            'cardNumber'         => 'CardNumber',
            'securityCode'       => 'SecurityNumber',
            'creditCardFullName' => 'Name',
            'street1'            => 'AddressLine',
            'city'               => 'City',
            'zip'                => 'Zip',
            'phone'              => 'PhoneNumber',
        ];

        foreach ($inputTextFieldsMap as $inputId => $awKey) {
            $this->logger->info("Setting '$awKey' to input '{$creditCard[$awKey]}' ...");
            $this->waitForElement(\WebDriverBy::id($inputId), $this->timeout)->sendKeys($creditCard[$awKey]);
            $this->logger->info('Done.');
        }
        $this->logger->info('Setting "Email" to input "billingEmail" ...');

        if ($elem = $this->waitForElement(\WebDriverBy::id('billingEmail'), $this->timeout)) {
            $elem->clear();
            $elem->sendKeys($fields['Email']);
            $this->logger->info('Done.');
        } else {
            $this->logger->info('Could not find input field for Email.');

            return false;
        }
        $this->waitForElement(\WebDriverBy::id('termsAndConditions'), $this->timeout)->click();

        $this->waitForElement(\WebDriverBy::xpath('//*[@class="bgt-order-information-form"]//*[@type="submit"]'), $this->timeout)->click();

        if ($this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"Please complete all of the required fields")])[1]'), $this->loadTimeout)) {
            $this->logger->info('Error: Missing required fields.');

            return false;
        }
        $this->logger->info('Done.');

        // success
        $success = $this->waitForElement(\WebDriverBy::xpath('//*[text()[contains(normalize-space(.), "Thank you for your purchase") or contains(normalize-space(.),"We have received your purchase request")]]'), $this->timeout);
        $this->saveResponse();

        if ($success) {
            $this->ErrorMessage = 'Thank you for your purchase!';

            return true;
        }

        return false;
    }

    protected function fail($message = null)
    {
        if (isset($message)) {
            $this->logger->info($message);
        }
        $this->saveResponse();

        return false;
    }

    protected function checkLoginFormErrors()
    {
        if ($error = $this->waitForElement(\WebDriverBy::xpath('//div[@class="bgt-form-error" and normalize-space(.) != ""]'), $this->timeout)) {
            throw new \UserInputError(CleanXMLValue($error->getText()));
        }
    }

    private function fillTextInputs($data)
    {
        foreach ($data as $name => $value) {
            $input = $this->waitForElement(\WebDriverBy::xpath(sprintf('//input[@id="%s" and (@type="text" or @type="password")]', $name)), $this->timeout);

            if (!$input) {
                return $this->fail(sprintf('input %s not found', $name));
            }
            $ok = false;

            for ($i = 0; $i < 3; $i++) {
                $input->clear();
                $input->click()->sendKeys($value);

                $filled = $this->driver->executeScript(sprintf('return $("input#%s").val()', $name));

                if (strcmp(trim($value), trim($filled)) === 0) {
                    $ok = true;

                    break;
                }
                $this->http->Log(sprintf('retrying filling input %s', $name));
                sleep(1);
            }

            if (!$ok) {
                return $this->fail(sprintf('input %s didn\'t get filled properly'));
            }
        }

        return true;
    }
}
