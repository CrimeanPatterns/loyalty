<?php

namespace AwardWallet\Engine\freebirds\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'rewards@pxsmail.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to the Freebirds Fanatics Challenge",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['Login'] = re("#Username\s*:\s*(\S+)#", $text);

        return $result;
    }
}
