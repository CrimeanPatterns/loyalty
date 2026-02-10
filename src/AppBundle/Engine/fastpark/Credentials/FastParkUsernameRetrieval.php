<?php

namespace AwardWallet\Engine\fastpark\Credentials;

class FastParkUsernameRetrieval extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'login-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'RfRteam@thefastpark.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Fast Park Username Retrieval',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Email',
        ];
    }

    public function getRetrieveFields()
    {
        return [
            'FirstName',
            'LastName',
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('http://www.thefastpark.com/accounts/loginassist/');
        $res = $this->http->ParseForm('lost_username');

        if (!$res) {
            $this->http->Log('Failed to parse form', LOG_LEVEL_ERROR);

            return false;
        }

        $this->http->SetInputValue('FirstName', $data['FirstName']);
        $this->http->SetInputValue('LastName', $data['LastName']);
        $this->http->SetInputValue('email', $data['Email']);

        $res = $this->http->PostForm();

        if (!$res) {
            $this->http->Log('Failed to post form', LOG_LEVEL_ERROR);

            return false;
        }

        $r = '#Your\s+username\s+has\s+been\s+emailed\s+to\s+you\.\s+You\s+should\s+receive\s+it\s+shortly\.#i';

        if ($this->http->FindPreg($r)) {
            return true;
        } else {
            return false;
        }
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['FirstName'] = re('#Hello\s+(.*?),#i', $text);
        $result['Login'] = re('#Username:\s+(\S+)#i', $text);

        return $result;
    }
}
