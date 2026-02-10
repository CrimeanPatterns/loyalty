<?php

namespace AwardWallet\Engine\acerentacar\Credentials;

class Credentials0 extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials.eml',
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
            'ACE Rent A Car Reservation - New Member',
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
        $result['Login'] = $parser->getCleanTo();
        $result['Name'] = beautifulName(re('#Registered\s+to:\s*(.+)#i', $this->text()));

        return $result;
    }
}
