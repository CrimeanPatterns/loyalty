<?php

namespace AwardWallet\Engine\deltahotels\Credentials;

class WelcomeToDeltaHotelsAndResorts extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'deltanet@deltahotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to Delta Hotels and Resorts',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'LastName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#First\s+Name:\s+(.*?)\s+Delta\s+Privilege\s+Number#i', $text);
        $result['LastName'] = re('#Last\s+Name:\s+(.*?)\s+First\s+Name:#i', $text);

        return $result;
    }
}
