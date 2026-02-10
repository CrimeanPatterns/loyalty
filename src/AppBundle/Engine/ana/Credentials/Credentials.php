<?php

namespace AwardWallet\Engine\ana\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "anae-magazinee@121.ana.co.jp",
            "ana39mail@121.ana.co.jp",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your Registration Confirmation from ANA Mileage Club",
            "#ANA Mileage#i",
            "#cards from ANA#i",
            "#ANA is expanding#i",
            "#ANA Mail#i",
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
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        $result['Name'] = trim(re('#Dear ([^\n,.]+)#i', $text));
        $result['Login'] = re('#Dear\s+.*?\s*\.\s+(?:ANA Number\s*:)?\s*(\d+)#i', $text);

        return $result;
    }
}
