<?php

class TAccountCheckerClubq extends TAccountChecker
{
    private $scriptCookie;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //$arg['CookieURL'] = 'https://cqrewards.com/login';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://cqrewards.com/point-history', [], 20);
        $this->script();
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Login must be correct email
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Invalid e-mail and/or password', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->script();
        $this->http->GetURL('https://cqrewards.com/login');

        if (!$this->http->ParseForm(null, '//form[//input[@name="username"]]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('action', "default");

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[contains(@class, "ulp-input-error-message") and normalize-space() != ""]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Wrong email or password')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Points
        $balance = $this->http->FindSingleNode('//span[@data-balance]/@data-balance');

        if ($balance === "") {
            $balance = $this->http->FindSingleNode('//span[@data-balance]', null, true, "/(.+) Point/");
        }

        $this->SetBalance($balance);

        $this->http->GetURL('https://cqrewards.com/view-profile/');
        // CQ Rewards Number
        $this->SetProperty('Number', $this->http->FindSingleNode('//div[@class="account-sidebar-organization"]', null, false, '/CQ Rewards Number:\s*(.*?$)/ims'));
        // Name
        $name = $this->http->FindSingleNode('//span[contains(text(), "First name")]/following-sibling::span');
        $name .= ' ' . $this->http->FindSingleNode('//span[contains(text(), "Last name")]/following-sibling::span');
        $this->SetProperty('Name', beautifulName($name));
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://clubquartershotels.com/";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $this->notificationsFromConfirmation($arFields);

        $chain = $this->http->FindSingleNode("//a[contains(normalize-space(), 'View/Modify Reservations')]/@href", null, false, "/chain:%20'([^']+)'/ims");
        $start = $this->http->FindSingleNode("//a[contains(normalize-space(), 'View/Modify Reservations')]/@href", null, false, "/start:%20'([^']+)'/ims");
        $promo = $this->http->FindSingleNode("//a[contains(normalize-space(), 'View/Modify Reservations')]/@href", null, false, "/promo:%20'([^']+)'/ims");

        if (!$chain || !$start || !$promo) {
            $this->notificationsFromConfirmation($arFields);

            return null;
        }

        $data = [
            'chain' => $chain,
            'start' => $start,
            'promo' => $promo,
        ];
        $headers = [
            'Accept'       => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://gc.synxis.com/', $data, $headers);
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm("XbeForm")) {
            $this->notificationsFromConfirmation($arFields);

            return null;
        }
        $this->http->SetInputValue('V155$C1$LocateCustomerCntrl$EmailConfirmTextBox', $arFields['Email']);
        $this->http->SetInputValue('V155$C1$LocateCustomerCntrl$ConfirmTextbox', $arFields['ConfNo']);
        $this->http->SetInputValue('V155$C1$LocateCustomerCntrl$ConfirmSearchButton', 'Search');

        if (!$this->http->PostForm()) {
            $this->notificationsFromConfirmation($arFields);

            return null;
        }

        if ($message = $this->http->FindSingleNode("//span[@id='V156_C0_NoReservationMessageLabel']")) {
            return $message;
        }

        $it = $this->ParseItinerary();

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Cols"     => 40,
                "Required" => true,
            ],
            "Email"  => [
                "Type"     => "string",
                "Caption"  => "E-mail",
                "Size"     => 40,
                "Cols"     => 40,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
        ];
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//span[@class="hello"]')) {
            return true;
        }

        return false;
    }

    private function script()
    {
        $this->logger->notice(__METHOD__);

        if (isset($this->scriptCookie[1])) {
            $this->http->setCookie($this->scriptCookie[1], $this->scriptCookie[2], 'cqrewards.com', '/', strtotime("+1 day"));
        } elseif ($script = $this->http->FindPreg('#<script>(.+?sucuri_cloudproxy_js.+?)</script>#')) {
            $script = preg_replace('/e\(r\);/', 'sendPhpResponse(r);', $script);
            $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
            $script = $jsExecutor->executeString($script);
            $script = preg_replace('/;document.cookie=/', ';r=', $script);
            $script = preg_replace('/location.reload\(\);/', 'sendPhpResponse(r);', $script);
            $script = $jsExecutor->executeString($script);
            $this->logger->debug($script);

            if (preg_match('/(\w+)=(\w+);/', $script, $m)) {
                $this->scriptCookie = $m;
                $this->http->setCookie($m[1], $m[2], 'cqrewards.com', '/', strtotime("+1 day"));
                $this->http->GetURL($this->http->currentUrl());
            }
        }
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseItinerary()
    {
        $result = [];
        $bookNumber = '';
        $this->logger->info("Parse itinerary #{$bookNumber}", ['Header' => 3]);

        return $result;
    }

    private function notificationsFromConfirmation($arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification("failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Email: {$arFields['Email']}");
    }
}
