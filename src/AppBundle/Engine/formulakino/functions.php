<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerFormulakino extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->setProxyBrightData(null, "dc_ips_ru", "ru");
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['SessionId'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://kinoteatr.ru/bonus/");

        if (!$this->http->ParseForm(null, '//div[@id = "auth_slide_email"]//form')) {
            return $this->checkErrors();
        }
        $data = [
            "method" => "UserLogin",
            "params" => [
                "Login"    => $this->AccountFields['Login'],
                "Password" => $this->AccountFields['Pass'],
            ],
        ];
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->PostURL("https://kinoteatr.ru/cgi-bin/api.pl", json_encode($data), $headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $sessionId = $response->result->SessionId ?? null;

        if ($sessionId) {
            $this->State['SessionId'] = $sessionId;

            return $this->loginSuccessful();
        }

        $message = $response->error->message ?? null;

        if ($message) {
            $this->logger->error($message);

            if (
                $message == 'Такой email не зарегистрирован'
                || $message == 'Неправильный логин или пароль'
                || $message == 'Неверный email или пароль!'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }// if (isset($response->message))

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();
        // Balance
        $this->SetBalance($response->request_data->GetCardInfoResult->Card->CurrentBalance ?? null);
        // Name
        $this->SetProperty("Name", beautifulName($response->request_data->GetCardInfoResult->Card->Contact->Name ?? null));
        // Ваша карта
        $this->SetProperty("CardNumber", $response->request_data->GetCardInfoResult->Card->Number ?? null);
        // Level
        $this->SetProperty("Level", $response->request_data->GetCardInfoResult->Card->CategoryStatusName ?? null);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://kinoteatr.ru/bonus/?ajax=1&SessionId=" . $this->State['SessionId']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!empty($response->request_data->GetCardInfoResult->Card->Number)) {
            return true;
        }

        return false;
    }
}
