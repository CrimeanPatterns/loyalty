<?php

namespace AwardWallet\Engine\eva\Credentials;

class HappyBirthdayFromEvaair extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
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
            'HAPPY BIRTHDAY FROM EVAAIR',
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
        $text = text($this->http->Response['body']);
        $result['Name'] = re('#Dear\s+(.*?),#i', $text);

        return $result;
    }
}
