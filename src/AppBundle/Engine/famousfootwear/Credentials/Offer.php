<?php

namespace AwardWallet\Engine\famousfootwear\Credentials;

class Offer extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'famousfootwear@cn.famousfootwear.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'LAST DAY for 15%/20% Off | Vans Classic Sneakers | BOGO 1/2 Off',
            'Pack A Bold Color Punch | BOGO 1/2 Off',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['FirstName'] = re('#Hi\s+(.*?)\s+\|#i', $text);

        return $result;
    }
}
