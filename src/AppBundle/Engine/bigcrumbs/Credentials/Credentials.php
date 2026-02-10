<?php

namespace AwardWallet\Engine\bigcrumbs\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "info@bigcrumbs.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "BigCrumbs News Flash for",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = $parser->getBody();
        $subject = $parser->getSubject();
        $result['Name'] = beautifulName(re("#For\s+(.*?)\s+\(#", $subject));
        $result['Login'] = re("#For\s+.*?\s+\(([^\)]+)\)#", $subject);

        return $result;
    }
}
