<?php

namespace AwardWallet\Engine\airjamaica\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            '7heaven@airjamaica.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Air Jamaica - Online Enrollment",
        ];
    }

    public function GetParsedFields()
    {
        return [
            "Email",
            "Name",
            "Login",
            "FirstName",
            "LastName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = beautifulName(re("#Dear\s+(\w+\s+\w+)#", $text));
        $result['FirstName'] = re("#(\w+)\s+\w+#", $result['Name']);
        $result['LastName'] = re("#\w+\s+(\w+)#", $result['Name']);
        $result['Login'] = re("#Please note that your account number is\s+(\d+)#", $text);

        return $result;
    }
}
