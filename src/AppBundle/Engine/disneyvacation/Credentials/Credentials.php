<?php

namespace AwardWallet\Engine\disneyvacation\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "disneyvacationclub@dvc.chtah.com",
            "memberservices@wdw.twdc.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Disney#i",
            "#Vacation#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Name",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];

        $result['Name'] = clear("#\s+Family#i", re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text));
        $result['Login'] = $parser->getCleanTo();
        //$result['Login'] = re("#(?:^|\n)\s*Your Username is[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
