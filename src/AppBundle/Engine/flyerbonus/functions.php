<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerFlyerbonus extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

//    function GetRedirectParams($targetURL = null) {
//        $arg = parent::GetRedirectParams($targetURL);
//        $arg["CookieURL"] = 'https://flyerbonus.bangkokair.com/member/';
    ////        $arg["NoCookieURL"] = true;
    ////        $arg["SuccessURL"] = "https://member.flyerbonus.com/FlyerBonus/home_member.aspx";
//
//        return $arg;
//    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->SetProxy($this->proxyReCaptcha(), false); // error: Network error 6 - Could not resolve host: flyerbonus.bangkokair.com
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://flyerbonus.bangkokair.com/member/", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://flyerbonus.bangkokair.com/member/");

        if (!$this->http->ParseForm("form-auth-login")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('USER_LOGIN', $this->AccountFields['Login']);
        $this->http->SetInputValue('USER_PASSWORD', $this->AccountFields['Pass']);
        $this->http->SetInputValue('Login', "");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//div[@class = 'welcome-login']//text()[contains(., 'FlyerBonus website is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // FlyerBonus is temporarily closed for site enchancements.
        if ($message = $this->http->FindSingleNode("
                //p[contains(text(),'FlyerBonus is temporarily closed for ')]
                | //div[@class = 'welcome-login']//text()[contains(., 'FlyerBonus website is now temporarily unavailable due to a system maintenance.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Provider error
        if ($this->http->FindSingleNode("//text()[contains(.,'Call to undefined method classGeneral::pgDatabaseService() in')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//img[contains(@src, "https://flyerbonus.bangkokair.com/welbanner/systemmigration/SYSMI_") and contains(@src, "_mo.jpg")]/@src')) {
            throw new CheckException("The FlyerBonus website will be temporarily unavailable due to system migration", ACCOUNT_PROVIDER_ERROR);
        }

        // [Predis\Connection\ConnectionException]
        //Connection refused [tcp://10.10.10.185:6379] (111)
        if ($this->http->FindSingleNode('//pre[
                contains(text(), "[Predis\Connection\ConnectionException]")
                or contains(text(), "[Predis\Response\ServerException]")
            ]
            | //h1[contains(text(), "502 Bad Gateway")]
        ')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        $message = $this->http->FindSingleNode("//section[@name = 'Login_popup']//font[@class = 'errortext']");
        $this->logger->error("[Error]: {$message}");
        // Incorrect FlyerBonus ID or password
        if (strstr($message, 'Incorrect FlyerBonus ID or password')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, an unexpected error occurred
        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "Sorry, an unexpected error occurred.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $prefix = '//div[@data-dot="<span>1</span>"]';
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("{$prefix}//div[@class = 'card-name']/h4"));
        // FlyerBonus ID
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("{$prefix}//div[@class = 'card-id-m']/strong"));
        // Membership Tier Level
        $this->SetProperty("TierLevel", $this->http->FindSingleNode("{$prefix}//div[@class = 'card-level']/strong"));
        // Balance - Points Available
        $this->SetBalance($this->http->FindSingleNode("//div[@class = 'show-point']"));

        // this request required for QualifyingPoints, QualifyingSectors and Exp Date
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://flyerbonus.bangkokair.com/extension/mercator/pointActivities.php?t=" . date("UB"));
        $this->http->RetryCount = 2;

        $this->http->GetURL("https://flyerbonus.bangkokair.com/extension/mercator/viewQualifying.php?t=" . date("UB"));
        $prefix = '//div[@id = "box_upgrades"]';
        // Qualifying Points (Tier Points)
        $this->SetProperty('QualifyingPoints', $this->http->FindSingleNode("{$prefix}//div[contains(@class, 'qualifying-points')]/span[1]"));
        // Qualifying Sectors
        $this->SetProperty('QualifyingSectors', $this->http->FindSingleNode("{$prefix}//div[contains(@class, 'qualifying-sectors')]/span[1]"));

        // Expiration Date
        $this->http->GetURL("https://flyerbonus.bangkokair.com/extension/mercator/viewPointValid.php?t=" . date("UB"));
        $nodes = $this->http->XPath->query("//ul[contains(@class, 'points-list')]/li");
        $this->logger->debug("Total {$nodes->length} exp nodes were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            // Expiry Date
            $date = strtotime($this->http->FindSingleNode("span[@class = 'date']", $nodes->item($i)));
            // Points
            $miles = $this->http->FindSingleNode("span[@class = 'points']", $nodes->item($i), true, self::BALANCE_REGEXP_EXTENDED);
            $this->logger->debug("{$date} -> {$miles}");

            if ((!isset($exp) || $date < $exp) && $miles > 0) {
                $exp = $date;
                $this->SetExpirationDate($exp);
                // Expiring Balance
                $this->SetProperty('ExpiringBalance', $miles);
            }// if (!isset($exp) || $date < $exp)
        }// for ($i = 0; $i < $nodes->length; $i++)
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://www.bangkokair.com/managing-my-booking';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);

        $this->setProxyGoProxies();
        $this->http->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36');
        $result = $this->seleniumRetrieve("https://www.bangkokair.com/", $arFields);
        $this->sendNotification('check retrieve // MI');

        if (isset($result) && is_string($result)) {
            return null;
        }

        if ($result === false) {
            throw new CheckRetryNeededException(2, 2);
        }

        $data = $this->http->FindPreg("/config:\s*/{(.+?)/},\s*pageEngine/");
        $this->logger->debug($data);
        $data = $this->http->JsonLog($data);

        return null;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindNodes("//a[contains(@href, 'logout')]/@href")
            && !stristr($this->http->currentUrl(), 'https://flyerbonus.bangkokair.com/disconnect.php?logout=yes&act=N')
        ) {
            return true;
        }

        return false;
    }

    private function seleniumRetrieve($url, $arFields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            /*$resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);*/
            //$selenium->seleniumOptions->userAgent = null;
//            $selenium->seleniumOptions->addAntiCaptchaExtension = true;
//            $selenium->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
//            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            //$selenium->http->removeCookies();
            //$selenium->disableImages();

            /*$request = FingerprintRequest::chrome();
            $request->platform = 'Linux x86_64';
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                //print_r($fingerprint->getFingerprint());
                $this->logger->debug($fingerprint->getUseragent());

                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }*/
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

//            $selenium->http->GetURL('https://bot.sannysoft.com/');
//            sleep(5);

            $selenium->http->GetURL($url);
            $manageMyBooking = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(.,"Manage My Booking")]'), 7);

            if ($manageMyBooking) {
                $manageMyBooking->click();
            }

            $pnr = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class,"showOnlymobile")]//input[@placeholder="Booking Reference"]'), 3);
            $lastName = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class,"showOnlymobile")]//input[@placeholder="Last Name"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class,"showOnlymobile")]//input[@placeholder="Last Name"]/../following-sibling::div//button[@type="submit"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$pnr || !$lastName || !$button) {
                return $this->checkErrors();
            }

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $selenium->logger;
            $mover->duration = rand(300, 1000);
            $mover->steps = rand(1, 5);

            //$mover->moveToElement($pnr);
            //$mover->click();
            $mover->sendKeys($pnr, $arFields['ConfNo'], 3);

            //$mover->moveToElement($lastName);
            //$mover->click();
            $mover->sendKeys($lastName, $arFields['LastName'], 3);

//            $pnr->sendKeys($arFields['ConfNo']);
//            $lastName->sendKeys($arFields['LastName']);
            $this->savePageToLogs($selenium);
            $button->click();
            $result = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(),'Reservation number:')]"), 10);

            if ($result) {
                return true;
            }
            $error = $selenium->waitForElement(WebDriverBy::xpath("
            //span[contains(text(),'We are unable to find this confirmation number.')] 
            | //span[contains(text(),'We are unable to find this booking reference.')]"), 0);
            $this->savePageToLogs($selenium);

            if ($error) {
                return $error->getText();
            }

            //$this->savePageToLogs($selenium);
            /*$cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }*/

            return $selenium->http->currentUrl();
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return false;
    }
}
