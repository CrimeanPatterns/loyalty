<?php

class TAccountCheckerFoxrewards extends TAccountChecker
{
    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerFoxrewardsSelenium.php";

        return new TAccountCheckerFoxrewardsSelenium();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (false === filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL)) {
            throw new CheckException('There is a problem with your login, please verify your email and password to log in.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL('https://www.foxrentacar.com/en/rewards-program/my-rewards.html');

        if (!$this->http->ParseForm("login_form")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('login-email', $this->AccountFields['Login']);
        $this->http->SetInputValue('login-password', $this->AccountFields['Pass']);
        unset($this->http->Form['rememberme']);
        $this->http->FormURL = 'https://www.foxrentacar.com/bin/login';

        return true;
    }

    /*
     * from js
     */
    public function FoxEncrypt($password)
    {
        $value = "";
        $asciiKeys = [];

        for ($i = 0; $i < strlen($password); $i++) {
            $asciiKeys[] = ord($password[$i]);

            if ($value == "") {
                $value = ord($password[$i]);
            } else {
                $value .= " " . ord($password[$i]);
            }
        }

        return $value;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.foxrentacar.com/content/fox-rent-a-car/en/rewards-program.html';
        $arg['SuccessURL'] = 'https://www.foxrentacar.com/en/rewards-program/my-rewards.html';

        return $arg;
    }

    public function checkErrors()
    {
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            // Server error
            || $this->http->FindSingleNode("//h2[contains(text(), '404 - File or directory not found')]")
            // We're sorry, but there was an error processing your request
            || $this->http->FindSingleNode('//h1[contains(text(), "We\'re sorry, but there was an error processing your request")]')
            || $this->http->Response['code'] == 503) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm() && !empty($this->http->Response['body'])) {
            return $this->checkErrors();
        }
        // Access is allowed
        if ($this->http->FindPreg("/loyaltyMember/")) {
            return true;
        }
        // Error occured while getting User Details
        if ($message = $this->http->FindPreg("/(?:Error occured while getting User Details|Unable to locate Loyalty customer\.)/")) {
            throw new CheckException("There is a problem with your login, please verify your email and password to log in.", ACCOUNT_INVALID_PASSWORD);
        }

        if (empty($this->http->Response['body'])) {
            throw new CheckException('There is a problem with your login, please verify your email and password to log in.', ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();
        // Balance - REWARD POINTS
        if (isset($response->loyaltyMember->pointBalance)) {
            $this->SetBalance($response->loyaltyMember->pointBalance);
        } else {
            $this->http->Log("Balance not found");
        }
        // REWARD NUMBER
        if (isset($response->loyaltyMember->loyaltyID)) {
            $this->SetProperty("Number", $response->loyaltyMember->loyaltyID);
        } else {
            $this->http->Log("REWARD NUMBER not found");
        }
        // Name
        if (isset($response->loyaltyMember->renterFirst, $response->loyaltyMember->renterLast)) {
            $this->SetProperty("Name", beautifulName($response->loyaltyMember->renterFirst . " " . $response->loyaltyMember->renterLast));
        } else {
            $this->http->Log("Name not found");
        }
    }
}
