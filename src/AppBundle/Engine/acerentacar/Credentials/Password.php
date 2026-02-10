<?php

namespace AwardWallet\Engine\acerentacar\Credentials;

class Password extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Password.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'rent@acerentacar.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'ACE Rent A Car Web Site Login Information',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
            'Email',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = beautifulName(re('#Name:\s*(.+)#i', $this->text()));
        $result['Password'] = trim(re('#Password:\s*(.+)#i', $this->text()));

        return $result;
    }
}
