<?php

namespace AwardWallet\Engine\asiana\Credentials;

class Balance extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'iclub@flyasiana.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "This is your Mileage balance",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Name'] = beautifulName(trim(orval(
            $this->http->FindSingleNode("//img[contains(@src, 'http://www.asianaclub.net/newsletter/1405/global/us01.gif')]/..", null, true, "#^(.*?)\(#ms"),
            $this->http->FindSingleNode("//*[contains(text(), 'Your mileage balance is based as of')]/preceding-sibling::strong[1]", null, true, "#^(.*?)\(#ms")
        )));

        return $result;
    }
}
