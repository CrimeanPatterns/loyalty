<?php

namespace AwardWallet\Engine\aeromexico\Credentials;

class YourClubPremierPassword extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'password-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'noreply@clubpremier.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Your Club Premier password',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Password',
        ];
    }

    /* function getRetrieveFields() {
        return [
            'Login'
        ];
    }

    function RetrieveCredentials($data) {
        $this->http->GetURL('https://member.clubpremier.com/recuperar-password?lang=en');
        if (!$this->http->ParseForm(null, 1, false, "//form[contains(@class, "retrievePasswordForm")]"))
            return false;

        $this->http->SetInputValue('username', $data['Login']);
        $captchaURL = $this->http->FindPreg('#(https://www.google.com/recaptcha/api/noscript[^"]+)#ims');
        $captcha = $this->parseCaptcha($captchaURL);
        if ($captcha === false)
            return false;
        $this->http->SetInputValue('recaptcha_response_field', $captcha);

        $this->http->PostForm();
        if ($this->http->FindPreg('#A\s+confirmation\s+email\s+has\s+been\s+sent\s+to\s+your\s+email\s+address#i'))
            return true;
        else
            return false;
    } */

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Name'] = re('#Hi\s+(.*?)\s+Your\s+Club\s+Premier#i', $text);
        $result['Password'] = re('#Your\s+Club\s+Premier\s+password\s+is\s*:\s+(\S+)#i', $text);

        return $result;
    }

    /* function parseCaptcha($captchaURL)
    {
        if (!$captchaURL) return false;
        $http2 = clone $this->http;
        $http2->GetURL($captchaURL);
        $this->http->SetInputValue('recaptcha_challenge_field', $http2->FindSingleNode("//input[@id = 'recaptcha_challenge_field']/@value"));
        $file = $this->http->DownloadFile('https://www.google.com/recaptcha/api/'.$http2->FindSingleNode('//img/@src'), "jpeg");
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        try{
            $captcha = $recognizer->recognizeFile($file);
        } catch(\CaptchaException $e){
            $this->http->Log("exception: ".$e->getMessage());
            return false;
        }
        unlink($file);
        return $captcha;
    } */
}
