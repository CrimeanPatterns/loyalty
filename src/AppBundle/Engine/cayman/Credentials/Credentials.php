<?php

namespace AwardWallet\Engine\cayman\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'noreply@caymanairways.com',
            'specialsemail@caymanairways.net',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Cayman Airways - Frequent Flyer Login Information',
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
            "FirstName",
            "LastName",
            "Password",
            "Number",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Email"] = $parser->getCleanTo();
        $result["FirstName"] = re("#First\s+Name\s*:\s*(\w+)#", $this->text());
        $result["LastName"] = re("#Last\s+Name\s*:\s*(\w+)#", $this->text());
        $result["Login"] = $result["Number"] = re("#Member\s+ID\s*:\s*(\d+)#", $this->text());
        $result["Password"] = re("#Password\s*:\s*(\S+)#", $this->text());

        return $result;
    }
}
