<?php

namespace AwardWallet\Engine\azamara\Credentials;

class Credentials extends \TAccountCheckerExtended
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
            "Thanks for setting up a My Azamara account.",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Name",
            "FirstName",
            "LastName",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];
        $result["Email"] = $parser->getCleanTo();
        $result["Name"] = beautifulName(re("#Dear\s+(\w+\s+\w+)#", $text));
        $result["FirstName"] = re("#^(\w+)\s+(\w+)$#", $result["Name"]);
        $result["LastName"] = re(2);

        return $result;
    }
}
