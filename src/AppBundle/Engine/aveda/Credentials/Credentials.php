<?php

namespace AwardWallet\Engine\aveda\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "aveda@e.aveda.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Thank you for registering at aveda.com",
            "A welcome gift from aveda.com",
            "It's Your Birthday Month, Celebrate!",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
