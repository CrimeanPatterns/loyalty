<?php

namespace AwardWallet\Engine\europcar\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "europcar@mail.europcar.com"',
            'FROM "europcar-ww@europcar-loyalty.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($n = re('#Dear\s+M[RS]+\s+(.*?),#i', $text)) {
            // credentials-1.eml
            $result['LastName'] = $n;
        } elseif (re('#First Name\s*:#i', $text)) {
            // credentials-2.eml
            $result['FirstName'] = re('#First\s+Name:\s+(.*)#', $text);
            $result['LastName'] = re('#Last\s+Name:\s+(.*)#', $text);
        }

        return $result;
    }
}
