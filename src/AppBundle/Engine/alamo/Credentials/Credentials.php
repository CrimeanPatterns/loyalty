<?php

namespace AwardWallet\Engine\alamo\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetRemindLoginFields()
    {
        return [
            'Email',
            'FirstName',
            'LastName',
        ];
    }

    public function RemindLogin($data)
    {
        $this->http->GetURL('https://www.alamo.com/en_US/modals/forgot-username.modal.html');

        $this->http->FormURL = 'https://www.alamo.com/en_US/apis/live/insider/forgotUsername.sfx.json';

        foreach (array_keys($this->http->Form) as $key) {
            unset($this->http->Form[$key]);
        }

        $this->http->SetInputValue('firstName', $data['FirstName']);
        $this->http->SetInputValue('lastName', $data['LastName']);
        $this->http->SetInputValue('additionalInformation', $data['Email']);

        $this->http->PostForm();

        $response = json_decode($this->http->Response['body'], true);

        if (isset($response['messageList'])) {
            foreach ($response['messageList'] as $m) {
                if (isset($m['message'])) {
                    $this->http->Log($m['message'], LOG_LEVEL_ERROR);
                }
            }

            return false;
        } else {
            return true;
        }
    }

    public function GetRemindLoginCriteria()
    {
        return [
            'SUBJECT "Important Information about your Alamo Insiders Membership" FROM "webmaster@goalamo.com"',
        ];
    }

    public function ParseRemindLoginEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);

        if ($login = re('#Your\s+Username\s+is\s*:\s+(\S+)#i', $text)) {
            $result['Login'] = $login;
        }

        return $result;
    }

    public function GetCredentialsCriteria()
    {
        return [
            'FROM "noreply@goalamo.com"',
            'SUBJECT "Quicksilver Enrollment Confirmation Email for" FROM "enrollal@goalamo.com"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $subject = $parser->getSubject();

        if (stripos($subject, 'Quicksilver Enrollment Confirmation Email for') !== false) {
            $result['FirstName'] = re('#(.*),\s+Hello!#i', $text);
            $regex = '#Email\s+for\s+(' . $result['FirstName'] . '\s+(.*))\s+-\s+\d+#i';

            if (preg_match($regex, $parser->getHeader('subject'), $m)) {
                $result['Name'] = $m[1];
                $result['LastName'] = $m[2];
            }
        }
        $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
