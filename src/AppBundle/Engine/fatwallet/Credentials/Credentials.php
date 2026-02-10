<?php

namespace AwardWallet\Engine\fatwallet\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
        'credentials-2.eml',
        'credentials-3.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'cashback_followups@fatwallet.com',
            'cbfollowups@fatwallet.com',
            'clickfollowups@fatwallet.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Did You Get Your Cash Back? See Stores You Visited & Today\'s Hottest Deals.',
            'FatWallet Cash Back Update',
            'FatWallet Click Follow-Ups',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Username',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Username'] = re('#Hello\s+(.*?),#i', $text);

        return $result;
    }
}
