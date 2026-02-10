<?php

namespace AwardWallet\Engine\bigcrumbs\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "news@bigcrumbs.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Last E-mail from BigCrumbs. MainStreetSHARES makes History.",
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = $parser->getBody();
        $subject = $parser->getSubject();
        $result['FirstName'] = beautifulName(re("#Hello\s+([^,]+),#", $text));

        return $result;
    }
}
