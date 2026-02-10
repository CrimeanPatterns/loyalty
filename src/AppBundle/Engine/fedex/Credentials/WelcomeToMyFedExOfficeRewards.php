<?php

namespace AwardWallet\Engine\fedex\Credentials;

class WelcomeToMyFedExOfficeRewards extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'info@email.fedex.epsihost.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to My FedEx® Office Rewards',
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re('#\s+Dear\s+(.*?),#i', $this->text());

        return $result;
    }
}
