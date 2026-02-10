<?php

namespace AwardWallet\Engine\disney\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'SUBJECT "Your Rewards Update" FROM "DisneyDVD@disney.dvdmailcenter.com"',
            'FROM "DisneyDVD@disney.dvdmailcenter.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        if (stripos($parser->getSubject(), 'Your Rewards Update') !== false) {
            $result['FirstName'] = re('#Welcome, (.*?) -- You#i', $parser->getPlainBody());
        }
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
