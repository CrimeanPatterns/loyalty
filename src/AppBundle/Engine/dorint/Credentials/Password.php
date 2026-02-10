<?php

namespace AwardWallet\Engine\dorint\Credentials;

class Password extends \TAccountCheckerExtended
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
            "Dorint Card: Password",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

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
