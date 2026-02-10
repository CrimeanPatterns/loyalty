<?php

namespace AwardWallet\Engine\allaccess\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'hardrock@email.bp00.com',
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
            "Login",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);
        $result["Login"] = $parser->getCleanTo();
        $result["FirstName"] = beautifulName(re("#Hey\s+(\w+),#", $text));

        return $result;
    }
}
