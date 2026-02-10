<?php

namespace AwardWallet\Engine\airmaroc\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'news@casanet.ccemails.net',
            'safarflyer@royalairmaroc.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Air\s*maroc#i",
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
        $text = text($this->http->Response['body']);

        $result['Login'] = re([
            "#\n\s*Your account number[:\s]+([^\s]+)#i",
            "#Card number[:\s]+([^\s]+)#i",
        ], $text);

        $result['Name'] = re([
            "#\n\s*Dear(?:\s+Mrs?)?\s+([^\n,]+)#i",
        ], $text);

        return $result;
    }
}
