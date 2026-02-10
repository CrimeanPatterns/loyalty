<?php

namespace AwardWallet\Engine\fedex\Credentials;

class UseYourMyFedExOfficeRewardsProgramNumber extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'fedexoffice@emails.fedex.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Use your My FedEx Office Rewards program number',
            'Double points on self-service transactions at FedEx Office',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = beautifulName(re('#Your\s+Account\s+(.*?)\s+Total\s+Points\s+as\s+of#i', $this->text()));
        $result['FirstName'] = re('#\s+Dear\s+(.*?),#i', $this->text());

        return $result;
    }
}
