<?php

namespace AwardWallet\Engine\eraalaska\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "customerservice@flyera.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Profile Created",
        ];
    }

    public function getParsedFields()
    {
        return [
            // "Login",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        // $result['Login'] = re('#Username\s*:\s*(\S+)#i', $this->text());
        $result['Name'] = beautifulName(re('#Dear\s+(\w+\s+\w+)#', $this->text()));

        return $result;
    }
}
