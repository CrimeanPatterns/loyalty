<?php

namespace AwardWallet\Engine\bodyshop\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    use \RegExpTools;

    public function getCredentialsImapFrom()
    {
        return [
            'TheBodyShopUSA@mail.thebodyshop-usa.com',
            'thebodyshopusa@mail.thebodyshop-usa.com',
            'support@thebodyshop.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to your The Body Shop account",
            "Welcome to your Body Shop Account",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
            "FirstName",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result["Login"] = $result["Email"] = $parser->getCleanTo();
        $result["FirstName"] = $this->re("#Dear\s+(\w+)#", $this->text());

        return $result;
    }
}
