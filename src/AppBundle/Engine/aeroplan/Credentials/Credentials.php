<?php

namespace AwardWallet\Engine\aeroplan\Credentials;

class Credentials extends \TAccountCheckerExtended
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
            "newsletter and mileage balance",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();

        $result['Login'] = preg_replace("#\s+#", '', $this->http->FindSingleNode("//*[contains(text(),'Aeroplan Number')]/../following-sibling::*[1]"));
        $result['FirstName'] = beautifulName(re("#Hi (.*?), discover all that Aeroplan#i", $text));

        return $result;
    }
}
