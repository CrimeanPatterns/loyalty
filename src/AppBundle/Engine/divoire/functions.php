<?php

class TAccountCheckerDivoire extends TAccountChecker
{
    use \AwardWallet\Engine\ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

//        $this->http->GetURL('https://vre.frequentflyer.aero/smilesen/my-account');
//
//        $this->selenium();

//        $this->http->GetURL('https://ifsvre.frequentflyer.aero/StandardWebSite/Login.jsp?activeLanguage=EN&wmode=transparent');
        $reese84 = '3:TCDZJMuYyj5ONiG2TTYQIA==:4T2ih5KYeL8uJ9zYljaMwPMIVFpAIpUbBsRJhRDkTubGglFctVYU5BOMDHQtN9CeW/DPOIYYIQ5iuBoJd467cXGYUh/9e7WR6SapEXUPX/hYDE8Hic42QdizXrFVWo4J1++SbXoytMB3Clq+tQrKai3976nJikSBX9XTSclvTqP1xgYRc+s6/9hzqp8StgpS/NLM31AhQMunbRqquLzwhMnnnQz7SMA56nUVfqEJNujWbuCvLXdbGXvfgTzEnmgDC5on5SEB7ft/cDJBHIaaIKlLKPgp4KrG9/BxhEhPFYZxv6w8YyKM1hFDZgJEEgbq1h0RE3X/Ik+Z2dTo9grBT1Fir01khyHTMqmBasVxrCwbKu2+dAwB2X6eRKcKrjeOerdzPODJc+4OHpS3TKg7T5o7tJoDuG7Fn5LnUujMbykgU0nWghx+TvIVEbKbM1+qk+zlrRTxJ0gkvSwDxW8aj+qXQm2psKLAwWVRbaCJ3M0=:bzUcwlDWTS0BNydRGQQN3dyVyiOIh+hVbO7ZYL7h024=';
        $this->http->setCookie('reese84', $reese84, ".ifsvre.frequentflyer.aero");
        $this->http->GetURL('https://ifsvre.frequentflyer.aero/StandardWebSite/Login.jsp?activeLanguage=EN&wmode=transparent');

//        if ($iframe = $this->http->FindSingleNode('//iframe[contains(@src, "_Incapsula_Resource") and @id = "main-iframe"]/@src')) {
//            $this->http->NormalizeURL($iframe);
        ////            $this->http->GetURL($iframe);
//
//            $this->selenium($iframe);
//
//            $this->http->GetURL('https://ifsvre.frequentflyer.aero/StandardWebSite/Login.jsp?activeLanguage=EN&wmode=transparent');
//        }

        if (!$this->http->ParseForm('form1')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('clickedButton', 'Login');
        $this->http->SetInputValue('txtUser', $this->AccountFields["Login"]);
        $this->http->SetInputValue('txtPass', $this->AccountFields["Pass"]);

        return true;
    }

    public function checkErrors()
    {
        // // The page you requested is in the water hazard.
        // if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'The page you requested is in the water hazard.')]"))
        // throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("//a[contains(text(), 'Logout')]")) {
            return true;
        }

        if ($message = $this->http->FindPreg("/innerHTML = '(INVALID_LOGIN)'/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[@class='LoginName']", null, false,
            '/(?:MONSIEUR|MADAME)\s+(.+)/i')));
        // Balance - Award Miles
        $this->SetBalance($this->http->FindSingleNode("//div[@class='LoginAwd']", null, false, '/:\s+([\d.,]+)/i'));
        // Card Number
        $this->SetProperty('CardNumber',
            $this->http->FindSingleNode("//text()[contains(., 'Card Number ')]", null, false, '/Number\s*(\d+)/'));
        // Status
        $this->SetProperty('Status',
            $this->http->FindSingleNode("//text()[contains(., ' | Tier ')]", null, false, '/Tier\s*([\w\s]+)/'));

        $this->http->GetURL('https://ifsvre.frequentflyer.aero/StandardWebSite/StatusMilesToExpire.jsp?activeLanguage=EN&amp;wmode=transparent');
        // Total Tier Miles
        $this->SetProperty('TotalTierMiles', $this->http->FindSingleNode("//div[contains(text(), 'Total Tier Miles')]/following-sibling::div[1]"));
        // Tier Count
        $this->SetProperty('TierCount', $this->http->FindSingleNode("//div[contains(text(), 'Tier Count')]/following-sibling::div[1]"));

        // Expiration date
        $nodes = $this->http->XPath->query("//div[contains(text(),'Expire table explanation')]/following-sibling::table/tr[@class='row']");
        $minDate = strtotime('01/01/3018');

        foreach ($nodes as $node) {
            $expDate = $this->ModifyDateFormat($this->http->FindSingleNode("td[1]", $node));
            $this->logger->debug("Expiration Date: {$expDate}");
            $expDate = strtotime($expDate, false);
            $balance = $this->http->FindSingleNode("td[2]", $node);

            if ($expDate && $expDate < $minDate && $balance > 0) {
                $minDate = $expDate;
                $this->SetExpirationDate($minDate);
                $this->SetProperty('ExpiringBalance', $balance);
            }
        }
    }

    private function selenium($iframe)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice('Running Selenium...');
            $selenium->UseSelenium();

            $selenium->useGoogleChrome(\Sele);
            $selenium->http->setUserAgent($this->http->userAgent);
