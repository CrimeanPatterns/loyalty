<?php

namespace AwardWallet\Engine\finnair\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "finnair.campaign@plus.finnair.com"',
            'FROM "web@finnair.fi"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $subject = $parser->getSubject();
        $text = str_replace('=', '', text($this->http->Response['body']));

        if (stripos($subject, 'Finnair Plus membership verification') !== false) {
            // credentials-1.eml
            $result['Login'] = re('#membership\s+number\s*:\s+(\d+)#i', $text);
            $result['Name'] = re('#Name: (.*)#i', $text);
        } elseif (stripos($subject, 'Welcome to Finnair Plus') !== false) {
            // credentials-2.eml
            $result['Login'] = re('#Membership\s+number\s*:\s+(\d+)#i', $text);
            $result['Name'] = nice(re('#Dear ((?s).*?),\s+Membership#i', $text));
        } elseif (stripos($subject, 'Your Finnair Plus account statement') !== false) {
            // credentials-3.eml
            $result['Login'] = re('#Your account number\s+(\d+)#i', $text);
            $result['Name'] = nice(re('#Your account information:\s+((?s).*)\s+Your account#i', $text));
        } elseif (stripos($subject, 'Finnair Plus log in information') !== false) {
            // credentials-4.eml
            $result['Login'] = re('#UserId:\s+(\d+)#i', $text);
            $result['Name'] = re('#Dear (.*)\s+Your login#i', $text);
            $result['Password'] = re('#Password:\s+(\S+)#i', $text);
        }

        return $result;
    }
}
