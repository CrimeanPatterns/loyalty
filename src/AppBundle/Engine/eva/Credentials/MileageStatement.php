<?php

namespace AwardWallet\Engine\eva\Credentials;

class MileageStatement extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
        'credentials-3.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'eservice@service2.evaair.com',
            'eservice@evaair.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            '個人哩程核對表',
            'Evergreen Club E-Mileage Statement',
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
        $text = text($this->http->Response['body']);
        $result['Login'] = re('#\((\w+)\).*?Evergreen\s+Club\s+Mileage\s+Statement#is', $text);
        $result['Name'] = re('#M[rs]+\. (.*)#i', $text);

        return $result;
    }
}
