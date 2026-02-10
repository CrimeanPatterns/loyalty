<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerEva extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    private $PostForm = [];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        if ($this->attempt == 1) {
            $this->http->SetProxy($this->proxyReCaptcha());
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://eservice.evaair.com/flyeva/eva/ffp/frequent-flyer.aspx", [], 20);

        // crocked server workaround
        if ($this->http->Response['code'] == 403) {
            $this->http->SetProxy($this->proxyDOP());
            $this->http->GetURL("https://eservice.evaair.com/flyeva/eva/ffp/frequent-flyer.aspx", [], 20);
        }

        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://eservice.evaair.com/flyeva/EVA/FFP/login.aspx");
        $this->http->setCookie("EVATIMESPAN", "1", ".evaair.com");
        $this->http->setCookie("EVALOGINTIME", date("Ydm H:i:s"), ".evaair.com");

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "~System Maintenance Announcement~")]/following-sibling::div/div[contains(., "System is unavailable for maintenance.")]', null, true, "/諒!!\s(.*)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (!$this->http->ParseForm("User_Page")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('ctl00$content$wuc_login$txt_Member', $this->AccountFields["Login"]);
        $this->http->SetInputValue('ctl00$content$wuc_login$txt_Password', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('ctl00$content$wuc_login$hid_url', $this->http->currentUrl());
        $this->http->SetInputValue('ctl00$__MasterEVENTTARGET', 'ctl00$content$wuc_login$btn_Login_Server');
        $this->http->SetInputValue('ctl00$content$wuc_login$hid_DateTime', date("Y/d/m H:i:s"));
        $this->http->SetInputValue('ctl00$content$wuc_login$Chk_RmbrMbrID', "on");
        $this->http->SetInputValue('languageLocation', "North+America");
        $this->http->SetInputValue('languageSelector', "4");
        unset($this->http->Form['ctl00$MainContent$wuc_login$Chk_RmbrMbrID']);
        unset($this->http->Form['SETMarket']);
        unset($this->http->Form['cookieCheck']);

        return $this->seleniumAuth();

        return false;

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue('ctl00$content$wuc_login$grecaptcharesponse', $captcha);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue('ctl00$content$wuc_login$txt_CaptchaCode', $captcha);

        return true;
    }

    public function Login()
    {
        /*
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        */

        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        // Invalid credentials
        /*        if (($message = $this->http->FindPreg("/show_msg\(['\"]([^';]+)\"\);/ims"))
                    && !strstr($message, 'Lib_FFP.of_Get_mth_MileDataHTML')
                    && !strstr($message, 'sErrmsg')) {
                    throw new CheckException(str_replace('<a ', '<a target="_blank" ', $message), ACCOUNT_INVALID_PASSWORD);
                }
                /**
                 * You have input wrong password four times. If you forget your password, please refer to “Forgot Password” first.
                 * For privacy security policy, If the password is input incorrectly for 5 times,
                 * the log-in function will be locked until you finish the procedure of “Forgot Password'.
                 * /
                elseif (($message = $this->http->FindPreg("/show_msg\(\"((?:You have input wrong password [^;]+|Notice: If the password is input incorrectly for 5 times,[^;]+))\"\);/ims"))
                    && !strstr($message, 'Lib_FFP.of_Get_mth_MileDataHTML')) {

                    $this->logger->error($message);
                    $message = str_replace('<a ', '<a target="_blank" ', $message);
                    if (strstr($message,
                        'password five times.<br>For privacy security policy, the log-in function will be inaccesssible until you finish the procedure of')) {
                        throw new CheckException($message, ACCOUNT_LOCKOUT);
                    } else {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }
                } elseif ($message = $this->http->FindPreg("/show_msg\(\"(You have not applied for the password yet),/ims")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }*/
        if ($message = $this->http->FindSingleNode('//div[@id="wuc_Error"]/descendant::li')) {
            $this->logger->error($message);

            if ($message == 'Wrong CAPTCHA entry. Please try again.') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if (
                stripos($message, 'No data found. Please input correct Membership Number, E-Mail Address or Username!') !== false
                || (stripos($message, 'You have input wrong password') !== false && stripos($message, 'You have input wrong password five times') == false)
                || strstr($message, 'Currently the system is unable to log in by E-Mail Address.')
                || strstr($message, 'You have not applied for the password yet, please click here to acquire your password. Thank you!')
                || strstr($message, 'Incomplete Membership Number or E-Mail Address or Username.')
                || $message == 'Invalid Membership Number.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                stripos($message, 'You have input wrong password five times. For privacy security policy, the log-in function will be inaccesssible until you finish the procedure of “Forgot Password".') !== false
                || stripos($message, 'For privacy security policy, the log-in function will be inaccesssible until you finish the procedure') !== false
                || stripos($message, 'The functions of on-line services for this member had been terminated. If you want to use this system again please contact Infinity MileageLands Service Center.') !== false
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (stripos($message, 'The system is temporarily unavailable, so please try it later. We apologize for any inconvenience that might have caused you.') !== false) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (stripos($message, 'Your password strength is not strong enough. Please check your email for the password reset. It will take you to a page where you can reset your password.') !== false) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//span[contains(text(), "Join Infinity MileageLands")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//title[contains(text(), "Request Rejected")]')) {
            throw new CheckRetryNeededException(2);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance -  Self Award Miles
        // refs #5696
        $this->SetBalance($this->http->FindSingleNode('//div[h3[contains(text(), "Self Award Miles")]]/following-sibling::p/span[contains(@class, "color-green") and contains(@class, "vertical-baseline")]'));
        // Membership status
        $this->SetProperty('Status', Html::cleanXMLValue($this->http->FindSingleNode("//dt[contains(text(), 'Your current membership status is')]", null, true, "/([A-Za-z]+)\s*card/ims")));

        // Expiration Date  // refs #19216
        // Search all dates with miles > 0
        $nodes = $this->http->XPath->query("//div[h3[contains(text(), 'Own Earned miles which will expire within 6 months')]]/following-sibling::table//tr[td[3]]");
        $this->logger->debug("Total {$nodes->length} exp nodes were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $miles = $this->http->FindSingleNode("td[2]", $node);

            if ($miles > 0) {
                $expire[] = [
                    'date'  => "01 " . $this->http->FindSingleNode("td[1]", $node),
                    'miles' => $miles,
                ];
            }// if ($miles > 0)
        }// for ($i = 0; $i < $nodes->length; $i++)

        if (isset($expire)) {
            // Find the nearest date with non-zero balance
            $N = count($expire);
            $this->logger->debug(">>> Total nodes with expiring miles " . $N);
            // Log
            $this->logger->debug(var_export($expire, true), ['pre' => true]);
            $i = 0;

            while (($N > 0) && ($i < $N)) {
                if ($date = strtotime($expire[$i]['date'])) {
                    $this->SetExpirationDate($date);
                    // Set Property 'Expiring Balance' (Mileage Balance)
                    $this->SetProperty('ExpiringBalance', $expire[$i]['miles']);

                    break;
                }// if ($date = strtotime($expire[$i]['date']))
                $i++;
            }// while (($N > 0 ) && ($i < $N))
        }// if (isset($expire))
        elseif ($this->http->FindSingleNode('//h3[contains(text(), "There is no mile which will be expired within 6 months.")]')) {
            $this->ClearExpirationDate();
        }

        // Needed Status Miles to Next Level
        $this->SetProperty('EarnedFlightMiles', $this->http->FindSingleNode('//p/node()[contains(normalize-space(.), "more Status Miles")]/preceding-sibling::span[1]'));
        // Needed Sectors to Next Level
        $this->SetProperty('EarnedSectors', $this->http->FindSingleNode('//p/node()[contains(normalize-space(.), "more Status Miles")]/following-sibling::span[1]'));

        if (!isset($this->Properties['EarnedSectors']) && isset($this->Properties['EarnedFlightMiles'])) {
            $this->SetProperty('EarnedSectors', $this->http->FindSingleNode('//div[h2[contains(normalize-space(text()), "Upgrade")]]/following-sibling::div[2]//p[contains(normalize-space(.), "more Sectors")]/node()[contains(normalize-space(.), "You need to obtain")]/following-sibling::span[1]'));
        }
        // (Validity) Status Expiration Date
        $this->SetProperty('StatusExpirationDate', $this->http->FindSingleNode('//dt[contains(text(), "Validity")]/following-sibling::dd', null, true, "/\-\s*([^<]+)/"));
        // Membership Number
        $this->SetProperty('MemberNo', $this->http->FindSingleNode('//span[contains(text(), "Membership Number:")]/following-sibling::span'));
        // Name
        $this->SetProperty('Name', beautifulName(Html::cleanXMLValue($this->http->FindSingleNode('(//p[contains(text(), "Hello,")]/span)[1]'))));
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->FilterHTML = false;
        $this->http->GetURL("https://eservice.evaair.com/flyeva/eva/ffp/frequent-flyer.aspx");

        if ($this->http->FindSingleNode("//h2[contains(.,'Flight Preparation')]/following-sibling::div[1]/p[1][contains(normalize-space(text()),'You do not have any upcoming trip. Book a flight now!')]")) {
            $noIts = true;
        }
        $itinerariesURL = "https://booking.evaair.com/flyeva/EVA/B2C/manage-your-trip/reservation-reference.aspx";
        $this->http->GetURL($itinerariesURL);

        if ($msg = $this->http->FindSingleNode("//div[contains(text(),\"We're busy updating the website for you and will be back shortly.\")]")) {
            $this->logger->error($msg);

            return [];
        }
        $this->evaPostForm();
        $this->evaCompanionPostForm();
        // sometimes... one more time
        $this->evaPostForm();

        /* when it was shown?
           Most likely when there are no reservations*/
        if ($msg = $this->http->FindSingleNode('//*[self::p or self::li][contains(text(), "Your booking record could not be retrieved.")]')) {
            if (isset($noIts)) {
                return $this->noItinerariesArr();
            }
            $this->logger->error($msg);

            return [];
        }

        // When one reservation in your profile
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Manage Your Trip')]")) {
            if ($msg = $this->http->FindSingleNode("//li[
                    contains(text(), 'Sorry, there is no booked itinerary under your booking number.')
                    or contains(normalize-space(text()), \"Sorry! Your booking record cannot be retrieved.Please check your data and try again!Thank you!\")
                ]")
            ) {
                $this->logger->error($msg);

                return $this->noItinerariesArr();
            }

            if ($this->http->FindSingleNode("//p[contains(normalize-space(text()), 'You can manage your trip by entering your booking information')]")) {
                if (isset($noIts)) {
                    return $this->noItinerariesArr();
                }

                return [];
            }

            $it = $this->parseItinerary();

            if ($it === false) {
                $this->parseItinerary();
                $this->sendNotification('retry parse it // MI');
            }
        } elseif ($this->http->ParseForm('User_Page')) {
            $this->PostForm = $this->http->Form;

            $targets = $this->http->XPath->query('//button[@data-act = "View"]');
            $this->logger->debug('Total itineraries were found: ' . $targets->length);

            foreach ($targets as $target) {
                $this->logger->notice("");
                $this->logger->notice("");
                $this->logger->notice("[Key]: " . $target->getAttribute('data-key'));
                $this->http->Form = $this->PostForm;
                $this->http->FormURL = 'https://booking.evaair.com/flyeva/EVA/B2C/manage-your-trip/reservation-reference.aspx';
                $this->http->SetInputValue('ctl00$__MasterEVENTTARGET', 'View');
                $this->http->SetInputValue('ctl00$__MasterEVENTARGUMENT', $target->getAttribute('data-key'));
                $this->http->PostForm();

                if ($err = $this->http->FindSingleNode("//div[@id='content_wuc_Error']//li[contains(text(),'For domestic flights on UNI Air, visit')]")) {
                    $this->logger->error($err);

                    continue;
                }

                $this->evaPostForm();

                $this->parseItinerary();
            }
        }

        if (
            count($this->itinerariesMaster->getItineraries()) == 0
            && (
                $this->http->FindSingleNode('//li[contains(normalize-space(text()), "Your booking record could not be retrieved. If the booking was made by a travel agent, it is possible that no Infinity MileageLands membership number was appended to the record.")]')
                || $this->http->FindSingleNode('//li[contains(normalize-space(text()), "Sorry！Your booking record cannot be retrieved.")]')
                || isset($noIts)
            )
        ) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"    => [
                "Caption"  => "Reference No.",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName"  => [
                "Type"     => "string",
                "Caption"  => "Family Name",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Caption"  => "Given Name",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://booking.evaair.com/flyeva/EVA/B2C/manage-your-trip/log_in.aspx";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("User_Page")) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }
        $this->http->SetInputValue('ctl00$content$wuc_PNR$txt_Code', $arFields['ConfNo']);
        $this->http->SetInputValue('ctl00$content$wuc_PNR$txt_LastName', $arFields['LastName']);
        $this->http->SetInputValue('ctl00$content$wuc_PNR$txt_FirstName', $arFields['FirstName']);
        $this->http->SetInputValue('ctl00$__MasterEVENTTARGET', 'btn_PNR_Login');

        if (!$this->http->PostForm()) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }
        // Sorry! Your booking record cannot be retrieved. Please check your data and try again! Thank you!
        if ($error = $this->http->FindSingleNode("//div[@id = 'content_wuc_Error']//li")) {
            return $error;
        }

        if ($error = $this->http->FindSingleNode("//h1[contains(normalize-space(text()),'~System Maintenance Announcement~') or contains(normalize-space(text()),'～系 統 維 護 公 告～')]")) {
            return $error;
        }
        $this->evaPostForm();

        $this->parseItinerary();

        return null;
    }

    public function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useGoogleChrome();
