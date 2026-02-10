<?php

namespace AwardWallet\Engine\airjamaica\Credentials;

class Credentials2 extends \TAccountCheckerExtended
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
            "7th Heaven Rewards",
        ];
    }

    public function GetParsedFields()
    {
        return [
            "Email",
            "Name",
            "FirstName",
            "LastName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = beautifulName(re("#7th Heaven Rewards Member:\s+(\w+\s+\w+)#", $text));
        $result['FirstName'] = re("#(\w+)\s+\w+#", $result['Name']);
        $result['LastName'] = re("#\w+\s+(\w+)#", $result['Name']);

        return $result;
    }
}
