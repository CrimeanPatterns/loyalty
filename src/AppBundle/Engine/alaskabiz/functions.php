<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAlaskabiz extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && !str_starts_with($properties['SubAccountCode'], 'alaskabizDiscountCode')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        /*
        $this->useFirefox();
        */

        $this->useFirefoxPlaywright();
//        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->setProxyGoProxies();
//        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL('https://easybiz.alaskaair.com/ssl/coprofile/myeasybizactivity.aspx?view=miles');
        $el = $this->waitForElement(WebDriverBy::xpath('//input[@id = "userIdInput"] | //div[@id = "FormUserControl__mileagePlanAccountDetail__mileagePlanInfo"]'), 7);

        if ($el && stripos($el->getText(), 'Available miles') !== false) {
            return true;
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "userIdInput"]'), 0);
        $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@id = "Password"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//input[@id = "ezbSignIn"]'), 0);

        if (!isset($login, $pwd, $btn)) {
            $this->saveResponse();

            return false;
        }
        $this->driver->executeScript('let remMe = document.getElementById("EZBRememberMe"); if (remMe != null) remMe.checked = true;');
        $login->sendKeys($this->AccountFields['Login']);
        $pwd->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $btn->click();
        $this->waitForElement(WebDriverBy::xpath('//div[@class="errorText errorTextSummary"] | //div[@id = "FormUserControl__mileagePlanAccountDetail__mileagePlanInfo"]'), 10);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($error = $this->http->FindSingleNode('//span[@id="_message"]')) {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/This feature is temporarily unavailable because our database is currently down. Please try again later /ims")) {
            throw new CheckException('Alaska Airlines website is experiencing technical difficulties, please try to check your balance at a later time.', ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Our Mileage Plan System is temporarily down')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Our Mileage Program System is undergoing scheduled maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our Mileage Plan™ system is temporarily unavailable both at alaskaair.com and through our Reservations call center.
        if ($message = $this->http->FindPreg("/(Our Mileage Plan&#8482; system is temporarily unavailable both at alaskaair.com and through our Reservations call center\.<br>Please try again later\.)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->currentUrl() == 'https://easybiz.alaskaair.com/signin?url=https://easybiz.alaskaair.com/ssl/coprofile/MyEasyBizActivity.aspx?view=miles&Action=TimedOut') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->saveResponse();

        // The User ID and password entered is not valid for access to EasyBiz.
        // The sign-in information entered does not match our records. Please try again.
        if ($message = $this->http->FindSingleNode("//div[@id = 'errorTextSummaryId']", null, true, "/Error\s*((?:The sign-in information entered does not match our records.*|The User ID and password entered is not valid for access to EasyBiz.*|The allowed number of sign-in attempts has been reached. This User ID has been temporarily disabled\. Please try again later\.$))/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/My Account is currently unavailable and will be restored as quickly as possible/')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->checkErrors();

        return true;
    }

    public function Parse()
    {
        // Balance - Available miles
        $this->SetBalance(str_replace(',', '', $this->http->FindSingleNode('//div[@id = "FormUserControl__mileagePlanAccountDetail__mileagePlanInfo"]', null, true, '/Available miles:\s+([\d,]+)/')));
        // Member name
        $this->SetProperty('CompanyName', beautifulName($this->http->FindSingleNode('//span[@class="navbar-company-name populate-company-name"]')));
        // Mileage Plan number
        $this->SetProperty('Number', $this->http->FindSingleNode('//div[@id = "FormUserControl__mileagePlanAccountDetail__mileagePlanInfo"]', null, true, '/Mileage Plan number:\s+(\d+)/'));

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && ($message = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Our Mileage Plan™ system is temporarily unavailable both at alaskaair.com and through our Reservations call center.")]'), 0))
        ) {
            $this->SetWarning($message->getText());
        }

        // $this->http->GetURL('https://easybiz.alaskaair.com/ssl/coprofile/myeasybizactivity.aspx?view=transactions');
        $linkToTransactions = $this->waitForElement(WebDriverBy::id('FormUserControl__tabMenu_ctl01__tabMenuItemPanel'), 0);

        if (!$linkToTransactions) {
            if (
                $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && ($message = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We are experiencing issues with the EasyBiz travel portal")]'), 0))
            ) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }

        $linkToTransactions->click();
        $this->waitForElement(WebDriverBy::xpath('//div[@id = "FormUserControl__walletTransactions__walletBalance"]'), 3);
        $this->saveResponse();

        // Available Balance
        $walletBalance = PriceHelper::cost($this->http->FindSingleNode('//div[@id = "FormUserControl__walletTransactions__walletBalance"]', null, true, '/\$([\d.,]+)/'));

        if (is_numeric($walletBalance)) {
            $this->AddSubAccount([
                'Code'        => 'EasyBizWallet',
                'DisplayName' => 'EasyBiz® Wallet',
                'Balance'     => '$' . $walletBalance,
            ]);

            if ($walletBalance > 0) {
                // $this->http->GetURL('https://easybiz.alaskaair.com/ssl/coprofile/myeasybizactivity.aspx?view=expiration');
                $linkToExpirations = $this->waitForElement(WebDriverBy::id('FormUserControl__tabMenu_ctl02__tabMenuItemPanel'), 0);

                if (!$linkToExpirations) {
                    return;
                }
                $linkToExpirations->click();
                $this->waitForElement(WebDriverBy::xpath('//table[@summary = "Wallet Expiration Dates"]/tbody'), 3);
                $this->saveResponse();

                $now = time();
                $certificates = $this->http->XPath->query('//table[@summary = "Wallet Expiration Dates"]/tbody/tr[./td]');
                $this->logger->info("found {$certificates->count()} certificates");

                foreach ($certificates as $certificate) {
                    $expStr = $this->http->FindSingleNode('td[2]', $certificate) ?? '';
                    $exp = strtotime($expStr);
                    $balance = PriceHelper::cost($this->http->FindSingleNode('td[last()]', $certificate, true, '/\$([\d.,]+)/'));
                    $displayName = $this->http->FindSingleNode('td[3]', $certificate);

                    if (!$exp) {
                        $this->logger->error("skip '$displayName' - expiration date not parsed");

                        continue;
                    }

                    if ($exp < $now) {
                        $this->logger->info("break on '$displayName' - already expired on $expStr");

                        break;
                    }

                    if ($balance === null) {
                        $this->logger->error("skip '$displayName' - balance not parsed");

                        continue;
                    }

                    if ($balance == 0.00) {
                        $this->logger->info("skip '$displayName' - zero balance");

                        continue;
                    }
                    $code = $this->http->FindSingleNode('td[3]', $certificate, true, '/:\s+(\w+)/');
                    $this->AddSubAccount([
                        'Code'           => 'Certificate' . $code,
                        'DisplayName'    => $displayName,
                        'Balance'        => '$' . $balance,
                        'ExpirationDate' => $exp,
                    ]);
                }
            }
        }

        // $this->http->GetURL('https://easybiz.alaskaair.com/ssl/coprofile/myeasybizactivity.aspx?view=discounts');
        $linkToDiscounts = $this->waitForElement(WebDriverBy::id('FormUserControl__tabMenu_ctl06__tabMenuItemPanel'), 0);

        if (!$linkToDiscounts) {
            return;
        }
        $linkToDiscounts->click();

        $this->waitForElement(WebDriverBy::xpath('//table[@id="FormUserControl__validDiscountCodes__eCertificateList_eCertsTable"] | //*[@id="FormUserControl__validDiscountCodes__eCertificateList__noeCertsMessage"]'), 5);
        $this->saveResponse();

        foreach ($this->http->XPath->query('//table[@id="FormUserControl__validDiscountCodes__eCertificateList_eCertsTable"]//tr[not(@id)]') as $discount) {
            $code = $this->http->FindSingleNode('td/a[starts-with(@href, "javascript:showRoutes")]/@href', $discount, true, "/showRoutes\('([^']+)/");
            $type = $this->http->FindSingleNode('td[2]', $discount);
            $expiration = strtotime($this->http->FindSingleNode('td[last()]', $discount) ?? '');

            if ($code && $type && $expiration) {
                $this->AddSubAccount([
                    'Code'           => "DiscountCode$code",
                    'DisplayName'    => "$type. Code $code",
                    'Balance'        => null,
                    'ExpirationDate' => $expiration,
                ]);
            }
        }

        $this->http->GetURL('https://www.alaskaair.com/account/overview');
        $this->waitForElement(WebDriverBy::xpath('//span[@class = "mp-info__name"]'), 5);
        $this->saveResponse();
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//span[@class = "mp-info__name"]', null, true, '/([\w ]+)\s+- Mileage/')));
    }
}
