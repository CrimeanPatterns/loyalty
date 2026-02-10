<?php

class TAccountCheckerChasesears extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.chaseloyalty.com/searsclub/index.do");

        if (!$this->http->ParseForm("accountForm")) {
            return false;
        }
        $this->http->SetInputValue("userId", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->Form["rememberInd"] = "on";
        $this->http->Form["nextButton"] = "Next";

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        $error = $this->http->FindSingleNode("//span[@class = 'errorText']");

        if (isset($error)) {
            throw new CheckException($error);
        }

        return true;
    }

    public function Parse()
    {
        $this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'Current Balance')]/following-sibling::td[1]", null, false, "/([\d\.\,]+)\s+points/ims"));
        $expireDates = $this->http->FindNodes("//table[tr/td/p[contains(text(), 'Points Expration Forecast')]]/tr/td[5]");
        $expirationDate = null;

        foreach ($expireDates as $dateStr) {
            if ($dateStr != "") {
                $d = strtotime($dateStr);

                if ($d !== false && (!isset($expirationDate) || ($d < $expirationDate))) {
                    $expirationDate = $d;
                }
            }
        }

        if (isset($expirationDate)) {
            $this->SetExpirationDate($expirationDate);
        }
        $this->http->GetURL("https://www.chaseloyalty.com/searsclub/points_details.do");
        $this->SetProperty("Earned", $this->http->FindSingleNode("//td[contains(text(), 'Earned This Month')]/following-sibling::td[1]"));
        $this->SetProperty("BonusEarned", $this->http->FindSingleNode("//td[contains(text(), 'Bonus Earned This Month')]/following-sibling::td[1]"));
        $this->SetProperty("Returns", $this->http->FindSingleNode("//td[contains(text(), 'Returns and Adjustments')]/following-sibling::td[1]"));
        $this->SetProperty("Redeemed", $this->http->FindSingleNode("//td[contains(text(), 'Redeemed')]/following-sibling::td[1]"));
        $this->SetProperty("Expired", $this->http->FindSingleNode("//td[contains(text(), 'Expired Since Last Statement')]/following-sibling::td[1]"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.chaseloyalty.com/searsclub/index.do';

        return $arg;
    }
}
