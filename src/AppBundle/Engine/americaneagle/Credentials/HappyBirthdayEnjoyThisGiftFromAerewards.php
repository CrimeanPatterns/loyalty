<?php

namespace AwardWallet\Engine\americaneagle\Credentials;

class HappyBirthdayEnjoyThisGiftFromAerewards extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-2.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'AEREWARD$@e.ae.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Happy Birthday! Enjoy This Gift From AEREWARD$',
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
        $result['FirstName'] = re('#HAPPY\s+BIRTHDAY\s+(.*?)!#i', $text);

        return $result;
    }
}
