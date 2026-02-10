<?php

namespace AwardWallet\Engine\celebritycruises\Credentials;

class ThanksForSettingUpAMyCelebrityAccount extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'celebritywebsupport@celebrity.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Thanks for setting up a My Celebrity account',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'FirstName',
            'LastName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#Dear\s+(.*?)\s*,#i', $text);
        $result['FirstName'] = re('#Dear\s+(\S+)\s+(\S+?),#i', $text);
        $result['LastName'] = re(2);

        return $result;
    }
}
