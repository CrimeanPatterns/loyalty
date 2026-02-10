<?php

namespace AwardWallet\Engine\airmalta\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return ["noreply.icon@airmalta.com"];
    }

    public function getCredentialsSubject()
    {
        return ["AirMalta - Welcome to FlyPass"];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
            "FirstName",
            "Number",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Login"] = $result["Email"] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $result["FirstName"] = re("#M[rsi]+\.\s+(\w+\s+\w+)#", $text);

        if ($Number = re("#Your Flypass Member ID is\s+(\S+)#", $text)) {
            $result["Number"] = $Number;
        }

        return $result;
    }
}
