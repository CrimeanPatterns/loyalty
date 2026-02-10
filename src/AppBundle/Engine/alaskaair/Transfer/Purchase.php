<?php

namespace AwardWallet\Engine\alaskaair\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    protected $loadTimeout = 20;

    protected $waitTimeout = 2;

    protected $ccTypes = [
        'amex' => 'string:transaction.payment.form.creditCard.amex',
        'visa' => 'string:transaction.payment.form.creditCard.visa',
    ];

    public function InitBrowser()
    {
        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
//            $this->InitSeleniumBrowser($this->proxyDOP());
            $this->InitSeleniumBrowser();
            $this->http->SetProxy($this->proxyDOP());
        } else {
            $this->InitSeleniumBrowser();
            $this->http->SetProxy($this->proxyPurchase());
        }
        $this->useChromium();
        $this->keepCookies(false);
        $this->disableImages();
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $number = intval($numberOfMiles);

        if ($number < 1000 || $number > 60000 || $number % 1000 !== 0) {
            throw new \UserInputError('Number of miles should be lesser then 60,000 and divisible by 1000');
        }

        if (!isset($this->ccTypes[$creditCard['Type']])) {
            return $this->fail('unknown cc: ' . $creditCard['Type']);
        }

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) { // use affiliate link
            $this->http->GetURL('http://www.kqzyfj.com/click-8184014-11347188-1436971884000');
        } else {
            $this->http->GetURL('https://storefront.points.com/mileage-plan/en-US/buy');
        }

        //		$link = $this->waitForElement(\WebDriverBy::xpath('//a[contains(text(), "BUY MILES")]'), $this->loadTimeout);
        //		if (!$link)
        //			return $this->fail('no buy link');
        //		$link->click();
        $form = $this->waitForElement(\WebDriverBy::xpath('//form[@name="loginForm"]'), $this->loadTimeout);

        if (!$form) {
            return $this->fail('login form not found');
        }

        foreach ([
            'firstName' => 'FirstName',
            'lastName' => 'LastName',
            'memberId' => 'AccountNumber',
            'email' => 'Email',
        ] as $name => $key) {
            $this->waitForElement(\WebDriverBy::id($name), $this->waitTimeout)->sendKeys($this->TransferFields[$key]);
        }

        $button = $this->waitForElement(\WebDriverBy::xpath('//button[@type="submit" and not(contains(@class, "disabled"))]'), $this->waitTimeout);

        if (!$button) {
            $this->checkErrors();

            return $this->fail('button failed');
        }
        $button->click();

        if (!$this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout)) {
            $this->checkErrors();
            $this->fail('login failed');
        }

        try {
            (new \WebDriverSelect($this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->waitTimeout)))->selectByValue($numberOfMiles);
        } catch (\NoSuchElementException $e) {
            return $this->fail($e->getMessage());
        }

        if (!$this->waitForElement(\WebDriverBy::id('cardName'), $this->waitTimeout)) {
            return $this->fail('cc form not loaded?');
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

        $this->waitForElement(\WebDriverBy::id('termsAndConditions'), $this->waitTimeout)->click();

        $this->waitForElement(\WebDriverBy::xpath('//*[@class="bgt-order-information-form"]//*[@type="submit"]'), $this->waitTimeout)->click();

        if ($this->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"Please complete all of the required fields")])[1]'), $this->loadTimeout)) {
            $this->logger->info('Error: Missing required fields.');

            return false;
        }
        $this->logger->info('Done.');

        // success
        $success = $this->waitForElement(\WebDriverBy::xpath('//*[text()[contains(normalize-space(.), "Thank you for your purchase") or contains(normalize-space(.),"We have received your purchase request")]]'), $this->waitTimeout);
        $this->saveResponse();

        if ($success) {
            $this->ErrorMessage = 'Thank you for your purchase!';

            return true;
        }

        return false;
    }

    public function getPurchaseMilesFields()
    {
        return [
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
            ],
            'AccountNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Mileage Plan Number',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
        ];
    }

    protected function checkErrors()
    {
        $this->saveResponse();

        if ($errors = $this->http->FindNodes('//div[contains(@class, "bgt-form-error") and not(contains(@class, "bgt-form-errors")) and normalize-space(.) != ""]')) {
            throw new \UserInputError(implode(';', $errors));
        }
    }

    protected function fail($s = null)
    {
        if (isset($s)) {
            $this->http->Log($s, LOG_LEVEL_ERROR);
        }
        $this->saveResponse();

        return false;
    }
}
