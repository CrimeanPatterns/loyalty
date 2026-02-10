<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerChina extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://members.china-airlines.com/dynasty-flyer/overview.aspx?lang=us-EN&country=us&locale=en';

    private $name = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
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
        $this->http->GetURL("https://members.china-airlines.com/ffp_b2b/dynastySSO.aspx?lang=en-us");
        $mmpswd = $this->http->FindSingleNode("//form[@id = 'form1']//input[@id = 'txtPwd']/@maxlength");

        if ($this->http->Response['code'] != 200 || !$mmpswd) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 0;
        //		$this->http->OptionsURL("https://www.china-airlines.com/us/en/Loginlink?sd=calec");

        //		$this->http->GetURL("https://www.china-airlines.com/us/en/Loginlink?sd=calec");

        $this->AccountFields['Login'] = preg_replace("/\s*/", '', $this->AccountFields['Login']);
        // invalid login
        if (strlen($this->AccountFields['Login']) > 9 || preg_match("/^[a-z]+$/ims", $this->AccountFields['Login'])) {
            throw new CheckException("Please enter a valid card number.", ACCOUNT_INVALID_PASSWORD);
        }

        if (strlen($this->AccountFields['Login']) == 7 && is_numeric($this->AccountFields['Login'])) {
            throw new CheckException("Please enter a valid Card No/Email/Mobile", ACCOUNT_INVALID_PASSWORD);
        }
        $data = [
            "CaptchaString" => null,
            "DfpCardNo"     => $this->AccountFields['Login'],
            "DfpPwd"        => substr($this->AccountFields['Pass'], 0, $mmpswd),
        ];
        $headers = [
            "Content-Type" => "application/json;charset=utf-8",
            "Accept"       => "application/json, text/plain, */*",
        ];
        // send login
        $this->http->PostURL("https://www.china-airlines.com/us/en/Loginlink?sd=calec", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Oops, we are facing some technical issue
        if ($message = $this->http->FindSingleNode("//h5[contains(text(),'Oops, we are facing some technical issue')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The system is busy
        if ($message = $this->http->FindSingleNode("//span[@id = 'ContentPlaceHolder1_lblSysBusyTxt']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error - Read
        if ($this->http->FindSingleNode('
                //h1[contains(text(), "Internal Server Error - Read")]
                | //p[contains(text(), "HTTP Error 503. The service is unavailable.")]
                | //*[self::p or self::td][contains(text(), "This page can\'t be displayed. Contact support for additional information.")]
            ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($this->http->currentUrl() == 'http://www.china-airlines.com/ServiceMaintenance.htm') {
            throw new CheckException("The website is temporarily unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/
        //# An error occurred while processing your request
        if ($message = $this->http->FindPreg('/An error occurred while processing your request/i')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        //		if (!$this->http->PostForm())
//            return $this->checkErrors();
        $response = $this->http->JsonLog(null, 3, true);

        if (isset($response->responseText) && $this->http->FindPreg('/Sorry, the system is not available now, Please try it again later/', false, $response->responseText)) {
            throw new CheckException($response->responseText, ACCOUNT_PROVIDER_ERROR);
        }
        // Oops , we are facing some technical isssues.
        if ($message = $this->http->FindPreg('/"(Oops\s*, we are facing some technical isssues\.)"/')) {
            throw new CheckRetryNeededException(2, 10, Html::cleanXMLValue($message));
        }

        if ($message = $this->http->FindPreg("/(?:The DFP membership card number or password you provided doesn\.)/ims")) {
            throw new CheckException("The DFP membership card number or password you provided doesn't match that we have on the profile.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/alert\('To improve account security and protect your privacy,/")) {
            throw new CheckException("To improve account security and protect your privacy, your password is case sensitive and must be 6 to 8 digits combined with numeric and character.", ACCOUNT_INVALID_PASSWORD);
        }
        // Service is not currently working.
        $responseText = ArrayVal($response, 'responseText');

        if ($responseText == 'Service is not currently working.' && ArrayVal($response, 'cardno') == "") {
            throw new CheckException($responseText, ACCOUNT_PROVIDER_ERROR);
        }
        // Login is successful
        if (ArrayVal($response, 'success') == true) {
            $this->name = ArrayVal($response, 'eFirstName') . " " . ArrayVal($response, 'eLastName');
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            return $this->loginSuccessful();
        }

        if (
            // The DFP membership card number or password you have provided does not match what we have on file. Please verify and try again.
            strstr($responseText, 'The DFP membership card number or password you have provided does not match what we have on file.')
            // Invalid password, please apply for your password.
            || strstr($responseText, 'Invalid password, please apply for your password.')
            // The mobile number or password you have provided does not match what we have on file. Please verify and try again.
            || strstr($responseText, 'The mobile number or password you have provided does not match what we have on file.')
        ) {
            throw new CheckException($responseText, ACCOUNT_INVALID_PASSWORD);
        }
        // Please enter a valid Card No/Email/Mobile
        if (strstr($responseText, 'The format of account is incorrect, Please try again') || strstr($responseText, 'core.MemberLogin_ErrMsg_777')) {
            throw new CheckException("Please enter a valid Card No/Email/Mobile", ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, the system is not available now, Please try it again later.
        if (strstr($responseText, 'Sorry, the system is not available now, Please try it again later.')) {
            throw new CheckException($responseText, ACCOUNT_PROVIDER_ERROR);
        }
        // error shown on the website "core.MemberLogin_ErrMsg_904"
        if (strstr($responseText, 'core.MemberLogin_ErrMsg_904')) {
            throw new CheckException("Please enter a valid Card No/Email/Mobile", ACCOUNT_INVALID_PASSWORD);
        }
        // Please enter a valid Card No/Email/Mobile
        if (strstr($responseText, 'The system has been log-out automatically because you')) {
            throw new CheckException("Please enter a valid Card No/Email/Mobile", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Site Maintenance
        /*
        if ($this->http->currentUrl() == 'https://calec.china-airlines.com/dynasty-flyer/sysmaintenance.aspx')
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        */

        //# Balance - Balance Mileage of Account
        if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(@id, 'lblvMileage')]"))) {
            if ($this->http->FindSingleNode('//p[contains(text(), "HTTP Error 503. The service is unavailable.")]')) {
                throw new CheckException("Sorry, the system is not available now, Please try it again later.", ACCOUNT_PROVIDER_ERROR);
            }
        }
        //# Name
        $name = explode('/', $this->http->FindSingleNode("//span[contains(@id, 'lblName')]/span"));

        if (isset($name[1])) {
            $this->SetProperty('Name', beautifulName($name[1] . ' ' . $name[0]));
        } else {
            $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//span[contains(@id, 'lblName')]/span")));
        }
        //# Membership Card Number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//span[contains(@id, 'lblDfpCard')]"));
        //# Membership Tier
        $this->SetProperty('MembershipTier', $this->http->FindSingleNode("(//span[contains(@id, 'lblCardType')])[1]", null, true, "/([A-Za-z ]+) (membership)?/ims"));
        //# Validity Period
        $this->SetProperty('ValidityPeriod', $this->http->FindSingleNode("//span[contains(@id, 'lblStrExprDt')]/text()[1]"));
        //# Reserved Mileage for Upgrade
        $this->SetProperty('MileageForUpgrade', $this->http->FindPreg("/<td>Reserved Mileage for Upgrade<\/td>\s*<td align='right'><font class='mileNum'>([^<]+)</ims"));
        //# Usable Mileage of Account<
        $this->SetProperty('UsableMileage', $this->http->FindPreg("/<td>Usable Mileage of Account<\/td>\s*<td align='right'><font class='mileNum'>([^<]+)</ims"));
        //# Miles will be expired within 6 months
        $nodes = $this->http->XPath->query("//table[contains(@id, 'ExprMil')]//tr[td]");
        $this->logger->debug("Total {$nodes->length} exp date nodes were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $date = strtotime($this->http->FindSingleNode("td[1]", $nodes->item($i)));
            $miles = $this->http->FindSingleNode("td[4]", $nodes->item($i));

            if ((!isset($exp) || $date < $exp) && $miles > 0) {
                $exp = $date;
                $this->SetExpirationDate($exp);
                //# Miles to expire
                $this->SetProperty('MilesToExpire', $miles);
            }// if (!isset($exp) || $date < $exp)
        }// for ($i = 0; $i < $nodes->length; $i++)

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // maintenance
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'China Airlines (CI) and Mandarin Airlines (AE) will migrate to new passenger service system')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // I have read and agree with the Information Security Policy of China Airlines.
            if ($this->http->currentUrl() == 'https://members.china-airlines.com/dynasty-flyer/gdprconfirm.aspx') {
                // Balance - Mileage Balance
                $this->SetBalance($this->http->FindSingleNode("//span[@id = 'ContentPlaceHolder1_lblHMileage']"));
                // Name
                $this->SetProperty('Name', beautifulName($this->name));
                // Card Number
                $this->SetProperty("AccountNumber", $this->http->FindSingleNode("(//span[contains(@id, 'ContentPlaceHolder1_lblHCardNo')])[2]"));
                // Membership Tier
                $this->SetProperty('MembershipTier', $this->http->FindSingleNode("(//span[contains(@id, 'ContentPlaceHolder1_lblHCardType')])[2]"));

//                $this->throwProfileUpdateMessageException();
            }// if ($this->http->currentUrl() == 'https://calec.china-airlines.com/dynasty-flyer/gdprconfirm.aspx')
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(text(), "Logout")]')) {
            return true;
        }

        return false;
    }
}
