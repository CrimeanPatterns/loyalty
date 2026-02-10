<?php

namespace AwardWallet\Engine\foxrewards\Credentials;

class OffYourNextCarRental extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'no-reply@foxrentacar.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "10% OFF your next car rental with Fox Rent A Car",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
            'AccountNumber',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $result['AccountNumber'] = $this->http->FindSingleNode("//p[contains(@class,'customer-id')]", null, true, "/Reward\s*#\s*:\s*(.*)\s*/ims");
        $result['Name'] = beautifulName($this->http->FindSingleNode("//p[contains(@class,'customer-name')]"));

        return $result;
    }
}
