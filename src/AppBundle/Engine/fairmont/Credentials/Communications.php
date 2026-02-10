<?php

namespace AwardWallet\Engine\fairmont\Credentials;

class Communications extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'reply@fairmontemailcommunications.com',
            'emails@fairmontemailcommunications.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "exlcusive offers and updates await",
            "your Fairmont President's Club Great Rates, Great Dates",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Number',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Name'] = beautifulName(orval(
            $this->http->FindSingleNode("//*[contains(text(),'Membership Number')]/ancestor::tr[1]/preceding-sibling::*[1]"),
            $this->http->FindSingleNode("//*[contains(text(),'Status:')]/ancestor::table[1]/preceding-sibling::*[1]")
        ));
        $result['Number'] = orval(
            $this->http->FindSingleNode("//*[contains(text(),'Membership Number')]/..", null, true, "#Membership Number\s*:\s*(\d+)#"),
            $this->http->FindSingleNode("//*[contains(text(),'Status:')]", null, true, "#Member Number\s*:\s*(\d+)#")
        );

        return $result;
    }
}
