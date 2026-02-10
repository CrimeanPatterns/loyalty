<?php

namespace AwardWallet\Engine\flybe\Credentials;

class YourFlybeAccountActivation extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
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
            'Your Flybe account activation',
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
