<?php

namespace AwardWallet\Engine\astana\Credentials;

class Password extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'nomadclub@airastana.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Nomad Club Password recovery#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Name',
            'Login',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();

        $result['Name'] = re([
            "#(?:^|\n)\s*Dear\s+(?:M[rsi]+\s+|)([^,\n]+)#i",
        ], $text);

        $result['Password'] = re("#\n\s*Your Password[:\s]+([^\s]+)#i", $text);

        $result['Login'] = re([
            "#\n\s*Your Membership Number[:\s]+([^\s]+)#ix",
        ], $text);

        return $result;
    }
}
