<?php

namespace AwardWallet\Engine\china\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'SUBJECT "China Airlines DFP member\'s card number advice" FROM "cal-notice@email.china-airlines.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Name'] = re('#Dear M[RS]+\. (.*?),#i', $text);
        $result['Login'] = re('#Your\s+DFP\s+membership\s+card\s+number\s+is\s+(\S+)\s+and#i', $text);

        return $result;
    }
}
