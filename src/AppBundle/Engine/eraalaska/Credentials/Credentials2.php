<?php

namespace AwardWallet\Engine\eraalaska\Credentials;

class Credentials2 extends \TAccountCheckerExtended
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
            "Password",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Password'] = re('#Your\s+password\s+is\s*:\s*(\S+)#i', $this->text());
        $result['Name'] = beautifulName(re('#Dear\s+(\w+\s+\w+)#', $this->text()));

        return $result;
    }
}
