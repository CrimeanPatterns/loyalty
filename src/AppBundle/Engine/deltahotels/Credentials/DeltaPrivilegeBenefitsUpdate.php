<?php

namespace AwardWallet\Engine\deltahotels\Credentials;

class DeltaPrivilegeBenefitsUpdate extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'privilege@e.deltahotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Delta Privilege Benefits Update',
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
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = re('#Dear\s+(.*?),#i', $text);

        return $result;
    }
}
