<?php

namespace AwardWallet\Engine\ezrentacar\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'newsletter@ezrentacar.net',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "A New Year and New Rental Specials",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
            'Login!',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];

        $result['Name'] = re("#(?:^|\n)\s*(?:Dear|Hello)\s+([^\n,]+)#i", $text);
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
