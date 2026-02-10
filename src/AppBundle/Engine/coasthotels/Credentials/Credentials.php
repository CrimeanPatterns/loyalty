<?php

namespace AwardWallet\Engine\coasthotels\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    use \StringTools;
    use \RegExpTools;

    public function getCredentialsImapFrom()
    {
        return [
            'info@coasthotels.com',
            'news@coasthotels.com',
            'rewards@coasthotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to Coast Hotels & Resorts!",
        ];
    }

    public function getParsedFields()
    {
        return [
            'LastName',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = $this->htmlToPlainText($this->http->Response['body']);
        file_put_contents('/www/awardwallet/lol.txt', $text);

        $result['LastName'] = $this->re("#(?:^|\n)\s*Dear\s+M[irs]+\.\s+([^\n,]+)#i", $text);
        $result['Login'] = $this->re("#(?:^|\n)\s*Your Username is[:\s]+([^\s]+)#i", $text);

        return $result;
    }
}
