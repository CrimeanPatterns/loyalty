<?php

namespace AwardWallet\Engine\bmi\Credentials;

class WelcomeToDiamondClub extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'bmidiamondclub@bmi-news.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to Diamond Club',
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
        $text = text($this->http->Response['body']);
        $result['FirstName'] = re('#Dear\s+(.*?)\s+Membership#i', $text);
        $result['Login'] = re('#Membership\s+number:\s+(\d+)#i', $text);

        return $result;
    }
}
