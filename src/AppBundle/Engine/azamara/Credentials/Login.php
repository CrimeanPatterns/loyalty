<?php

namespace AwardWallet\Engine\azamara\Credentials;

class Login extends \TAccountCheckerExtended
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
            "Your My Azamara User Name",
        ];
    }

    public function getParsedFields()
    {
        return ["Login"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Login'] = re('#User\s+Name\s*:\s*(\S+)#i', $this->text());

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
        $this->http->GetURL('https://secure.azamaraclubcruises.com/dc/myAccount/forgotUserName.do');

        if (!$this->http->ParseForm("requestForm")) {
            return false;
        }

        $this->http->SetInputValue("firstName", $data["FirstName"]);
        $this->http->SetInputValue("lastName", $data["LastName"]);
        $this->http->SetInputValue("email", $data["Email"]);
        $this->http->SetInputValue("deliveryMethod", "E");
        $this->http->SetInputValue("retrieveUsername2.x", rand(10, 50));
        $this->http->SetInputValue("retrieveUsername2.y", rand(5, 15));
        $this->http->SetInputValue("retrieveUsername2", "Retrieve User Name");

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
