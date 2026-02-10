<?php

namespace AwardWallet\Engine\airtran\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'SUBJECT "with AirTran Airways" FROM "aplusrewards@go.airtran.com"',
            'SUBJECT "AirTran Airways" FROM "info@airtranweb.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['Email'] = $parser->getCleanTo();

        if (stripos($subject, 'with AirTran Airways') !== false) {
            $result['Name'] = re('#Member Name: (.*)#i', $text);
            $result['Login'] = re('#A\+ Rewards Account Number: (\d+)#i', $text);
        } elseif (stripos($subject, 'AirTran Airways') !== false) {
            $result['FirstName'] = re('#Welcome (.*?),#i', $text);
            $result['Login'] = re('#Login: (\d+)#i', $text);
            $result['Name'] = re('#Contact Information\s+M[RS]+ (.*)#i', $text);
        }

        return $result;
    }
}
