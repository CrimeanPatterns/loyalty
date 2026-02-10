<?php

class TAccountCheckerCyprusair extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('http://cyprusair.com/459,0,0,0,2-Members-Services.aspx');

        if (!$this->http->ParseForm("Form1")) {
            return $this->checkErrors();
        }
        $this->http->SetFormText('__EVENTTARGET=ctl00$cp$ctl05$lnkLogin', '&');
        $this->http->SetInputValue('ctl00$cp$ctl05$txtUsername', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$cp$ctl05$txtPassword', $this->AccountFields['Pass']);
        $this->http->setDefaultHeader('User-Agent', 'Mozilla/5.0 (Windows; U; Windows NT 6.1; ru; rv:1.9.2.15) Gecko/20110303 Firefox/3.6.15');

        return true;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Site is currently offline we will back shortly!
        if ($message = $this->http->FindSingleNode("//img[contains(@src, 'cyprus-airways-offline.jpg')]/@src")) {
            throw new CheckException("Site is currently offline we will back shortly!", ACCOUNT_PROVIDER_ERROR);
        }/*review*/
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode('//a[@id="LogButton" and @href="/logoff.aspx"]')) {
            return true;
        }

        //collect errors
        preg_match_all('/(\w+)\.(\w+) = "False"/ims', $this->http->Response['body'], $matches);
        $num_matches = count($matches[1]);
        $errorMessage = '';

        if (($num_matches != 0) && (!empty($matches[1][0]))) {
            for ($i = 0; $i < $num_matches; $i++) {
                $errorMessage .= $this->http->FindSingleNode('//span[@id="' . $matches[1][$i] . '"]/node()[1]') . ' ';
            }
            $this->ErrorMessage = $errorMessage;

            if (in_array('ctl00_cp_ctl05_valUsername2', $matches[1]) or in_array('ctl00_cp_ctl05_valUsername', $matches[1])) {
                $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
            } else {
                $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->getURL('http://cyprusair.com/461,0,0,0,2-Point-Balances.aspx');
        //# Balance - Points Available
        $this->SetBalance($this->http->FindSingleNode('//span[@id="cp_ctl03_txtPointsBalance"]'));

        // Expiration Date
        $expiration = $this->http->XPath->query('//span[@id="cp_ctl03_txtPointsExpirySummary"]/node()');
        $this->http->Log("Total nodes found: " . $expiration->length);

        for ($i = 0; $i < $expiration->length; $i++) {
            $expiringBalance = $this->http->FindSingleNode('//span[@id="cp_ctl03_txtPointsExpirySummary"]/node()[' . ($i + 1) . ']', null, true, '/:(.*)/i');

            if ($expiringBalance > 0) {
                //# Expiring Balance
                $this->SetProperty("ExpiringBalance", $expiringBalance);
                //# Expiration Date
                $this->SetExpirationDate(strtotime($this->http->FindSingleNode('//span[@id="cp_ctl03_txtPointsExpirySummary"]/node()[' . ($i + 1) . ']', null, true, '/(.*):/i')));

                break;
            }
        }
        //# Name
        $this->SetProperty("MemberName", $this->http->FindSingleNode('//span[@id="cp_ctl03_txtMemberName"]'));
        //# Membership Number
        $this->SetProperty("FFPNumber", $this->http->FindSingleNode('//span[@id="cp_ctl03_txtFFPNumber"]'));
        //# Member Tier
        $this->SetProperty("MemberTier", $this->http->FindSingleNode('//span[@id="cp_ctl03_txtMemberTier"]'));
        //# Join Date
        $this->SetProperty("JoinDate", $this->http->FindSingleNode('//span[@id="cp_ctl03_txtMemberSince"]'));
        //# Points Earned (Current year)
        $this->SetProperty("PointsTotalEarned", $this->http->FindSingleNode('//span[@id="cp_ctl03_txtPointsTotalEarnedCurrentYear"]'));
        //# Redeemed points
        $this->SetProperty("TotalPointsSpent", $this->http->FindSingleNode('//span[@id="cp_ctl03_txtPointsTotalSpent"]'));
        //# Expired points
        $this->SetProperty("TotalPointsExpired", $this->http->FindSingleNode('//span[@id="cp_ctl03_txtPointsTotalExpired"]'));
        //# Tier Points(current year)
        $this->SetProperty("PointsTierMiles", $this->http->FindSingleNode('//span[@id="cp_ctl03_txtPointsTierMiles"]'));
        //# Tier Points(previous year)
        $this->SetProperty("PointsTierMilesLY", $this->http->FindSingleNode('//span[@id="cp_ctl03_txtPointsTierMilesLastYear"]'));
        //# Class category used
        $this->SetProperty("PointsTierSectorsFlown", $this->http->FindSingleNode('//span[@id="cp_ctl03_txtPointsTierSectorsFlown"]'));
    }
}
