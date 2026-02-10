<?php

namespace AwardWallet\Engine\freerice\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'WFP.Freerice@wfp.org',
            'community@donate.wfp.org',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Freerice.com account details",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($parser->getBody());
        $result['Email'] = $parser->getCleanTo();
        $result['Login'] = re("#Username:\s+(\S+)#", $text);

        return $result;
    }
}
