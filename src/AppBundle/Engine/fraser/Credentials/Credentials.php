<?php

namespace AwardWallet\Engine\fraser\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "email@mail1.houseoffraser.co.uk",
            "no-reply@houseoffraser.co.uk",
        ];
    }

    public function getCredentialsSubject()
    {
        return [" "];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Login"] = $result["Email"] = $parser->getCleanTo();
        $result["Name"] = re("#Hello\s+(.*?),#", $this->text());

        return $result;
    }
}
