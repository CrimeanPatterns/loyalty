<?php

namespace AwardWallet\Engine\aeroplan\Credentials;

class Welcome extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'Aeroplan@ems2.aeroplan.com',
            'aeroplan@ems2.aeroplan.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to the club",
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
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();

        $result['Login'] = trim($this->http->FindSingleNode("//img[contains(@src,'1641226289.600059221.GIF')]/../../following-sibling::*[1]"));
        $result['FirstName'] = beautifulName($this->http->FindSingleNode("//img[contains(@src,'1641226289.600059221.GIF')]/../../following-sibling::*[2]"));

        return $result;
    }
}
