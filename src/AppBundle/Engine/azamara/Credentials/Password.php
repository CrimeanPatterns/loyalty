<?php

namespace AwardWallet\Engine\azamara\Credentials;

class Password extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "azamarawebsupport@azamaraclubcruises.com",
            "web_master_cci@azamaraclubcruises.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your My Azamara Password",
        ];
    }

    public function getParsedFields()
    {
        return ["Password"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Password'] = re('#Password\s*:\s*(\S+)#i', $this->text());

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'Email',
            'FirstName',
            'LastName',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL('https://secure.azamaraclubcruises.com/dc/myAccount/forgotPassword.do');

        if (!$this->http->ParseForm("requestForm")) {
            return false;
        }

        $this->http->SetInputValue("firstName", $data["FirstName"]);
        $this->http->SetInputValue("lastName", $data["LastName"]);
        $this->http->SetInputValue("email", $data["Email"]);
        $this->http->SetInputValue("image.x", rand(10, 72));
        $this->http->SetInputValue("image.y", rand(5, 15));

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//*[contains(text(), 'information has been sent to the address provided')]")) {
            return true;
        } else {
            return false;
        }
    }
}
