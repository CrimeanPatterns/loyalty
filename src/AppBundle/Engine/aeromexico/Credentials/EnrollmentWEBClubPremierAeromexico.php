<?php

namespace AwardWallet\Engine\aeromexico\Credentials;

class EnrollmentWEBClubPremierAeromexico extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'inscprem01@aeromexico.com.mx',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Enrollment WEB Club Premier Aeromexico',
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
        $result['Email'] = $parser->getCleanTo();
        $result['Login'] = re('#Welcome\s+to\s+Club\s+premier\s+your\s+account\s+number\s+is\s+(\d+)#i', $this->text());

        return $result;
    }
}
