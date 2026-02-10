<?php

namespace AwardWallet\Engine\alitalia\Credentials;

class Pin extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "LostPin@alitalia.us",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Pin request',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);

        if ($password = re('#Your\s+PIN\s+is\s+(\d+)#i', $text)) {
            $result['Password'] = $password;
        }

        return $result;
    }

    public function getRetrieveFields()
    {
        return [
            'Login',
            'LastName',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('https://mm.alitalia.com/US_EN/mm/lostpin.aspx');

        if (!$this->http->ParseForm("Form1")) {
            return false;
        }

        $this->http->SetInputValue('btnRequest.x', '64');
        $this->http->SetInputValue('btnRequest.y', '14');
        $this->http->SetInputValue("txtCustomerNumber", $data["Login"]);
        $this->http->SetInputValue("txtLastName", $data["LastName"]);

        $this->http->PostForm();

        if ($this->http->FindSingleNode('//*[contains(text(), "Your PIN was sent to the email address")]')) {
            return true;
        } else {
            return false;
        }
    }
}
