<?php

class TAccountCheckerCarlton extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.carlton.ie");
        $this->http->FormURL = "https://www.carlton.ie/login";
        $this->http->Form['USERNAME'] = $this->AccountFields['Login'];
        $this->http->Form['PASSWORD'] = $this->AccountFields['Pass'];
        $this->http->Form['acc'] = 'login';

        return true;
    }

    public function checkErrors()
    {
        // 503 Service Unavailable
        if ($error = $this->http->FindSingleNode("//h1[contains(text(), '503 Service Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->LogHeaders = true;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindPreg("/Logout/ims")) {
            return true;
        }

        if ($error = $this->http->FindSingleNode("//div[@style = 'color:#860037;']")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        //		}else
//            throw new CheckException("The username and password are incorrect", ACCOUNT_INVALID_PASSWORD);

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->getURL('http://www.carlton.ie/rewards/priority.php?func=1');

        if ($this->http->ParseForm("myForm")) {
            $this->http->Form['func'] = '1';
            $this->http->Form['numDays'] = '365';
            $this->http->PostForm();
        }
        //# Balance - Current Balance
        $this->SetBalance($this->http->FindSingleNode("//b[contains(text(),'Current Balance')]/parent::*/parent::*/td[9]"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("(//p[@class = 'input_name'])[1]")));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && !empty($this->Properties['Name'])
            && $this->http->FindPreg("/There are no transactions during the selected period/ims")) {
            $this->SetBalanceNA();
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "http://www.carlton.ie/rewards/priority.php?func=1";

        return $arg;
    }
}
