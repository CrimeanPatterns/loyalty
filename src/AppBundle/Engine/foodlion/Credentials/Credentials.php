<?php

namespace AwardWallet\Engine\foodlion\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "noreply@foodlion.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Food\s*lion#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];

        //$result['Name'] = re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text);
        $result['Login'] = $parser->getCleanTo();
        $result['Login2'] = re("#Your membership number is[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
