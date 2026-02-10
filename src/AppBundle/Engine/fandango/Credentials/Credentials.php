<?php

namespace AwardWallet\Engine\fandango\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return ["welcome@mtx.fandango.com"];
    }

    public function getCredentialsSubject()
    {
        return ["Welcome to Fandango"];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result["Login"] = $result["Email"] = $parser->getCleanTo();

        return $result;
    }
}
