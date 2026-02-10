<?php

namespace AwardWallet\Engine\ace\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "Acehardware.com@em.acehardware.com",
            "customerservice@acehardware.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            " ",
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
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
