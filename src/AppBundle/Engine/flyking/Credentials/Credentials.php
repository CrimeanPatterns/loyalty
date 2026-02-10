<?php

namespace AwardWallet\Engine\flyking\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "kingclub@flykingfisher.com"',
            'FROM "kingclubnews@info.kingclub.me"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Welcome to King Club!') !== false) {
            // credentials-1.eml
            $result['Name'] = re('#Dear M[rs]+\. (.*?)\s*,#i', $text);
            $result['Login'] = re('#Membership\s+Number\s*:\s*(\d+)#i', $text);
            $result['Password'] = re('#Password\s*:\s*(\S+)#i', $text);
        } elseif (stripos($subject, 'Your King Club e-statement') !== false) {
            // credentials-2.eml
            $result['LastName'] = re('#Dear\s*M[rs]+\.\s+(.*)\s*,#i', $text);
            $result['Login'] = re('#Membership\s+No\.:\s*(\S+)#i', $text);
        } elseif (stripos($subject, 'Special fares on Kingfisher Airlines') !== false) {
            // credentials-3.eml
            $result['LastName'] = re('#Greetings\s+M[rs]+\.\s+(.*)\s*,#i', $text);
            $result['Login'] = re('#Membership\s+No:\s+(\S+)#i', $text);
        }

        return $result;
    }
}
