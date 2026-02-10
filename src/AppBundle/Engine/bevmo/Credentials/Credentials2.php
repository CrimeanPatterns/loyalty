<?php

namespace AwardWallet\Engine\bevmo\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "BevMo_Newsletter@shop.bevmo.com",
            "BevMo_newsletter@e.bevmo.com",
            "CustomerService@bevmo.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            " ",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Email",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $text = text($this->http->Response['body']);

        if ($name = re('#ClubBev!\s+Customer\s*:\s+(.*)\s+Account\s+\#\s*:#i', $text)) {
            $result['Name'] = $name;
        }

        if ($name = $this->http->FindSingleNode("//img[contains(@alt, 'Cusmomer:')]/following::text()[normalize-space(.)][1]")) {
            $result['Name'] = $name;
        }

        if ($name = trim(re("#([^\n]*?),\s*\n\s*There\s*was\s*recently#msi", $text))) {
            $result['Name'] = $name;
        }

        return $result;
    }
}
