<?php

//  ProviderID: 1405

class TAccountCheckerFlynas extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://booking.flynas.com/api/MemberProfile';

    public $headers = [
        'Accept'       => 'application/json, text/plain, */*',
        'Content-Type' => 'application/json;charset=UTF-8',
    ];

    public $regionOptions = [
        ""          => "Select your login type",
        "Member"    => "Member Login",
        "Corporate" => "Corporate Login",
        "Agencies"  => "Agencies Login",
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        foreach ($this->headers as $header => $value) {
            $this->http->setDefaultHeader($header, $value);
        }
//        $this->http->setDefaultHeader('X-Culture', 'en-US');
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['X-Session-Token'])) {
            return false;
        }

        $this->http->setDefaultHeader('X-Session-Token', $this->State['X-Session-Token']);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->profileDetails->details->nasmiles->nasmilesNumber)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://booking.flynas.com/');

        if ($this->http->FindSingleNode('//img[@src = "Flynas-Website-MaintenanceENG-AR.PNG"]/@src')) {
            throw new CheckException("Dear Customer, Sorry for inconvenience caused as we are upgrading for a superior experience. Thank you for choosing flynas.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->PostURL('https://booking.flynas.com/api/SessionCreate', '{"session":{"channel":"web"}}');

        // first x-session-token
        if (!isset($this->http->Response['headers']['x-session-token'])) {
            return false;
        }

        $firstSessionToken = $this->http->Response['headers']['x-session-token'];

        $formURL = 'https://booking.flynas.com/api/TravelAgentLogin';
        $data = [
            'travelAgentLogin' => [
                'userName' => $this->AccountFields['Login'],
                'password' => $this->AccountFields['Pass'],
            ],
        ];

//        $this->AccountFields['Login2'] = 'Corporate';//todo

        switch ($this->AccountFields['Login2']) {
            case 'Corporate':
                $this->http->GetURL('https://booking.flynas.com/#/agent/login-corp');

                break;

            case 'Agencies':
                $this->http->GetURL('https://booking.flynas.com/#/agent/login');

                break;

            default:
                $this->http->GetURL('https://booking.flynas.com/#/member/login');

                $captcha = $this->parseReCaptcha("6Lfq4dUiAAAAALovF8hu3tWn2XEF7ZF5G2rdhdso");

                if (!$captcha) {
                    return false;
                }

                $formURL = 'https://booking.flynas.com/api/MemberLogin';
                $data = [
                    "captcha" => [
                        "recaptchaToken" => $captcha,
                    ],
                    'memberLogin' => [
                        'userName' => $this->AccountFields['Login'],
                        'password' => $this->AccountFields['Pass'],
                    ],
                ];
        }

        $this->http->setDefaultHeader('X-Session-Token', $firstSessionToken);

        $this->http->RetryCount = 0;
        $this->http->PostURL($formURL, json_encode($data));
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (
            isset($response->memberLogin->firstName)
            || isset($response->travelAgentLogin->firstName)
        ) {
            // last x-session-token
            if (!isset($this->http->Response['headers']['x-session-token'])) {
                return false;
            }
            $this->captchaReporting($this->recognizer);
            $this->State['X-Session-Token'] = $this->http->Response['headers']['x-session-token'];
            $this->http->setDefaultHeader('X-Session-Token', $this->State['X-Session-Token']);

            return true;
        }

        // User account is locked. Please contact your account's administrator.
        if ($this->http->FindPreg("/User account is locked. Please contact your account/")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("User account is locked. Please contact your account's administrator.", ACCOUNT_LOCKOUT);
        }

        if ($this->http->FindPreg("/Invalid Captcha, please try again/")) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
        }

        if ($this->http->FindPreg('/"errorMessage":"Unable to find Agent."/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException('Invalid username and password combination. Please check the details entered are correct and try again.', ACCOUNT_INVALID_PASSWORD);
        }
        // The user is not allowed to login.
        if ($message = $this->http->FindPreg('/^\["(The user is not allowed to login\.)"\]$/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/^\["(Invalid username\/password combination\.)"\]$/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        $response = $this->http->JsonLog(null, 0);

        if (!isset($response->profileDetails->details->nasmiles->nasmilesPoints, $response->profileDetails->details->first)) {
            return;
        }

        // Name
        $this->SetProperty('Name', beautifulName("{$response->profileDetails->details->first} {$response->profileDetails->details->last}"));
        // Account ID
        $this->SetProperty('AccountId', $response->profileDetails->details->nasmiles->nasmilesNumber);
        // Point earned
        $this->SetProperty('PointEarned', $response->profileDetails->details->nasmiles->nasmilesPointsEarnd);
        // SMILE Points Available:
        $this->SetBalance($response->profileDetails->details->nasmiles->nasmilesPoints);
        // Qualifying points - Tier qualifying points:
        $this->SetProperty('QualifyingPoints', $response->profileDetails->details->nasmiles->tierqualifyingpoints);
        // SMILE Points to expire end of the month
        $expiryPoints = $response->profileDetails->details->nasmiles->nasmilesExpirypoints ?? null;
        $this->SetProperty('ExpiringBalance', $expiryPoints);
        // SMILE Points to expire end of the month:
        if ($expiryPoints
            && isset($response->profileDetails->details->nasmiles->nasmilesExpiryDate)
            && $expiryPoints > 0
        ) {
            $expDate = strtotime($response->profileDetails->details->nasmiles->nasmilesExpiryDate, false);

            if ($expDate && $expDate > time()) {
                $this->SetExpirationDate($expDate);
            }
        } elseif ($expiryPoints == 0) {
            $this->ClearExpirationDate();
        }
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            //            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
