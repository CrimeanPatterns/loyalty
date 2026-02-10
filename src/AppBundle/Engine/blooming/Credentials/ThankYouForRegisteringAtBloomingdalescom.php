<?php

namespace AwardWallet\Engine\blooming\Credentials;

class ThankYouForRegisteringAtBloomingdalescom extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'welcome@bloomingdales.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Thank You for Registering at Bloomingdales.com!',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
