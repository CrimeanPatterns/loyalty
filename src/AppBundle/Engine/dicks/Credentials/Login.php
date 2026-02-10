<?php

namespace AwardWallet\Engine\dicks\Credentials;

class Login extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'DSG@em.dickssportinggoods.com',
            'dsg@em.dickssportinggoods.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your ScoreCard Account User Name",
        ];
    }

    public function getParsedFields()
    {
        return ["Password"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);

        if ($login = re('#Your\s+user\s+name\s+is\s*:\s+(\S+)#i', $text)) {
            $result['Login'] = $login;
        }

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('https://www.mydickssportinggoods.com/forgot.aspx');

        if (!$this->http->ParseForm('aspnetForm')) {
            return false;
        }

        $this->http->SetInputValue('ctl00$cph_content_main$txtUsernameEmail', $data['Email']);

        $this->http->SetInputValue('ctl00$cph_content_main$btnSendUsername', 'Send User Name »');

        $this->http->PostForm();

        if ($this->http->FindPreg('#We\s+sent\s+your\s+user\s+name\s+to#i')
                or $this->http->FindPreg('#Your\s+send\s+user\s+name\s+request\s+has\s+already\s+been\s+submitted#i')) {
            return true;
        } else {
            return false;
        }
    }
}
