<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAveda extends TAccountChecker
{
    use ProxyList;

    public $regionOptions = [
        ""   => "Select your country",
        "CA" => "Canada",
        "UK" => "UK",
        "US" => "USA",
    ];
    private $domain = "com";

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams();

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        // crocked server workaround
        $this->http->SetProxy($this->proxyDOP());

        if ($this->AccountFields["Login2"] == "CA") {
            $this->domain = "ca";
        }

        if ($this->AccountFields["Login2"] == "UK") {
            $this->domain = "co.uk";
        }

        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.aveda.{$this->domain}/account/index.tmpl", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.aveda.{$this->domain}/account/index.tmpl");

        if (!$this->http->ParseForm("signin")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('EMAIL_ADDRESS', $this->AccountFields["Login"]);
        $this->http->SetInputValue('PASSWORD', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('Login2$smdSignIn', 'Sign In');

        $this->sendSensorData();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Server Application Unavailable
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Server Application Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('
                //ul[@id = "form--errors--signin"]//li[not(contains(@style, "display:none")) and (
                    contains(text(), "Hmmm, that email address didn’t work. Try again or check out as a guest.")
                    or contains(text(), "Hmmm, that password didn’t work. Try again or check out as a guest.")
                    or contains(text(), "We do not recognize your sign in information. Please try again. Please note the password field is case sensitive.")
                    or contains(text(), "We didn’t recognise your details - Please try again.")
                    or contains(text(), "Please enter an email address in the following format:")
            )]')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->parseQuestion()) {
            return false;
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//div[@class = "signin-block" and contains(text(), "A verification code was sent to the email address")]');

        if (!isset($question) || !$this->http->ParseForm("signin_verify")) {
            return false;
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->SetInputValue('VERIFY_CODE', $this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);
        $this->http->PostForm();

        if ($message = $this->http->FindSingleNode('//ul[@id = "form--errors--signin"]//li[@id = "missing_data.verify_code.signin_verify"]')) {
            if (strstr($message, 'Your time limit to enter the code expired.')) {
                $this->AskQuestion($this->Question, $message, 'Question');
            }

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $ppNumber = $this->http->FindSingleNode('//div[contains(@class, "pure-privilege-dashboard__item--account-number")]/text()[last()]');

        if (!$ppNumber) {
            $this->logger->error("Membership Card # not found");

            if ($this->http->FindSingleNode('//*[contains(@data-mh, "account-section")]//a[contains(text(), "Join Now")]')) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }

        $ppNumber = preg_replace("/^00(\d+)$/", "\$1", $ppNumber);
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $data = [
            "JSONRPC" => "[{\"method\":\"pure_privilege.getMemberInfo\",\"id\":6,\"params\":[{\"force\":1,\"pp_number\":{$ppNumber}}]}]",
        ];
        $this->http->PostURL("https://www.aveda.{$this->domain}/rpc/jsonrpc.tmpl?dbgmethod=pure_privilege.getMemberInfo", $data, $headers);
        $response = $this->http->JsonLog();

        if (!isset($response[0]->result->value)) {
            return;
        }
        $response = $this->http->JsonLog(null, 0);
        $info = $response[0]->result->value ?? null;
        // Name
        $this->SetProperty('Name', beautifulName($info->firstName . " " . $info->lastName));
        // Balance - Points
        $this->SetBalance($info->pointBalanceFormatted);
        // Status
        $this->SetProperty('Status', $info->currentTier);
        // ... Points away from ...
        $this->SetProperty('PointsToNextTier', $info->pointsToNextTierFormatted);
        // Member Since
        $this->SetProperty('MemberSince', $info->memberSince);
        // Membership Card
        $this->SetProperty('Number', $info->ppNumberFormatted);
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            "7a74G7m23Vrp0o5c9112511.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395095,7332237,1536,872,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.16634250383,802883666118,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,1000,630,0;0,-1,0,0,2788,776,0;0,-1,0,0,2704,692,0;0,-1,0,0,2348,1215,0;1,-1,0,0,2639,627,0;0,-1,0,0,5182,1603,0;0,-1,0,0,5098,1519,0;0,-1,0,0,4863,1284,0;0,-1,0,0,2026,973,0;1,-1,0,0,1680,627,0;0,-1,0,0,3960,776,0;0,-1,0,0,3876,692,0;0,-1,0,0,4399,1215,0;1,0,0,0,3811,627,0;0,-1,0,0,5182,1603,0;0,-1,0,0,5098,1519,0;0,-1,0,0,4863,1284,0;0,-1,0,0,2843,973,0;1,0,0,0,2497,627,0;0,-1,0,0,-1,1215,0;0,-1,0,0,-1,913,0;-1,2,-94,-102,0,0,0,0,1000,630,0;0,-1,0,0,2788,776,0;0,-1,0,0,2704,692,0;0,-1,0,0,2348,1215,0;1,-1,0,0,2639,627,0;0,-1,0,0,5182,1603,0;0,-1,0,0,5098,1519,0;0,-1,0,0,4863,1284,0;0,-1,0,0,2026,973,0;1,-1,0,0,1680,627,0;0,-1,0,0,3960,776,0;0,-1,0,0,3876,692,0;0,-1,0,0,4399,1215,0;1,0,0,0,3811,627,0;0,-1,0,0,5182,1603,0;0,-1,0,0,5098,1519,0;0,-1,0,0,4863,1284,0;0,-1,0,0,2843,973,0;1,0,0,0,2497,627,0;0,-1,0,0,-1,1215,0;0,-1,0,0,-1,913,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aveda.{$this->domain}/account/index.tmpl-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1605767332236,-999999,17178,0,0,2863,0,0,4,0,0,8435F6142D6C097AB8E8104049628C5E~-1~YAAQzx/JFw0WT911AQAASvww3wR5f6s5qECZq+52jkPybyFBPF36FJrKXrCl6XSDxZS/oJzUKYeLpIgGXqZZogHEZwjmgh9X+zz57kn3qqqUilfentuBhDWIYXF3AaRITLVdRzDtdMV+l3qjgH+NHdqbHzpxg6msj4PiVhhYsa1ZFXDmJ7XVvOtrGBvStTWlj4wwRmkjuqU4gxsezYjecJBV/xsxVvkfIjf8tn4VnYogBMBQ5vz/+PVZR15t7Jyae4Q1pZEZkflQy0cEGiZJ5DAUqnHvx0itGSnGhF1WQyT1hB73I3RbVWA=~-1~-1~-1,30091,-1,-1,30261693,PiZtE,103412,28-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,21996645-1,2,-94,-118,118775-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9112511.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395095,7332237,1536,872,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.235261973117,802883666118,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,1000,630,0;0,-1,0,0,2788,776,0;0,-1,0,0,2704,692,0;0,-1,0,0,2348,1215,0;1,-1,0,0,2639,627,0;0,-1,0,0,5182,1603,0;0,-1,0,0,5098,1519,0;0,-1,0,0,4863,1284,0;0,-1,0,0,2026,973,0;1,-1,0,0,1680,627,0;0,-1,0,0,3960,776,0;0,-1,0,0,3876,692,0;0,-1,0,0,4399,1215,0;1,0,0,0,3811,627,0;0,-1,0,0,5182,1603,0;0,-1,0,0,5098,1519,0;0,-1,0,0,4863,1284,0;0,-1,0,0,2843,973,0;1,0,0,0,2497,627,0;0,-1,0,0,-1,1215,0;0,-1,0,0,-1,913,0;-1,2,-94,-102,0,0,0,0,1000,630,0;0,-1,0,0,2788,776,0;0,-1,0,0,2704,692,0;0,-1,0,0,2348,1215,0;1,-1,0,0,2639,627,0;0,-1,0,0,5182,1603,0;0,-1,0,0,5098,1519,0;0,-1,0,0,4863,1284,0;0,-1,0,0,2026,973,0;1,-1,0,0,1680,627,0;0,-1,0,0,3960,776,0;0,-1,0,0,3876,692,0;0,-1,0,0,4399,1215,0;1,0,0,0,3811,627,0;0,-1,0,0,5182,1603,0;0,-1,0,0,5098,1519,0;0,-1,0,0,4863,1284,0;0,-1,0,0,2843,973,0;1,0,0,0,2497,627,0;0,-1,0,0,-1,1215,0;0,-1,0,0,-1,913,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aveda.{$this->domain}/account/index.tmpl-1,2,-94,-115,1,32,32,0,0,0,0,961,0,1605767332236,72,17178,0,0,2863,0,0,963,0,0,8435F6142D6C097AB8E8104049628C5E~0~YAAQzx/JF3oXT911AQAAcwMx3wQDi+x6c2YuM+kTYDmvUqm+rUemmO5lB6i3dde0CVGnycBx8ja8hyI2zMunBY7ygYigbEe20RLgOooS8GIpD3rN7kizCBIAvkDAKWtkUGt+Ap/ggiUhiPhOe6cRLVuANcOVEY+V+0dYdXFcuS0suEj8oSlT4OiqmRo8XyXCJqz6KoSA93lI30CrIGuYrwyaTbmVIeWxIFn4JnchL3/AdVw+L/FKBoH2VkhjjaTfvPMIawMmi5VeDj1XcoA1HxgBtvNgXi99MDaMvz9bTPZsXUWEFMNAC+aOGnJv6lBhNggi2ZWZKKfpVHdwI2KvmdzN3re2~-1~||1-iDChnHSTCT-1-10-1000-2||~-1,34584,712,-1455554790,30261693,PiZtE,62461,53-1,2,-94,-106,8,1-1,2,-94,-119,54,65,57,973,53,51,33,31,7,6,6,5,10,360,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.5c163a7ce5a7c,0.54d7c22df6414,0.709ce760a8413,0.9a9b6cdfc4413,0.9b0330198e00a,0.ef3770d0e9547,0.079a9e2e6dda9,0.7a4d91c941e4b,0.a809769666486,0.108730bc49bfb;1,0,1,4,0,1,3,0,1,2;0,1,3,13,3,5,6,1,0,5;8435F6142D6C097AB8E8104049628C5E,1605767332236,iDChnHSTCT,8435F6142D6C097AB8E8104049628C5E1605767332236iDChnHSTCT,1,1,0.5c163a7ce5a7c,8435F6142D6C097AB8E8104049628C5E1605767332236iDChnHSTCT10.5c163a7ce5a7c,170,214,28,4,216,160,177,219,193,238,174,201,207,183,104,135,43,170,161,75,255,180,219,141,102,148,182,207,10,144,99,92,581,0,1605767333197;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,21996645-1,2,-94,-118,158354-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0d65734a64c01ce05475c3345af06aef5246ddb8f0161ec4776e6f9c160dfd9b,,,,0-1,2,-94,-121,;5;8;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "application/json",
            "Origin"       => "https://www.aveda.com",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//div[@id = 'account-page__welcome']")) {
            return true;
        }

        return false;
    }
}
