<?php

namespace AwardWallet\Engine\autozone\Credentials;

class PasswordResetSuccessfully extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'customercare@autozonerewards.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Password reset successfully',
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
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = re('#we\s+reach\s+your\s+inbox.\s+(.*?),\s+member\s+id#i', $this->text());

        return $result;
    }
}
