<?php

class TAccountCheckerAirblue extends TAccountChecker
{
    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams();
        $arg['SuccessURL'] = 'https://www.airblue.com/bluemiles/myaccount';

        return $arg;
    }

    public function LoadLoginForm()
    {
        // Incorrect Login or Password
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            throw new CheckException("Incorrect Login or Password", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.airblue.com/rewards/login.asp');

        if (!$this->http->ParseForm('frm_Login')) {
            return false;
        }

        $this->http->SetInputValue('login_action', 'dologin');
        $this->http->SetInputValue('login', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $this->http->GetURL("https://www.airblue.com/api/members/DoLogin?id={$this->AccountFields['Login']}&pwd={$this->AccountFields['Pass']}");

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->result) && $response->result == 'OK') {
            $maxRedirects = $this->http->getMaxRedirects();
            $this->http->setMaxRedirects(0);
            $this->http->RetryCount = 0;
            $this->http->PostForm();
            $this->http->RetryCount = 2;
            $this->http->setMaxRedirects($maxRedirects);

            if ($this->http->Response['code'] > 299 && $this->http->Response['code'] < 400) {
                $this->http->FormURL = 'https://www.airblue.com/rewards/' . $this->http->Response['headers']['location'];
                $this->http->PostForm();
            }
        }

        if (strstr($this->http->currentUrl(), '/rewards/dashboard')) {
            return true;
        }

        $errors = $response->error ?? null;

        if (!empty($errors->conflicts) && is_array($errors->conflicts) && isset($errors->conflicts[0]->text)) {
            $this->logger->error($error = $errors->conflicts[0]->text);

            if (stripos($error, 'Either the Member was not found, locked or the password was wrong') !== false) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            $this->DebugInfo = $error;

            return false;
        }

        // Incorrect Login or Password
        if ($message = $this->http->FindSingleNode('//div[@class="loginButton"]/div[contains(text(), "Incorrect Login or Password")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "The server encountered an error processing the request. See server logs for more details")]')) {
            throw new CheckException("Service not available. Please try again at a later time. [400]", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.airblue.com/api/members/GetMemberDetails?verbose=true");
        $response = $this->http->JsonLog();
        // Name
        $this->SetProperty('Name', $response->member->firstName . " " . $response->member->lastName);
        // Member ID#
        $this->SetProperty('MemberId', $response->account->id);
        // Member Since
        $this->SetProperty('MemberSince', $response->account->memberSince);
        // Balance - Miles Balance
        $this->SetBalance($response->account->milesBalance);

        if (!$this->http->FindPreg("/,\"upcomingFlights\":\[\]/")) {
            $this->sendNotification('airblue: Appeared Itineraries');
        }
    }
}
