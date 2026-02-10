<?php

namespace AwardWallet\Engine\amazongift\Credentials;

class Credentials extends \TAccountChecker
{
    public function getCredentialsImapFrom()
    {
        return [
            "delivers@amazon.com",
            "account-update@amazon.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            '#.#',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        return [
            'Login' => $parser->getCleanTo(),
        ];
    }
}
