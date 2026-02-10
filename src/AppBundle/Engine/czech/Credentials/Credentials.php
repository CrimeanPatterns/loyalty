<?php

namespace AwardWallet\Engine\czech\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "okplus.app@csa.cz",
            "directmail@csa.cz",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to the OK Plus Programme",
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
        $result['Login'] = re('#Your\s+OK\s+Plus\s+membership\s+number\s+is\s*:\s+(\d+)#i', $text);
        $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
