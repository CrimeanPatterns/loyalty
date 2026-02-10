<?php

namespace AwardWallet\Engine\friendchips\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return ["noreply@tuifly.com"];
    }

    public function getCredentialsSubject()
    {
        return ["Willkommen bei FriendChips"];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Login"] = $result["Email"] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);
        $result["Name"] = re("#Herr\s+(\w+\s+\w+)#", $text);

        return $result;
    }
}
