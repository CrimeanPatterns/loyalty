<?php

namespace AwardWallet\Engine\finnair\Transfer;

class Purchase extends \TAccountCheckerFinnair
{
    use \PointsDotComSeleniumHelper;

    protected $timeout = 5;

    protected $loadTimeout = 20;

    /**
     * @var Purchase
     */
    protected $seleniumChecker = null;

    public function InitBrowser()
    {
        //		if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
        ////			$this->UseCurlBrowser();
        ////            parent::InitBrowser();
//            $this->http->SetProxy($this->proxyPurchase());
        //		}
        //		else
        parent::InitBrowser();
//        $this->setProxyBrightData(null, "static", "fi");
        $this->setProxyBrightData();
    }

    public function LoadLoginForm()
    {
        $numberOfMiles = intval($this->TransferFields['numberOfMiles']);

        if ($numberOfMiles <= 0 || $numberOfMiles > 100000 || $numberOfMiles % 1000 !== 0) {
            throw new \UserInputError("Number of purchased points should be lesser then 100,000 and divisible by 1000");
        }

        return parent::LoadLoginForm();
    }

    public function Login()
    {
        return parent::Login();
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->http->Log('PointsDotComSeleniumHelper');
        $cookies = $this->http->GetCookies('www.finnair.com', '/', true);
        $this->http->Log('<pre>' . json_encode($cookies, JSON_PRETTY_PRINT) . '</pre>', null, false);

        $http2 = clone $this;
        $this->http->brotherBrowser($http2->http);

        try {
            $this->http->Log('Running Selenium...');
            $http2->InitSeleniumBrowser();
            $http2->useFirefox59();
            $http2->saveScreenshots = true;
            // $http2->disableImages();
            $http2->http->start();
            $http2->Start();

            $this->seleniumChecker = $http2;

            $http2->http->GetURL('https://www.finnair.com/INT/GB/plus');

            foreach ($cookies as $key => $value) {
                $http2->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => "www.finnair.com"]);
            }
            // start buying
            // account form
            // $http2->http->GetURL('https://www.finnair.com/int/gb/finnair-plus/buy-points');

            // if (!($frame = $http2->waitForElement(\WebDriverBy::xpath('//iframe[contains(@src,"ssogateway.points.com")]'), $this->loadTimeout)))
            //     return $this->clear('frame with storefront not found');
            // $http2->driver->switchTo()->frame($frame);
            $this->http->GetURL('https://www.finnair.com/int/gb/finnair-plus/buy-points');
            $buyUrl = $this->http->FindSingleNode('//iframe[contains(@src, "https://ssogateway.points.com")]/@src');
            $http2->http->GetURL($buyUrl);

            //step 1
            if (!$http2->waitForElement(\WebDriverBy::xpath('//form[@data-name="transactionForm"]//select[@id="bgt-offer-dropdown"]'), $this->loadTimeout)) {
                $this->checkLoginFormErrors();

                return $this->clear('Buy form not loaded');
            }

            if (!($elem = $http2->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout))) {
                return $this->clear('Select "bgt-offer-dropdown" not found.');
            }
            $optionXpath = sprintf('//select[@id = "bgt-offer-dropdown"]//option[@value = "%s"]', $numberOfMiles);

            if ($option = $http2->waitForElement(\WebDriverBy::xpath($optionXpath), $this->timeout)) {
                $option->click();
                $this->logger->info('Select ' . $numberOfMiles . ' points done.');
            } else {
                return $this->clear('Didn\'t find option for ' . $numberOfMiles . ' points.');
            }

            if (!$http2->waitForElement(\WebDriverBy::id('cardName'), $this->loadTimeout)) {
                $this->checkLoginFormErrors();

                return $this->clear('Credit Card form not loaded.');
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
                    (new \WebDriverSelect($http2->waitForElement(\WebDriverBy::id($selectFieldId), $this->timeout)))->selectByValue($value);
                } catch (\NoSuchElementException $e) {
                    return $this->clear($e->getMessage());
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
                $http2->waitForElement(\WebDriverBy::id($inputId), $this->timeout)->sendKeys($creditCard[$awKey]);
                $this->logger->info('Done.');
            }
            $this->logger->info('Setting "Email" to input "billingEmail" ...');

            if ($elem = $http2->waitForElement(\WebDriverBy::id('billingEmail'), $this->timeout)) {
                $elem->clear();
                $elem->sendKeys($fields['Email']);
                $this->logger->info('Done.');
            } else {
                $this->logger->info('Could not find input field for Email.');

                return false;
            }
            $http2->waitForElement(\WebDriverBy::id('termsAndConditions'), $this->timeout)->click();

            $http2->waitForElement(\WebDriverBy::xpath('//*[@class="bgt-order-information-form"]//*[@type="submit"]'), $this->timeout)->click();

            if ($http2->waitForElement(\WebDriverBy::xpath('(//div[@class="bgt-form-error" and contains(.,"Please complete all of the required fields")])[1]'), $this->loadTimeout)) {
                $this->logger->info('Error: Missing required fields.');

                return false;
            }
            $this->logger->info('Done.');

            // success
            $success = $http2->waitForElement(\WebDriverBy::xpath('//*[text()[contains(normalize-space(.), "Thank you for your purchase") or contains(normalize-space(.),"We have received your purchase request")]]'), $this->loadTimeout);
            $http2->saveResponse();
            $http2->http->cleanup();

            if ($success) {
                $this->ErrorMessage = 'Thank you for your purchase!';

                return true;
            }

            return false;
        } catch (\InstanceThrottledException $e) {
            return $this->clear($e->getMessage());
        } catch (\WebDriverCurlException $e) {
            return $this->clear($e->getMessage());
        } catch (\UnknownServerException $e) {
            return $this->clear($e->getMessage());
        } catch (\Exception $e) {
            $this->http->Log($e->getMessage(), LOG_LEVEL_ERROR);

            if (isset($this->seleniumChecker)) {
                $this->seleniumChecker->http->cleanup();
            }

            return false;
        }
    }

    public function getPurchaseMilesFields()
    {
        return [
            "Login" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "User Name",
            ],
            "Password" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Password",
            ],
            "Email" => [
                "Type"     => "string",
                "Required" => true,
                "Caption"  => "Email Address",
            ],
        ];
    }

    protected function clear($message = null)
    {
        if (isset($message)) {
            $this->http->Log($message, LOG_LEVEL_ERROR);
        }
        $this->seleniumChecker->saveResponse();

        if (isset($this->seleniumChecker)) {
            $this->seleniumChecker->http->cleanup();
        }

        return false;
    }

    protected function checkLoginFormErrors()
    {
        if ($error = $this->seleniumChecker->waitForElement(\WebDriverBy::xpath('//div[@class="bgt-form-error" and normalize-space(.) != ""]'), $this->timeout)) {
            throw new \UserInputError(CleanXMLValue($error->getText()));
        }
    }

    /** test credentials.
    "Email" : "whatever@whatever.com"
     */
}
