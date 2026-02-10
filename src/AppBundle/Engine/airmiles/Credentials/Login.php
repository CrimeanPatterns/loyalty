<?php

namespace AwardWallet\Engine\airmiles\Credentials;

class Login extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'auto-generated@avios.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Your Avios username reminder",
        ];
    }

    public function getParsedFields()
    {
        return [
            "Login",
            "Name",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Login'] = re('#Your\s+username\s+is\s*:\s+(\S+)#i', $text);
        $result['Name'] = beautifulName(trim(re('#Dear\s+([^,]+),#i', $text)));
        $result['Email'] = $parser->getCleanTo();

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
        $this->http->GetURL('https://www.avios.com/gb/en_gb/my-account/forgotten-my-username');

        if (!$this->http->ParseForm('forgottenUserNameRequest')) {
            return false;
        }

        $this->http->SetInputValue('email', $data['Email']);
        $captcha = $this->parseCaptcha('https://www.avios.com/captchaImage.png');

        if ($captcha === false) {
            $this->http->Log('Captcha recognition failed');

            return false;
        }
        $this->http->SetInputValue('imageCaptchaSecurityCode', $captcha);
        $this->http->SetInputValue('forgottenDetailsSecurityIdentifierType', 'EMAIL');
        $this->http->SetInputValue('membershipNumber', '');
        $this->http->SetInputValue('reset', 'Send my username');

        $this->http->PostForm();

        if ($this->http->FindPreg('#We\s+have\s+sent\s+an\s+email\s+to#i')) {
            return true;
        } else {
            if ($errMsg = $this->http->FindPreg('#Sorry\s+but\s+your\s+account\s+has\s+been\s+locked.\s+Please\s+try\s+again\s+in\s+30\s+minutes#i')) {
                $this->http->Log('Provider error: ' . $errMsg);
            } elseif ($errMsg = $this->http->FindPreg('#Sorry,\s+that\s+didn\'t\s+match\s+our\s+code#i')) {
                $this->http->Log('Incorrect captcha');
            } else {
                $this->http->Log('Unknown error');
            }

            return false;
        }
    }

    public function parseCaptcha($captchaURL)
    {
        if (!$captchaURL) {
            return false;
        }
        $file = $this->http->DownloadFile($captchaURL, 'png');
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);

        try {
            $captcha = $recognizer->recognizeFile($file);
        } catch (\CaptchaException $e) {
            $this->http->Log("exception: " . $e->getMessage());
            // Notifications
            if (strstr($e->getMessage(), "ERROR_ZERO_BALANCE")) {
                $this->sendNotification("WARNING! " . $recognizer->domain . " - balance is null");
            }

            return false;
        }
        unlink($file);

        return $captcha;
    }
}
