<?php

class TAccountCheckerAmericinn extends TAccountChecker
{
    public function LoadLoginForm()
    {
        throw new CheckException("Now that AmericInn is part of Wyndham Hotel Group, AmericInn's Easy Rewards memberships have been transitioned to Wyndham Rewards. If you would like to redeem your Easy Rewards points for vouchers or checks, please call 1-877-886-8664 by March 15, 2018 as online redemption is no longer available.", ACCOUNT_PROVIDER_ERROR);
        $this->http->removeCookies();
        $this->http->ParseMetaRedirects = false;
        $this->http->LogHeaders = true;
        $this->http->GetURL("https://www.americinn.com/rewards");

        if (!$this->http->ParseForm("form1")) {
            return $this->сheckErrors();
        }
        $this->http->SetInputValue('ctl00$SiteContent$sidebar_0$ERLogin', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$SiteContent$sidebar_0$ERPassword', $this->AccountFields['Pass']);
        $this->http->Form['ctl00$SiteContent$sidebar_0$ERLoginButton.x'] = '47';
        $this->http->Form['ctl00$SiteContent$sidebar_0$ERLoginButton.y'] = '6';
        $this->http->Form['ctl00$SiteContent$content_0$AmericInnRefrence'] = '10';
        $this->http->Form['ctl00$SiteContent$content_0$NightsOfAccomidation'] = '47';
        $this->http->Form['ctl00$SiteContent$content_0$HowManyStates'] = '307';

        return true;
    }

    public function сheckErrors()
    {
        $this->http->Log("[Current URL]: " . $this->http->currentUrl());
        $this->http->Log("[Code]: " . $this->http->Response['code']);

        if ($this->http->Response['code'] == 500) {
            throw new CheckException('AmericInn website had a hiccup, please try to check your balance at a later time.', ACCOUNT_PROVIDER_ERROR);
        }
        //# We’re sorry but the page you are looking for can’t be found
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "re sorry but the page you are looking for can")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // HTTP Error 503. The service is unavailable.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "HTTP Error 503. The service is unavailable.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // We're sorry, but the website is experiencing some technical difficulties at the moment.
        if (in_array($this->http->currentUrl(), ['https://www.americinn.com/Technical%20Issues?', 'https://www.americinn.com/technical%20issues'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->сheckErrors();
        }
        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'SignOut')]/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'or password you entered is incorrect')]", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->сheckErrors();
    }

    public function Parse()
    {
        //# Balance - Point Balance
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'You currently have')]/strong/span"));
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@id = 'FirstName']") . ' ' . $this->http->FindSingleNode("//span[@id = 'LastName']")));
        //# Membership #
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[@id ='MemberNumber']"));

        // old code was returned
        $oldCode = false;

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $oldCode = true;
            // Balance - Point Balance
            $this->SetBalance($this->http->FindSingleNode("//span[contains(@id, 'PointBalance')]"));
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@id, 'FirstName')]") . ' ' . $this->http->FindSingleNode("//span[contains(@id, 'LastName')]")));
            // Membership #
            $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(@id, 'MemberNumber')]"));
            // Easy Rewards Status
            $this->SetProperty("Status", $this->http->FindSingleNode("//span[contains(@id, 'EasyRewardsStatus')]"));
            // Member Since
            $this->SetProperty("MemberSince", $this->http->FindSingleNode("//span[contains(@id, 'MemberSince')]"));
        }

        //# Expiration Date   // refs #4252
        //# Table - My Account History
        $this->http->Form = [];

        if ($this->http->ParseForm('form1')) {
            $this->http->FormURL = 'https://www.americinn.com/rewards/my-account/activity';

            if ($oldCode) {
                $this->http->GetURL("https://www.americinn.com/rewards/my-account/activity");
//                $this->http->SetInputValue('ctl00$SiteContent$content_0$TransactionDateFilter', "");
//                $this->http->SetInputValue('ctl00$SiteContent$content_0$TransactionTypeFilter', "Show All");
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$CheckInDate', "mm/dd/yy");
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$CheckOutDate', "mm/dd/yy");
//
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$adults', "1");
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$adults2', "1");
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$adults3', "1");
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$adults4', "1");
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$children', "0");
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$children2', "0");
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$children3', "0");
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$children4', "0");
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$iata', "Rate Code or IATA");
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$locationSearch', "City, Address, Airport, Attraction");
//                $this->http->SetInputValue('ctl00$SiteContent$sidebar_1$rooms', "1");
//
//                unset($this->http->Form['CheckInDate']);
//                unset($this->http->Form['CheckOutDate']);
//                unset($this->http->Form['roomNumber']);
            } else {
                $this->http->Form['ctl00$SiteContent$content_0$ctl14$TransactionTypeFilter'] = 'Show All';
                $this->http->Form['ctl00$SiteContent$content_0$ctl14$TransactionDateFilter'] = '';
                $this->http->Form['ctl00$SiteContent$content_0$CommentsPoints'] = '';
                $this->http->Form['ctl00$SiteContent$content_0$checkindate'] = 'mm/dd/yyyy';
                $this->http->Form['ctl00$SiteContent$content_0$checkoutdate'] = 'mm/dd/yyyy';
                $this->http->Form['ctl00$SiteContent$content_0$ConfirmationPoints'] = '*Confirmation Number';
                $this->http->Form['ctl00$SiteContent$content_0$FirstNamePoints'] = '*First name';
                $this->http->Form['ctl00$SiteContent$content_0$LastNamePoints'] = '*Last name';
                $this->http->Form['ctl00$SiteContent$content_0$EmailPoints'] = '*Email';
                $this->http->Form['ctl00$SiteContent$content_0$PhonePoints'] = '*Phone';
                $this->http->Form['ctl00$SiteContent$content_0$HotelListPoints'] = '* Select Hotel';
                $this->http->PostForm();
            }

            $dateNodes = $this->http->XPath->query("//ul[@class = 'account-history-info']/li[not(@class = 'account-history-info-header')]");
            $this->http->Log("Total {$dateNodes->length} date nodes were found");

            for ($i = 0; $i < $dateNodes->length; $i++) {
                $node = $dateNodes->item($i);

                if ($this->http->FindSingleNode("div/ul/li/p[contains(text(), 'Points')]//following-sibling::p", $node)) {
                    $expire[$i] = [
                        'date'   => $this->http->FindSingleNode("div[@class = 'dates']", $node),
                        'points' => $this->http->FindSingleNode("div/ul/li/p[contains(text(), 'Points')]//following-sibling::p", $node),
                    ];
                    $exp = strtotime("+24 month", strtotime($expire[$i]['date']));

                    if ($exp !== false && $expire[$i]['points'] > 0) {
                        $this->http->Log("Expiration Date - $exp - " . var_export(date('m/d/Y', $exp), true), true);
                        $this->SetExpirationDate($exp);
                        $this->SetProperty("LastActivity", $expire[$i]['date']);

                        break;
                    }
                }// if ($this->http->FindSingleNode("td[4]", $node))
            }// for($i=0; $i < $dateNodes->length; $i++)
        }// if($this->http->ParseForms('form1'))
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.americinn.com/rewards";

        return $arg;
    }
}
