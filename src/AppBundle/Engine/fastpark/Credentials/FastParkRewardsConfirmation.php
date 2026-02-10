<?php

namespace AwardWallet\Engine\fastpark\Credentials;

class FastParkRewardsConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'RFRteam@thefastpark.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Fast Park Rewards Confirmation',
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
        $result['Login'] = re('#\s+Username:\s+(\S+)#i', $this->text());

        return $result;
    }
}
