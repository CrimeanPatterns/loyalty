<?php

namespace AwardWallet\Engine\elitehotels\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "from@elitehotels.carmamail.com",
            "info@elite.se",
            "no-reply@elite.se",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Nya hotell i Stockholm och Linköping, upp till 40% i sommar, boka golfpaketet online",
            "Nya hotell, sportlov och Alla hjärtans dag",
            "Elite Hotels Guest Program",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Login",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];
        $result['Email'] = $result['Login'] = $parser->getCleanTo();

        if ($login = orval(
            re("#Medlemsnummer:\s+(\d+)#", $text),
            re("#membership\s+number\s+is:\s+(\d+)#", $text)
        )) {
            $result['Login!'] = $login;
        }

        $result['Name'] = beautifulName(nice(
            orval(
                re("#Namn:\s+(\w+\s+\w+)#ms", $text),
                re("#Welcome\s+to\s+Elite\s+Hotels\s+Guest\s+Program,\s+(\w+)#i", $text)
            )
        ));

        return $result;
    }
}
