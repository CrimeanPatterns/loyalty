<?php

namespace AwardWallet\Engine\carlson\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountCheckerCarlson
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    protected $loadTimeout = 30;
    protected $waitTimeout = 2;

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyPurchase());
        }
        $this->ArchiveLogs = true;
    }

    public function LoadLoginForm()
    {
        $numberOfMiles = intval($this->TransferFields['numberOfMiles']);

        if ($numberOfMiles <= 0 || $numberOfMiles > 40000 || $numberOfMiles % 1000 !== 0) {
            throw new \UserInputError('Number of purchased points should be lesser then 40,000 and divisible by 1000.');
        }

        return parent::LoadLoginForm();
    }

    public function Login()
    {
        return parent::Login();
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->http->GetURL('https://www.clubcarlson.com/fgp/secure/earn/points/gateway.do?action=buy');
        $this->http->setMaxRedirects(0);

        if (!$this->http->ParseForm('ssoForm') || !$this->http->PostForm()) {
            return false;
        }
        //		if (!isset($this->http->Response['headers']['location']) || strpos($this->http->Response['headers']['location'], 'https://storefront.points.com/clubcarlson/sso/buy?mv=') !== 0) {
        if (!isset($this->http->Response['headers']['location']) || strpos($this->http->Response['headers']['location'], 'https://storefront.points.com/radisson-rewards/sso/buy?mv=') !== 0) {
            $this->logger->info('didn\'t get correct redirect');

            return false;
        }

        $url = $this->http->Response['headers']['location'];
        $selenium = $this->getSeleniumChecker();

        try {
            $result = $selenium->seleniumBuy($url, $fields, $numberOfMiles, $creditCard);
            $this->ErrorMessage = $selenium->ErrorMessage;
        } catch (\Exception $e) {
            throw $e;
        } finally {
            $selenium->http->cleanup();
        }

        if (isset($result)) {
            return $result;
        } else {
            return false;
        }
    }

    public function seleniumBuy($url, $fields, $numberOfMiles, $creditCard)
    {
        $this->http->GetURL($url);

        if (!($elem = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout))) {
            return $this->fail('Select "bgt-offer-dropdown" not found.');
        }

        try {
            (new \WebDriverSelect($this->waitForElement(\WebDriverBy::xpath('//select[@id = "bgt-offer-dropdown"]'), $this->waitTimeout)))->selectByValue($numberOfMiles);
        } catch (\NoSuchElementException $e) {
            return $this->fail($e->getMessage());
        }
        $this->logger->info('Select ' . $numberOfMiles . ' points done.');

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
                (new \WebDriverSelect($this->waitForElement(\WebDriverBy::id($selectFieldId), $this->waitTimeout)))->selectByValue($value);
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
            $this->waitForElement(\WebDriverBy::id($inputId), $this->waitTimeout)->sendKeys($creditCard[$awKey]);
            $this->logger->info('Done.');
        }
        $this->logger->info('Setting "Email" to input "billingEmail" ...');

        if ($elem = $this->waitForElement(\WebDriverBy::id('billingEmail'), $this->waitTimeout)) {
            $elem->clear();
            $elem->sendKeys($fields['Email']);
            $this->logger->info('Done.');
        } else {
            $this->logger->info('Could not find input field for Email.');

            return false;
        }

        if ($input = $this->waitForElement(\WebDriverBy::xpath('//input[@id="termsAndConditions"]'), $this->waitTimeout, false)) {
            $input->click();
        }

        $this->saveResponse();

        if ($elem = $this->waitForElement(\WebDriverBy::xpath('//*[@class="bgt-order-information-form"]//*[@type="submit"]'),
            $this->waitTimeout)
        ) {
            $this->logger->info('click submit');
            $elem->click();
        }
        $this->saveResponse();

        if ($this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"Please complete all of the required fields")])[1]'),
            $this->loadTimeout)
        ) {
            $this->logger->info('Error: Missing required fields.');

            return false;
        }

        if ($this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"To continue with your purchase")])[1]'),
            $this->loadTimeout)
        ) {
            $this->logger->info('Error: termsAndConditions not checked.');

            return false;
        }

        $this->logger->info('Done.');

        // success
        $success = $this->waitForElement(\WebDriverBy::xpath('//*[text()[contains(., "Thank you for your purchase")]]'), $this->waitTimeout);

        if (!$success) {
            $success = $this->waitForElement(\WebDriverBy::xpath('//*[text()[contains(., "We have received your purchase request")]]'), $this->waitTimeout);
        }

        if ($success) {
            $this->ErrorMessage = 'Thank you. We have received your purchase request.';

            return true;
        }

        return false;
    }

    public function getPurchaseMilesFields()
    {
        return [
            'Email' => [
                'Type'     => 'string',
                'Required' => true,
            ],
            'Login' => [
                'Type'     => 'string',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Required' => true,
            ],
        ];
    }

    protected function fail($message = null)
    {
        if (isset($message)) {
            $this->logger->error($message);
        }
        //$this->http->Log($message, LOG_LEVEL_ERROR);
        $this->http->SaveResponse();

        return false;
    }

    /**
     * @return Purchase
     */
    protected function getSeleniumChecker()
    {
        $checker2 = clone $this;
        $this->http->brotherBrowser($checker2->http);
        $this->logger->notice("Running Selenium...");
        $checker2->UseSelenium();
        $checker2->http->SetProxy($this->http->GetProxy());
        $checker2->useFirefox(\SeleniumFinderRequest::FIREFOX_53);
        $checker2->http->saveScreenshots = true;
        $checker2->http->start();
        $checker2->Start();

        return $checker2;
    }

    protected function checkLoginFormErrors()
    {
        if ($error = $this->waitForElement(\WebDriverBy::xpath('//div[@class="bgt-form-error" and normalize-space(.) != ""]'), $this->waitTimeout)) {
            throw new \UserInputError(CleanXMLValue($error->getText()));
        }
    }
}
