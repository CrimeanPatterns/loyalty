<?php

namespace AwardWallet\Engine\aviancataca\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'soporte@lifemiles.com',
            'LifeMiles@mail-lifemiles.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            ' ',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $text = text($parser->getBody());

        if (stripos($parser->getSubject(), 'Welcome to LifeMiles') !== false) {
            // credentials-1.eml
            $result['Login'] = re('#Your\s+LifeMiles\s+number\s+is:\s+(\d+)#i', $text);
            $result['FirstName'] = re('#Greetings (.*)!\s+Welcome to LifeMiles#i', $text);
        } elseif ($login = re('#Tu\s+Número\s+LifeMiles:\s+(\d+)#i', $text)) {
            // credentials-{2,3}.eml
            $result['Login'] = $login;
            $result['FirstName'] = re('#Estimado\s+\(a\)\s+(.*)\s*:#i', $text);
        }

        return $result;
    }
}
