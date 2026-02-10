<?php

namespace AwardWallet\Engine\flyerbonus\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'flyerbonus@bangkokair.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Bangkok Airways Frequent Flyer Program",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($login = $this->http->FindSingleNode("//*[contains(text(), 'Your FlyerBonus ID')]/ancestor-or-self::td[1]/following-sibling::td[1]")) {
            // welcome
            $result['Login'] = $login;
            $result['Name'] = nice(re('#Dear\s+M[RS]+\.\s+(.*)\s*,#i', $text));
        } elseif ($login = $this->http->FindPreg('#Your FlyerBonus ID is\s*([A-Z\d\-]+)#')) {
            // news
            $result['Login'] = $login;
            $result['Name'] = nice(re('#Dear\s+M[RS]+\.\s+(.*)\s*,#i', $text));
        }

        return $result;
    }
}
