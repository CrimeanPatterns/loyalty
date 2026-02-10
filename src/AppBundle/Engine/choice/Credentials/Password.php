<?php

namespace AwardWallet\Engine\choice\Credentials;

class Password extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'email_choiceprivileges@your.choicehotels.com',
            'email_choiceprivileges@choicehotels.com',
            'PROFILE_SUPPORT@choicehotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Password for Your ChoiceHotels.com Profile",
        ];
    }

    public function getParsedFields()
    {
        return ["Password"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        if ($pass = re('#The\s+password\s+for\s+your\s+profile\s+on\s+choicehotels\.com\s+is\s+(\S+)#i', $this->http->Response['body'])) {
            $result['Password'] = $pass;
        }

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'Login',
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('https://secure.choicehotels.com/ires/en-US/html/LoginHelp');

        if (!$this->http->ParseForm("forgot-password-form")) {
            return false;
        }

        $this->http->SetInputValue("username", $data["Login"]);
        $this->http->SetInputValue("email", $data["Email"]);

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//*[contains(text(), 'Your password has been sent to the e-mail address in your profile')]")) {
            return true;
        } else {
            return false;
        }
    }
}
