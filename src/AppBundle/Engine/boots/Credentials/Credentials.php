<?php

namespace AwardWallet\Engine\boots\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'registration@care.boots.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your Boots.com registration",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = re('#Your\s+e-mail\s+address?\s+(.*)#i', $text);
        $result['Name'] = re('#Your\s+name\s+(.*)#i', $text);

        return $result;
    }
}
