<?php

namespace AwardWallet\Engine\drury\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "newsletter@druryhotels.com",
            "drury@druryhotels.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            " ",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Email'] = $parser->getCleanTo();

        if (re('#The\s+username\(s\)\s+you\s+requested\s+for\s+DruryHotels\.com\s+is\s*:\s*(\d+)#msi', text($this->http->Response['body']))) {
            return null;
        }

        if (re('#The\s+password\s+you\s+requested\s+for\s+DruryHotels\.com\s+is\s*:\s*(\d+)#msi', text($this->http->Response['body']))) {
            return null;
        }

        return $result;
    }
}
