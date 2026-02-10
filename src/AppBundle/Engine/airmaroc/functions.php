<?php

class TAccountCheckerAirmaroc extends TAccountChecker
{
    private const REWARDS_PAGE_URL = "https://www.royalairmaroc.com/nl-en/safar-flyer/my-dashboard";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.royalairmaroc.com/nl-en/login");

        if (!$this->http->ParseForm("_login_WAR_ramairwaysportlet_fm")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("_login_WAR_ramairwaysportlet_emailId", $this->AccountFields["Login"]);
        $this->http->SetInputValue("_login_WAR_ramairwaysportlet_password", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("_login_WAR_ramairwaysportlet_remember", 'true');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Login is temporarily unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('(//div[contains(@class, "alert-error")])[1]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'User id or password are not correct.Please try again.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Portlet is temporarily unavailable.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Due to 3 failed access attempts, your Safar Flyer account has been locked, please contact your Safar Flyer customer service by email')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Balance - Award miles
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'Award miles')]/preceding-sibling::h2"));
        // Safar Flyer nº
        $this->SetProperty("Number", $this->http->FindSingleNode("//p[contains(text(), 'Safar Flyer nº')]", null, true, "/Safar Flyer nº\s*([\d+]+)/ims"));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//p[contains(text(), 'Member ')]", null, true, "/Member\s*([^<+]+)/ims"));
        // Status miles
        $this->SetProperty("QualifyingMiles", $this->http->FindSingleNode('//section[@id = "award-miles"]//span[contains(text(), "Status flights")]/preceding-sibling::span[contains(text(), "Status miles")]/preceding-sibling::span[1]'));
        // Status flights
        $this->SetProperty('QualifyingSectors', $this->http->FindSingleNode('//section[@id = "award-miles"]//span[contains(text(), "Status flights")]/preceding-sibling::span[contains(text(), "Status miles")]/following-sibling::span[1]'));

        // You need to collect *** points to become *STATUS*
//        $this->SetProperty('NeededToNextLevel', $this->http->FindPreg('/You need to collect (\d+) (?:points|Qualifying Miles) to become/iU'));    // Kind = Miles needed to next level
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
        ) {
            if ($message = $this->http->FindSingleNode('//div[contains(text(), "Member Selector is temporarily unavailable.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // Balance - Award miles
            if (
                $this->http->FindSingleNode('//span[contains(text(), "Change password")]')
                || isset($this->Properties['Number'], $this->Properties['QualifyingMiles'], $this->Properties['QualifyingSectors'])// AccountID: 4642344
            ) {
                $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'dashboard-right')]/p[span[contains(text(), 'Award miles')]]/span[1]"));
            }
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && !$this->http->FindSingleNode('//p[contains(text(), "No upcoming trips. Book a trip!")]')) {
            $this->sendNotification("trips were found");
        }

        // ... miles expire on ...
        $date = $this->http->FindSingleNode('//small[contains(text(), "expire on")]', null, true, "/expire on (.+)/");
        $date = $this->ModifyDateFormat($date);
        // Total Balance To Expire
        $balanceToExpire = $this->http->FindSingleNode('//small[contains(text(), "expire on")]', null, true, "/(.+) mile/");

        if ($balanceToExpire > 0 && strtotime($date)) {
            $this->SetProperty("ExpiringBalance", $balanceToExpire);
            $this->SetExpirationDate(strtotime($date));
        }// if ($balanceToExpire > 0 && strtotime($date))

        $this->http->GetURL("https://www.royalairmaroc.com/nl-en/safar-flyer/my-profile");
        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode('//div[@id = "membership-details"]/div[contains(@class, "h3")]')
            ?? $this->http->FindSingleNode('//h1[contains(@class, "heading-name")]', null, true, "/Hello\s*(.+)!/")
        ));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode('//p[contains(text(), "Member since")]', null, true, "/since\s*(.+)/"));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }
}
/*
require_once __DIR__.'/../aircaraibes/functions.php';

class TAccountCheckerAirmarocOld extends TAccountCheckerAircaraibes {

    public $domain = 'ifsram';

    function GetRedirectParams($targetURL = NULL){
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://ifsram.frequentflyer.aero/StandardWebSite/StatusMilesToExpire.jsp?activeLanguage=EN';
        return $arg;
    }

}
*/
