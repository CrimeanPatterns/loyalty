<?php

/**
 * not used anymore
 * saved it in case we need to go back to selenium.
 */
class CarlsonRewardsPurchaserSelenium extends RewardsPurchaser
{
    public function purchase(array $fields, $numberOfMiles, $creditCard)
    {
        $this->checker->ArchiveLogs = true;
        $secondaryChecker = new CarlsonRewardsPurchaseSeleniumTAccountChecker();
        $secondaryChecker->primaryChecker = $this->checker;
        $secondaryChecker->LogMode = "none"; // TODO: Implement some better solution
        $secondaryChecker->InitBrowser();

        return $secondaryChecker->purchase($fields, $numberOfMiles, $creditCard);
    }

    public static function getPurchaseRewardsFields()
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
}

class CarlsonRewardsPurchaseSeleniumTAccountChecker extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public $timeout = 30;

    /** @var TAccountChecker */
    public $primaryChecker;

    public $loginUrl = 'https://www.clubcarlson.com/secure/login.do';

    protected $accountFields;

    protected $desiredRewardsQuantity;

    protected $creditCard;

    public function InitBrowser()
    {
        $this->InitSeleniumBrowser();
        $this->primaryChecker->http->brotherBrowser($this->http);

        if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_PRODUCTION) {
            $this->log('Setting proxy for Selenium secondary checker');
            $badRegexp = '#You\s+don\'t\s+have\s+permission\s+to\s+access#i';
            $proxy = $this->http->getLiveProxy($this->loginUrl, 15, $badRegexp);

            if ($proxy) {
                $this->http->SetProxy($proxy);
            }
        }
    }

    public function purchase(array $fields, $rewardsQuantity, $creditCard)
    {
        $this->accountFields = $fields;
        $this->desiredRewardsQuantity = $rewardsQuantity;
        $this->creditCard = $creditCard;
        $this->http->driver->start($this->http->GetProxy());
        $this->Start();
        $status = false;

        try {
            $status = $this->purchaseInternal();
        } catch (CheckException $e) {
            $this->saveResponse();
            $this->http->cleanup();

            throw $e;
        } catch (\Exception $e) {
            $this->log($e->getMessage(), LOG_LEVEL_ERROR);
            $this->saveResponse();
            $this->http->cleanup();

            return false;
        }
        $this->http->cleanup();

        return $status;
    }

    public function loginsel()
    {
        $this->log('Logging in');

        $this->driver->get($this->loginUrl);

        $loginInput = $this->waitForElement(WebDriverBy::name('userId'), $this->timeout, false);

        if (!$loginInput) {
            throw new \Exception('Could not find login input');
        }
        $loginInput->sendKeys($this->accountFields['Login']);

        $passwordInput = $this->waitForElement(WebDriverBy::name('password'), $this->timeout);

        if (!$passwordInput) {
            throw new \Exception('Could not find password input');
        }
        $passwordInput->sendKeys($this->accountFields['Password']);

        $loginButton = $this->waitForElement(WebDriverBy::cssSelector('a.forward'));

        if (!$loginButton) {
            throw new \Exception('Could not find login button');
        }
        $loginButton->click();

        $logoutLinkXpath = '//a[normalize-space(@href) = "/secure/logout.do"]';
        $errorsXpath = '//div[@class="globalerrors" or @class="errors"]';

        if ($this->waitForElement(WebDriverBy::xpath($logoutLinkXpath), $this->timeout, false)) {
            $this->log('Success');
        } elseif ($this->waitForElement(WebDriverBy::xpath($errorsXpath), $this->timeout, false)) {
            $errorMessage = '';
            $errorElements = $this->driver->findElements(WebDriverBy::xpath($errorsXpath), $this->timeout, false);

            foreach ($errorElements as $errorElement) {
                $errorMessage .= ' ' . $errorElement->getText();
            }
            $errorMessage = preg_replace('#\s+#i', ' ', trim($errorMessage));

            throw new CheckException($errorMessage, ACCOUNT_INVALID_PASSWORD);
        } else {
            throw new \Exception('Login failed');
        }
    }

    public function loadPurchasePage()
    {
        $this->log('Loading earn points page');
        $this->driver->get('http://www.clubcarlson.com/fgp/earn/points.do');
        $purchasePointsLink = $this->waitForElement(WebDriverBy::linkText('Purchase Points Now'), $this->timeout, false);

        if (!$purchasePointsLink) {
            throw new \Exception('Earn points page loading failed');
        }
        $this->log('Success');

        $this->log('Loading purchase form');
        $purchasePointsLink->click();
        $this->driver->switchTo()->window($this->driver->getWindowHandles()[1]);
        $xpath = '//select[@id="quantity6" and contains(@class, "off-quantities-options-select")]';

        if (!$this->waitForElement(WebDriverBy::xpath($xpath), $this->timeout, false)) {
            throw new \Exception('Purchase form loading failed');
        }
        $this->log('Success');
    }

    protected function logPageSource($logLevel = null)
    {
        $this->log($this->driver->executeScript('return document.documentElement.innerHTML'), $logLevel);
    }

    private function fillPurchaseData()
    {
        $this->log('Setting credit card data');

        $formattedRewardsQuantity = number_format($this->desiredRewardsQuantity);
        $this->driver->executeScript('$("#quantity6.off-quantities-options-select option[label ^= \'' . $formattedRewardsQuantity . ' points\']").prop("selected", true);'); // TODO: Fill with user data
        $this->driver->executeScript('$("#quantity6.off-quantities-options-select").trigger("change");');

        $creditCardTypesMap = [
            'amex' => 'American Express',
            'visa' => 'Visa',
        ];
        $expirationMonth = $this->creditCard['ExpirationMonth'];
        $formattedExpirationMonth = (strlen($expirationMonth) == 1) ? '0' . $expirationMonth : $expirationMonth;
        $expirationMonthLabel = DateTime::createFromFormat('!m', $expirationMonth)->format('F') . ' / ' . $formattedExpirationMonth;
        $expirationYear = $this->creditCard['ExpirationYear'];
        $expirationYear = (strlen($expirationYear) == 2) ? '20' . $expirationYear : $expirationYear;
        $selectInputs = [
            'creditCardType8' => $creditCardTypesMap[$this->creditCard['Type']],
            'expiryMonth11'   => $expirationMonthLabel,
            'expiryYear12'    => $expirationYear,
            'country19'       => $this->creditCard['Country'],
            'state18'         => $this->creditCard['State'], // TODO: Use it only for several countries
        ];

        foreach ($selectInputs as $key => $value) {
            $this->log("Setting \"$key\" to \"$value\"");
            $this->driver->executeScript('$("#' . $key . ' option[label=\'' . $value . '\']").prop("selected", true)');
            $this->driver->executeScript('$("#' . $key . '").trigger("change")');
        }

        if (preg_match('#^(\w+) (\w+)$#i', $this->creditCard['Name'], $m)) {
            // TODO: Implement working with another name formats, maybe pass first and last name to credit card structure
            $firstName = $m[1];
            $lastName = $m[2];
        } else {
            throw new CheckException('Bad name format');
        }
        $textInputs = [
            'creditCardNumber9' => $this->creditCard['CardNumber'],
            'creditCardCvv10'   => $this->creditCard['SecurityNumber'],
            'firstName13'       => $firstName,
            'lastName14'        => $lastName,
            'address115'        => $this->creditCard['AddressLine'],
            'city17'            => $this->creditCard['City'],
            'zipCode20'         => $this->creditCard['Zip'],
            'adr-phone-number'  => $this->creditCard['PhoneNumber'],
            'email2'            => $this->accountFields['Email'],
        ];

        foreach ($textInputs as $key => $value) {
            $this->log("Setting \"$key\" to \"$value\"");
            $this->driver->executeScript('$("#' . $key . '").val("' . $value . '")');
            $this->driver->executeScript('$("#' . $key . '").trigger("change")');
        }

        $this->driver->executeScript('$("input#termsAndConditions4").click()');

        $this->log('Success');
    }

    private function submit()
    {
        $this->log('Submitting data and checking result');
        $this->driver->executeScript('$("button[rel=\'pay-now\'][type=\'submit\']").click()');
        $xpath = '//*[@rel = "payment-error"] | //*[contains(@class, "pts-form-errors")]';

        if ($this->waitForElement(WebDriverBy::xpath($xpath), $this->timeout, false)) {
            $inputErrorElements = $this->driver->findElements(WebDriverBy::xpath($xpath));
            $inputErrors = [];

            foreach ($inputErrorElements as $element) {
                $inputErrors[] = $element->getText();
            }
            // TODO: Move error message formation code from here and from other places to some generic class
            if (count($inputErrors) > 1) {
                $i = 1;

                foreach ($inputErrors as &$ie) {
                    $ie = "$i) $ie";
                    $i++;
                }
                $err = 'Input errors: ' . implode('; ', $inputErrors);
            } elseif (count($inputErrors) == 1) {
                $err = $inputErrors[0];
            } else {
                $this->log('This should never happen');

                return false;
            }

            throw new CheckException($err, ACCOUNT_PROVIDER_ERROR);
        } elseif ($element = $this->waitForElement(WebDriverBy::xpath('//p[@ng-if = "receiptCtrl.isPending" and contains(., "Thank you for your purchase")]'), $this->timeout, false)) {
            $this->log('Provider response: ' . $element->getText());
            $this->log('SUCCESS');
            $this->primaryChecker->ErrorMessage = $element->getText();

            return true;
        } else {
            throw new \Exception('Unsupported server response to submit request');
        }
    }

    private function purchaseInternal()
    {
        $this->printPurchaseParameters();
        $this->loginsel();
        $this->loadPurchasePage();
        $this->fillPurchaseData();

        return $this->submit();
    }

    private function printPurchaseParameters()
    {
        $this->log('Purchasing parameters');
        $this->log('Account fields:');
        $this->log(var_export($this->accountFields, true));
        $this->log('Desired rewards quantity: ' . $this->desiredRewardsQuantity);
        $this->log('Credit card:');
        $this->log(var_export($this->creditCard, true));
    }

    private function log($msg, $loglevel = null)
    {
        $this->http->Log($msg, $loglevel);
    }
}
