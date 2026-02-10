<?php

namespace AwardWallet\Engine\autozone\Credentials;

class WelcomeToAutozoneRewards extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'customercare@autozonerewards.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to Autozone Rewards',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = re('#\s+Welcome!\s+(.*?),\s+your\s+card\s+number\s+i#i', $this->text());

        return $result;
    }
}
