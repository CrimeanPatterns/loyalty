<?php

namespace AwardWallet\Engine\carlson\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'credentials-4.eml',
        'credentials-5.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            "clubcarlson@carlson.com",
            "carlsonhotels@email.carlsonhotels.com",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "#Increase your point balance#i",
            "#Tune in! Time to earn TRIPLE POINTS!#",
        ];
    }

    public function GetParsedFields()
    {
        return [
            "Name",
            "Login",
            "Email",
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        //		$subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        $result['Email'] = $parser->getCleanTo();

        $result['Name'] = re('#Dear (.*?),#i', $text) ? re('#Dear (.*?),#i', $text) : re('#Hello, ([^\s]+)#i', $text);
        $result['Login'] = re('#Your\s+member\s+number\s+is\s+([\w\-]+)#', $text);

        if (!$result['Login']) {
            $result['Login'] = re('#Account\s*:\s*([A-Z\d-]+)#', $text);
        }

        if (!$result['Login']) {
            $result['Login'] = $parser->getCleanTo();
        }

        return $result;
    }

    /*
        protected function parseCaptcha()
        {
            $file = $this->http->DownloadFile("https://www.clubcarlson.com/captcha/capjpg", "jpeg");
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
            try{
                $captcha = str_replace(' ', '', $recognizer->recognizeFile($file));
            } catch(\CaptchaException $e){
                $this->http->Log("exception: ".$e->getMessage());
                return false;
            }
            unlink($file);
            return $captcha;
        }

        function RetrievePassword($data)
        {
            $this->http->GetURL('https://www.clubcarlson.com/login/secure/loginemail.do');
            if (!$this->http->ParseForm("loginEmailForm"))
                return false;

            $this->http->SetInputValue("userId", $data["Email"]); // email
            $this->http->SetInputValue("captcha", $this->parseCaptcha()); // captcha

            $this->http->PostForm();
            if ($this->http->FindSingleNode("//*[contains(text(), 'We have sent an email message with instructions to create a new password')]"))
                return true;
            else
                return false;
        }

        function GetRetrievePasswordCriteria()
        {
            return [
                'SUBJECT "Your Username and Password Information" FROM "clubcarlson@carlson.com"',
            ];
        }

        function ParseRetrievePasswordEmail(\PlancakeEmailParser $parser)
        {
            $result = [];
            if ($pass = $this->http->FindPreg('#Your\s+Password\s+is:\s*([^\s]+)#i'))
                $result['Password'] = $pass;
            return $result;
        }*/
}
