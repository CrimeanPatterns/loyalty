<?php

namespace AwardWallet\Engine\airjamaica\Credentials;

class Password extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            '7heaven@caribbean-airlines.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your Caribbean Miles PIN Number",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Password",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Password'] = re('#Your PIN number is\s+(\d+)#i', text($this->http->Response['body']));

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'FirstName',
            'LastName',
            'Login',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL('http://www.cardbalance.com/air%20jamaica/frame/pinhelp.cfm');

        if (!$this->http->ParseForm("PinRetrieval")) {
            return false;
        }

        $this->http->SetInputValue("FF_MemFName", $data["FirstName"]);
        $this->http->SetInputValue("FF_MemLName", $data["LastName"]);
        $this->http->SetInputValue("FF_Acc_Number", $data["Login"]);
        $this->http->SetInputValue("btnSend", "Send Reminder");

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//*[contains(text(), 'Your PIN number has been sent to you via email.')]")) {
            return true;
        } else {
            return false;
        }
    }
}
