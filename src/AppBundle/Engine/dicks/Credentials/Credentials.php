<?php

namespace AwardWallet\Engine\dicks\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'DSG@em.dickssportinggoods.com',
            'dsg@em.dickssportinggoods.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Please Verify Your ScoreCard Email Address",
        ];
    }

    public function getParsedFields()
    {
        return ["Email"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
