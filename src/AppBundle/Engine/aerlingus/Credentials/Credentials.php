<?php

namespace AwardWallet\Engine\aerlingus\Credentials;

class Credentials extends \TAccountCheckerExtended
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
            "Welcome to Aer Lingus Website",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#Dear\s+(.*)\s+Welcome#i', $text);

        return $result;
    }
}
