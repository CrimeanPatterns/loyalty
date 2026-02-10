<?php

namespace AwardWallet\Engine\brussels\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'no-reply@brusselsairlines.com',
            'info@news.brusselsairlines.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Welcome to mybrusselsairlines#i",
            "#Your mybrusselsairlines account#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Name",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $html = $this->http->Response['body'];
        $text = text($html);

        $result["Name"] = trim(re("#(?:^|\n)\s*Dear\s+([^,.\n]+)#i", $text));
        $result["Login"] = $parser->getCleanTo();

        return $result;
    }
}
