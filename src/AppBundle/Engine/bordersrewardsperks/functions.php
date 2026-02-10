<?php

class TAccountCheckerBordersrewardsperks extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        //$this->http->LoadFile("/tmp/step01.html");$this->Parse();exit;
        $this->http->getURL('https://www.bordersrewardsperks.com/login/index/uSource/H09');

        if ($this->http->FindSingleNode('//h1[contains(text(), "The Borders Rewards Perks program is no longer available")]')) {
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
            $this->ErrorMessage = 'The Borders Rewards Perks program is no longer available';

            return false;
        }

        $this->checkErrors();

        if (!$this->http->ParseForm('login')) {
            return false;
        }
        $this->http->FormURL = "https://www.bordersrewardsperks.com/login/login";
        //$this->http->SetFormText('','&',false);
        $this->http->Form['fEmail'] = $this->AccountFields['Login'];
        $this->http->Form['password'] = $this->AccountFields['Pass'];
        //$this->http->ParseForms = false;
        //$this->http->setDefaultHeader('User-Agent','Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.2) Gecko/20100115 Firefox/3.6');
        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        $error = $this->http->FindSingleNode("//div[@id='registrationErrorMessage']");

        if (!isset($error) || empty($error)) {
            $error = $this->http->FindSingleNode("//span[@id='errorMessageCopy']");
        }

        if (isset($error) && !empty($error)) {
            $this->ErrorMessage = $error;
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;

            return false;
        }

        return true;
    }

    public function checkErrors()
    {
        $error = $this->http->FindPreg("/borders_error\.png/ims");

        if (isset($error)) {
            $this->ErrorMessage = "Site is temporarily down. Please try to access it later.";
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;

            return false;
        }
    }

    public function Parse()
    {
        $this->http->getURL('https://www.bordersrewardsperks.com/wowpoints/index/uSource/H09');
        $this->checkErrors();
        $this->SetProperty("Name", $this->http->FindPreg('/Hello,\s([^<]*)</ims'));
        $this->SetProperty("PendingEarnings", $this->http->FindSingleNode('//td[contains(text(), "Pending Earnings")]/following::td[1]/span'));
        $this->SetProperty("LifetimeWOWPointsEarned", $this->http->FindSingleNode('//td[contains(text(), "Lifetime WOWPoints Earned")]/following::td[1]/span'));
        $this->SetProperty("LifetimeWOWPointsRedeemed", $this->http->FindSingleNode('//td[contains(text(), "Lifetime WOWPoints Redeemed")]/following::td[1]/span'));
        $this->SetBalance($this->http->FindSingleNode('//td[contains(text(), "Available Balance")]/following::td[1]/span'));
        $node = $this->http->XPath->query('//strong[contains(text(), "Status Level")]/./following::td[1]/img');

        if ($node->length > 0) {
            $src = $node->item(0)->getAttribute("src");

            if (isset($src) && preg_match("/level_([^\.]*)\./ims", $src, $matches)) {
                $this->SetProperty("StatusLevel", $matches[1]);
            }
        }

        $this->http->getURL('https://www.bordersrewardsperks.com/pointsregister/index/uSource/myAcct');
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode('//strong[contains(text(), "Account Number:")]/../../td[2]'));
    }
}
