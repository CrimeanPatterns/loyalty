<?php

namespace AwardWallet\Engine\airindia\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'flyingreturns@loyaltyplus.aero',
            'flyingreturns@ainindiausa.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Welcome to Flying Returns#",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Name'] = re('#Dear\s*M[RS]+\s*(.*?),#i', $text);
        $result['Login'] = re('#Membership Number:\s*(\d+)#i', $text);
        $result['Password'] = re('#Password:\s*(.*?)\s*\n#i', $text);

        return $result;
    }
}
