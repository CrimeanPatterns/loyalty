<?php

namespace AwardWallet\Engine\czech\Credentials;

class Credentials2 extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "okplus.app@csa.cz",
            "directmail@csa.cz",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Account Statement",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Email",
            "Login",
            "Name",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Email'] = $parser->getCleanTo();

        if (preg_match('#\n\s*(.*)\s+\|\s+\#(\d+)#i', $text, $m)) {
            $result['Name'] = trim($m[1]);
            $result['Login'] = trim($m[2]);
        }

        return $result;
    }
}
