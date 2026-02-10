<?php

namespace AwardWallet\Engine\flysaa\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "saanoreply@flysaa.com"',
            'FROM "noreply@mailer.flysaa.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Activate your Voyager account and earn rewards now!') !== false) {
            // credentials-3.eml
            $result['Login'] = re('#Reference\s+number\s*:\s+AIR/RVP\.MEX\s+(\d+)#i', $text);
            $result['LastName'] = re('#Dear\s+M[rs]+\s+[A-Z]{2,3}\s+(.*)#i', $text);
        } elseif (preg_match('#Voyager Online Newsletter .* 2013#i', $subject)) {
            // credentials-1.eml
            $result['Login'] = re('#Voyager\s+No:\s+(\d+)#i', $text);
            $result['LastName'] = re('#Dear\s+M[rs]+\s+[A-Z]{2,3}\s+(.*)\s*,#i', $text);
        } elseif (preg_match('#Voyager Online Newsletter .* 2014#i', $subject)) {
            // credentials-2.eml
            $result['Login'] = re('#Membership\s+Number\s+(\d+)#i', $text);
            $result['LastName'] = re('#Dear\s+M[rs]+\s+[A-Z]{2,3}\s+(.*)#i', $text);
        }

        return $result;
    }
}
