<?php

namespace AwardWallet\Engine\boyne\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "dreambig@boynerewards.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to BoyneRewards",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result = [];
        $result['Email'] = $parser->getCleanTo();

        if ($name = re("#Dear\s+(\w+\s+\w+)#i", $text)) {
            $result['Name'] = beautifulName($name);
        }

        return $result;
    }
}