//            $selenium->setKeepProfile(true);

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://eservice.evaair.com/flyeva/EVA/FFP/login.aspx");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'content_wuc_login_Account']"), 7);

            if ($accept = $selenium->waitForElement(WebDriverBy::xpath("//button[@data-all=\"Accept All Cookies\"]"), 0)) {
                $this->savePageToLogs($selenium);
                $accept->click();
            }

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'content_wuc_login_Password']"), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(., 'Login')]"), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $selenium->driver->executeScript('document.querySelector(\'input[id="content_wuc_login_Remember"]\').checked = true;');
            $this->savePageToLogs($selenium);

            $captchaCode = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'content_wuc_login_CaptchaCode']"), 0);
            $captcha = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "c_eva_ffp_login_logincaptcha_CaptchaDiv"]'), 0);

            if (!$captchaCode || !$captcha) {
                return false;
            }

            $x = $captcha->getLocation()->getX();
            $y = $captcha->getLocation()->getY() - 200;
            $selenium->driver->executeScript("window.scrollBy($x, $y)");
            $this->savePageToLogs($selenium);
            $imageData = $selenium->driver->executeScript("
                let canvas = document.createElement('CANVAS'),
                    ctx = canvas.getContext('2d'),
                    img = document.getElementById('c_eva_ffp_login_logincaptcha_CaptchaImage');

                canvas.height = img.height;
                canvas.width = img.width;
                ctx.drawImage(img, 0, 0);
                let imageData = canvas.toDataURL('image/png');
                
                return imageData;             
            ");

            if (!$imageData) {
                $this->logger->error('Failed to get screenshot of iFrame with captcha');

                return false;
            }

            $imageData = str_replace('data:image/png;base64,', '', $imageData);
//            $this->logger->debug("png;base64: {$imageData}");

            if (!empty($imageData)) {
                $this->logger->debug("decode image data and save image in file");
                // decode image data and save image in file
                $imageData = base64_decode($imageData);
                $image = imagecreatefromstring($imageData);
                $file = "/tmp/captcha-" . getmypid() . "-" . microtime(true) . ".png";
                imagejpeg($image, $file);
            }

            if (!isset($file)) {
                return false;
            }

            $this->logger->debug("file: " . $file);

            $this->recognizer = $this->getCaptchaRecognizer();
            $this->recognizer->RecognizeTimeout = 120;

            $captcha = $this->recognizeCaptcha($this->recognizer, $file);
            unlink($file);

            $captchaCode->click();
            $captchaCode->sendKeys($captcha);

            $this->logger->debug("click by btn");
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('//div[h3[contains(text(), "Self Award Miles")]]/following-sibling::p/span[contains(@class, "color-green") and contains(@class, "vertical-baseline")] | //div[@id="wuc_Error"]/descendant::li'), 10);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (Facebook\WebDriver\Exception\UnknownErrorException | WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/\"([^\"]+)\",\s*\{\s*action:\s*\"wucLogin/");

        if (!$key) {
            return false;
        }

        /*
        $postData = [
            "type"       => "RecaptchaV3TaskProxyless",
            "websiteURL" => $this->http->currentUrl(),
            "websiteKey" => $key,
            "minScore"   => 0.3,
            "pageAction" => "wucLogin",
            "apiDomain"  => "www.recaptcha.net",
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        */

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "action"    => "wucLogin",
            "min_score" => 0.3,
            "domain"    => "recaptcha.net",
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $link = $this->http->FindSingleNode("//img[@id = 'c_eva_ffp_login_logincaptcha_CaptchaImage']/@src");

        if (!$link) {
            return false;
        }

        $this->http->NormalizeURL($link);
        // https://eservice.evaair.com/FLYEVA/BotDetectCaptcha.ashx?get=image&c=c_eva_ffp_login_logincaptcha&t=678f503f20984c39a2055e0a43bee448
        $this->logger->debug("Download Image by URL: {$link}");
        $recognizer = $this->getCaptchaRecognizer();

        return $this->recognizeCaptchaByURL($recognizer, $link, 'jpg');
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//div[h3[contains(text(), "Self Award Miles")]]/following-sibling::p/span[contains(@class, "color-green") and contains(@class, "vertical-baseline")]', null, false) != null) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // In an on-going effort of improving our online services, the EVA Air website will be down for maintenance
        if ($message = $this->http->FindSingleNode('
                //h1[contains(text(), "EVA Air Website Maintenance Announcement")]
                | //p[contains(text(), "In an on-going effort of improving our online services, the EVA Air website will be down for maintenance")]
                | //text()[contains(., "In order to provide the best quality to our customers, EVA Air Official Website will be currently unavailable for upgrade maintenance")]
                | //div[contains(text(), "We\'re busy updating the website for you and will be back shortly.")]
')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // System is down for maintenance
        if ($message = $this->http->FindPreg("/(System is down for maintenance)/ims")
                ?? $this->http->FindPreg("/Due to the network issue, our website is temporarily unavailable\. We sincerely appreciate your understanding and thank you for your consideration of any inconvenience caused\./")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The resource cannot be found
        if ($message = $this->http->FindSingleNode("//i[contains(text(), 'The resource cannot be found')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            || $this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Couldn\'t resolve host \'eservice\.evaair\.com\')/ims")
            // Service Unavailable
            || $this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable")]')
            || $this->http->FindPreg('/<h1>Service Unavailable<\/h1>/ims')
            || $this->http->FindPreg('/<TITLE>Service Unavailable<\/TITLE>/ims')
            || in_array($this->http->Response['code'], [500, 302])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->AccountFields["Login"] == '1306579363' && ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, this page is not available. You may be following a broken link or outdated link on this site. You can continue using the following links or use the search box')]"))) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //	    if ($this->http->Response['code'] == 404)
//            throw new CheckRetryNeededException(3, 10);

        return false;
    }

    private function evaPostForm()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->ParseForm("PostForm")) {
            $this->logger->info("FormURL: " . $this->http->FormURL);
            $this->http->FormURL = str_replace('https://booking.evaair.com//booking.evaair.com', 'https://booking.evaair.com', $this->http->FormURL);
            $this->http->RetryCount = 0;
            $this->http->PostForm([], 90);
            $this->http->RetryCount = 2;
        }
    }

    private function evaCompanionPostForm()
    {
        if (strpos($this->http->currentUrl(), '/manage-your-trip/companion.aspx') !== false
            && $this->http->FindSingleNode("//h1[contains(text(), 'Input Passenger')]")
        ) {
            if ($this->http->ParseForm("User_Page")) {
                $this->logger->info("FormURL: " . $this->http->FormURL);
                $this->http->FormURL = str_replace('https://booking.evaair.com//booking.evaair.com',
                    'https://booking.evaair.com', $this->http->FormURL);
                $this->http->Form['languageLocation'] = 'Global';
                $this->http->Form['languageSelector'] = $this->http->getCookieByName('_EVAlang');

                if (!empty($this->http->Form['ctl00$content$hid_CompanName'])) {
                    $this->http->Form['ctl00$content$hid_PaxNo'] = 2;
                    $this->http->Form['ctl00$content$hid_CompanName'] = "," . $this->http->Form['ctl00$content$hid_CompanName'];
                    $this->http->Form['ctl00$__MasterEVENTTARGET'] = 'btn_InputConfirm_Click';
                } else {
                    $this->http->Form['ctl00$content$hid_PaxNo'] = 1;
                    $this->http->Form['ctl00$__MasterEVENTTARGET'] = 'btn_returntrip_Click';
                }
                $this->http->Form['cookieCheck'] = 'on';
                $this->http->PostForm();
                $this->evaPostForm();
            }
        }
    }

    private function parseItinerary()
    {
        $this->logger->notice(__METHOD__);

        if (
            strstr($this->http->Error, 'Network error 28 - Operation timed out after')
        ) {
            $this->logger->error('Skip due to time out, same on the website');

            return null;
        }

        if ($error = $this->http->FindPreg('/"message":"(Please note that your booking \w+ has yet been completed. You have to finalize the payment before.+?)",/')) {
            $this->logger->error($error);

            return $error;
        }

        if ($error = $this->http->FindSingleNode('//li[contains(normalize-space(text()), "Your booking record could not be retrieved. If the booking was made by a travel agent, it is possible that no Infinity MileageLands membership number was appended to the record.")]')) {
            $this->logger->error($error);

            return $error;
        }

        $this->evaCompanionPostForm();

        if ($this->http->FindSingleNode('//img[@alt = "Processing, please wait."]/@alt') && $this->http->ParseForm('PostForm')) {
            $this->logger->notice("posting one more form");
            $this->http->PostForm();
            $this->evaPostForm();

            if ($this->http->FindSingleNode('//h1[contains(text(), "Select Itinerary")]')) {
                $this->logger->info('Broken Itinerary', ['Header' => 3]);
                $this->logger->error("skip broken itinerary");

                return null;
            }
        }

        if ($this->http->FindSingleNode('//li[contains(text(), "The system is temporarily unavailable, so please try it later. We apologize for any inconvenience that might have caused you.")]')) {
            $this->logger->info('Broken Itinerary', ['Header' => 3]);
            $this->logger->error("skip broken itinerary");

            return null;
        }

        // RecordLocator
        $confNo = $this->http->FindSingleNode("//dt[contains(text(), 'Booking Reference')]/following-sibling::dd/span");
        $confNo = preg_replace('/\s*\(.+\)/ims', '', $confNo);

        $this->logger->info('Parse Itinerary #' . $confNo, ['Header' => 3]);

        if (!$confNo && !$this->http->FindSingleNode("//div[@aria-label='Please select the segment you want to manage.']/div/button")
        && $this->http->FindSingleNode("//dt[contains(text(), 'Booking Reference')]/following-sibling::dd[contains(text(),'(Online booking)')]")) {
            $this->logger->error("skip broken itinerary");

            return null;
        }

        $f = $this->itinerariesMaster->add()->flight();
        $f->general()->confirmation($confNo, 'Booking Reference', true);
        // travellers
        $passengerItems = $this->http->FindNodes("//dt[contains(@id, 'dt_Passanger_Name')]");
        $passengers = array_filter(array_unique(array_map(function ($item) {
            return beautifulName($item);
        }, $passengerItems)));
        $paxFromSegmentItems = $this->http->FindNodes('//div[contains(@id, "Segment_meal_") or contains(@id, "Segment_seat_")]//dd[@class = "task-status"]/preceding-sibling::dt[1]');
        $pax = array_filter(array_unique(array_map(function ($item) {
            return beautifulName($item);
        }, $paxFromSegmentItems)));

        $segments = $this->http->XPath->query("//div[starts-with(translate(@id,'0123456789','dddddddddd'),'flightSegment')]");

        if (count($pax) > count($passengers)) {
            $f->general()->travellers($pax, true);
        } elseif (!empty($passengers)) {
            $f->general()->travellers($passengers, true);
        } elseif ($segments->length == 0) {
            return false;
        }

        $f->setTicketNumbers(array_unique(array_filter($this->http->FindNodes("//dd[contains(@id, 'dd_TicketNo_')]/span[not(contains(text(), 'No Ticket'))]"))), false);

        $f->setAccountNumbers(array_unique(array_filter($this->http->FindNodes("//dd[contains(@id, 'dd_FrequentFlyerNumber_')]", null, "/([^\/]+)/"))), false);

        $this->logger->debug("Total {$segments->length} segments were found");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $departure = $this->http->FindSingleNode('.//div[contains(@class, "flightSegment-airport--chooseStart")]/p[2]', $segment);
            $s->departure()
                ->code($this->http->FindSingleNode('.//div[contains(@class, "flightSegment-airport--chooseStart")]/p[1]', $segment))
                ->terminal($this->http->FindPreg("/\(Terminal\s*([^\)]+)/", false, $departure), false, true)
                ->date2($this->http->FindSingleNode('.//div[contains(@class, "flightSegment-airport--chooseStart")]/p[3]', $segment));

            if ($dep = $this->http->FindPreg("/([^\(]+)/", false, $departure)) {
                $s->departure()->name($dep);
            }

            $arrival = $this->http->FindSingleNode('.//div[contains(@class, "flightSegment-airport--chooseEnd")]/p[2]', $segment);
            $s->arrival()
                ->code($this->http->FindSingleNode('.//div[contains(@class, "flightSegment-airport--chooseEnd")]/p[1]', $segment))
                ->terminal($this->http->FindPreg("/\(Terminal\s*([^\)]+)/", false, $arrival), false, true)
                ->date2($this->http->FindSingleNode('.//div[contains(@class, "flightSegment-airport--chooseEnd")]/p[3]', $segment));

            if ($arr = $this->http->FindPreg("/([^\(]+)/", false, $arrival)) {
                $s->arrival()->name($arr);
            }

            $number = $this->http->FindSingleNode('.//li[span[contains(text(), "Flight Number")]]/text()[last()]', $segment);
            $s->airline()
                ->name($this->http->FindPreg("/([A-Z]{1,2})/", false, $number))
                ->number($this->http->FindPreg("/[A-Z]{1,2}(\d+)/", false, $number))
                ->operator($this->http->FindSingleNode('.//dd[contains(@id, "dd_AirlineName_")]', $segment), false, true);

            $cabin = $this->http->FindSingleNode('.//li[contains(@id, "Segment_li_Flight_Cabin_")]', $segment);
            $s->extra()
                ->duration($this->http->FindSingleNode('.//p[span[contains(text(), "Flight time")]]/text()[last()]', $segment), true, true)
                ->cabin($this->http->FindPreg("/([^\(]+)/", false, $cabin), false, true)
                ->bookingCode($this->http->FindPreg("/\(([^\)]+)/", false, $cabin), false, true)
                ->status($this->http->FindSingleNode('.//dd[contains(@id, "dd_FlightStatus_")]', $segment), false, true)
                ->aircraft($this->http->FindSingleNode('.//dd[contains(@id, "dd_Aircraft_")]/text()[1]', $segment), true, true)
                ->seats($this->http->FindNodes('.//div[contains(@id, "Segment_seat_")]//dd[contains(@class, "task-status") and not(contains(text(), "Unable to select seat") or contains(text(), "Unselected")) and not(contains(@class, "statusAction"))]', $segment))
                ->meal(implode(', ', $this->http->FindNodes('.//div[contains(@id, "Segment_meal_")]//dd[@class = "task-status" and not(contains(text(), "unordered"))]', $segment)), true);

            if ($s->getDepCode() === $s->getArrCode()) {
                $this->logger->error('Removing invalid segment');

                if ($s->getDepCode() !== 'TPE') {
                    $this->sendNotification('check remove invalid segment // MI');
                }
                $f->removeSegment($s);
            }
        }// foreach ($segments as $segment)

        if ($segments->length > 0 && count($f->getSegments()) === 0) {
            $this->logger->error('Removing invalid itinerary');
            $this->itinerariesMaster->removeItinerary($f);

            return null;
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return null;
    }
}
