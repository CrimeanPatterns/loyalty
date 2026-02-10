<?php

class TAccountCheckerDeltahotels extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://reservations.deltahotels.com');

        if (!$this->http->ParseForm(null, 1, "//form[@action = '/user/login']")) {
            return $this->checkErrors();
        }
        $this->http->Form = [];
        $this->http->FormURL = 'https://www.deltahotels.com/user/login';
        $this->http->SetInputValue('Login', $this->AccountFields['Login']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);
        $this->http->Form['LoginButton'] = '';
        $this->http->Form['RedirectURI'] = '/member/dashboard';

        return true;
    }

    public function checkErrors()
    {
        //# We are currently experiencing difficulties
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are currently experiencing difficulties')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# 500 Internal Server Error
        if ($message = $this->http->FindSingleNode("//p[contains(text(), '500 Internal Server Error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An unexpected error has occurred.
        if ($message = $this->http->FindPreg("/(An unexpected error has occurred\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        //# Please try to login again with a valid username and password
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Please try to login again with a valid username and password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# There is already an account associated with this email address but it has not been confirmed.
        if ($message = $this->http->FindPreg("/There is already an account associated with this email address but it has not been confirmed\./ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//a[contains(@href,'logout')]/@href")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // MEMBER #
        $this->SetProperty("Number", $this->http->FindSingleNode("//h4[contains(text(), 'Your Delta Privilege Member Status')]/span[1]", null, true, '/#(.*)/ims'));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//h4[contains(text(), 'Your Delta Privilege Member Status')]/span[1]", null, true, '/(.*)\#/ims'));
        //# Qualifying Stays
        $stays = $this->http->FindSingleNode("//div[@class = 'member-status-block']//p[contains(text(), 'Qualifying Stays:')]", null, true, '/Qualifying\s*Stays:\s*([^<]+)/ims');
        $this->SetProperty("QualifyingStays", $stays);
        //# Balance - Qualifying Room Nights
        $this->SetBalance($stays);
        //# Qualifying Room Nights
        $this->SetProperty("QualifyingRoomNights", $this->http->FindSingleNode("//div[@class = 'member-status-block']//p[contains(text(), 'Qualifying Room Nights:')]", null, true, '/Qualifying\s*Room\s*Nights:\s*([^<]+)/ims'));

        // SubAccounts - Your partner programs
        $nodes = $this->http->XPath->query("//div[contains(@class, 'line-block') and div[@class = 'booking-title']]");
        $this->http->Log("Total nodes found " . $nodes->length);

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            //# Program
            $displayName = $this->http->FindSingleNode("div[@class = 'booking-title']", $node);
            //# Member Number
            $memberNumber = $this->http->FindSingleNode("p[contains(text(), 'Member Number:')]", $node, true, "/Member\s*number\s*:\s*([^<]+)/ims");
            //# Program points
            $programPoints = $this->http->FindSingleNode("p[contains(text(), 'Points:')]", $node, true, "/points\s*:\s*([^<]+)/ims");

            if (isset($displayName, $programPoints) && $programPoints > 0) {
                if (isset($memberNumber)) {
                    $displayName .= ' (' . $memberNumber . ')';
                }
                $subAccount[] = [
                    "Code"         => 'deltahotels' . preg_replace('/\s*/', '', $displayName),
                    "DisplayName"  => $displayName,
                    "Balance"      => $programPoints,
                    "MemberNumber" => $memberNumber,
                ];
            }// if (isset($displayName, $programPoints))
        }// for ($i = 0; $i < $nodes->length; $i++)

        if (isset($subAccount) && count($subAccount) > 0) {
            $this->http->Log("Total Subaccounts found " . count($subAccount));
            $this->SetProperty("CombineSubAccounts", false);
            $this->SetProperty("SubAccounts", $subAccount);
        }

        $this->http->GetURL("https://www.deltahotels.com/member/edit");
        // Name
        $name = $this->http->FindSingleNode("//div[@id='profileSummary']/h3");

        if (!isset($name)) {
            $name = CleanXMLValue($this->http->FindSingleNode("//input[@id = 'first-name']/@value")
                . ' ' . $this->http->FindSingleNode("//input[@id = 'last-name']/@value"));
        }
        $this->SetProperty("Name", beautifulName($name));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//label[contains(text(), 'Member Since:')]", null, true, "/Member Since:\s*([^<]+)/ims"));
    }
}
