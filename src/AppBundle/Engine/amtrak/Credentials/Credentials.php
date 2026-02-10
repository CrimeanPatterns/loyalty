<?php

namespace AwardWallet\Engine\amtrak\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "support@amtrakguestrewards.com",
            "updates@amtrakguestrewards.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to the program. We\'re proud to have you on board.",
            "A program update regarding travel redemptions",
            "Remember, it’s good practice to change your password",
        ];
    }

    public function GetParsedFields()
    {
        return [
            "FirstName",
            "Login",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();

        $text = $this->http->FindSingleNode("(//img[contains(@alt, 'Amtrak')])[1]/following::td[1]");

        if (preg_match('#\s*(.*)\s+Member\s*\#\s*(\d+)#i', text($this->http->Response['body']), $m)) {
            $result['FirstName'] = $m[1];
            $result['Login'] = $m[2];
        } elseif (re("#^(.*?)\s+\#\s*(\d+)#", $text)) {
            $result['FirstName'] = re(1);
            $result['Login'] = re(2);
        }

        return $result;
    }
}
