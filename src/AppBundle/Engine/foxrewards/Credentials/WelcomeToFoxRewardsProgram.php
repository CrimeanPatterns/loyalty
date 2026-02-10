<?php

namespace AwardWallet\Engine\foxrewards\Credentials;

class WelcomeToFoxRewardsProgram extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'rewards@foxrentacar.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Fox Rent-A-Car's Rewards Program",
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
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();
        $result['AccountNumber'] = re("/Fox\s+Rewards\s+ID\s*:\s*(.*?)\s+If/ims", $text);
        $result['Name'] = re("/Welcome\s+(.*?)\s+to\s+the\s+Fox/ims", $text);

        return $result;
    }
}
