<?php

namespace AwardWallet\Engine\citirewards\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            '@thankyou.citibank.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Annual Privacy Notice",
            "Important changes to ThankYou.com",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Email',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#(?:^\s*Dear\s+|^)(\w+\s+\w+),[^\n\w]*\n#i', $text);

        return $result;
    }
}
