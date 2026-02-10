<?php

namespace AwardWallet\Engine\fiesta\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "fiestarewards@posadas.com",
            "fiestarewards@marketingposadas.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Fiesta#",
            "Enrrollment",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Password",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];

        $result['Login'] = $parser->getCleanTo();
        $result['Login2'] = re("#member number is[:\s]+([^\s]+)#ix", $text);
        $result['Password'] = re("#password is[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
