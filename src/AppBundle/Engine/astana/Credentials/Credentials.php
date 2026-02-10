<?php

namespace AwardWallet\Engine\astana\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'nomadclubnews@nomadclubnews.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Nomad Club#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Name',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();

        $result['Name'] = re([
            "#Manage Your Account\s+([^\n]+)#i",
        ], $text);

        $result['Login'] = re([
            "#\n\s*Membership Number\s+([^\s]+)#ix",
        ], $text);

        return $result;
    }
}
