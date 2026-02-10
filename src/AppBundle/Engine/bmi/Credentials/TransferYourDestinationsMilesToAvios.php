<?php

namespace AwardWallet\Engine\bmi\Credentials;

class TransferYourDestinationsMilesToAvios extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-3.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'bmidiamondclub@flybmi-email.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Transfer your destinations miles to Avios',
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['FirstName'] = re('#Dear\s+(.*?)\s+As\s+part\s+of\s+the\s+bmi\s+integration#i', $text);

        return $result;
    }
}
