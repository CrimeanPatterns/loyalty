<?php

class TAccountCheckerAmcstubs extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.amctheatres.com/Login.aspx?ReturnUrl=%2frewards%2f");

        if (!$this->http->ParseForm()) {
            $this->CheckErrors();

            return false;
        }

        $this->http->SetInputValue('ctl00$ctl00$cphBodyContent$cphMainContent$loginMain$UserName', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$ctl00$cphBodyContent$cphMainContent$loginMain$Password', $this->AccountFields['Pass']);

        $this->http->Form['__EVENTTARGET'] = 'ctl00$ctl00$cphBodyContent$cphMainContent$loginMain$LoginButton';

        return true;
    }

    public function CheckErrors()
    {
        //# The AMC Community is closed
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'The AMC Community is closed')]//parent::div")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->PostForm();

        $access = $this->http->FindSingleNode("//a[contains(@id, 'ctl00_ctl00_LogOut')]");

        if (isset($access)) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//p[contains(@class, 'errorMessage')][1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Maintenance
        if ($message = $this->http->FindPreg('/(THE SITE IS DOWN)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        // PRIMARY MOVIEWATCHER CARD NUMBER
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//span[@id='ctl00_ctl00_cphBodyContent_cphMainContent_ucRewardsCardInformation_lblMWNumber']"));
        // NEXT REWARD
        $this->SetProperty("NextReward", ucfirst(strtolower($this->http->FindSingleNode("//span[@id='ctl00_ctl00_cphBodyContent_cphMainContent_ucRewardsCardInformation_lblNextReward']"))));

        // Inbox
        $this->SetProperty("Inbox", $this->http->FindSingleNode("//span[@id='ctl00_ctl00_cphBodyContent_cphColumnRight_lvUserProfile_myProfile_lblInboxCount']"));
        // Friends
        $this->SetProperty("Friends", $this->http->FindSingleNode("//span[@id='ctl00_ctl00_cphBodyContent_cphColumnRight_lvUserProfile_myProfile_lblFriendCount']"));
        // Groups
        $this->SetProperty("Groups", $this->http->FindSingleNode("//span[@id='ctl00_ctl00_cphBodyContent_cphColumnRight_lvUserProfile_myProfile_lblGroupCount']"));
        // Blog
        $this->SetProperty("Blog", $this->http->FindSingleNode("//span[@id='ctl00_ctl00_cphBodyContent_cphColumnRight_lvUserProfile_myProfile_lblBlogCount']"));
        // Reviews
        $this->SetProperty("Reviews", $this->http->FindSingleNode("//span[@id='ctl00_ctl00_cphBodyContent_cphColumnRight_lvUserProfile_myProfile_lblReviewCount']"));
        // Movie Queue
        $this->SetProperty("MovieQueue", $this->http->FindSingleNode("//span[@id='ctl00_ctl00_cphBodyContent_cphColumnRight_lvUserProfile_myProfile_lblMovieQueueCount']"));

        // TOTAL POINTS
        $this->SetBalance($this->http->FindSingleNode("//span[@id='ctl00_ctl00_cphBodyContent_cphMainContent_ucRewardsCardInformation_lblPoints']"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://www.amctheatres.com/rewards/';

        return $arg;
    }
}
