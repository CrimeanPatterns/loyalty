<?php

class TAccountCheckerDisneyresort extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://disneyland.disney.go.com/login/');
        $clientId = $this->http->FindPreg("/clientId\":\"([^\"]+)/");

        if (!$clientId) {
            return $this->checkErrors();
        }

        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/{$clientId}-PROD/api-key?langPref=en-US", []);
        $apiKey = ArrayVal($this->http->Response['headers'], 'api-key');
        $correlationId = ArrayVal($this->http->Response['headers'], 'correlation-id', null);
        $conversationId = ArrayVal($this->http->Response['headers'], 'conversation-id', null);

        $this->http->GetURL("https://cdn.registerdisney.go.com/v2/{$clientId}-PROD/en-US?include=config,l10n,js,html&?clientID={$clientId}scheme=https&postMessageOrigin=https%3A%2F%2Fdisneyland.disney.go.com%2Flogin&cookieDomain=disneyland.disney.go.com&config=PROD&logLevel=INFO&topHost=disneyland.disney.go.com&cssOverride=https%3A%2F%2Fcdn1.parksmedia.wdprapps.disney.com%2Fmedia%2Flightbox%2Fdlr%2Fstyles%2Fbranded-web.css&responderPage=%2Fauthentication%2Fresponder.html&buildId=17957843e92");
//        if (!$this->http->ParseForm(null, '//section[contains(@class, "workflow-login")]//form')) {
//            return $this->checkErrors();
//        }

        // enterprise ...
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "loginValue" => $this->AccountFields['Login'],
            "password"   => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Content-Type"      => "application/json",
            "Accept"            => "*/*",
            'authorization'     => sprintf('APIKEY %s', $apiKey),
            'g-recaptcha-token' => $captcha,
            "correlation-id"    => $correlationId ?? $this->gen_uuid(),
            "conversation-id"   => $conversationId ?? $this->gen_uuid(),
            "oneid-reporting"   => 'eyJjb250ZXh0IjoiIiwic291cmNlIjoiIn0=',
            "device-id"         => 'null',
            "expires"           => -1,
            "Origin"            => "https://cdn.registerdisney.go.com",
            "Referer"           => '',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/{$clientId}-PROD/guest/login?langPref=en-US", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "Service Unavailable - Zero size object")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $this->http->JsonLog();

        if (
            $this->http->FindSingleNode('//a[contains(text(), "Sign Out")]/@href')
            || ($this->http->getCookieByName("WOMID") && !strstr($this->http->currentUrl(), 'https://disneyland.disney.go.com/registration/index/swid/'))
        ) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Email or Username and/or Password do not match")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Sorry, there are one or more errors on this page")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The email or username and/or password do not match our records. Please try again.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "The email or username and/or password do not match our records. Please try again.")]')) {
            throw new CheckException("The email or username and/or password do not match our records. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }
        // Error:You have exceeded the limit of sign-in attempts. Please wait and try again or reset your password.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "You have exceeded the limit of sign-in attempts")]')) {
            throw new CheckException("Error:You have exceeded the limit of sign-in attempts. Please wait and try again or reset your password.
", ACCOUNT_INVALID_PASSWORD);
        }
        // We need you to update your password. We've emailed
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We need you to update your password. We")]')) {
            $this->throwProfileUpdateMessageException();
        }
        // We need some help to ensure your account information is complete.
        if ($message = $this->http->FindSingleNode('
                //h3[contains(text(), "Complete Your Registration")]
                | //div[contains(text(), "We\'ve updated our Terms of Use and you need to read and agree to the new terms to continue.")]
            ')
        ) {
            $this->throwAcceptTermsMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://disneyland.disney.go.com/profile/');
        // Your Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@class = "guestName"]/h2[@class="guestSensitive"]')));

        if (ACCOUNT_ENGINE_ERROR == $this->ErrorCode && !empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        } else {
            // hard code
            $accountID = ArrayVal($this->AccountFields, 'RequestAccountID', $this->AccountFields['AccountID']);
            $accountIDs = [3565913, 4034795, 3516812, 4010402, 3821511, 3550993];

            if ($this->http->FindSingleNode("//h2[contains(text(), 'Someone Ate the Page!')]") && in_array($accountID, $accountIDs)) {
                $this->SetBalanceNA();
            }

            // We need you to update your password. We've emailed
            if ($message = $this->http->FindSingleNode('//div[contains(text(), "We\'ve updated our Terms of Use and you need to read and agree to the new terms to continue.")]')) {
                $this->throwAcceptTermsMessageException();
            }
        }
    }
}
