<?php

namespace AwardWallet\Engine\caribbeanair\Credentials;

class CaribbeanMilesRegistration extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'noreply@loyaltyplus.aero',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Caribbean Miles Registration',
        ];
    }

    public function getParsedFields()
    {
        return [
            'LastName',
            'Email',
            'Login',
            'Password',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['Email'] = $parser->getCLeanTo();
        $result['LastName'] = re('#\s+Mr\s+(.*?)\s+,#i', $text);
        $result['Login'] = rew('Member Account Number: (\w+)', $text);
        $result['Password'] = trim(rew('Password: (.+?) Please print', $text));

        return $result;
    }
}
