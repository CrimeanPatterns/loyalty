<?php

namespace AwardWallet\Engine\flyerbonus\Credentials;

class Login extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "DO_NOT_REPLY_FLYERBONUS@bangkokair.com",
            "do_not_reply_flyerbonus@bangkokair.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your FlyerBonus membership card",
        ];
    }

    public function getParsedFields()
    {
        return ["Login"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);

        if ($login = re('#Your\s+FlyerBonus\s+ID\s+is\s+([^\s]+)#i', $text)) {
            $result['Login'] = $login;
        }

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'Email',
            'FirstName',
            'LastName',
            'BirthDate',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('https://member.flyerbonus.com/FlyerBonus/home_acn_forgotten.aspx');

        if (!$this->http->ParseForm("aspnetForm")) {
            return false;
        }

        $birth = $data['BirthDate'];

        $this->http->SetInputValue('ctl00$contentPanel$txtFirstName', $data['FirstName']);
        $this->http->SetInputValue('ctl00$contentPanel$txtFamilyName', $data['LastName']);
        // todo: fix birthdate usage
        return false;
        $this->http->SetInputValue('ctl00$contentPanel$day_num', date('d', $birth));
        $this->http->SetInputValue('ctl00$contentPanel$month_num', date('M', $birth));
        $this->http->SetInputValue('ctl00$contentPanel$year_num', date('Y', $birth));
        $this->http->SetInputValue('ctl00$contentPanel$txtEmail', $data['Email']);

        $this->http->SetInputValue('ctl00$contentPanel$numDay', date('d', $birth));
        $this->http->SetInputValue('ctl00$contentPanel$numMonth', date('m', $birth));
        $this->http->SetInputValue('ctl00$contentPanel$numYear', date('Y', $birth));

        $this->http->SetInputValue('ctl00$contentPanel$btnSubmit.x', rand(1, 50));
        $this->http->SetInputValue('ctl00$contentPanel$btnSubmit.y', rand(1, 20));

        $this->http->PostForm();

        if ($this->http->FindPreg('#Your\s+Membership\s+number\s+is\s+sent#i')) {
            return true;
        } else {
            if ($errMsg = $this->http->FindPreg('#Sorry\s+your\s+Membership\s+number\s+cannot\s+be\s+retrieved#i')) {
                $this->http->Log('Provider error: ' . $errMsg);
            } else {
                $this->http->Log('Unknown error');
            }

            return false;
        }
    }
}
