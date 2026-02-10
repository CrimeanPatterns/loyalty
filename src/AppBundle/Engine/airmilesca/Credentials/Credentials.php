<?php

namespace AwardWallet\Engine\airmilesca\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "newsandmore@emails.airmiles.ca",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "help us keep your account up to date",
            "A quick question from AIR MILES",
            "An important notice about your AIR MILES",
        ];
    }

    public function getParsedFields()
    {
        return ["FirstName"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re("#Dear\s+(\w+)#", text($this->http->Response['body']));

        return $result;
    }
}
