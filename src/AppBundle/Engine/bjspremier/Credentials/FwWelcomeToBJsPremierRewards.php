<?php

namespace AwardWallet\Engine\bjspremier\Credentials;

class FwWelcomeToBJsPremierRewards extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'Credentials.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'BJRPremierRewards@kobie.com',
            'bjrpremierrewards@kobie.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Welcome to BJ\'s Premier Rewards',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['Name'] = re('#\s+Dear\s+(.*?),#i', $text);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
