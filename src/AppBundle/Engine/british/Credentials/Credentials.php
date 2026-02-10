<?php

namespace AwardWallet\Engine\british\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'BAExecutiveClub_US@my.ba.com',
            'executiveclubconfirm@email.ba.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your latest Executive Club statement",
            "#Your Executive Club [Mm]{1}embership#",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'LastName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();

        if (stripos($subject, 'Your latest Executive Club statement') !== false) {
            $result['Login'] = re('#Membership\s+no\s*:\s+(\d+)#i', $text);
            $result['LastName'] = re('#(M[rsi]+|Dr|Prof) (.*?),#i', $text, 2);
        } elseif (stripos($subject, 'Your Executive Club Membership') !== false) {
            $result['LastName'] = re('#Dear (M[rsi]+|Dr|Prof) (.*?),#i', $text, 2);
            $result['Login'] = re('#Your Executive Club Membership Number( is|):\s+(\d+)#i', $text, 2);
        }

        return $result;
    }
}
