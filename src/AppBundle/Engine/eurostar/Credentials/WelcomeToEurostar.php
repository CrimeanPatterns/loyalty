<?php

namespace AwardWallet\Engine\eurostar\Credentials;

class WelcomeToEurostar extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'contactus@eurostar.co.uk',
            'eurostar-en@maileu.custhelp.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to Eurostar!',
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
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Name'] = nice(re('#Dear\s+M[rs]+\s+(.*)#i', $text));

        return $result;
    }
}
