<?php

namespace AwardWallet\Engine\ebags\Credentials;

class SurpriseWeRemembered extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'eBags@response.ebags.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Surprise Alexi! We remembered.',
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
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Happy\s+Birthday\s+(.*?)!\s+Click\s+anywhere\s+to#i', $this->text());

        return $result;
    }
}
