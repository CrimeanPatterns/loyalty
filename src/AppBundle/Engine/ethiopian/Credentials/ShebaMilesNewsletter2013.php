<?php

namespace AwardWallet\Engine\ethiopian\Credentials;

class ShebaMilesNewsletter2013 extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-3.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'noreply@campaignshebamiles.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'ShebaMiles Newsletter',
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
        $result['Login'] = re('#ShebaMiles\s+Number\s*:\s+(\d+)#i', $this->text());
        $result['Name'] = re('#\s+M[RS]+\.\s+(.*?)\s+ShebaMiles\s+Number#i', $this->text());

        return $result;
    }
}
