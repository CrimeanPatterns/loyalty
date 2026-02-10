<?php

namespace AwardWallet\Engine\acmoore\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "ACMoore.noreply@us.emaildir2.com",
            "ACMoore@us.emaildir2.com",
            "acmoore.noreply@us.emaildir2.com",
            "acmoore@us.emaildir2.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            " ",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];

        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        //$result['Name'] = re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text);
        //$result['Login'] = re("#(?:^|\n)\s*Your Username is[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
