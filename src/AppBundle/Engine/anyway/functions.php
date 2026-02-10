<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAnyway extends TAccountChecker
{
    use ProxyList;

    private $response;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        // all three works
        $this->http->SetProxy($this->proxyUK());
//        $this->http->SetProxy($this->proxyAustralia());
//        $this->setProxyBrightData(null, 'static', 'ru');
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www.anywayanyday.com/");
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
//        if (!$this->http->ParseForm(null, '//form[contains(@action, "authorize2")]'))
//            return $this->checkErrors();
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        $formURL = 'https://auth.anywayanyday.com/auth/Login/';

        $this->http->GetURL("https://www.anywayanyday.com/Controller/User/Authorize/?_Serialize=JSON&UserName=" . urlencode($this->AccountFields['Login']) . "&UserPass=" . urlencode($this->AccountFields['Pass']) . "&_=" . time() . date('B'));

        $html = $this->prepareJSON();
        $this->response = $this->http->JsonLog($html);
        // Invalid email or password.
        if (isset($this->response->Authorized, $this->response->Error) && $this->response->Authorized == 'NotAuthorized'
            && $this->response->Error == 'IncorrectEmailOrPassword') {
            throw new CheckException("Invalid email or password.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->FormURL = $formURL;

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.anywayanyday.com';
        $arg['SuccessURL'] = 'https://www.anywayanyday.com/personal/bonusaccount/';

        return $arg;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[@class = 'error']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Access is allowed
        if (isset($this->response->Authorized) && $this->response->Authorized == 'Authorized') {
            return true;
        }
        // Invalid email or password.
        if ($this->http->FindPreg("/^\s*anywayanyday\.com\s*$/")) {
            throw new CheckException("Invalid email or password.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $dateFrom = date("m.Y");
        $dateTo = strtotime("+1 month", strtotime("01." . $dateFrom));
        $dateTo = date('d.m.Y', $dateTo);
        $this->http->GetURL("https://www.anywayanyday.com/UserFuncs/PersonalAccountsPrivateOffice/GetCustomerPersonalAccountOperationList2/?_Serialize=JSON&AccName=AwadBonus2&AccCurrency=APP&Language=en&Currency=USD&DateFrom=01." . $dateFrom . "&DateTo=" . $dateTo . "&_=" . time() . date('B'));
        $response = $this->http->JsonLog($this->http->FindPreg('/"PersonalAccount":(.+),"ErrorCode"/'));
//        if (isset($response->PersonalAccount))
//            $this->logger->debug(var_export($response->PersonalAccount, true), ["pre" => true]);

        //# Balance
        if (isset($response->Amount)) {
            $this->SetBalance($response->Amount);
        }
        $this->SetProperty('StatusPoints', $this->Balance);
        //# Your current status
        if (isset($response->CurrentLevel)) {
            $level = $response->CurrentLevel;

            switch ($level) {
                case '1':
                    $this->SetProperty('Status', 'Tourist');

                    break;

                case '2':
                    $this->SetProperty('Status', 'Traveler');

                    break;

                case '3':
                    $this->SetProperty('Status', 'Man of world');

                    break;
            }// switch ($level)
        }// if (isset($response->CurrentLevel))
    }

    private function prepareJSON($body = null, $logs = false)
    {
        $this->logger->notice(__METHOD__);

        if (is_null($body)) {
            $body = $this->http->Response['body'];
        }

        $matches = $this->http->FindPregAll('/"(?<key>[^\"]+)":"?(?<value>[^\"\,]+)"/', $body, PREG_SET_ORDER);
        $arr = [];

        foreach ($matches as $match) {
            $arr[$match['key']] = $match['value'];
        }

        if ($logs) {
            $this->logger->debug(var_export($arr, true), ["pre" => true]);
        }
        $result = json_encode($arr);

        return $result;
    }
}
