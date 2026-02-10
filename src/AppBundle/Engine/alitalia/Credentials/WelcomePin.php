<?php

namespace AwardWallet\Engine\alitalia\Credentials;

class WelcomePin extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "RegistrationConfirmation@alitalia.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Registration confirmation',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);

        if ($password = re('#Your\s+PIN\s+is\s+(\d+)#i', $text)) {
            $result['Password'] = $password;
        }

        return $result;
    }
}
