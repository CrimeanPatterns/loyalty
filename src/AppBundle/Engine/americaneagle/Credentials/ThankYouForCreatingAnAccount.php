<?php

namespace AwardWallet\Engine\americaneagle\Credentials;

class ThankYouForCreatingAnAccount extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'ae@e.ae.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Thank You For Creating An Account',
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
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Dear\s+(.*?),#i', $text);

        return $result;
    }
}
