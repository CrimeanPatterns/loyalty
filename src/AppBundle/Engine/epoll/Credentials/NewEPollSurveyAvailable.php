<?php

namespace AwardWallet\Engine\epoll\Credentials;

class NewEPollSurveyAvailable extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'mail@epoll.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'New E-Poll survey available',
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
        $result['Login'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Dear\s+(.*?):#i', $text);

        return $result;
    }
}
