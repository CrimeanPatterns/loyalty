<?php

namespace AwardWallet\Engine\airbaltic\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsSubject()
    {
        return ["#check out your latest account statement#i"];
    }

    public function getCredentialsImapFrom()
    {
        return [
            "news@pinsforme.com",
            "info@pinsforme.com",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Name",
            "Email",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        $result['Name'] = $this->http->FindSingleNode("//*[contains(text(), 'my PINS')]/preceding::td[1]");
        $result['Email'] = $parser->getCleanTo();
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
