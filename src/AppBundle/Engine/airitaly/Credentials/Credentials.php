<?php

namespace AwardWallet\Engine\airitaly\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'meridiana.hifly@meridianafly.com',
            'sendtkt@sender.meridiana.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Hi-Fly Now",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Login!',
            'Name',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($parser->getBody());
        $result['Login'] = $parser->getCleanTo();
        $result['Login!'] = re("#HI-FLY\s+CODE\s*:\s+(\d+)#", $text);
        $result['Name'] = beautifulName(re("#Dear\s+(\w+\s+\w+)#i", $text));
        $result['Password'] = re("#PIN\s+CODE\s*:\s+(\d+)#", $text);

        return $result;
    }
}
