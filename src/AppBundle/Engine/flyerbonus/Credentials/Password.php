<?php

namespace AwardWallet\Engine\flyerbonus\Credentials;

class Password extends \TAccountCheckerExtended
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
            "Your forgotten password request",
        ];
    }

    public function getParsedFields()
    {
        return ["Password"];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];

        if ($pass = $this->http->FindPreg('#Internet\s+Password\s*:\s*([^\s]+)#i')) {
            $result['Password'] = $pass;
        }

        return $result;
    }

    public function GetRetrieveFields()
    {
        return [
            'Login',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL('https://member.flyerbonus.com/FlyerBonus/home_pwd_forgotten.aspx');

        if (!$this->http->ParseForm("aspnetForm")) {
            return false;
        }

        $this->http->SetInputValue('ctl00$contentPanel$txtFlyerBonusId', $data["Login"]); // id
        $this->http->SetInputValue('ctl00$contentPanel$CaptchaControl1', $this->parseCaptcha()); // captcha

        $this->http->SetInputValue('ctl00$contentPanel$btnSubmit.x', rand(1, 50));
        $this->http->SetInputValue('ctl00$contentPanel$btnSubmit.y', rand(1, 20));

        $this->http->SetInputValue('ctl00$hdLanguage', 'English');

        $this->http->PostForm();

        if ($this->http->FindPreg("#Your\s+password\s+has\s+been\s+sent#i")) {
            return true;
        } else {
            return false;
        }
    }

    protected function parseCaptcha()
    {
        $src = $this->http->FindSingleNode("//*[@class='ssw-short-captcha']//img[1]/@src");

        $file = $this->http->DownloadFile("https://member.flyerbonus.com/FlyerBonus/$src", "jpeg");
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);

        try {
            $captcha = str_replace(' ', '', $recognizer->recognizeFile($file));
        } catch (\CaptchaException $e) {
            $this->http->Log("exception: " . $e->getMessage());

            return false;
        }
        unlink($file);

        return $captcha;
    }
}
