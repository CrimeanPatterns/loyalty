<?php

namespace AwardWallet\Engine\aeroflot\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "bonus@aeroflot.ru"',
            'FROM "info@aeroflot.ru"',
            'FROM "webgreeting@aeroflot.ru"',
            'FROM "bonusnews@aeroflot.ru"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $result['Email'] = $parser->getCleanTo();
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Aeroflot Bonus Account Statement')) {
            // credentials-1.eml
            $result['Name'] = re('#M[RS]+\.\s+(.*)#i', $text);
        } elseif (stripos($subject, 'News and promotions') !== false) {
            // credentials-2.eml
            $result['Name'] = $this->http->FindSingleNode('//text()[contains(normalize-space(.), "Membership number:")]/ancestor::tr[1]/preceding-sibling::tr[1]/td[1]');
        } elseif (stripos($subject, 'Aeroflot Website Password Reminder') !== false) {
            // credentials-3.eml
            $result['Name'] = re('#Dear\s+(.*?)\s*,#i', $text);
            // NOTE: For old emails, now only password reset available
            $result['Password'] = re('#Your\s+password\s+is\s*:\s*(\S+)#i', $text);
        }
        //		if ($login = re('#Membership\s+Number\s*:\s*(\d+)#i', $text)) {
        //			// Aeroflot Bonus Account Statement email (credentials-1.eml)
        //			// News and promotions (credentials-2.eml)
        //			$result['Login2'] = $login;
        //		}
        return $result;
    }
}
