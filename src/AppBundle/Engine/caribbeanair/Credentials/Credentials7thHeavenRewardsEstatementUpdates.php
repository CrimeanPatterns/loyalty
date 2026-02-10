<?php

namespace AwardWallet\Engine\caribbeanair\Credentials;

class Credentials7thHeavenRewardsEstatementUpdates extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'donotreply@caribbean-airlines.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            '7th Heaven Rewards E-statement & Updates',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['Email'] = $parser->getCLeanTo();
        $result['Name'] = reni('
			Rewards Member:
			(.+?)
			Previous Balance:
		', $text);

        return $result;
    }
}
