<?php

// refs #6426
class TAccountCheckerAvisfirst extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.ccrgservices.com/web/AvisFirst/");

        if (!$this->http->ParseForm("frmLogin")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("wizno", $this->AccountFields["Login"]);
        $this->http->SetInputValue("pswd1", $this->AccountFields["Pass"]);

        return true;
    }

    public function checkErrors()
    {
        // There is a system error and we couldn't process your request online
        if ($message = $this->http->FindPreg("/(There is a system error and we couldn\'t process your request online\.(?:&nbsp;\s*|\s*)Please call Avis at 1-800-331-1200 for support or try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Due to scheduled system maintenance, we are unable to process your request at this time.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("//a[contains(@onclick, 'logoff.do')]/@onclick")) {
            return true;
        }
        //# We're sorry, but we are unable to verify your login information at this time.
        if ($message = $this->http->FindPreg("/(We\'re sorry,&nbsp;but we are unable to verify your login information at this time\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Our records indicate that you are not an Avis First member
        if ($message = $this->http->FindPreg("/(Our records indicate that you are not an Avis First member)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# The password you have entered doesn't match our records.
        if ($message = $this->http->FindPreg("/(The password you have entered doesn't match our records\.\&nbsp;Please try to enter your information again\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Our records indicate that you have not yet activated your Avis First membership.
        if ($message = $this->http->FindPreg("/(Our records indicate that you have not yet activated your Avis First membership\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * There is a system error and we couldn't process your request online.
         * Please call Avis at 1-800-331-1200 for support or try again later.
         */
        if ($message = $this->http->FindPreg("/(There is a system error and we couldn\'t process your request online\.\s*Please call Avis at 1-800-331-1200 for support or try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        /*
         * To access this website, Avis First members must complete 12 qualifying rentals
         * or 35 qualifying rental days in a calendar year. Please note that you have not yet met these requirements.
         * Once you have met the requirements, we will inform you that you can access the site.
         */
        if ($message = $this->http->FindPreg("/(To access this website\,\&nbsp\;Avis First members must complete<br>12 qualifying rentals or 35 qualifying rental days in a calendar<br>year\.)/ims")) {
            throw new CheckException(CleanXMLValue(str_replace('<br>', ' ', $message)), ACCOUNT_PROVIDER_ERROR);
        }

        // hard code
        if ($this->http->ParseForm("frmLogin") && $this->http->Form["wizno"] == substr($this->AccountFields["Login"], 0, 6)
            && $this->http->FindPreg("/As of July 1, 2015, Avis First members will be automatically converted to the new Avis Preferred Plus tier\./")) {
            throw new CheckException("Avis Preferred now comes with a new level of rewards - Avis Preferred Points. <b/>As of July 1, 2015, Avis First members will be automatically converted to the new Avis Preferred Plus tier. Your current Weekend Rewards will be available to view and use through September 30, 2015. To earn rewards on future rentals, opt-in to Avis Preferred Points. <b/>This website will expire October 1, 2015.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//td[contains(text(), 'Welcome,')]", null, true, "/Welcome,\s*([^<]+)/ims")));
        //# Wizard Number
        $this->SetProperty('WizardNumber', $this->http->FindSingleNode("//td[contains(text(), 'Wizard Number')]/following-sibling::td/strong"));
        //# Membership Date
        $this->SetProperty('MembershipDate', $this->http->FindSingleNode("//td[contains(text(), 'Membership Date')]/following-sibling::td/strong"));
        //# Activation Date
        $this->SetProperty('ActivationDate', $this->http->FindSingleNode("//td[contains(text(), 'Activation Date')]/following-sibling::td/strong"));
        //# Membership Valid Through
        $this->SetProperty('MembershipValidThrough', $this->http->FindSingleNode("//td[contains(text(), 'Membership Valid Through')]/following-sibling::td/strong"));
        //# Balance - Qualifying Rentals (Annual Weekend Reward Qualification)
        $this->SetBalance($this->http->FindSingleNode("//*[contains(text(), 'Annual Weekend Reward Qualification')]/ancestor::tr[1]/following-sibling::tr/td[contains(text(), 'Qualifying Rentals')]/following-sibling::td/strong"));
        //# Rentals to Next Reward
        $this->SetProperty('RentalsToNextReward', $this->http->FindSingleNode("//td[contains(text(), 'Rentals to Next Reward')]/following-sibling::td/strong"));
        //# Rewards Issued
        $this->SetProperty('RewardsIssued', $this->http->FindSingleNode("//td[contains(text(), 'Rewards Issued')]/following-sibling::td/strong"));

        //# Qualifying Rentals (Annual Membership Qualification)
        $this->SetProperty('QualifyingRentals', $this->http->FindSingleNode("//*[contains(text(), 'Annual Membership Qualification')]/ancestor::tr[1]/following-sibling::tr[1]/td[contains(text(), 'Qualifying Rentals')]/following-sibling::td/strong"));
        //# Qualifying Rental Days
        $this->SetProperty('QualifyingRentalDays', $this->http->FindSingleNode("//td[contains(text(), 'Qualifying Rental Days')]/following-sibling::td/strong"));

        //# Program Status
        $this->http->GetURL("https://www.ccrgservices.com/web/AvisFirst/info.do");
        $this->SetProperty('ProgramStatus', $this->http->FindSingleNode("//td[contains(text(), 'Program Status')]/following-sibling::td/strong"));

        // Voucher - Coupon Details
        $this->http->GetURL("https://www.ccrgservices.com/web/AvisFirst/award.do");
        $coupons = $this->http->XPath->query("//table[@class = 'awardtable']//tr[@class = 'FootNoteText']");
        $this->http->Log("Total coupons found: " . $coupons->length);

        if ($coupons->length > 0) {
            for ($i = 0; $i < $coupons->length; $i++) {
                $mailedDate = $this->http->FindSingleNode('td[1]', $coupons->item($i));
                $code = $this->http->FindSingleNode('td[2]', $coupons->item($i));
                $exp = $this->http->FindSingleNode('td[3]', $coupons->item($i));
                $redeemed = $this->http->FindSingleNode('td[4]', $coupons->item($i));

                if (strtolower($redeemed) != 'expired' && strtolower($redeemed) != 'y') {
                    $subAccounts[] = [
                        'Code'           => 'avisfirst' . $code,
                        'DisplayName'    => "Coupon # " . $code,
                        'Balance'        => null,
                        'MailedDate'     => $mailedDate,
                        'ExpirationDate' => strtotime($exp),
                    ];
                }// if (strtolower($redeemed) != 'Expired')
            }// for ($i = 0; $i < $coupons->length; $i++)

            if (isset($subAccounts)) {
                //# Set Sub Accounts
                $this->SetProperty("CombineSubAccounts", false);
                $this->http->Log("Total subAccounts: " . count($subAccounts));
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
            }// if(isset($subAccounts))
        }// if ($coupons->length > 0)
    }
}
