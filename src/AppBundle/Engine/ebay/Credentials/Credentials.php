<?php

namespace AwardWallet\Engine\ebay\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'SUBJECT "Your eBay Bucks statement is here" FROM "eBay@reply1.ebay.com"',
            'SUBJECT "your eBay Bucks balance and more" FROM "eBay@reply1.ebay.com"',
            'FROM "eBay@reply1.ebay.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);
        $result['Login'] = $parser->getCleanTo();

        if (stripos($subject, 'Your eBay Bucks statement is here') !== false) {
            // credentials-1.eml
            $result['Name'] = re('#\s*(.*)\s+eBay Member since#i', $text);
        } elseif ($firstName = re('#(.*), your eBay Bucks balance and more#i', $subject)) {
            // credentials-2.eml
            $result['FirstName'] = $firstName;
        } elseif ($name = re('#message to (.*?) \(#i', $parser->getPlainBody())) {
            // credentials-3.eml
            $result['Name'] = $name;
        } elseif ($name = re('#\s*(.*)\s+Rewards member since#i', $text)) {
            // credentials-4.eml
            $result['Name'] = $name;
        }

        return $result;
    }
}
