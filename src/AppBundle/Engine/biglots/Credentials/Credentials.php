<?php

namespace AwardWallet\Engine\biglots\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'buzzclub@buzzclub.biglots.com',
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
            'Email',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
