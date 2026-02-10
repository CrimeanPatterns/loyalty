<?php

namespace AwardWallet\Engine\americinn\Credentials;

class ThankYouForEnrolling extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'info@email.americinn.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Thank You for Enrolling',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Dear\s+(.*?),#i', $this->text());

        return $result;
    }
}
