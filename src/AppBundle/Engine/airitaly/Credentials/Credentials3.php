<?php

namespace AwardWallet\Engine\airitaly\Credentials;

class Credentials3 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'noreply@meridiana.it',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Convert your old mileage balance into Avios immediately",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Login!',
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($parser->getBody());
        // echo $text;
        $result['Login'] = $parser->getCleanTo();
        $result['Login!'] = re("#Meridiana\s+Club\s+code\s+is\s+(\d+)#", $text);
        $result['FirstName'] = beautifulName(re("#\)\s+(\w+),#msi", $text));

        return $result;
    }
}
