<?php

namespace AwardWallet\Engine\drury\Credentials;

class Password extends \TAccountCheckerExtended
{
    protected $http2 = null;

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
            "DruryHotels.com login request",
        ];
    }

    public function getParsedFields()
    {
        return ["Password"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Password'] = re('#The\s+password\s+you\s+requested\s+for\s+DruryHotels\.com\s+is\s*:\s*(\d+)#msi', text($this->http->Response['body']));

        return $result;
    }
}
