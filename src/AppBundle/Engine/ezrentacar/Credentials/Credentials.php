<?php

namespace AwardWallet\Engine\ezrentacar\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'ezmoney@e-zrentacar.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Rent-A-Car",
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
        $result['Login!'] = re("#(?:^|\n)\s*Username[:\s]+([^\s]+)#ix", $text);
        $result['Password'] = re("#(?:^|\n)\s*Password[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
