<?php

namespace AwardWallet\Engine\agoda\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "no-reply@agoda.com"',
            'FROM "newsletter@reply.agoda-rewards.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#Hi\s+(.*?),\s+you\s+have#i', $text);
        $result['FirstName'] = re('#Hi\s+(.*?),#i', $parser->getSubject());

        return $result;
    }
}
