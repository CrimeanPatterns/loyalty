<?php

namespace AwardWallet\Engine\extrabux\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'info@extrabux.com',
            'email@extrabux.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Extrabux#",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];

        //$result['Name'] = re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text);
        //$result['Login'] = re("#(?:^|\n)\s*Your Username is[:\s]+([^\s]+)#ix", $text);
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
