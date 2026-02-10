<?php

namespace AwardWallet\Engine\dorint\Credentials;

class Other extends \TAccountCheckerExtended
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
            "Dorint",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Name'] = re([
            "#\n\s*Name[:\s]+([^\n]+)#i",
            "#Dear(?:\s+Mr[.s]*?)?\s+([^\n,]+)#i",
        ], $text);

        return $result;
    }
}
