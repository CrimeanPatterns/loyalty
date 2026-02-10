<?php

namespace AwardWallet\Engine\eva\Credentials;

class EVAAirInfinityMileageLandsPasswordNotification extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'password-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'eservice@service2.evaair.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'EVA Air Infinity MileageLands - Password Notification',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Password',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Name'] = re('#Dear\s+M[rs]+\.\s+(.*)#i', $text);
        $result['Password'] = re('#Your\s+password\s*:\s*(\S+)#i', $text);

        return $result;
    }
}
