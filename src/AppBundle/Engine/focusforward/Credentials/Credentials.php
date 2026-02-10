<?php

namespace AwardWallet\Engine\focusforward\Credentials;

class Credentials extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return ["claudia@focusfwdonline.com"];
    }

    public function getCredentialsSubject()
    {
        return [
            "Focus Forward Online Account Activation",
            "Paid Online Bulletin Board",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result["Login"] = $result["Email"] = $parser->getCleanTo();
        $result["FirstName"] = re("#Dear\s+(\w+),#", $text);

        return $result;
    }
}
