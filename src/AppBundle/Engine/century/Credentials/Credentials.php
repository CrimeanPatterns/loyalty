<?php

namespace AwardWallet\Engine\century\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return ["service@shop.c21stores.com"];
    }

    public function getCredentialsSubject()
    {
        return ["#Can't-go-wrong\s*:\s*.*starting at#"];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Login"] = $result["Email"] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $result["FirstName"] = re("#Hi\s+([^,]+),#", $text);

        return $result;
    }
}
