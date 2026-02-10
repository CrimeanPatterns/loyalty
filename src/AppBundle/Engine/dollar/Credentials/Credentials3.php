<?php

namespace AwardWallet\Engine\dollar\Credentials;

class Credentials3 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "dollarrentacar@email.dollar.com",
            "DollarExpress@dollar.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Thank you for enrolling in Dollar EXPRESS",
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
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['Name'] = beautifulName(re('#Thank\s+you\s+([^,]+),#i', $text));

        return $result;
    }
}
