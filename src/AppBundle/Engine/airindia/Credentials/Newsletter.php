<?php

namespace AwardWallet\Engine\airindia\Credentials;

class Newsletter extends \TAccountCheckerExtended
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
            "#Air India E-Newsletter#",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        // echo $text;
        // die();
        $result['Name'] = re('#Dear\s*M[RS]+\s*(.*?),#i', $text);
        $result['Login'] = re('#Membership No\s+(\d+)#i', $text);

        return $result;
    }
}
