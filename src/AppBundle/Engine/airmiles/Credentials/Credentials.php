<?php

namespace AwardWallet\Engine\airmiles\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'avios@mail.avios.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Avios - are you ready to fly?",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Password",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['LastName'] = re('#Dear M[rs]+ (.*?),#', $text);
        $result['AccountNumber'] = re('#Membership number:\s*(\d+)#i', $text);

        return $result;
    }
}
