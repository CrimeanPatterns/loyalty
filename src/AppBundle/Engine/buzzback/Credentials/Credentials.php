<?php

namespace AwardWallet\Engine\buzzback\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'support@surveyhelpcenter.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Buzzback#i",
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

        $result['Name'] = re("#(?:^|\n)\s*Hello\s+([^\n,]+)#i", $text);
        $result['Login'] = $parser->getCleanTo();
        //$result['Login'] = re("#(?:^|\n)\s*Your Username is[:\s]+([^\s]+)#ix", $text);

        return $result;
    }
}
