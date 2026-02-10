<?php

namespace AwardWallet\Engine\eddiebauer\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "info@eddiebauerfriends.com",
            "EddieBauerEmail@em.eddiebauer.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Eddie Bauer Friends",
            "#With Double Rewards#i",
            "#Reward[\s-]+Friends Exclusive#i",
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

        $result['Name'] = re("#(?:^|\n)\s*Member\s+Name\s*:\s*([^\n]+)#i", $text);
        $result['Login'] = re("#(?:^|\n)\s*(?:Member|Friends)\s+Number\s*:\s*([^\n]+)#i", $text);

        if (!$result['Name']) {
            $arr = array_filter($this->http->FindNodes("//*[contains(text(), 'Certificate Number:')]/ancestor-or-self::td[1]//text()"));
            $result['Name'] = reset($arr);
        }

        return $result;
    }
}
