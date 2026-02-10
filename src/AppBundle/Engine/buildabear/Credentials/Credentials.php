<?php

namespace AwardWallet\Engine\buildabear\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'buildabear@buildabearnews.com',
            'guest.services@buildabear.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Build-A-Bear Workshop new account confirmation",
            "Don't lose your Stuff Fur Stuff points!",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Login"] = $result["Email"] = $parser->getCleanTo();

        return $result;
    }
}
