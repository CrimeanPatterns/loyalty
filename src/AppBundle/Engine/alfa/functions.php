<?php

class TAccountCheckerAlfa extends TAccountChecker
{
    private $auth_token = null;
    private $headers = [
        "Accept" => "application/json",
        "Origin" => "https://travel.alfabank.ru",
    ];

    public function UpdateGetRedirectParams(&$arg)
    {
        if ($this->AccountFields['Login2'] == 'AlfaTravel') {
            $arg["RedirectURL"] = 'https://travel.alfabank.ru/login';
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.alfamiles.com/";
        $arg["SuccessURL"] = "https://www.alfamiles.com/";

        return $arg;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""           => "Please select your program",
            "AlfaMiles"  => "Alfa-Miles",
            "AlfaTravel" => "Alfa Travel",
        ];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] == 'AlfaTravel') {
            if (!isset($this->State['auth_token'])) {
                return false;
            }

            $this->auth_token = $this->State['auth_token'];
//            $this->http->GetURL("https://travel.alfabank.ru/api-alfa/v4/user/info?auth_token={$auth_token}&lang=ru&metadata={$this->metadata}&uuid={$this->uuid}&partner=ALFA");
            $this->http->GetURL("https://travel.alfabank.ru/api-alfa/v4/user/all-orders?auth_token={$this->auth_token}&type=&partner=ALFA&real_only=true&lang=ru");
            $response = $this->http->JsonLog();

            if (isset($response->data->user)) {
                return true;
            }

            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.alfamiles.com/cabinet/cards/history", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ($this->AccountFields['Login2'] == 'AlfaTravel') {
            $this->http->GetURL("https://travel.alfabank.ru/login");

            if ($this->http->Response['code'] != 200) {
                return false;
            }

            $data = [
                "email"    => $this->AccountFields['Login'],
                "password" => $this->AccountFields['Pass'],
                "lang"     => "ru",
                "extend"   => 1,
                "partner"  => "ALFA",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://travel.alfabank.ru/api-alfa/v4/user/login", $data, $this->headers);
            $this->http->RetryCount = 2;

            return !$this->Redirecting;
        } else {
            $this->http->GetURL("https://www.alfamiles.com/");
            $this->http->GetURL("https://www.alfamiles.com/popup_auth?w=l&m=");

            if (!$this->http->ParseForm(null, "//form[contains(@action, '/popup_auth?w=l')]")) {
                return false;
            }

            $this->http->FormURL = 'https://www.alfamiles.com/xmler/auth/?';
            $this->http->SetInputValue("usr", $this->AccountFields['Login']);
            $this->http->SetInputValue("pwd", $this->AccountFields['Pass']);
        }

        return true;
    }

    public function Login()
    {
        if ($this->AccountFields['Login2'] == 'AlfaTravel') {
            $response = $this->http->JsonLog();
            $auth_token = $response->data->auth_token ?? null;

            if ($auth_token) {
                $this->auth_token = $response->data->auth_token;

                return true;
            }// if ($auth_token)

            $message = $response->data->message ?? null;

            if ($message) {
                $this->logger->error($message);

                if (
                    strstr($message, "Неверный email или пароль")
                    || $message == "Некорректный email"
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;
            }// if ($message)

            return false;
        }

        sleep(2);

        if (!$this->http->PostForm()) {
            return false;
        }
        // Access is allowed
        if ($this->http->FindPreg("/root msg=\"ok\"/ims")) {
            return true;
        }
        // Неверный логин или пароль.
        if ($message = $this->http->FindPreg("/root msg=\"fail\"/ims")) {
            throw new CheckRetryNeededException(3, 10, "Неверный логин или пароль.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function parseAlfaTravel()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        $user = $response->data->user ?? null;
        // Name
        $this->SetProperty("Name", beautifulName($user->first_name . " " . $user->last_name));
        // Ваш бонусный номер
        $this->SetProperty("CardNumber", $user->alfa_identifier ?? null);

        $headers = [
            "Authorization" => "Bearer {$this->auth_token}",
        ];
        $this->http->GetURL("https://travel.alfabank.ru/api-alfa/v4/user/alfa/get-info", $this->headers + $headers);
        $response = $this->http->JsonLog();
        // Balance - Ваш баланс
        $this->SetBalance($response->data->balance ?? null);
        // Name
        $this->SetProperty("Name", beautifulName($response->data->cardHolder ?? null));
    }

    public function Parse()
    {
        if ($this->AccountFields['Login2'] == 'AlfaTravel') {
            $this->parseAlfaTravel();

            return;
        }

        if ($this->http->currentUrl() != "https://www.alfamiles.com/cabinet/cards/history") {
            $this->http->GetURL("https://www.alfamiles.com/cabinet/cards/history");
        }

        if ($this->http->FindPreg('#Согласие на обработку персональных данных#i')) {
            throw new CheckException('Необходимо согласие на обработку персональных данных', ACCOUNT_PROVIDER_ERROR);
        }

        // Balance - На счёте: ... миль
        $this->SetBalance($this->http->FindSingleNode("//a[contains(text(), 'На счёте')]/strong"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(@class, 'welcome')]", null, true, "/Здравствуйте\,\s*([^\!]+)/ims")));
        // Единая карта
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//td[contains(text(), 'Единая карта:')]/following-sibling::td/span"));
        // Full Name
        if (empty($this->Properties['CardNumber'])) {
            return;
        }
        $this->http->GetURL("https://www.alfamiles.com/cabinet/cards/bank");
        $name = $this->http->FindSingleNode("//div[contains(text(), '{$this->Properties['CardNumber']}')]/following-sibling::div[@class = 'name']");

        if (!empty($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(@href, "logout=yes")]/@href')) {
            return true;
        }

        return false;
    }
}
