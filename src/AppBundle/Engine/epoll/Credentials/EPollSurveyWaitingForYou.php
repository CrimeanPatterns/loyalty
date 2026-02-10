<?php

namespace AwardWallet\Engine\epoll\Credentials;

class EPollSurveyWaitingForYou extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
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
            'E-Poll survey waiting for you',
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
