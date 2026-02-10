<?php

namespace AwardWallet\Engine\basspro\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return ['basspro@basspronews.com'];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Weclome to Basspro#i",
            "Welcome to BassPro.com",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            "Login",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text);

        return $result;
    }
}
