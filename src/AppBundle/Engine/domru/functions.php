<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerDomru extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://lk.domru.ru/logout';

        return $arg;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://lk.domru.ru/", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://lk.domru.ru/login");

        if ($this->http->Response['code'] !== 200
            && !$this->http->FindSingleNode('(//input[@placeholder="Номер договора"])[1]')
        ) {
            return false;
        }

        $data = '-----------------------------33290246873129000010775668083
Content-Disposition: form-data; name="username"

' . $this->AccountFields['Login'] . '
-----------------------------33290246873129000010775668083
Content-Disposition: form-data; name="password"

' . $this->AccountFields['Pass'] . '
-----------------------------33290246873129000010775668083
Content-Disposition: form-data; name="rememberMe"

1
-----------------------------33290246873129000010775668083--
';

        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Accept-Language" => "en-US,en;q=0.5",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "multipart/form-data; boundary=---------------------------33290246873129000010775668083",
            "Domain"          => "perm",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api-auth.domru.ru/v1/person/auth", $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->data->access_token)) {
            $this->http->setCookie("ACCESS_TOKEN", $response->data->access_token, ".domru.ru");
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://lk.domru.ru/");
            $this->http->RetryCount = 0;

            if ($this->loginSuccessful()) {
                return true;
            }
        }

        $message = $response->message ?? null;

        if ($message = "Пожалуйста, проверьте правильность введенных данных: логина, пароля и города") {
            throw new CheckException("Пожалуйста, проверьте правильно ли Вы ввели свой логин и пароль.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $clubLink = $this->http->FindSingleNode("//a[contains(text(),'Программа лояльности')]/@href");

        if (!$clubLink) {
            $this->sendNotification('refs #6482, domru - Check Exp Date');
        }

        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//p[@data-name]"));
        // № договора:
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//span[contains(text(), '№ договора:')]/following-sibling::span[1]"));
        // Ваш адрес:
        $this->SetProperty("Address", $this->http->FindSingleNode("//span[contains(text(), 'Адрес:')]/parent::p", null, true, "/Адрес:\s*(.+)/"));

        if ($token = $this->http->FindSingleNode("(//input[@name = 'YII_CSRF_TOKEN']/@value)[1]")) {
            $headers = [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept'           => '*/*',
            ];
            /*
            $this->http->PostURL('https://lk.domru.ru/index/default/GetloyaltyPoints', [
                'YII_CSRF_TOKEN' => $token
            ], $headers);
            $response = $this->http->JsonLog();
            // У вас
            if (isset($response->content)) {
                $this->http->SetBody($response->content);
                $this->SetBalance($this->http->FindSingleNode("//span[@class='b-status__link-underlined']"));
            }
            */

            $data = "YII_CSRF_TOKEN=$token";
            $this->http->PostURL('https://lk.domru.ru/payments/default/GetDataForMoneybagWidget', $data, $headers);
        }
        $response = $this->http->JsonLog();
        // У вас на счете
        if (isset($response->balance)) {
            $this->SetBalanceNA();
            $this->SetProperty("CashBalance", $response->balance . ' ₽');
        }
        // Не забудьте до
        if (!empty($response->paymentPay)) {
            $this->SetProperty("NeededToPay", $response->paymentAmount . ' ₽');
        }

        // refs 6482#note-14, Exp Date
        // https://club.domru.ru/events?token={token}&domain=perm
        $clubLink = preg_replace('/^https?:/', '', $clubLink);
        $clubLink = preg_replace('/\/events?/', '/history', $clubLink);
        $this->http->GetURL("https:{$clubLink}");

        $expVal = $this->http->FindSingleNode("//span[contains(normalize-space(text()),'Аннулирование')]/preceding-sibling::div[1]");

        if ($expVal > 0) {
            $nodes = $this->http->XPath->query("//div[@data-tab-content='3']//div[contains(@class,'b-listing__item')]/div[@class='row']");

            if ($nodes->length > 1) {
                $this->sendNotification('refs #6482, domru - Check Exp Date 2');
            }

            foreach ($nodes as $node) {
                $balance = $this->http->FindSingleNode("./div[@class='col col-2']", $node, false, '/^[\-\d]+$/');

                if ($exp = $this->http->FindSingleNode("./div[@class='col col-3']", $node, false, '/^\d+\.\d+\.\d+$/')) {
                    $this->logger->debug("Original Exp Date: {$exp}");
                    $exp = preg_replace('/(\d{2})\.(\d{2})\.(\d{2})/', '$2/$1/$3', $exp);
                    $this->logger->debug("Format Exp Date: {$exp}");

                    if ($balance < 0 && ($exp = strtotime($exp, false))) {
                        $this->SetExpirationDate($exp);
                        $this->SetProperty('ExpiringBalance', abs($balance));

                        break;
                    }
                }
            }
        } elseif ($expVal == 0) {
            $this->ClearExpirationDate();
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // not a member
            if ($this->http->FindSingleNode("//div[contains(text(), 'Программа Привилегий недоступна. Пожалуйста, подтвердите своё участие')]")
                || $this->http->currentUrl() == 'https://lk.domru.ru/club.domru.ru/about') {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[@href='/logout']")) {
            return true;
        }

        return false;
    }
}
