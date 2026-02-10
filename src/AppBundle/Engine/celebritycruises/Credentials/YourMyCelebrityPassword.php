<?php

namespace AwardWallet\Engine\celebritycruises\Credentials;

class YourMyCelebrityPassword extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'login-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'web_master_cci@celebritycruises.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Your My Celebrity  Password',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Password',
            'FirstName',
            'LastName',
            'Email',
        ];
    }

    public function GetRetrieveFields()
    {
        return [
            'FirstName',
            'LastName',
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('https://secure.celebritycruises.com/forgotPassword?cancelPage=/login');

        if (!$this->http->ParseForm('forgot_password_form')) {
            return false;
        }

        $this->http->SetInputValue('firstName', $data['FirstName']);
        $this->http->SetInputValue('lastName', $data['LastName']);
        $this->http->SetInputValue('email', $data['Email']);

        $this->http->PostForm();

        if ($this->http->FindPreg('#We\s+sent\s+you\s+an\s+email\s+with\s+your\s+password#i')) {
            return true;
        } else {
            return false;
        }
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['FirstName'] = trim(re('#First\s+Name:\s+(.*)#', $text));
        $result['LastName'] = trim(re('#Last\s+Name:\s+(.*)#', $text));
        $result['Email'] = re('#Email\s+Address:\s+(\S+)#', $text);
        $result['Login'] = re('#Username:\s+(\S+)#', $text);
        $result['Password'] = trim(re('#Password:\s+(\S+)#', $text));

        return $result;
    }
}
