<?php

namespace AwardWallet\Engine\chase\Credentials;

class YourCreditCardStatementIsAvailableOnline extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'Chase@emailinfo.chase.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Your credit card statement is available online',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['FirstName'] = re('#\s+Dear\s+(.*?):#i', $this->text());

        return $result;
    }
}
