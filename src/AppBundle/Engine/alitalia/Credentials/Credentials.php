<?php

namespace AwardWallet\Engine\alitalia\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "MilleMiglia@comunicazioni.alitalia.it",
            "mailing@offerte.alitalia.it",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to the MilleMiglia Club',
            'The new edition of the MilleMiglia Program',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            'Name',
            'FirstName',
            'LastName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = nice(re('#Dear\s+(.*?)\s*,#i', $text));

        if (preg_match('#^(\w+)\s+(\w+)$#i', $result['Name'], $m)) {
            $result['FirstName'] = $m[1];
            $result['LastName'] = $m[2];
        }

        if (stripos($subject, 'Welcome to the MilleMiglia Club') !== false) {
            if (isset($result['Name']) and $result['Name']) {
                $result['Login'] = re('#(\d+)\s*' . $result['Name'] . '#i', $text);
            }
        } elseif (stripos($subject, 'The new edition of the MilleMiglia Program') !== false) {
            $result['Login'] = re('#MilleMiglia\s+Code:\s*(\d+)#i', $text);
        }

        return $result;
    }
}
