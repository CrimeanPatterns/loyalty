<?php

namespace AwardWallet\Engine\frontierairlines\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'info@fly.frontierairlines.com',
            'no-reply@flyfrontier.com',
            'info@notification.frontierairlines.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "EarlyReturns Enrollment information!",
            "#eStatement for#i",
        ];
    }

    public function GetParsedFields()
    {
        return [
            "FirstName",
            "Name",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $subject = $parser->getSubject();

        if (re("#eStatement\s+for\s+([^\s]+)#i", $subject)) {
            $text = $parser->getPlainBody();
            $result['FirstName'] = re(1);
            $result['Name'] = re("#\n\s*([^\n]+)\s+Member\s+Number\s*:\s*(\d+)#", $text);
            $result['Login'] = re(2);
        } else {
            $text = text($this->http->Response['body']);
            $result['FirstName'] = re('#Dear\s+(.*?)\s*,#i', $text);
            $result['Name'] = re('#Name:\s+(.*)#i', $text);
            $result['Login'] = $this->http->FindSingleNode("//text()[contains(., 'ID:')]/ancestor-or-self::td[1]", null, true, "#EarlyReturns\s*[^\s]+\s*ID\s*:\s*(\d+)#i");
        }

        return $result;
    }
}
