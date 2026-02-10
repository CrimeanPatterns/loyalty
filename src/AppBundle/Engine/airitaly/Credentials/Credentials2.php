<?php

namespace AwardWallet\Engine\airitaly\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'meridiana.hifly@meridianafly.com',
            'sendtkt@sender.meridiana.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Meridiana Club",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Login!',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($parser->getBody());
        $result['Login'] = $parser->getCleanTo();
        $result['Login!'] = re("#Meridiana\s+Club\s+code:\s+(\d+)#", $text);
        $result['Name'] = beautifulName(re("#Dear\s+(\w+\s+\w+)#i", $text));

        return $result;
    }
}
