<?php

namespace AwardWallet\Engine\drugstore\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'customercare@drugstore.com',
            'drugstore@e.drugstore.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            ' ',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Password',
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
