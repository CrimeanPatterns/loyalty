<?php

namespace AwardWallet\Engine\dorint\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'dorintcard@dorint.com',
            'newsletter@dorint.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Dorint Card: Registration",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Login'] = re([
            "#\n\s*Card number[:\s]+([^\s]+)#i",
        ], $text);

        $result['Password'] = re([
            "#Password[:\s]+([^\s]+)#ms",
        ], $text);

        $result['Name'] = re([
            "#\n\s*Name[:\s]+([^\n]+)#i",
            "#Dear(?:\s+Mr[.s]*?)?\s+([^\n,]+)#i",
        ], $text);

        return $result;
    }
}
