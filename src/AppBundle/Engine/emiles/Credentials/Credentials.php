<?php

namespace AwardWallet\Engine\emiles\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "info@e-miles.com",
            "earn@earn-e-miles.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Welcome to e-Miles",
            "Monthly Activity Statement from e-Miles",
            "Your e-Miles offers are ready",
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
        $text = text(orval($this->http->Response['body'], $parser->getPlainBody()));
        $result['Login'] = $parser->getCleanTo();

        if (stripos($subject, 'Welcome to e-Miles') !== false) {
            // credentials-1.eml
            $result['Name'] = re('#Welcome to e-Miles\(R\) (.*?),#i', $text);
        } elseif (stripos($subject, 'Monthly Activity Statement from e-Miles') !== false) {
            // credentials-2.eml
            $result['Name'] = re('#Name: (.*)#i', $text);
        } elseif (stripos($subject, 'Your e-Miles offers are ready') !== false) {
            // credentials-3.eml
            $result['Name'] = re('#Hi (.*?),#i', $text);
        }

        return $result;
    }
}
