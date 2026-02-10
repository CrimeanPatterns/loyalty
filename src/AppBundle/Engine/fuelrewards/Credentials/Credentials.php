<?php

namespace AwardWallet\Engine\fuelrewards\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'support@excentus.com',
            'info@e.fuelrewards.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#FRN\s+Account#i",
            "#Fuel\s*Rewards#i",
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
        $result = [];

        $result['Name'] = re("#(?:^|\n)\s*(.+?),\n\s*Thank you for register#ix", $text);

        if (!$result['Name']) {
            $result['Name'] = re("#(?:^|\n)\s*(.+?)\s*\|\s*FRN Account#ix", $text);
        }

        $result['Login'] = $parser->getCleanTo();

        $result['Login2'] = re("#FRN Account\s+\#\s*([^\s]+)#ix", $text);

        if (!$result['Login2']) {
            $result['Login2'] = re("#FRN account number is[:\s]+([^\s]+)#ix", $text);
        }

        return $result;
    }
}
