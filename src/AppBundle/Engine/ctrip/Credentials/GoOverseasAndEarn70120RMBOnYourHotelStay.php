<?php

namespace AwardWallet\Engine\ctrip\Credentials;

class GoOverseasAndEarn70120RMBOnYourHotelStay extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'newsletter@ctrip.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Go overseas, and earn 70-120 RMB on your hotel stay!',
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'LastName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        if (preg_match('#\s+Dear\s+(.*?)/(.*?),#i', $this->text(), $m)) {
            $result['FirstName'] = $m[2];
            $result['LastName'] = $m[1];
        }

        return $result;
    }
}
