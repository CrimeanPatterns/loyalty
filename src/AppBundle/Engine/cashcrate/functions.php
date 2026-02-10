<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerCashcrate extends TAccountChecker
{
    use ProxyList;

    private $login = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL("http://www.cashcrate.com/earnings");

        if ($this->http->FindSingleNode("//a[@href = 'logout']/@href")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.cashcrate.com/login_s");

        if (!$this->http->ParseForm("login-cashcrate")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        if ($key = $this->http->FindSingleNode("//form[@id = 'login-cashcrate']//div[@class = 'g-recaptcha']/@data-sitekey")) {
            $captcha = $this->parseCaptcha($key);

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        return true;
    }

    public function Login()
    {
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        if (!$this->http->PostForm()) {
            $this->checkErrors();
        }

        if ($this->http->FindSingleNode("//a[@href = 'logout']/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[@class ='temp-message error-notice-wrapper']/div/span/span")) {
            if ($this->login < 1 && strstr($message, 'For your security, please complete the captcha to verify you')) {
                $this->login++;
                $this->logger->notice("Retry: {$this->login}");
                $this->http->Form = $form;
                $this->http->FormURL = $formURL;
                $captcha = $this->parseCaptcha();

                if ($captcha === false) {
                    return false;
                }
                $this->http->SetInputValue('g-recaptcha-response', $captcha);

                return $this->Login();
            }

            if (!strstr($message, "For your security, please complete the captcha")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            //	        else
//		        throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        // We are performing some emergency repairs
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are performing some emergency repairs')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 500) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.cashcrate.com/earnings");

        $this->SetProperty("TotalCash", $this->http->FindPreg("/Total cash earned:<[^>]+>\s*<[^>]+>([^<]+)</ims"));
        // Balance - Points earned
        $this->SetBalance($this->http->FindPreg("/Points earned:<[^>]+>\s*<[^>]+>([^<]+)</ims"));

        $this->SetProperty("Offers", $this->http->FindSingleNode("//ul[@id = 'member-income']/li[1]/span"));
        $this->SetProperty("Shopping", $this->http->FindSingleNode("//ul[@id = 'member-income']/li[2]/span"));
        $this->SetProperty("Surveys", $this->http->FindSingleNode("//ul[@id = 'member-income']/li[3]/span"));
        $this->SetProperty("Referrals", $this->http->FindSingleNode("//ul[@id = 'member-income']/li[4]/span"));
        $this->SetProperty("Bonuses", $this->http->FindSingleNode("//ul[@id = 'member-income']/li[5]/span"));

        $this->SetProperty("NeededForCheck", $this->http->FindPreg("/Collect another\s+([^\s]*)/ims"));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//span[contains(text(), 'Member since')]", null, true, "/Member\s*since\s*([^\<]+)/ims"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("(//a[@class = 'member-name'])[1]")));

        $this->http->GetURL("https://www.cashcrate.com/dashboard_sidebars.jsvc?load_str=ccjq__0%3Dreferrals");
        $response = $this->http->JsonLog();
        $this->http->SetBody($response->ccjq__0 ?? '');
        // Active US Referrals
//        $this->SetProperty("ActiveUSReferrals", $this->http->FindPreg("/Active US Referrals:.+<\/dt>\s*<dd>([^<]+)/"));
        $this->SetProperty("ActiveUSReferrals", $this->http->FindSingleNode("//dt[contains(text(), 'Active US Referrals:')]/following-sibling::dd[1]"));
        // Status
//        $this->SetProperty("Status", $this->http->FindPreg("/<h4>([^<]+)<\/h4>\s*<div[^>]*>\s*Your current level/"));
        $this->SetProperty("Status", $this->http->FindSingleNode("//div[contains(text(), 'Your current level')]/preceding-sibling::h4"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'http://www.cashcrate.com/login';

        return $arg;
    }

    protected function parseCaptcha($key = null)
    {
        $this->http->Log(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("//form[@id = 'login-cashcrate']//div[@class = 'g-recaptcha']/@data-sitekey");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }
}
