<?php

namespace AwardWallet\Engine\asiana\Credentials;

class Credentials extends \TAccountCheckerExtended
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
            "Welcome to be a Asiana Club member.",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $result = [];

        if (preg_match('#Dear.(.*?)/(.*?), Welcome#i', $subject, $m)) {
            $result['Name'] = beautifulName(trim($m[2])) . ' ' . beautifulName(trim($m[1]));
            $result['Login'] = $this->http->FindSingleNode("//*[contains(text(), 'ID')]/ancestor::th[1]/following-sibling::td[3]");
        }

        return $result;
    }
}
