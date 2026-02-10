<?php

class TAccountCheckerBondrewards extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->UseSSLv3();
        $this->http->getURL('https://www.bondrewards.com/?');
        $this->http->FormURL = "https://www.bondrewards.com/signin";
        $this->http->Form["emailAddress"] = $this->AccountFields['Login'];
        $this->http->Form["password"] = $this->AccountFields['Pass'];
        $this->http->Form["keepLoggedIn"] = 'on';
        $this->http->Form["only_btn"] = 'Sign In';

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'signout')]/@href")) {
            return true;
        }

        $error = $this->http->FindSingleNode("//ol[@class = 'ui-state-error-text']/li[1]");

        if (isset($error) && !empty($error)) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        //# System Error
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'System Error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // BondRewards is Closed!
        $this->http->getURL('http://www.bondrewards.com/');

        if ($message = $this->http->FindPreg("/<title>(BondRewards is closed!)<\/title>/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->getURL('https://www.bondrewards.com/myrewards');
        //# Name
        $this->SetProperty("Name", $this->http->FindPreg("/Hello,\s*([^\!]*)\!/ims"));
        //# Balance - Available
        $this->SetBalance($this->http->FindPreg("/Available: ([\-\d\,\.]*) BD/ims"));
        $this->SetProperty("Earned", $this->http->FindSingleNode('//td[contains(text(),"Earned")]/../td[2]', null, true, '/[0-9\.]+/'));
        $this->SetProperty("Pending", $this->http->FindSingleNode('//td[contains(text(),"Pending")]/../td[2]', null, true, '/[0-9\.]+/'));
        $this->SetProperty("Redeemed", $this->http->FindSingleNode('//td[contains(text(),"Redeemed")]/../td[2]', null, true, '/[0-9\.]+/'));

        $this->http->getURL('https://www.bondrewards.com/myinformation');
        //# Name
        $name = CleanXMLValue($this->http->FindSingleNode("//label[contains(text(), 'Name :')]/parent::td/following-sibling::td[1]/text()[1]"));

        if (!isset($this->Properties['Name']) || strstr($name, $this->Properties['Name'])) {
            $this->SetProperty("Name", $name);
        }
    }
}
