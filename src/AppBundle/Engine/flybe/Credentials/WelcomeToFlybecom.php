<?php

namespace AwardWallet\Engine\flybe\Credentials;

class WelcomeToFlybecom extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'customer@bookings.flybe.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to flybe.com',
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
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = re('#Dear\s+(.*?),#i', $this->text());

        return $result;
    }
}
