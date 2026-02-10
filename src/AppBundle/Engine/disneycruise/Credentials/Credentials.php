<?php

namespace AwardWallet\Engine\disneycruise\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'news@fos.go.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Exclusive\s+Sailings#i",
            "Four Exclusive Sailings With All-New Pixar Magic",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = re("#\n\s*Ahoy\s+(.*?)\s+Family,#i", $text);

        return $result;
    }
}
