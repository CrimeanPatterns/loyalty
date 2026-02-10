<?php

namespace AwardWallet\Engine\chinasouthern\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'SUBJECT "Welcome to China Southern Airlines\' Sky Pearl Club" FROM "cbdhighpri@edm.csair.com"',
            'FROM "cbdhighpri@edm.csair.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);

        if (stripos($parser->getSubject(), 'Welcome to China Southern Airlines\' Sky Pearl Club') !== false) {
            $result['Name'] = re('#M[RS]+.(.*)\s+Welcome#i', $text);
            $result['Login'] = re('#Your\s+Sky\s+Pearl\s+Club\s+membership\s+number\s+is\s+(\d+)#i', $text);
        }

        return $result;
    }
}
