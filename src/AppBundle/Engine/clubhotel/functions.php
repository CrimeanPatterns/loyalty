<?php

class TAccountCheckerClubhotel extends TAccountChecker
{
    public function LoadLoginForm()
    {
        // makes it easier to parse an invalid HTML
        $this->http->FilterHTML = false;
        // reset cookie
        $this->http->removeCookies();
        // getting the HTML code
        $this->http->getURL('https://www.clubhotel.com/sign-in/');
        // parsing form on the page
        if (!$this->http->ParseForm('signInForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("j_username", $this->AccountFields["Login"]);
        $this->http->SetInputValue("j_password", $this->AccountFields["Pass"]);
        $this->http->Form["signIn"] = 1;

        return true;
    }

    public function checkErrors()
    {
        // Sorry, a problem occurred while processing your request.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, a problem occurred while processing your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // check for invalid password
        if ($message = $this->http->FindSingleNode("//div[@class='validation-advice']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // login successful
        if ($this->http->FindSingleNode("//a[contains(@href,'/signout')]")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // set Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//ul[@class='list_data']/li[1]/text()[last()]")));
        // set Location
        $this->SetProperty("Location", $this->http->FindSingleNode("//ul[@class='list_data']/li[3]/text()[last()]"));
        // set MemberSince
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//ul[@class='list_data']/li[4]/text()[last()]"));
        $this->http->GetURL("https://www.clubhotel.com/activity");
        // set Balance
        $this->SetBalance($this->http->FindSingleNode("//tr[@class='total']/td"));
    }
}
