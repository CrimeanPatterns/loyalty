<?php

namespace AwardWallet\Engine\egyptair\Credentials;

class WelcomeMessage extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'customeraff@egyptair.com.eg',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            '/Welcome\s+Message/ims',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['Login'] = re("/membership\s+number\s+is\s*:\s*(\d+?)\s*Your/ims", $text);
        $result['Name'] = re("/Dear\s+\w+\s+(.*?),\s*Welcome/ims", $text);

        return $result;
    }
}
