<?php

namespace AwardWallet\Engine\americaneagle\Credentials;

class YourAEREWARDSMonthStatement extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-3.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'AEREWARD$@e.ae.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            '#Your\s+AEREWARD\$\s+July\s+Statement#i',
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
        $result['Name'] = re('#\s*(.*)\s*CARD\s+NUMBER\s*:#i', $text);

        return $result;
    }
}
