<?php

namespace AwardWallet\Engine\friends\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "no-reply@reply.freshandeasy.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Friends of fresh&easy!",
            "this is your last email from Fresh & Easy",
            "Only 3 days left to qualify for 5X points",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Login',
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($name = re("#Hi,?\s+(\w+)#i", $text)) {
            $result['FirstName'] = $name;
        }

        return $result;
    }
}
