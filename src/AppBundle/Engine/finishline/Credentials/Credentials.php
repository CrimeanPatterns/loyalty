<?php

namespace AwardWallet\Engine\finishline\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'finishline@news.finishline.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Winner's\s+Circle#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];

        $result['Name'] = re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text);
        //$result['Login'] = re("#(?:^|\n)\s*Your Username is[:\s]+([^\s]+)#ix", $text);
        $result['Login'] = $parser->getCleanTo();

        return $result;
    }
}
