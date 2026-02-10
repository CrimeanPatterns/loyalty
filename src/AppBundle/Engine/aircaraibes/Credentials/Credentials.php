<?php

namespace AwardWallet\Engine\aircaraibes\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'preference@aircaraibes.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Confirmation d'inscription",
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
        $text = text($this->http->Response['body']);
        $result = [];
        $result['Name'] = beautifulName(re("#Bienvenue au programme Air Caraïbes PREFERENCE, ([^.]+)#ix", $text));
        $result['Login'] = re("#membre PREFERENCE est\s+:\s+(\d+)#ix", $text);

        return $result;
    }
}
