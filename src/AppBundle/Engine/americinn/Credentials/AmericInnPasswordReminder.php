<?php

namespace AwardWallet\Engine\americinn\Credentials;

class AmericInnPasswordReminder extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'password-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'info@email.americinn.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'AmericInn Password Reminder',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Password',
            'Email',
        ];
    }

    //	function getRetrieveFields() {
    //		return [
    //			...
    //		];
    //	}
//
    //	function RetrieveCredentials($data) {
    //		$this->http->GetURL(...);
    //		$res = $this->http->ParseForm(...);
    //		if (!$res) {
    //			$this->http->Log('Failed to parse form', LOG_LEVEL_ERROR);
    //			return false;
    //		}
//
    //		$this->http->SetInputValue(..., ...);
//
    //		$res = $this->http->PostForm();
    //		if (!$res) {
    //			$this->http->Log('Failed to post form', LOG_LEVEL_ERROR);
    //			return false;
    //		}
//
    //		if ($this->http->FindPreg(...))
    //			return true;
    //		else
    //			return false;
    //	}

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Login'] = $parser->getCleanTo();
        $result['Password'] = re('#password\s+is\s*:\s+(\S+)#i', $this->text());

        return $result;
    }
}
