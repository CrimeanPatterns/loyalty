<?php

namespace AwardWallet\Engine\ctrip\Credentials;

class CtripRegistrationConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'service@ctrip.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Ctrip Registration Confirmation',
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
        $result['Login'] = re('#Congratulations,\s+(\S+)!\s+Thousands\s+of\s+great\s+deals#i', $this->text());

        return $result;
    }
}
