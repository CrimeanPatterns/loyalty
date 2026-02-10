<?php

namespace AwardWallet\Engine\coasthotels\Credentials;

class Missed extends \TAccountCheckerExtended
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
            "We've missed you!",
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = $this->htmlToPlainText($this->http->Response['body']);

        $result['FirstName'] = $this->re("#(?:^|\n)\s*Dear\s+([^\n,]+)#i", $text);

        return $result;
    }
}
