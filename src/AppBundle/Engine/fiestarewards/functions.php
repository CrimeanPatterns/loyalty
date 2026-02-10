<?php

class TAccountCheckerFiestarewards extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FormURL = "http://www.fiestarewards.com/fiestaRewards/Recompensas/logInFiestaRewards.do";
        $this->http->Form["{actionForm.userId}"] = $this->AccountFields['Login'];
        $this->http->Form["{actionForm.nip}"] = $this->AccountFields['Pass'];

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        $error = $this->http->FindSingleNode('//td[@class = "errors"]');

        if (isset($error) && !empty($error)) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        return true;
    }

    public function Parse()
    {
        $this->http->getURL('https://www.fiestarewards.com/fiestaRewards/Recompensas/showAccountStateFiestaRewards.do');
        $name = $this->http->FindPreg("/Welcome\ ([^<]*)</ims");

        if (isset($name)) {
            $this->SetProperty("Name", str_replace("&nbsp;", " ", $name));
        }
        $this->SetBalance($this->http->FindPreg("/([^>\ ])\ points/ims"));
    }
}
