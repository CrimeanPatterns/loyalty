<?php

namespace AwardWallet\Engine\boltbus\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "rewards@boltbus.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = re('#Login\s*:\s+(\S+)#i', $text);
        $result['Name'] = re('#Congratulations\s+(.*?),#i', $text);
        $result['FirstName'] = trim(re('#First\s+Name\s*:\s+(.*)#i', $text));
        $result['LastName'] = trim(re('#Last\s+Name\s*:\s+(.*)#i', $text));

        return $result;
    }
}
