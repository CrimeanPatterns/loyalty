<?php

namespace AwardWallet\Engine\dividendmiles\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'dividendmiles@email-usairways.com',
            'dividendmiles@myusairways.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your Dividend Miles profile",
            "Your Dividend Miles e-Statement",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Name",
            "FirstName",
            "LastName",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Your Dividend Miles profile') !== false) {
            // credentials-1.eml
            $result['Name'] = beautifulName(re('#Your Dividend Miles information\s+(.*)#i', $text));
            $result["FirstName"] = re("#(\w+)\s+(\w+)#", $result['Name']);
            $result['LastName'] = re(2);
            $result['Login'] = re('#Dividend\s+Miles\s+number\s+(\d+)#i', $text);
        } elseif (stripos($subject, 'Your Dividend Miles e-Statement') !== false) {
            // credentials-2.eml
            $regex = '#Your\s+account\s+summary,\s+news,\s+offers\s+and\s+more\s*(.*)\s+\|\s+(\d+)#i';

            if (preg_match($regex, $text, $m)) {
                $result['Name'] = beautifulName($m[1]);
                $result["FirstName"] = re("#(\w+)\s+(\w+)#", $result['Name']);
                $result['LastName'] = re(2);
                $result['Login'] = $m[2];
            }
        }

        return $result;
    }
}
