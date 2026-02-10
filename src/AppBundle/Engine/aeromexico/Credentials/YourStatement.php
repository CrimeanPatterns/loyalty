<?php

namespace AwardWallet\Engine\aeromexico\Credentials;

class YourStatement extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'clubpremier@clubpremieremail.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'your statement for',
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
        $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Hello\s+(.*?),\s+Your#i', $this->text());
        $result['Login'] = re('#Hello\s+' . $result['FirstName'] . '\s+(\d+)#i', $this->text());

        return $result;
    }
}
