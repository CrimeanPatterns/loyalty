<?php

namespace AwardWallet\Engine\etihad\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'SUBJECT "Welcome to the Etihad Guest Programme" FROM "guest@etihadguest.com"',
            'SUBJECT "Your Etihad Guest E-Statement" FROM "email@mail.etihadguest.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Welcome to the Etihad Guest Programme') !== false) {
            // credentials-1.eml
            $result['Login'] = re('#Etihad\s+Guest\s+Number:\s+(\d+)#i', $text);
            $result['Name'] = re('#Dear\s+(.*?)\s+Etihad Guest Number#i', $text);
        } elseif (stripos($subject, 'Your Etihad Guest E-Statement') !== false) {
            // credentials-2.eml
            $result['Login'] = re('#Membership\s+number:\s*(\d+)#i', $text);
            $result['Name'] = re('#Dear\s+(.*?),#i', $text);
        }

        return $result;
    }
}
