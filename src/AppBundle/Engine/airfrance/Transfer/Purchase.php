<?php

namespace AwardWallet\Engine\airfrance\Transfer;

use AwardWallet\Engine\ProxyList;

class Purchase extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    protected $waitTimeout = 2;
    protected $loadTimeout = 30;

    public function InitBrowser()
    {
        $this->useSelenium();
        $this->useGoogleChrome();
        $this->http->saveScreenshots = true;
        $this->ArchiveLogs = true;

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->http->SetProxy($this->proxyPurchase());
        } else {
            $this->http->SetProxy($this->proxyReCaptcha());
            //			$this->http->SetProxy($this->proxyDOP());
        }
    }

    public function LoadLoginForm()
    {
        $numberOfMiles = intval($this->TransferFields['numberOfMiles']);

        if ($numberOfMiles <= 0 || $numberOfMiles > 75000 || ($numberOfMiles % 2000 !== 0 && $numberOfMiles !== 75000)) {
            throw new \UserInputError('Number of purchased points should be lesser then 75,000 or less and divisible by 2000.');
        }

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
            $this->http->GetURL('http://www.jdoqocy.com/click-8184014-11285239');
        }
        $this->http->GetURL('https://www.airfrance.us/vlb/ecrm/US/en/local/core/engine/myaccount/DashBoardAction.do?tabDisplayed=milesTab');

        if ($elem = $this->waitForElement(\WebDriverBy::xpath('//button[@class="cookiebar-agree-button-agree"]'), $this->loadTimeout)) {
            $elem->click();
            //		    $this->driver->execute('document.querySelector(\'.cookiebar-agree-button-agree\').click()');
        }
        $this->waitForElement(\WebDriverBy::id('fbNumber'), $this->waitTimeout)->clear();
        $this->waitForElement(\WebDriverBy::id('fbPassword'), $this->waitTimeout)->clear();
        $this->waitForElement(\WebDriverBy::id('fbNumber'), $this->waitTimeout)->sendKeys($this->AccountFields['Login']);
        $this->waitForElement(\WebDriverBy::id('fbPassword'), $this->waitTimeout)->sendKeys($this->AccountFields['Pass']);

        // $this->waitForElement(\WebDriverBy::className('header__account_info'), 60);
        // captcha
        $this->saveResponse();
        $iframe = $this->waitForElement(\WebDriverBy::xpath("//div[@id='id_recaptcha_in_award']//iframe"), 60, false);

        if ($iframe) {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->logger->info("Remove iframe");
            $this->driver->executeScript("$('div#id_recaptcha_in_award iframe').remove();");
            $this->driver->executeScript("$('#g-recaptcha-response').val(\"" . $captcha . "\");");
        } else {
            $this->saveResponse();

            return false;
        }

        if ($elem = $this->waitForElement(\WebDriverBy::id('validate_login'), $this->waitTimeout)) {
            $elem->click();
        }

        if ($callback = $this->http->FindPreg('/callback\s*=\s*function\(\)\s*\{\s*([^;]+;)\s*\};/')) {
            $this->logger->info("Run callback after recaptcha");
            $this->driver->executeScript($callback);
        } else {
            throw new \EngineError('look\'s like recaptcha changed');
        }

        return true;
    }

    public function Login()
    {
        $el = $this->waitForElement(\WebDriverBy::className('header__account_info'), $this->loadTimeout);
        //		$el2 = $this->waitForElement(\WebDriverBy::className('header__user-profile-info'), $this->waitTimeout);
        $el2 = $this->waitForElement(\WebDriverBy::className('bw-profile-recognition-box'), $this->waitTimeout);

        if ($el || $el2) {
            return true;
        }

        if ($elem = $this->waitForElement(\WebDriverBy::xpath("//div[@class='errorValidation'][contains(translate(@style,' ',''),'display:block')]", $this->waitTimeout))) {
            $this->fail('not logged in');

            throw new \UserInputError($elem->getText());
        }
        $this->fail('not logged in or can\'t detected it');

        return false;
    }

    public function purchaseMiles(array $fields, $numberOfMiles, $creditCard)
    {
        $this->http->GetURL('https://www.airfrance.us/vlb/ecrm/US/en/local/voyageurfrequent/partners/RedirectToPoints.do?ssoProduct=buy');

        if (!($elem = $this->waitForElement(\WebDriverBy::id('bgt-offer-dropdown'), $this->loadTimeout))) {
            return $this->fail('Select "bgt-offer-dropdown" not found.');
        }
        $select = new \WebDriverSelect($elem);

        try {
            $select->selectByValue($numberOfMiles);
        } catch (\NoSuchElementException $e) {
            return $this->fail('no option for ' . $numberOfMiles . ' points');
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
                return $this->fail(sprintf('no value %s for select %s', $value, $key));
            }
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
            $this->logger->info("Setting '$inputId' input");
            $this->waitForElement(\WebDriverBy::id($inputId), $this->waitTimeout)->sendKeys($creditCard[$awKey]);
        }
        $this->waitForElement(\WebDriverBy::id('billingEmail'), $this->loadTimeout)->clear()->sendKeys($fields['Email']);
        $this->waitForElement(\WebDriverBy::id('termsAndConditions'), $this->waitTimeout)->click();

        $this->waitForElement(\WebDriverBy::xpath('//*[@class="bgt-order-information-form"]//*[@type="submit"]'), $this->waitTimeout)->click();
        $success = $this->waitForElement(\WebDriverBy::xpath('//*[text()[contains(., "Thank you for your purchase")]]'), $this->loadTimeout);
        $this->saveResponse();

        if ($success) {
            $this->ErrorMessage = 'Thank you for your purchase!';

            return true;
        }
        $errors = $this->http->FindNodes('//div[@class="bgt-form-error" and normalize-space(.) != ""]');

        if (count($errors) > 0) {
            $this->logger->error(var_export($errors, true));
        }

        return false;
    }

    public function getPurchaseMilesFields()
    {
        return [
            'Login' => [
                'Type'     => 'string',
                'Required' => true,
                'Caption'  => 'Flying Blue # or Email',
            ],
            'Email' => [
                'Type'     => 'string',
                'Required' => true,
                'Caption'  => 'Email',
            ],
            'Password' => [
                'Type'     => 'string',
                'Required' => true,
                'Caption'  => 'Password',
            ],
        ];
    }

    protected function parseCaptcha()
    {
        $key = $this->http->FindSingleNode('//script[contains(., "function onCaptchaLoadForAward")]', null, true, '/\'sitekey\'\s*:\s*\'([^\']+)\'/');
        $this->logger->debug("sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $recognizer->CurlTimeout = 30;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    protected function fail($message = null)
    {
        if (isset($message)) {
            $this->logger->error($message);
        }
        $this->http->SaveResponse();

        return false;
    }

    //fake creds
    // kemisumak@binka.me / 6301
}
