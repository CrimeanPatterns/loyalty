<?php

class TAccountCheckerBulgariaair extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://flymore.air.bg/pts/en/?ex=ScageUWZ.LMceoYm2", [], 60);

        if (!$this->http->ParseForm("FORM_EDIT")) {
//            $this->http->removeCookies();
//            $this->http->GetURL("https://flymore.air.bg/update_en.html");
            $this->http->GetURL("https://flymore.air.bg/pts/en/", [], 60);
            /*if (!$this->http->ParseForm("FORM_EDIT")) {
                $this->http->removeCookies();
                $this->http->GetURL("https://flymore.air.bg/pts/en/?ex=");
                if (!$this->http->ParseForm("FORM_EDIT")) {
                    $this->http->removeCookies();
                    $this->http->GetURL("https://flymore.air.bg/pts/en/");
                    if (!$this->http->ParseForm("FORM_EDIT")) {
                        $this->http->removeCookies();
                        $this->http->GetURL("https://flymore.air.bg/");
                    }
                }
            }*/
        }

        if (!$this->http->ParseForm("FORM_EDIT")) {
            return false;
        }
        $this->http->SetInputValue("F:CARDNUMBER", $this->AccountFields['Login']);
        $this->http->SetInputValue("F:HOMEEMAIL", $this->AccountFields['Login2']);
        $this->http->SetInputValue("F:CUSTOMERPASSWORD", $this->AccountFields['Pass']);
        $this->http->SetInputValue("BUTTON:PREVIEW", 'Login');

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("(//a[contains(text(), 'Back')]/@href)[1]")) {
            return true;
        }
        // Errors found: Invalid card number/email/PIN!
        if ($message = $this->http->FindPreg("/Errors found:\s*Invalid card number\/email\/PIN!/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // refs #14127
        if ($this->http->FindPreg("/(?:Errors\s*found\:\s*Field\s*'E\-mail\s*address'\s*is\s*requred\!|Errors\s*found:\s*Field\s*'Card\s*number'\s*is\s*requred\!)/ims")) {
            throw new CheckException("Both Card number and E-mail address are required", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode("//td[b[contains(text(), 'Card number')]]/following-sibling::td[2]")
            . ' ' . $this->http->FindSingleNode("//td[b[contains(text(), 'Card number')]]/parent::tr/following-sibling:: tr[1]/td[3]")));
        // Card number
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//td[b[contains(text(), 'Card number')]]/parent::tr/following-sibling:: tr[1]/td[1]"));
        // Current level
        $this->SetProperty("CurrentLevel", $this->http->FindSingleNode("//td[b[contains(text(), 'Current level')]]/parent::tr/following-sibling:: tr[1]/td[1]"));
        // Gathered points
        $this->SetProperty("GatheredPoints", $this->http->FindSingleNode("//td[b[contains(text(), 'Gathered points')]]/parent::tr[not(td[4])]/following-sibling:: tr[1]/td[1]"));
        // Used points
        $this->SetProperty("UsedPoints", $this->http->FindSingleNode("//td[b[contains(text(), 'Used points')]]/parent::tr[not(td[4])]/following-sibling:: tr[1]/td[2]"));
        // Balance - Account balance
        $this->SetBalance($this->http->FindSingleNode("//td[b[contains(text(), 'Account balance')]]/parent::tr[not(td[4])]/following-sibling:: tr[1]/td[3]"));
    }
}
