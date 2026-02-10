<?php

namespace AwardWallet\Engine\changi\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'info@changirewards.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Thanks for signing up for Changi Rewards",
            "March Fun at Changi Airport",
            "Important Notice: Scheduled System Upgrade",
            "Celebrate with Singapore this month with these deals at Changi Airport",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Email',
            'Login!',
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        if ($name = re("#Dear\s+(\w+)#", $this->text())) {
            $result['FirstName'] = beautifulName($name);
        }

        if ($login = re("#Card number:\s+([A-Z0-9-]+)#msi", $this->text())) {
            $result['Login!'] = $login;
        }

        return $result;
    }
}
