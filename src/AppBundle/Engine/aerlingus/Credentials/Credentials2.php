<?php

namespace AwardWallet\Engine\aerlingus\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "DoNotReply@aerlingusgoldcircleclub.com",
            "site_membership@aerlingus.com",
            "loyalty@aerlingus.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Aer Lingus Frequent Flyer Programme",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Login!",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#Dear\s+(.*)\s+Welcome#i', $text);
        $result['Login!'] = re("#your\s+Gold\s+Circle\s+application\s+number\s+(\d+)#msi", $text);

        return $result;
    }
}
