<?php

namespace AwardWallet\Engine\fairmont\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'GuestService@Fairmont.com',
            'guestservice@fairmont.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Fairmont President's Club",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
            'Number',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Login'] = re('#Your\s+username\s+(\S+)#i', $text);
        $result['Name'] = re('#Welcome\s+(.*)\.#i', $text);
        $result['Number'] = re('#Membership\s+Number\s+(\d+)#i', $text);

        return $result;
    }
}
