<?php

namespace AwardWallet\Engine\czech\Credentials;

class Pass extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "okplus.app@csa.cz",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "PIN request",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Password",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);

        if ($password = re('#The\s+PIN\s+for\s+your\s+OK\s+Plus\s+account\s+is\s*:\s+(\w+)#i', $text)) {
            $result['Password'] = $password;
        }

        return $result;
    }

    public function getRetrieveFields()
    {
        return [
            'Login',
            'Birthdate',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('https://secure.csa.cz/en/ok_plus/okp_forgotten_pin.htm');

        if (!$this->http->ParseForm('registrace')) {
            return false;
        }

        $this->http->SetInputValue('csano', $data['Login']);

        foreach (['day' => 'j', 'month' => 'n', 'year' => 'Y'] as $key => $code) {
            $value = date($code, $data['Birthdate']);
            $this->http->SetInputValue($key, $value);
        }
        $this->http->SetInputValue('btnsubmit', 'Confirm');

        $this->http->PostForm();

        if ($this->http->FindPreg('#Your\s+PIN\s+request\s+has\s+been\s+processed#i')) {
            return true;
        } else {
            return false;
        }
    }
}
