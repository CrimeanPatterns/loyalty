<?php

namespace AwardWallet\Engine\bevmo\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "BevMo_Newsletter@shop.bevmo.com",
            "BevMo_newsletter@e.bevmo.com",
            "CustomerService@bevmo.com",
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
            "Email",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($name = re('#\s*(\w.*?)\s+-\s+.*\s+View\s+this\s+email\s+with\s+images\.#i', $text)) {
            $result['FirstName'] = $name;
        }

        return $result;
    }
}
