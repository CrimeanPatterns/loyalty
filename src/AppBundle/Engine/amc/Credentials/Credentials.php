<?php

namespace AwardWallet\Engine\amc\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "amcstubs@amcentertainment.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [" "];
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
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
