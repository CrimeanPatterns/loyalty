<?php

// refs #1963
require_once __DIR__ . '/../royalcaribbean/functions.php';

class TAccountCheckerCelebritycruises extends TAccountCheckerRoyalcaribbean
{
    /**
     * like as royalcaribbean, celebritycruises, azamara.
     */
    public $headers = [
        "AppKey"           => "qpRMO6lj4smwkT1sWlSdIj7b8QF5rG8Q",
        "Accept"           => "application/json, text/plain, */*",
        "X-Requested-With" => "XMLHttpRequest",
        "Access-Token"     => '',
    ];
    public $brand = "C";
    public $domain = "celebritycruises.com";

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->access_token, $response->id_token)) {
            $accessToken = $response->access_token;
            $idToken = $response->id_token;

            $this->http->setCookie("accessToken", $accessToken, ".celebritycruises.com");
            $this->http->setCookie("idToken", $idToken, ".celebritycruises.com");

            foreach (explode('.', $idToken) as $str) {
                $str = base64_decode($str);
                $this->logger->debug($str);

                if ($this->vdsid = $this->http->FindPreg('/"vdsid":"(.+?)"/', false, $str)) {
                    break;
                }
            }

            if (!isset($this->vdsid)) {
                return false;
            }

            $this->headers["Access-Token"] = $accessToken;
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://aws-prd.api.rccl.com/en/celebrity/web/v3/guestAccounts/{$this->vdsid}", $this->headers);
            $this->http->RetryCount = 2;
            $this->http->JsonLog();

            if (!$this->http->FindPreg('/^G/', false, $this->vdsid)) {
                $this->http->JsonLog();
                /*
                 * We're improving your account
                 * To ensure your account works with recent updates, please complete all fields below.
                 */
                if ($this->http->FindPreg('/"internalMessage"\s*:\s*"(Both a access token and account ID are required in the request to continue\.")/')) {
                    $this->throwProfileUpdateMessageException();
                }
            }// if (!$this->http->FindPreg('/^G/', false, $this->vdsid))

            return true;
        }

        if (isset($response->error, $response->error_description)) {
            $message = $response->error_description;
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "Login failure")) {
                throw new CheckException("We can't find that email. Please check the email you entered or create a new account.", ACCOUNT_INVALID_PASSWORD);
            }

            if (is_numeric($message)) {
                throw new CheckException("The email or password is not correct. You have {$message} tries remaining before you'll need to reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, "Your account has been locked.")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // Invalid email and password combination.
        if (
            $this->http->FindPreg('/\{"code":401,"reason":"Unauthorized","message":"Login failure"(?:,"detail":\{"failureUrl":""\}|)\}/')
            || $this->http->Response['body'] == '{"code":401,"reason":"Unauthorized","message":"User has already been migrated"}'
        ) {
            throw new CheckException("Invalid email and password combination.", ACCOUNT_INVALID_PASSWORD);
        }
        // We're unable to complete your request, so please try again later.
        if ($this->http->Response['body'] == '{"code":500,"reason":"Internal Server Error","message":"Authentication Error!!"}') {
            throw new CheckException("We're unable to complete your request, so please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['body'] == '{"code":401,"reason":"Unauthorized","message":"Your account has been locked."}') {
            throw new CheckException("Your account has been locked.", ACCOUNT_LOCKOUT);
        }
        /*
         * Please try again. Make sure you enter the email and password associated with your account.
         * Your account will be frozen after 10 unsuccessful sign-in attempts.
         */
        if ($attempts = $this->http->FindPreg('/\{"code":401,"reason":"Unauthorized","message":"\s*(\d+)"/')) {
            throw new CheckException("Please try again. Make sure you enter the email and password associated with your account. Your account will be frozen after {$attempts} unsuccessful sign-in attempts.", ACCOUNT_INVALID_PASSWORD);
        }
        // provider bug fix - remember me cookie
        if ($this->http->Response['code'] == 502 && empty($this->http->Response['body'])) {
            $this->logger->error("Need to remove 'fr_remember_me' cookie!");

            return false;
        }
        /*
         * Pardon the interruption
         * Our enhanced security requires a one-time account validation.
         * /
        if (strstr($this->http->Response['body'], '{"code":401,"reason":"Unauthorized","message":"User Needs to be Migrated",'))
            $this->throwProfileUpdateMessageException();
        */

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Service Unavailable - DNS failure
        if ($this->http->Response['code'] == 503 && $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - DNS failure')]")) {
            throw new CheckRetryNeededException(2, 10, self::PROVIDER_ERROR_MSG);
        }
        // Sorry I missed you, I’m currently on a cruise for some much needed downtime.
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "Sorry I missed you, I’m currently on a cruise for some much needed downtime.")]
                | //p[contains(text(), "Our website needs an important system update to better serve you. We´ll be back shortly.")]
                | //p[contains(text(), "Well, our servers are – and our website is down temporarily.")]
                | //h4[contains(text(), "Our system is unavailable due to scheduled maintenance.")]
        ')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();

        if (!isset($response->payload->personalInformation->firstName, $response->payload->loyaltyInformation)) {
            if ($this->http->FindPreg('/developerMessage":"The upstream Profile Opt-ins service has unexpectedly failed for account ID/')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }
        // Name
        $this->SetProperty('Name', beautifulName($response->payload->personalInformation->firstName . ' ' . $response->payload->personalInformation->lastName));

        $loyalty = $response->payload->loyaltyInformation;

        if (isset($loyalty->captainsClubLoyaltyRelationshipPoints)) {
            // Balance - Points
            $this->SetBalance($loyalty->captainsClubLoyaltyRelationshipPoints);
            // for Elite Level Tab  // refs #16803
            $this->SetProperty('ClubPoints', $loyalty->captainsClubLoyaltyRelationshipPoints);
            // Number - Captain’s Club
            $this->SetProperty('Number', $loyalty->captainsClubId ?? null);
            // Tier
            $this->SetProperty('MembershipLevel', $loyalty->captainsClubLoyaltyTier ?? null);
        }
        // Sorry, we’re unable to display your loyalty tier and points right now. Please check back later.
        elseif (
            !empty($this->Properties['Name'])
            && !empty($loyalty->captainsClubId)
            && $this->http->FindPreg("/,\"loyaltyInformation\":\{\"azamaraLoyaltyId\":\"{$loyalty->azamaraLoyaltyId}\",\"captainsClubId\":\"{$loyalty->azamaraLoyaltyId}\"\},/")
        ) {
            $this->SetWarning("Sorry, we’re unable to display your loyalty tier and points right now. Please check back later.");
            // Number - Captain’s Club
            $this->SetProperty('Number', $loyalty->captainsClubId ?? null);
        } elseif (
            !empty($this->Properties['Name'])
            && (
                $this->http->FindPreg("/,\"loyaltyInformation\":\{\},/")
                || $this->http->FindPreg("/,\"loyaltyInformation\":\{\"crownAndAnchorId\":\"\d+\",\"crownAndAnchorSocietyLoyaltyTier\":\"[^\"]+\",\"crownAndAnchorSocietyLoyaltyIndividualPoints\":\d+,\"crownAndAnchorSocietyLoyaltyRelationshipPoints\":\d+\}/")// AccountID: 4832937
                || $this->http->FindPreg("/,\"loyaltyInformation\":\{\"celebrityBlueChipId\":\"\d*\",(?:\"clubRoyaleId\":\"\d*\",|)(?:\"clubRoyaleLoyaltyTier\":\"[^\"]+\",|)(?:\"clubRoyaleLoyaltyIndividualPoints\":\d+,|)(?:\"celebrityBlueChipLoyaltyTier\":\"[^\"]+\",|)(?:\"clubRoyaleLoyaltyRelationshipPoints\":\d+,|)(?:\"crownAndAnchorId\":\"\d*\",|)(?:\"crownAndAnchorSocietyLoyaltyTier\":\"[^\"]+\",|)(?:\"crownAndAnchorSocietyLoyaltyIndividualPoints\":\d*,|)(?:\"crownAndAnchorSocietyLoyaltyRelationshipPoints\":\d*|)\}/")// AccountID: 4832937
                || $this->http->FindPreg("/,\"loyaltyInformation\":\{\"celebrityBlueChipId\":\"\d+\",\"celebrityBlueChipLoyaltyTier\":\"PEARL\",\"celebrityBlueChipLoyaltyIndividualPoints\":\d+,\"celebrityBlueChipLoyaltyRelationshipPoints\":\d+\}\,/")// AccountID: 4628251
                || $this->http->FindPreg("/,\"loyaltyInformation\":\{\"crownAndAnchorId\":\"\d+\",\"crownAndAnchorSocietyLoyaltyTier\":\"PLATINUM\",\"crownAndAnchorSocietyLoyaltyIndividualPoints\":\d+,\"crownAndAnchorSocietyLoyaltyRelationshipPoints\":\d+\}\,/")// AccountID: 5857804
                || $this->http->FindPreg("/,\"loyaltyInformation\":\{\"crownAndAnchorId\":\"\d+\",\"crownAndAnchorSocietyLoyaltyTier\":\"EMERALD\",\"crownAndAnchorSocietyLoyaltyIndividualPoints\":\d+,\"crownAndAnchorSocietyLoyaltyRelationshipPoints\":\d+\}\,/")// AccountID: 1014776
                || $this->http->FindPreg("/,\"loyaltyInformation\":\{\"crownAndAnchorId\":\"\d+\"\},/")// AccountID: 4523029
            )
        ) {
            $this->SetBalanceNA();
        } elseif ($this->http->FindPreg("/,\"loyaltyInformation\":\{\"azamaraLoyaltyId\":\"\d+\",\"captainsClubId\":\"\d+\"(?:,\"celebrityBlueChipId\":\"\d*\"|)(?:,\"clubRoyaleId\":\"\d*\"|)(?:,\"crownAndAnchorId\":\"\d*\"|)\,?(?:\"crownAndAnchorSocietyLoyaltyTier\":\"(?:PINNACLE_CLUB|DIAMOND)\",\"crownAndAnchorSocietyLoyaltyIndividualPoints\":\d*,\"crownAndAnchorSocietyLoyaltyRelationshipPoints\":\d*|)\}/")// AccountID: 4340310, 1747367
        ) {
            // Number - Captain’s Club
            $this->SetProperty('Number', $loyalty->captainsClubId ?? null);
            // Balance - CLUB POINTS
            $this->SetBalance(0);
            // Tier
            $this->SetProperty('MembershipLevel', "PREVIEW");
        }
    }
}
