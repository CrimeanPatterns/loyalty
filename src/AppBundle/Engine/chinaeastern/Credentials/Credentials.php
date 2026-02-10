<?php

namespace AwardWallet\Engine\chinaeastern\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "master@easternmiles.com",
            "flychinaeastern7@flychinaeastern.com",
            "ChinaEastern@mail.flychinaeastern.cn",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your ScoreCard Account User Name",
            "We wish you a Happy Birthday!",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = trim(re("#Dear\s+([^,]+)#", text($this->http->Response['body'])));

        return $result;
    }
}
