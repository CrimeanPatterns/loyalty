<?php

namespace AwardWallet\Engine\bmi\Credentials;

class YoureMissingOutOnMiles extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'bmidiamondclub@flybmi-email.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'You\'re missing out on miles',
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
        $result['FirstName'] = re('#Dear\s+(.*?)\s+A\s+while#i', $text);
        $result['Login'] = re('#Membership\s+number:\s+(\d+)#i', $text);

        return $result;
    }
}
