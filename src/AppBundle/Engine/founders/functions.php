<?php

class TAccountCheckerFounders extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://founderscard.com/rewards/';
    /** @var CaptchaRecognizer */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://founderscard.com/rejoin/login');

        if (!$this->http->ParseForm('new_user')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('user[email]', $this->AccountFields['Login']);
        $this->http->SetInputValue('user[password]', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class,"flash-danger")]')) {
            $this->logger->error($message);

            if ($message == "reCAPTCHA verification failed") {
                if (!$this->http->ParseForm('new_user')) {
                    return $this->checkErrors();
                }

                $captcha = $this->parseReCaptcha();

                if ($captcha == false) {
                    return $this->checkErrors();
                }

                $this->http->SetInputValue('user[email]', $this->AccountFields['Login']);
                $this->http->SetInputValue('user[password]', $this->AccountFields['Pass']);
                $this->http->SetInputValue('g-recaptcha-response-data[login]', $captcha);

                if (!$this->http->PostForm()) {
                    return $this->checkErrors();
                }

                if ($this->loginSuccessful()) {
                    $this->captchaReporting($this->recognizer);

                    return true;
                }

                $message = $this->http->FindSingleNode('//div[contains(@class,"flash-danger")]');
                $this->logger->error($message);
            }

            if ($message == "Invalid login credentials. Please try again.") {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }

        if ($this->http->FindSingleNode('//h4[
                normalize-space() = "Your Membership has expired. You now have two options to renew."
                or normalize-space() = "Your Membership has expired. You now have two options to rejoin."
            ]
            | //h1[contains(normalize-space(), "YOUR CHARTER MEMBERSHIP HAS EXPIRED")]
            ')
            || $this->http->FindSingleNode('//div[@id = "logo-container"]//div[contains(text(), "Membership Expired")]')
            || $this->http->currentUrl() == 'https://founderscard.com/users/past_due'
            || $this->http->currentUrl() == 'https://founderscard.com:443/users/past_due'
            || $this->http->currentUrl() == 'https://founderscard.com/pages/reactivate'
            || $this->http->currentUrl() == 'https://founderscard.com:443/pages/reactivate'
        ) {
            throw new CheckException("Your Membership has expired.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//h1[contains(normalize-space(), "Updated Billing Information Required")]')
            || $this->http->currentUrl() == 'https://founderscard.com/users/credit_card_required'
        ) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $ProfileUrl = $this->http->FindSingleNode("//*[@id='account-menu-overlay']/descendant::a[contains(@href,'/users/')]/@href[not(contains(normalize-space(),'/edit'))]");

        if (empty($ProfileUrl)) {
            return;
        }

        $this->http->NormalizeURL($ProfileUrl);
        $this->http->GetURL($ProfileUrl);

        // You have 0 FCPoints
        $this->SetBalance($this->http->FindSingleNode('//*[@id="account-menu"]/descendant::a[contains(@href,"/rewards")]/h6[contains(@class,"account-menu-item")]', null, true, self::BALANCE_REGEXP));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//*[@id='user_name']/@value")));
        // Membership #
        $this->SetProperty('Number', $this->http->FindSingleNode('//span[starts-with(normalize-space(),"Membership #:")]', null, true, "/^Membership #:\s?(\d+)/"));
        //FoundersCard Member Since 2018
        $this->SetProperty('MemberSince', $this->http->FindSingleNode("//*[@id='profile-public-info-row']/descendant::h5[contains(normalize-space(),'Member Since')]", null, true, "/Member Since (\d{4})/"));
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);

        /*
         * function executeRecaptchaForLogin() {
            grecaptcha.ready(function() {
              grecaptcha.execute('6LdwQC0kAAAAAM-rQIA_eT7CNRmmtjxsx-I4e5Or', {action: 'login'}).then(function(token) {
                setInputWithRecaptchaResponseTokenForLogin('g-recaptcha-response-data-login', token)
              });
            });
          };
         */
        $key = '6LdwQC0kAAAAAM-rQIA_eT7CNRmmtjxsx-I4e5Or';

        if (!$key) {
            return false;
        }

//        $postData = [
//            "type"         => "RecaptchaV3TaskProxyless",
//            "websiteURL"   => $this->http->currentUrl(),
//            "websiteKey"   => $key,
//            "minScore"     => 0.3,
//            "pageAction"   => "login",
//        ];
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//        $this->recognizer->RecognizeTimeout = 120;
//
//        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "invisible" => 1,
            "action"    => "login",
            "min_score" => 0.3,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('(//a[contains(@href, "logout")]/@href)[1]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
