<?php

namespace AwardWallet\Engine\carinos\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'guest@paytronix.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Pasta Points Card Registration",
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
        $result["Login"] = $result["Email"] = $parser->getCleanTo();

        return $result;
    }
}
