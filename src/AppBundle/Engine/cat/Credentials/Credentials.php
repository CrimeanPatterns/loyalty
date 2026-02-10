<?php

namespace AwardWallet\Engine\cat\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "info@cityairporttrain.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "CAT - ",
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
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($name = re("#Dear\s+M[rsi]+\.?\s+(\w+)#i", $this->text())) {
            $result['LastName'] = beautifulName($name);
        }

        return $result;
    }
}
