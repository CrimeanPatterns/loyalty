<?php

namespace AwardWallet\Engine\alison\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "@alison.com",
            "@mailer.alison.com",
            "noreply@learning.alison.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Discover our Free Courses!",
            "New Course:",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Login",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($name = re("#(\w+)\s*,\s*Discover\s+our\s+Free\s+Courses#i", $this->text())) {
            $result['FirstName'] = beautifulName($name);
        }

        if ($name = re("#We've\s+added\s+a\s+new\s+course\s+for\s+you\s*,\s*(\w+)#i", $this->text())) {
            $result['FirstName'] = beautifulName($name);
        }

        return $result;
    }
}
