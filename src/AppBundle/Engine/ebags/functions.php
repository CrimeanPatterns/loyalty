<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerEbags extends TAccountChecker
{
    use ProxyList;

    public const REWARDS_PAGE_URL = 'https://www.ebags.com/rewards/accountsummary';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        /*
        if ($this->attempt == 1) {
                    $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
                } else
                $this->http->SetProxy($this->proxyReCaptcha());
        */
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL("https://www.ebags.com/my-account");
        $login = $this->http->FindSingleNode("//form[@id = 'dwfrm_login']//input[contains(@name, 'dwfrm_login_username')]/@name");

        if (!$this->http->ParseForm("dwfrm_login") || !isset($login)) {
            return $this->checkErrors();
        }
        // enter the login and password
        $this->http->SetInputValue($login, $this->AccountFields["Login"]);
        $this->http->SetInputValue("dwfrm_login_password", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("dwfrm_login_login", 'Login');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Website is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Please excuse us while we are temporarily off line')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            /*            if ($this->http->Response['code'] == 429) {
                            throw new CheckRetryNeededException();
                        }*/
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;

        if ($message = $this->http->FindSingleNode("
        //div[@class='messaging error'][normalize-space()!=''][1]
        | //div[@class='error-message'][normalize-space()!=''][1] 
        ")) {
            $this->logger->error($message);

            if (strstr($message, 'Sorry, this does not match our records. Check your spelling and try again.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        } else {
            $this->sendNotification("refs #19174: possibly a valid account //KS");
        }
        // if ($message = $this->http->FindSingleNode("//div[@class='validation-summary-errors']"))
        /*        if ($message = $this->http->FindSingleNode("//div[@class='validation-summary-errors']")) {
                    if (strstr($message, 'Invalid reCAPTCHA solution'))
                        throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
                    else
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }// if ($message = $this->http->FindSingleNode("//div[@class='validation-summary-errors']"))

                $this->http->GetURL(self::REWARDS_PAGE_URL);
                ## User is not member of this loyalty program
                if ($this->http->FindSingleNode("//a[contains(text(), 'Sign Up Today')]")
                    && $this->http->FindSingleNode("//p[contains(text(), 'To unlock more benefits become an')]")) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindSingleNode('//input[@value="I accept"]'))
                    throw new CheckException('eBags Rewards website needs you to update your profile,
                    until you do so we would not be able to retrieve your account information.', ACCOUNT_PROVIDER_ERROR);

                ## Access ia allowed
                if ($this->loginSuccessful()) {
                    return true;
                }

                ## Your Error ID is ...
                if ($message = $this->http->FindPreg("/<FONT COLOR=\"Red\"><B>(Your Error ID is [^<]+)<\/B>/ims"))
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);*/

        //# Access ia allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    /*    protected function parseCaptcha() {
            $this->logger->notice(__METHOD__);
            $key = $this->http->FindPreg("/sitekey\s*:\s*'([^\']+)/");
            $this->logger->debug("data-sitekey: {$key}");
            if (!$key)
                return false;
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
            $this->recognizer->RecognizeTimeout = 120;
            $parameters = [
                "pageurl" => $this->http->currentUrl(),
                "proxy" => $this->http->GetProxy(),
            ];
            $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

            return $captcha;
        }*/

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg('/welcome\s*([^\.\,<]*)/ims')));
        // Balance - Available Balance
        $this->SetBalance($this->http->FindSingleNode('//div[@id="content-rewards"]//div[contains(@class,"table-points-box")]/span', null, false, '/\$\s*([\d.,]+)/'));
        // Total points pending
        $this->SetProperty("Pendingpoints", $this->http->FindSingleNode('//div[p[contains(text(), "Total points pending")]]/following::div[1]'));
        // Total points redeemed
        $this->SetProperty("Lifetimepointsredeemed", $this->http->FindSingleNode('//div[div[p[contains(text(), "Total points redeemed")]]]/following-sibling::div[1]/div/p'));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//span[contains(text(), 'member since')]", null, false, '/member\ssince\s([a-zA-Z0-9\, ]+)/ims'));
        // Total point expiring
        $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode("(//div[span[contains(text(), 'Expires')]]/following-sibling::div[1]/span)[1]"));
        $exp = $this->http->FindSingleNode("(//span[contains(text(), 'Expires')])[1]", null, true, "/Expires\s*([^<]+)/");

        if ($exp = strtotime($exp)) {
            $this->SetExpirationDate($exp);
        }

        // Full Name
        $this->http->GetURL("https://www.ebags.com/members/memberprofile");
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//*[@id = 'FirstName']/@value")
            . ' ' . $this->http->FindSingleNode("//*[@id = 'LastName']/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "http://www.ebags.com/rewards/index.cfm?fuseaction=AccountSummary";
        $arg["NoCookieURL"] = true;

        return $arg;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(text(), 'Sign Out')])[1]")) {
            return true;
        }

        return false;
    }
}
