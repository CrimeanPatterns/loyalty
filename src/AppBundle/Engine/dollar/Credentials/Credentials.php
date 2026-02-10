<?php

namespace AwardWallet\Engine\dollar\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "dollarrentacar@email.dollar.com",
            "DollarExpress@dollar.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Dollar EXPRESS",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['FirstName'] = beautifulName(re('#Dear (.*?) \w,\s+Welcome#i', $text));
        $result['Login'] = re('#Your\s+Member\s+ID\s*:\s+(\d+)#i', $text);

        return $result;
    }
}
