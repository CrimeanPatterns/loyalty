<?php

class TAccountCheckerBorders extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        //Borders.com Closing
        $this->ErrorMessage = "Borders.com Closing. Borders.com has partnered with BarnesandNoble.com. <a href='https://www.borders.com/online/store/CustomerServiceView_faqcustomercare' target='_blank'>More Info</a>";
        $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;

        return false;

        $this->http->GetURL("https://www.borders.com/online/store/LogonForm");

        if (!$this->http->ParseForm("Logon")) {
            return false;
        }
        $this->http->Form['logonId'] = strtolower($this->AccountFields['Login']);
        $this->http->Form['logonPassword'] = $this->AccountFields['Pass'];

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        $error = $this->http->FindSingleNode("//div[@id='pageErrorsDiv']");

        if (isset($error)) {
            $this->ErrorMessage = $error;
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;

            return false;
        }

        if ($this->http->FindPreg("/Please enter in a permanent password below/ims")) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = "Please enter in a permanent password";

            return false;
        }

        if ($this->http->FindPreg("/site is currently unavailble/ims")) {
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
            $this->ErrorMessage = "Site is currently unavailble";

            return false;
        }

        if ($this->http->FindPreg("/feature is temporarily unavailable/ims")) {
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
            $this->ErrorMessage = "We're sorry. This feature is temporarily unavailable. Please try again later.";

            return false;
        }

        if ($this->http->FindPreg("/your profile at a glance/ims")) {
            return true;
        }

        if ($error = $this->http->FindPreg("/You have not placed any orders using this account/ims")) {
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
            $this->ErrorMessage = $error;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        if ($link = $this->http->FindSingleNode("//a[contains(text(), 'View Account Summary')]/@href")) {
            $this->http->getURL('https://www.borders.com/online/store/' . $link);
        } else {
            $this->http->getURL('https://www.borders.com/online/store/LogonForm?catalogId=10001&storeId=13551&langId=-1');
        }
        $this->SetProperty("Name", $this->http->FindSingleNode("//div[@class='quick_account_wrapper']/p[1]", null, true, '/Name:(.*)/'));
        $this->SetProperty("EMail", $this->http->FindSingleNode("//div[@class='quick_account_wrapper']/p[2]", null, true, '/Email(.*)/'));
        $this->SetProperty("UserName", $this->http->FindSingleNode("//div[@class='quick_account_wrapper']/p[3]", null, true, '/Username:(.*)/'));
        $this->SetBalance($this->http->FindSingleNode("//h1[contains(text(),'Account Summary')]/../table/tr[1]/td[@class='balance']"));
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(text(),'Card number')]", null, true, '/Card number (.*)/'));
        $this->SetProperty("AvailableMonth", $this->http->FindSingleNode("//h1[contains(text(),'Account Summary')]/../table/tr[2]/td[@class='balance']"));
        $this->SetProperty("AvailableYear", $this->http->FindSingleNode("//h1[contains(text(),'Account Summary')]/../table/tr[3]/td[@class='balance']"));

        $this->http->getURL('http://www.borders.com/online/store/CustomerServiceView_termsconditions');
        $error = $this->http->FindPreg("/The Borders Rewards and Borders Rewards Plus programs are suspended at this time/ims");

        if (!isset($error)) {
            $error = $this->http->FindPreg("/We're sorry\. This feature is temporarily unavailable\. Please try again later\./ims");
        }

        if (isset($error)) {
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
            $this->ErrorMessage = $error;

            return false;
        }
        $error = $this->http->FindSingleNode('//strong[contains(text(),"Register your Borders Rewards Number")]/..');

        if (isset($error)) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD; //member
            $this->ErrorMessage = $error;

            return false;
        }
    }
}
