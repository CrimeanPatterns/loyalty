<?php

class TAccountCheckerCitirewards extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://www.citibank.com.au/AUGCB/JSO/signon/uname/HomePage.do');
        $result = $this->http->FindSingleNode("//div[@class = 'headerWelcome2']") !== null;

        if ($result) {
            $this->http->GetURL('https://www.citibank.com.au/AUGCB/ICARD/rewmil/getEnrollInfo.do?forwardName=search');
        }

        return $result;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.citibank.com.au/AUGCB/ICARD/rewmil/checkMilesEnrollment.do?itemCode=I0004000&accessCheck=Y");

        if (!$this->http->ParseForm("SignonForm")) {
            return false;
        }
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        $this->CheckError($this->http->FindSingleNode("//td[@class = 'appMMWon']"));
        $this->selectOTP();

        if ($this->parseQuestion()) {
            return false;
        }
        //# Your card is not allowed to access this function.
        if (!$this->http->FindSingleNode("//span[contains(text(), 'Reward Points Balance')]")) {
            $this->CheckError($this->http->FindPreg("/(Your card is not allowed to access this function\.)/ims"), ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }

    public function selectOTP()
    {
        if ($this->http->ParseForm("LoginForm") && $this->http->FindPreg("/I want OTP to be enabled/ims")) {
            $this->http->Log("selecting OTP");
            $this->http->Form["login"] = "CQ";
            $this->http->PostForm();
        }

        if ($this->http->ParseForm("SecureTxnCodeForm") && $this->http->FindPreg("/Resend OTP/ims")) {
            $this->http->Log("selecting OTP");
            $this->http->Form["secureTxnCode"] = "123";
            $this->http->Form["secureTxnFunction"] = "ResendCode";
            $this->http->PostForm();

            if ($this->http->ParseForm("SecureTxnCodeForm") && $this->http->FindPreg("/Resend OTP/ims")) {
                $this->http->Log("entering OTP");
                $this->AskQuestion("Please enter your One-Time PIN (OTP)");

                if (isset($this->Answers["Please enter your One-Time PIN (OTP)"])) {
                    $this->http->Form["secureTxnCode"] = $this->Answers["Please enter your One-Time PIN (OTP)"];
                } else {
                    $this->http->Log("OTP is not found");

                    return;
                }
                $this->http->Form["secureTxnFunction"] = "CodeEntry";
                $this->http->PostForm();
            }
        }
    }

    public function parseQuestion()
    {
        if ($this->http->ParseForm("ChallQuesForm")) {
            $question = $this->http->FindSingleNode("//form[@name = 'ChallQuesForm']//td[@class = 'applabelFalt']");

            if (isset($question)) {
                $this->AskQuestion($question);

                return true;
            }
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->http->Form["challengeAnswer"] = $this->Answers[$this->Question];

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->parseQuestion()) {
            return false;
        }
        $this->CheckError($this->http->FindPreg("/The answer you entered is not recognised/ims"), ACCOUNT_PROVIDER_ERROR);

        return true;
    }

    public function Parse()
    {
        $this->selectOTP();
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'cardinfotxtlg']")));
        $this->SetProperty("Number", $this->http->FindPreg("/XXXXXXXXXXXX(\d{4})/ims"));
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Reward Points Balance')]/following-sibling::span[@class = 'applabelFalt']", null, false, "/[\d\,\.]+/ims"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.citibank.com.au/AUGCB/JSO/signon/uname/HomePage.do';
        $arg['PreloadAsImages'] = true;

        return $arg;
    }
}
