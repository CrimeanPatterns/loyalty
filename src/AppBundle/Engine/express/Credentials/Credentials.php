<?php

namespace AwardWallet\Engine\express\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'expressnext@e.express.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Express#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Number',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);

        $result['Login'] = $parser->getCleanTo();

        $result['Name'] = re("#\n\s*([^\n]+)\s+EXPRESS NEXT [ID.:\s]+([^\n,]+)#i", $text);
        $result['Number'] = re(2);

        return $result;
    }
}
