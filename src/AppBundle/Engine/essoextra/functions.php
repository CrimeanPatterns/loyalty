<?php

// refs #2010, essoextra

use AwardWallet\Engine\ProxyList;

class TAccountCheckerEssoextra extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""            => "Select your region",
            "Belgium"     => "Belgium",
            //            "Canada"      => "Canada",// The Esso Extra program in Canada ended on January 17, 2022 and was replaced by PC Optimum.
            "Netherlands" => "Netherlands",
        ];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyNetNut(null, 'nl');
        $this->http->setHttp2(true);
//            $this->http->SetProxy($this->proxyUK());
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ($this->AccountFields['Login2'] == 'Canada') {
            throw new CheckException("The Esso Extra program in Canada <a href='https://www.esso.ca/en-ca/esso-extra'>ended on January 17, 2022</a> and was replaced by PC Optimum.", ACCOUNT_PROVIDER_ERROR);
        }

        $country = 'nl';

        if ($this->AccountFields['Login2'] == 'Belgium') {
            $country = 'be';
        }

        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(2);
        $this->http->GetURL("https://www.essoextras.{$country}/#/auth/login/");

        if (!$this->http->FindSingleNode("//script[contains(@src, '/static/js/main.')]/@src")) {
            return false;
        }

        $email = '';
        $cardNumber = '';

        if ($this->http->FindPreg('/^\d{10,}$/', false, $this->AccountFields['Login'])) {
            $cardNumber = $this->AccountFields['Login'];
        } else {
            $email = $this->AccountFields['Login'];
        }

        $token = $this->parseCaptcha();

        if (!$token) {
            return false;
        }

        $this->http->PostURL('https://esso-backend-api-prod.m-point.eu/api/Auth/login', json_encode([
            'email'      => $email,
            'cardNumber' => $cardNumber,
            'password'   => $this->AccountFields['Pass'],
            'country'    => $country,
            'token'      => $token,
        ]), [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ]);

        return true;
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        switch ($this->AccountFields['Login2']) {
            case 'Belgium':
                $arg['RedirectURL'] = 'https://www.essoextras.be';

                break;

            case 'Netherlands':
                $arg['RedirectURL'] = 'https://www.essoextras.nl';

                break;

            default:
                $arg['RedirectURL'] = 'https://www.essoextra.com/pages/member_home.aspx';

                break;
        }// switch ($this->AccountFields['Login2'])

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->message) && in_array($response->message, [
            'Credentials are not much (Email/cardNumber + password).',
            'Email not found.',
            'Card must be min 12 and max 20 digits',
        ])
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);
        }

        if (isset($response->message) && in_array($response->message, [
            "Account’s email has not been confirmed.",
        ])) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($response->message, ACCOUNT_PROVIDER_ERROR);
        }
        // Successful login
        if (isset($response->payload->accessToken)) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if (isset($response->message) && $response->message == "None") {
            $this->captchaReporting($this->recognizer, false);
            $this->DebugInfo = "wrong captcha answer";

            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();
        $headers = [
            'AccessToken'  => $response->payload->accessToken,
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];
        $this->http->GetURL('https://esso-backend-api-prod.m-point.eu/api/Customer/currentUser', $headers);
        $response = $this->http->JsonLog();
        // Balance - Puntensaldo
        $this->SetBalance($response->payload->accountBalance);
        // Name
        $this->SetProperty("Name", beautifulName($response->payload->firstName . ' ' . $response->payload->lastName));
        // Kaartnr:
        $this->SetProperty("Number", $response->payload->masterCardNumber);

        // Expiration Date - refs#17792
        $this->http->GetURL("https://esso-backend-api-prod.m-point.eu/api/Points/{$response->payload->accountID}/getAllPointsHistory?sortOrder=0&pageNumber=1&pageSize=100", $headers);
        $response = $this->http->JsonLog();

        if (!empty($response->payload->records)) {
            $maxDate = 0;

            foreach ($response->payload->records as $item) {
                if (isset($item->pointsIssued)) {
                    $lastActivity = $this->http->FindPreg('/(\d+-\d+-\d+)T/', false, $item->timeStampy);
                    $this->logger->debug("Last Activity: {$lastActivity}");
                    $expDate = strtotime($lastActivity, false);

                    if ($expDate && $expDate > $maxDate && $item->pointsIssued != 0) {
                        $maxDate = $expDate;
                        $this->SetExpirationDate(strtotime('+12 month', $maxDate));
                        $this->SetProperty('LastActivity', $lastActivity);
                    }
                }
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        /*
         * // https://www.essoextras.nl/static/js/29.178d143d.chunk.js
            e(window.grecaptcha.execute("6Ld1XoEdAAAAAEKQCWIUE32IuChXSC53ibcATaTo", {
                action: "submit"
            }))
         */
        $key = '6Ld1XoEdAAAAAEKQCWIUE32IuChXSC53ibcATaTo';

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.9,
            "pageAction"   => "submit",
            //            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "invisible" => 1,
            "action"    => "submit",
            "min_score" => 0.7,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
