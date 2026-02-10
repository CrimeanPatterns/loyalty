<?php

namespace AwardWallet\Engine\bigcrumbs\Credentials;

class Credentials3 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "info@mainstreetshares.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "MainStreetSHARES Account Info and Password Reset",
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = $parser->getBody();
        $subject = $parser->getSubject();
        $result['FirstName'] = beautifulName(re("#Hello\s+([^,]+),#", $text));
        $result['Login'] = re("#User\s*ID\s*:\s*(\S+)#", $text);

        return $result;
    }
}
