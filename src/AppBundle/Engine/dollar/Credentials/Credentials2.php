<?php

namespace AwardWallet\Engine\dollar\Credentials;

class Credentials2 extends \TAccountCheckerExtended
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
            "It’s been awhile – come back and save.",
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
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['Name'] = beautifulName(re('#Dear\s+(\w+)\s+\w\s+(\w+),#i', $text));
        $result['Name'] = trim($result['Name'] . ' ' . beautifulName(re(2)));
        $result['Login'] = re('#Your\s+Dollar\s+EXPRESS\s+Renter\s+Rewards\s+ID\s+is\s*:\s*\#(\d+)#i', $text);

        return $result;
    }
}
