<?php

namespace AwardWallet\Engine\chase\Credentials;

class Offer extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'Chase@email.chase.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Exclusive Bellagio Hotel packages for Vegas Uncork\'d Events',
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
        $result['Name'] = re('#\s+Dear\s+(.*?),#i', $this->text());

        return $result;
    }
}
