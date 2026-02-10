<?php

namespace AwardWallet\Engine\aplus\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'noreply@a-club.com',
            'accorhotels@accor-mail.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to A|Club!",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($parser->getBody());
        $result['Login'] = trim($this->http->FindSingleNode("//td[contains(text(),'Member number:')]/span"));
        $result['Name'] = beautifulName(re("#Dear Mr (.*?)\n#i", $text));

        return $result;
    }
}
