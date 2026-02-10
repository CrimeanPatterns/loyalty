<?php

namespace AwardWallet\Engine\ethiopian\Credentials;

class EnrollmentConfirmationShebaMiles extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'shebamiles@ethiopianairlines.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Enrollment Confirmation - ShebaMiles',
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
        $result['Login'] = re('#Membership\s+Number\s*:\s+(\d+)#i', $this->text());

        return $result;
    }
}
