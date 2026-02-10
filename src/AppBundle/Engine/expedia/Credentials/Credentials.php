<?php

namespace AwardWallet\Engine\expedia\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'Expedia@sg.expediamail.com',
            'expedia@sg.expediamail.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Congratulations",
            "Sale",
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Login',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $result['FirstName'] = beautifulName(re("#Congratulations ([^,]+),#", $subject));

        return $result;
    }
}