//            $selenium->disableImages();

            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

//            $selenium->http->GetURL('https://vre.frequentflyer.aero/smilesen/my-account');
//            $selenium->http->GetURL('https://ifsvre.frequentflyer.aero/StandardWebSite/Login.jsp?activeLanguage=EN&wmode=transparent');
            $selenium->http->GetURL($iframe);

            $frame = $selenium->waitForElement(WebDriverBy::id('main-iframe'), 2);

            if ($frame) {
                $this->savePageToLogs($selenium);
                $selenium->driver->switchTo()->frame($frame);
                $this->savePageToLogs($selenium);
                $openInNewWindowButton = $selenium->waitForElement(WebDriverBy::xpath('//*[contains(text(), "Open Site in New Window")]'), 2);
//                $openInNewWindowButton = $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "openInNewWindowButton"]'), 2);
                $this->savePageToLogs($selenium);
                // open rewards site in the same window
//                $selenium->driver->executeScript('var windowOpen = window.open; window.open = function(url) { windowOpen(url, \'_self\'); }');
                $openInNewWindowButton->click();

                $login = $selenium->waitForElement(WebDriverBy::id('txtUser'), 5);
                $this->savePageToLogs($selenium);
            }

            $this->logger->debug("[current url]: {$selenium->http->currentUrl()}");
            $this->savePageToLogs($selenium);
//            $selenium->driver->executeScript('document.location.href = "https://ifsvre.frequentflyer.aero/StandardWebSite/Login.jsp?activeLanguage=EN&wmode=transparent";');

//            $selenium->driver->switchTo()->frame($selenium->waitForElement(WebDriverBy::id('main-iframe'), 2));
//            $this->savePageToLogs($selenium);
//            $selenium->http->GetURL('https://ifsvre.frequentflyer.aero/StandardWebSite/Login.jsp?activeLanguage=EN&wmode=transparent');

//            sleep(4);
//            $this->savePageToLogs($selenium);
//            $selenium->http->GetURL('https://ifsvre.frequentflyer.aero/');

            $login = $selenium->waitForElement(WebDriverBy::id('txtUser'), 5);
            $pwd = $selenium->waitForElement(WebDriverBy::id('txtPass'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::id('btnSubmit'), 0);

            if (!isset($login, $pwd, $btn)) {
                $this->savePageToLogs($selenium);

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    if ($cookie['name'] !== 'reese84') {
                        continue;
                    }
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                        $cookie['expiry'] ?? null);
                }

                return true;

                return $this->checkErrors();
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pwd->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);

            /*
            $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/uniqueHashCode/g.exec( this.responseText )) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ');
            */

            $btn->click();
            $selenium->waitForElement(WebDriverBy::xpath('//div[@class="LoginName"]'), 6);
//            $responseData = $selenium->driver->executeScript('return localStorage.getItem("responseData");');
//            $this->logger->info('[Form responseData]: ' . $responseData);

            foreach ($selenium->driver->manage()->getCookies() as $cookie) {
                if ($cookie['name'] !== 'reese84') {
                    continue;
                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

            $this->logger->debug('[Current URL]: ' . $selenium->http->currentUrl());
            $this->savePageToLogs($selenium);

//            if (!empty($responseData)) {
//                $this->http->SetBody($responseData);
//            }
        } catch (NoSuchWindowException $e) {
            $this->logger->error('Exception: ' . $e->getMessage());
            $retry = true;
        } finally {
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug('[attempt]: ' . $this->attempt);

                throw new CheckRetryNeededException(3, 7);
            }
        }

        return true;
    }
}
