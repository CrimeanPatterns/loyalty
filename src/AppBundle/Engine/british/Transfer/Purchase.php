<?php

namespace AwardWallet\Engine\british\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    protected $timeout = 5;

    protected $loadTimeout = 20;

    public function InitBrowser()
    {
        $this->UseSelenium();
        $this->useChromium();

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyPurchase());
        } else { // $this->http->setExternalProxy();
            // $this->http->SetProxy($this->proxyUK());
            $this->setProxyBrightData();
        }

        $this->http->saveScreenshots = true;
        $this->ArchiveLogs = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://www.britishairways.com/travel/purchase-avios/public/en_us/execclub?eId=115002&purchaseType=Buy");

        if (!($form = $this->waitForElement(\WebDriverBy::xpath('//form[@id="execLoginrForm"]'), $this->loadTimeout))) {
            return $this->fail('login form not found');
        }

        return true;
    }

    public function Login()
    {
        if ($elem = $this->waitForElement(\WebDriverBy::xpath('//form[@id="execLoginrForm"]//input[@id="membershipNumber"]'), $this->loadTimeout)) {
            $elem->sendKeys($this->AccountFields['Login']);
        }

        if ($elem = $this->waitForElement(\WebDriverBy::xpath('//form[@id="execLoginrForm"]//input[@id="input_password"]'), $this->loadTimeout)) {
            $elem->sendKeys($this->AccountFields['Pass']);
        }

        if ($elem = $this->waitForElement(\WebDriverBy::id('ecuserlogbutton'), $this->loadTimeout)) {
            $elem->click();
        }

        if ($elem = $this->waitForElement(\WebDriverBy::xpath('//a[@id="logout"]'), $this->loadTimeout)) {
            return true;
        }

        if ($error = $this->waitForElement(\WebDriverBy::xpath('//h1[contains(normalize-space(),"This page is not available")]/following-sibling::ul[1]'), $this->loadTimeout)) {
            return $this->fail('This page is not available - block: ' . $error->getText());
        }

        return $this->fail('login error');
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
            "Login" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Membership number / Username",
            ],
            "Password" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "PIN / Password",
            ],
        ];
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $numberOfMiles = intval($numberOfMiles);
        $validMiles = [
            1000, 2000, 3000, 4000, 5000, 6000, 8000, 10000,
            15000, 20000, 25000, 30000, 35000, 40000, 45000,
            50000, 60000, 70000, 80000, 90000, 100000,
        ];

        if (!in_array($numberOfMiles, $validMiles)) {
            throw new \UserInputError("Number of purchased points should be one of " . json_encode($validMiles));
        }
//        if ($numberOfMiles < 1000 || $numberOfMiles > 35000 || $numberOfMiles % 1000 !== 0)
//            throw new \UserInputError("Number of purchased points should be lesser then 35,000 and divisible by 1000");

        $this->http->GetURL("https://www.britishairways.com/travel/purchase-avios/public/en_us/execclub?eId=115002&purchaseType=Buy");

        if (!($frame = $this->waitForElement(\WebDriverBy::xpath('//iframe[@id="MPFrame"]'), $this->loadTimeout))) {
            return $this->fail('frame MPFrame not found');
        }
        $this->driver->switchTo()->frame($frame);

        //step 1
        if (!$this->waitForElement(\WebDriverBy::xpath('//form[@data-name="transactionForm"]//select[@id="bgt-offer-dropdown"]'), $this->loadTimeout)) {
            $this->checkLoginFormErrors();

            return $this->fail('Buy form not loaded');
        }

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

        if (!$this->waitForElement(\WebDriverBy::id('cardName'), $this->loadTimeout)) {
            $this->checkLoginFormErrors();

            return $this->fail('Credit Card form not loaded.');
        }
        /*
        string:transaction.payment.form.creditCard.amex.executive-club
        string:transaction.payment.form.creditCard.visaDebit
        string:transaction.payment.form.creditCard.masterCardDebit
        string:transaction.payment.form.creditCard.visa
        string:transaction.payment.form.creditCard.masterCard
        string:transaction.payment.form.creditCard.visaElectron
        string:transaction.payment.form.creditCard.amex
        string:transaction.payment.form.creditCard.dinersClub
        string:transaction.payment.form.creditCard.jcb
        */
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
}
