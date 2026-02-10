<?php

namespace AwardWallet\Engine\cashcrate\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'do-not-reply@cashcrateupdates.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Join us for CashCrate#i",
            "#CashCrate registration#i",
        ];
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
        $html = $this->http->Response['body'];
        $text = text($html);

        $result["Login"] = $parser->getCleanTo();
        $result["Name"] = orval(
            re("#(?:^|\n)\s*Hi\s+([^\n,:]+)#i", $text),
            re("#^(?:Fw:)?\s*(.*?)\s*\-\s*Join us#i", $parser->getSubject())
        );

        return $result;
    }
}
