<?php

namespace AwardWallet\Engine\cvs\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'SUBJECT "Your CVS/pharmacy Online Account" FROM "customercare@cvs.com"',
            'FROM "email@online.cvs.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();

        if (stripos($subject, 'Your CVS/pharmacy Online Account') !== false) {
            $result['Name'] = re('#Dear (.*?),#i', $text);
        } else {
            $result['FirstName'] = re('#Welcome, (.*)#i', $text);
        }

        return $result;
    }
}
