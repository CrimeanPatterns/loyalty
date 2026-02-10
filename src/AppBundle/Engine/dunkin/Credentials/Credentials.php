<?php

namespace AwardWallet\Engine\dunkin\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'ddperks@email.dunkindonuts.com',
            'noreply@email.dunkindonuts.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "DD Perks(R) Rewards program update",
            "Welcome to DD Perks",
            "#Welcome to Perks Rewards#",
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

        $result['Name'] = re("#(?:^|\n)\s*Welcome to Dunkin' Perks Rewards,\s*\s+([^\n,]+)#ix", $text);

        if (!$result['Name']) {
            $result['Name'] = re("#(?:^|\n)\s*([^\n]*?),\s*Welcome to DD#ix", $text);
        }

        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
