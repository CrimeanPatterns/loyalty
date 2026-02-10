<?php

namespace AwardWallet\Engine\ethiopian\Credentials;

class TodayIsYourBirthdayABirthdayWishFromShebamiles extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'shebacommunication@ethiopianairlines.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Today is Your Birthday! , A Birthday Wish From Shebamiles!!',
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
        $result['Login'] = re('#The\s+ShebaMiles\s+Team\s+(\d+)#i', $this->text());
        $result['Name'] = re('#Happy\s+birthday!!\s+M[RS]+\.\s+(.*?)\s+The\s+ShebaMiles\s+Team#i', $this->text());

        return $result;
    }
}
