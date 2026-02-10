<?php

namespace AwardWallet\Engine\airfrance\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsSubject()
    {
        return [
            "#Flying Blue#i",
        ];
    }

    public function getCredentialsImapFrom()
    {
        return [
            "NoReply-FlyingBlue@airfrance.fr",
            "donotreply@mail.af-klm.com",
            "news@flying-blue.com",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Name",
            "Email",
            "Login",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();
        $result['Email'] = $parser->getCleanTo();

        if (stripos($subject, 'Welcome to Flying Blue') !== false) {
            $result['Name'] = re('#Dear M[rs]+\. (.*?),#i', $text);
            $result['Login'] = re('#Your user\s*name\s*:\s+(\d+)#i', $text);
        } elseif (stripos($subject, 'welcome you to Flying Blue') !== false) {
            $x = '//img[contains(@src, "welcome-header-left.jpg")]/ancestor::td[1]/following-sibling::td[1]';

            if (preg_match('#M[RS]+ (.*) N° (\d+)#', $this->http->FindSingleNode($x), $m)) {
                $result['Name'] = $m[1];
                $result['Login'] = $m[2];
            }
        } elseif (stripos($subject, 'Your Flying Blue update on Miles, news and great offers') !== false) {
            $result['Name'] = re('#Dear M[rs]+ (.*?),#', $text);
            $result['Login'] = re('#Flying Blue number: (\d+)#i', $text);
        }

        return $result;
    }
}
