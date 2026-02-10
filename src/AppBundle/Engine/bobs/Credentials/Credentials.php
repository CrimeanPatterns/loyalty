<?php

namespace AwardWallet\Engine\bobs\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'webmaster@bobstores.com',
            'bobs@EMAIL-BOBSTORES.COM',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your Bob's Stores Account",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($parser->getBody());
        // echo $text;
        $result['Login'] = $parser->getCleanTo();

        if ($pass = re("#Password\s*:\s*(\S+)#", $text)) {
            $result['Password'] = $pass;
        }

        return $result;
    }
}
