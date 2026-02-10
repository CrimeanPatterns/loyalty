<?php
class TAccountCheckerFinalprice extends TAccountChecker
{
    private $headers = [
        "User-Agent"   => "finalprice/1.3.0 (com.finalprice.finalprice; build:211; iOS 11.0.0) Alamofire/4.5.1",
        "Accept"       => "application/json",
        "Content-Type" => "application/json",
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""            => "Select your login type",
            "email"       => "Email Address",
            "cell_number" => "Mobile #",
        ];
        $result = Cache::getInstance()->get('finalPrice_countries');

        if (($result !== false) && (count($result) > 1)) {
            $regionOptions = $result;
        } else {
            $regionOptions = [
                "" => "Select a county code",
            ];
            $browser = new HttpBrowser("none", new CurlDriver());
            $browser->GetURL("https://workflow-api.finalprice.com/v1/get_countries/");
            $response = $browser->JsonLog(null, true, true);
            $countries = ArrayVal($response, 'countries', []);

            foreach ($countries as $country) {
                $name = ArrayVal($country, 'name', null);
                $code = ArrayVal($country, 'code', null);

                if ($name && $code) {
                    $regionOptions[$name] = "{$name} (+{$code})";
                }
            }// foreach ($countries as $country)

            if (count($regionOptions) > 1) {
                Cache::getInstance()->set('finalPrice_countries', $regionOptions, 3600 * 24);
            } else {
                $this->sendNotification("finalprice - regions aren't found", 'all', true, $browser->Response['body']);
            }
        }
        ArrayInsert($arFields, "Login", true, ["Login3" => [
            "Type"      => "string",
            "InputType" => "select",
            "Required"  => false,
            "Caption"   => "Region",
            "Options"   => $regionOptions,
        ]]);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;

        foreach ($this->headers as $header => $value) {
            $this->http->setDefaultHeader($header, $value);
        }
    }

    public function IsLoggedIn()
    {
        if (isset($this->State['session_id'], $this->State['user_id'], $this->State['expiration']) && strtotime($this->State['expiration']) > time()) {
            $session_id = $this->State['session_id'];
            $user_id = $this->State['user_id'];
            $this->http->setDefaultHeader("x-user-id", $user_id);
            $this->http->setDefaultHeader("x-session-id", $session_id);

            $this->http->GetURL("https://workflow-api.finalprice.com/v1/base/user/get");
            $response = $this->http->JsonLog(null, true, true);
            // Your session has expired. Please sign in.
            if (ArrayVal($response, 'error') != 'SESSION_WRONG') {
                return true;
            }
        }
        $this->http->unsetDefaultHeader("x-user-id");
        $this->http->unsetDefaultHeader("x-session-id");

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://workflow-api.finalprice.com/v1/base/session/get/");
        $response = $this->http->JsonLog(null, true, true);
        $session_id = ArrayVal($response, 'session_id', null);

        if (!$session_id) {
            return false;
        }
        $this->State['session_id'] = $session_id;
        $this->State['expiration'] = ArrayVal($response, 'expiration', null);
        $this->http->setDefaultHeader("x-session-id", $session_id);

        $this->correctingPhoneNumber();

        return true;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);

        if ($this->parseQuestion()) {
            return false;
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        // send code
        $data = [
            $this->AccountFields['Login2'] => $this->AccountFields['Login'],
        ];
        $this->http->PostURL("https://workflow-api.finalprice.com/v1/base/user/signinup/", json_encode($data));
        $response = $this->http->JsonLog(null, true, true);
        $status = ArrayVal($response, 'status', null);
        $message = ArrayVal($response, 'message', null);

        if (!$status || $status == 'error') {
            // Incorrect cell number
            // We cannot find user profile. Please check your email and phone number when login.
            if (strstr($message, 'Incorrect cell number') || strstr($message, 'We cannot find user profile.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        $this->State['Login'] = $this->AccountFields['Login'];

        if ($this->AccountFields['Login2'] == 'email') {
            $question = "Please enter the Confirmation Code which was sent to the following email address: {$this->AccountFields['Login']}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        } else {
            $question = "Please enter Identification Code which was sent to the following phone number: {$this->AccountFields['Login']}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        foreach ($this->headers as $header => $value) {
            $this->http->setDefaultHeader($header, $value);
        }
        $session_id = $this->State['session_id'];
        $this->http->setDefaultHeader("x-session-id", $session_id);
        $data = [
            $this->AccountFields['Login2'] => $this->State['Login'],
            "approval_code"                => $this->Answers[$this->Question],
        ];
        $this->http->PostURL("https://workflow-api.finalprice.com/v1/base/user/signinup/", json_encode($data));
        unset($this->Answers[$this->Question]);
        $response = $this->http->JsonLog(null, true, true);
        $user_id = ArrayVal($response, 'user_id', null);
        $message = ArrayVal($response, 'message', null);

        if (!$user_id) {
            // Wrong confirmation code. Please check your input carefully.
            if (strstr($message, 'Wrong confirmation code')) {
                $this->AskQuestion($this->Question, $message);
            }

            return false;
        }
        $this->State['user_id'] = $user_id;
        $this->http->setDefaultHeader("x-user-id", $user_id);

        return true;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != "https://workflow-api.finalprice.com/v1/base/user/get") {
            $this->http->GetURL("https://workflow-api.finalprice.com/v1/base/user/get");
        }
        $response = $this->http->JsonLog(null, true, true);
        $user = ArrayVal($response, 'user', null);
        $cover_stats = ArrayVal($response, 'cover_stats', null);
        $credits = ArrayVal($cover_stats, 'credits', null);
        $savings = ArrayVal($cover_stats, 'savings', null);
        // Balance - Credits
        $this->SetBalance(ArrayVal($credits, 'text', null));
        // Pending credits
        $sections = ArrayVal($credits, 'sections', []);

        foreach ($sections as $section) {
            if (ArrayVal($section, 'subtext') == 'PENDING') {
                $this->SetProperty("CombineSubAccounts", false);
                $balance = ArrayVal($section, 'text', null);

                if ($balance == '$0' || $balance == null) {
                    $this->logger->notice("[Pending credits]: do not show zero balance {$balance}");
                } else {
                    $this->AddSubAccount([
                        "Code"        => "finalPricePending",
                        "DisplayName" => "Pending credits",
                        "Balance"     => $balance,
                    ]);
                }
            }// if (ArrayVal($section, 'subtext') == 'PENDING')
        }// foreach ($sections as $section)
        // Your savings
        $this->SetProperty("Savings", ArrayVal($savings, 'text'));
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($user, 'first_name') . " " . ArrayVal($user, 'last_name')));
    }

    private function correctingPhoneNumber()
    {
        $this->logger->notice(__METHOD__);
        // correcting mobile #
        if ($this->AccountFields['Login2'] == 'cell_number') {
            $this->logger->notice("correcting mobile #: adding country code");

            if (!empty($this->State['Login'])) {
                $this->AccountFields['Login'] = $this->State['Login'];
            } else {
                $this->http->GetURL("https://workflow-api.finalprice.com/v1/get_countries/");
                $response = $this->http->JsonLog(null, true, true);
                $countries = ArrayVal($response, 'countries', []);

                foreach ($countries as $country) {
                    $name = ArrayVal($country, 'name', null);
                    $code = ArrayVal($country, 'code', null);

                    if ($name == $this->AccountFields['Login3']) {
                        $this->AccountFields['Login'] = $code . $this->AccountFields['Login'];

                        break;
                    }
                }// foreach ($countries as $country)
            }
        }// if ($this->AccountFields['Login2'] == 'cell_number')
    }
}
