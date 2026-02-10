<?php

namespace AwardWallet\Engine\asia\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "memberservices@asiamiles.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Asia Miles",
        ];
    }

    public function getParsedFields()
    {
        return [
            'LastName',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = re('#Membership No.:\s+(\d+)#i', $text);
        $result['LastName'] = re('#Dear M[rsi]+ (.*?),#i', $text);

        return $result;
    }
}
