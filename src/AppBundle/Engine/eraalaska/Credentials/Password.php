<?php

namespace AwardWallet\Engine\eraalaska\Credentials;

class Password extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "customerservice@flyera.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Era Alaska Website Password Reminder",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Password",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Password'] = re('#Your\s+password\s+is\s*:\s*(\S+)#i', $this->text());
        $result['Name'] = beautifulName(re('#Dear\s+(\w+\s+\w+)#', $this->text()));

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'Login',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL('http://www.flyravn.com/my-ravn/');

        $loginUrl = $this->http->FindSingleNode("(//a[text()='Log in'])[1]/@href");
        $this->http->GetURL($loginUrl);

        if (!$this->http->ParseForm("frmLogin")) {
            return false;
        }

        $this->http->SetInputValue("page", "ssw_UserPasswordReminderRequestMessage");
        $this->http->SetInputValue("action", "requestPassword");
        $this->http->SetInputValue("reminderType", "displayReminderPage");
        $this->http->SetInputValue("accountID", $data['Login']);
        $this->http->SetInputValue("password", "");
        $this->http->SetInputValue("rememberLoginInfo", "");
        $this->http->SetInputValue("hold_purchase", "$hold_purchase");

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->http->ParseForm(null)) {
            return false;
        }

        $this->http->SetInputValue("action", "emailPassword");

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//*[contains(text(), 'Your password has been sent to your email address')]")) {
            return true;
        } else {
            return false;
        }
    }
}
