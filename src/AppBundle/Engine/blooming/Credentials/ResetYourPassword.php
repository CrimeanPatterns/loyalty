<?php

namespace AwardWallet\Engine\blooming\Credentials;

class ResetYourPassword extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'CustomerService@oe.bloomingdales.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Reset your password',
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
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
