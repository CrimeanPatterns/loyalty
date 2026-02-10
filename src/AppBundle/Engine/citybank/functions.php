<?php

// this program is almost clone of searsclub

class TAccountCheckerCitybank extends TAccountChecker
{
    public const DEVICE_PRINT = 'version%3D2%26pm%5Ffpua%3Dmozilla%2F5%2E0%20%28macintosh%3B%20intel%20mac%20os%20x%2010%2E9%3B%20rv%3A27%2E0%29%20gecko%2F20100101%20firefox%2F27%2E0%7C5%2E0%20%28Macintosh%29%7CMacIntel%26pm%5Ffpsc%3D24%7C1280%7C800%7C726%26pm%5Ffpsw%3D%26pm%5Ffptz%3D6%26pm%5Ffpln%3Dlang%3Dru%7Csyslang%3D%7Cuserlang%3D%26pm%5Ffpjv%3D1%26pm%5Ffpco%3D1%26pm%5Ffpasw%3Dgoogletalkbrowserplugin%7Co1dbrowserplugin%7Cflash%20player%7Cquicktime%20plugin%7Cdefault%20browser%7Cjavaappletplugin%7Csharepointbrowserplugin%7Cskype%5Fc2c%5Fsafari%7Cflip4mac%20wmv%20plugin%26pm%5Ffpan%3DNetscape%26pm%5Ffpacn%3DMozilla%26pm%5Ffpol%3Dtrue%26pm%5Ffposp%3D%26pm%5Ffpup%3D%26pm%5Ffpsaw%3D1280%26pm%5Ffpspd%3D24%26pm%5Ffpsbd%3D%26pm%5Ffpsdx%3D%26pm%5Ffpsdy%3D%26pm%5Ffpslx%3D%26pm%5Ffpsly%3D%26pm%5Ffpsfse%3D%26pm%5Ffpsui%3D';

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $properties['Currency'] . "%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"             => "PostingDate",
            "Merchant"         => "Description",
            "Transaction Info" => "Info",
            "Transaction Data" => "Info",
            "Points"           => "Miles",
            "Amount"           => "Amount",
            "Currency"         => "Currency",
            "Transaction Type" => "Info",
            "Category"         => "Category",
            "Reference Number" => "Info",
            "Merchant Country" => "Info",
        ];
    }

    public function GetHiddenHistoryColumns()
    {
        return [
            'Transaction Data',
            'Reference Number',
            "Merchant Country",
            "Transaction Type",
        ];
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        $redirectURL = 'https://accountonline.citi.com/cards/svc/Login.do?siteId=CB&langId=EN';

        switch ($this->AccountFields['Login2']) {
            case 'Australia':
                $redirectURL = 'https://citibank.com.au/AUGCB/JSO/signon/DisplayUsernameSignon.do';

                break;

            case 'Brazil':
                $redirectURL = 'https://www.citibank.com.br/BRGCB/JPS/portal/Index.do';

                break;

            case 'India':
                $redirectURL = 'http://www.citibank.com/india';

                break;

            case 'Singapore':
                $redirectURL = 'https://www.citibank.com.sg/SGGCB/JSO/signon/DisplayUsernameSignon.do';

                break;

            case 'Thailand':
                $redirectURL = 'https://www.citibank.co.th/THGCB/JSO/signon/DisplayUsernameSignon.do?locale=en_TH';

                break;

            case 'Taiwan':
                $redirectURL = 'https://www.citibank.com.tw/TWGCB/JSO/signon/DisplayUsernameSignon.do?locale=en_TW';

                break;

            case 'Mexico':
                $redirectURL = 'https://bancanet.banamex.com/MXGCB/JPS/portal/LocaleSwitch.do?locale=en_MX';

                break;

            case 'Malaysia':
                $redirectURL = 'https://www.citibank.com.my/MYGCB/JSO/signon/DisplayUsernameSignon.do?';

                break;

            case 'HongKong':
                $redirectURL = 'https://www.citibank.com.hk/HKGCB/JSO/signon/DisplayUsernameSignon.do';

                break;
        }
        $arg["RedirectURL"] = $redirectURL;
    }

    public static function GetAccountChecker($accountInfo)
    {
        if (!in_array($accountInfo['Login2'], ['Brazil', 'India', 'Mexico', 'HongKong', 'Malaysia', 'Thailand', 'Taiwan', 'Australia'])) {
            require_once __DIR__ . "/TAccountCheckerCityBankSelenium.php";

            return new TAccountCheckerCityBankSelenium();
        }// if (!in_array($accountInfo['Login2'], ['Brazil', 'India']))
        else {
            return new TAccountCheckerCitybank();
        }
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields, $values);
        $fields['Login2']['Options'] = [
            ""          => "Select your country",
            //            'India'     => 'India',
            //            'Australia' => 'Australia',
            //            'Brazil'    => 'Brazil',
            //            'HongKong'  => 'Hong Kong',// 01 Feb 2023: We no longer support the Hong Kong region for Citibank program because the reward balance is not shown on the provider’s website.
            //            'Malaysia'  => 'Malaysia',// With effect from 1 November 2022, Citibank Berhad (“Citi”) has transferred ownership of its consumer banking business to United Overseas Bank (Malaysia
            'Mexico'    => 'Mexico',
            'Singapore' => 'Singapore',
            //            'Thailand'  => 'Thailand',// With effect from 1 November 2022 Citigroup Inc. has transferred ownership of its consumer banking business in Thailand to United Overseas Bank (Thai) PCL (registration number 0107535000176) and/or its related group entities ("UOB")
            //            'Taiwan'    => 'Taiwan',// In January 2022, Citibank Taiwan Limited ("Citi Taiwan") and DBS Bank (Taiwan) Ltd. ("DBS Taiwan") reached agreement on the acquisition of Citi’s consumer banking franchise in Taiwan. The transaction was completed on August 12, 2023 (Saturday) at 12 a.m. ("Effective Date").
            'USA'       => 'United States',
        ];
    }

    public function LoadLoginForm()
    {
        if ($this->AccountFields['Login2'] == 'Australia') {
            throw new CheckException("Sorry, the Australia region is no longer supported for technical reasons.", ACCOUNT_PROVIDER_ERROR);
        }

        if (in_array($this->AccountFields['Login2'], ['India', 'Australia', 'Brazil', 'Mexico'])) {
            return call_user_func([$this, "LoadLoginForm" . $this->AccountFields['Login2']]);
        }

        if ($this->AccountFields['Login2'] == 'HongKong') {
            $this->CheckError("We no longer support the Hong Kong region for Citibank program because the reward balance is not shown on the provider’s website.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->AccountFields['Login2'] == 'Malaysia') {
            throw new CheckException("With effect from 1 November 2022, Citibank Berhad (“Citi”) has transferred ownership of its consumer banking business to United Overseas Bank (Malaysia) Bhd (Registration No. 199301017069 (271809-K)) (“UOB”). From 16 July 2023 onwards, you will no longer be able to log in via Citibank.com.my or Citi Mobile® App.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->AccountFields['Login2'] == 'Taiwan') {
            throw new CheckException("In January 2022, Citibank Taiwan Limited (\"Citi Taiwan\") and DBS Bank (Taiwan) Ltd. (\"DBS Taiwan\") reached agreement on the acquisition of Citi’s consumer banking franchise in Taiwan. The transaction was completed on August 12, 2023 (Saturday) at 12 a.m. (\"Effective Date\"). At this time, your user ID and log-in password for Citibank Online Banking and the Citi Mobile app have expired.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->AccountFields['Login2'] == 'Thailand') {
            throw new CheckException('With effect from 1 November 2022 Citigroup Inc. has transferred ownership of its consumer banking business in Thailand to United Overseas Bank (Thai) PCL (registration number 0107535000176) and/or its related group entities ("UOB"). UOB is the issuer of "Citi" branded consumer banking products in Thailand and Citibank, N.A., Bangkok Branch is providing certain services in respect of those products. The trademarks "Citi", "Citibank", "Citigroup", the Arc design and all similar trademarks and derivations thereof are used temporarily under licence by UOB entities from Citigroup Inc.', ACCOUNT_PROVIDER_ERROR);
        }

        // loyalty gag
        return false;
        //		$this->http->FilterHTML = false;
        //		$this->http->removeCookies();
//        $this->http->setDefaultHeader("User-Agent", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:28.0) Gecko/20100101 Firefox/28.0");
        ////        $this->http->setCookie("fsr.a", time().date("B"), ".citibank.com");
        ////        $this->http->setCookie("testcookie", "testcookie", "");
        ////        $this->http->setCookie("style", "null", "online.citibank.com");
        ////        $this->http->setCookie("s_pers", "%20gpv_p7%3DNon%2520Cookied%2520Username%2520Password%7C1396591363903%3B%20s_vnum%3D1398880800904%2526vn%253D1%7C1398880800904%3B%20s_invisit%3Dtrue%7C1396591363904%3B%20s_nr%3D1396589563906-New%7C1554269563906%3B", ".citibank.com", "/");
        ////        $this->http->setCookie("s_sess", "%20s_cc%3Dtrue%3B%20SC_LINKS%3D%3B%20s_sq%3D%3B", ".citibank.com");
        ////        $this->http->setCookie("s_vi", "[CS]v1|299F21FE05313DD4-40000105C0009426[CE]", ".citibank.com");
        //		$this->http->GetURL("https://creditcards.citi.com/");
        //		if(!$this->http->ParseForm("logon"))
        //			return false;
        //		$this->http->SetInputValue('USERNAME', $this->AccountFields['Login']);
        //		$this->http->SetInputValue('PASSWORD', $this->AccountFields['Pass']);
        //		$this->http->SetInputValue('TakeMeTo', 'True');
        //		$this->http->SetInputValue('NEXT_SCREEN', '/AccountSummary');
        //		$this->http->SetInputValue('remember', 'on');
        //		$this->http->SetInputValue('devicePrint', self::DEVICE_PRINT);
        //		$this->http->SetInputValue('ioBlackBox', '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');
//        $this->http->Log("<pre>".var_export($this->http->Form, true)."</pre>", false);

        return true;
    }

    public function Login()
    {
        if (in_array($this->AccountFields['Login2'], ['India', 'Australia', 'Brazil', 'Mexico'])) {
            return call_user_func([$this, "Login" . $this->AccountFields['Login2']]);
        }

        return true;
    }

    public function LoadLoginFormIndia()
    {
        $this->logger->notice(__METHOD__);

        throw new CheckException("We currently do not support this region. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        /*$this->http->removeCookies();
        $this->http->GetURL('https://www.citibank.co.in/ibank/login/IQPin.jsp');
        $this->http->GetURL('https://www.citibank.co.in/ibank/login/IQPin1.jsp');
        if(!$this->http->ParseForm("frmLogin"))
            return false;
        $this->http->SetInputValue("User_Id", strtoupper($this->AccountFields['Login']));

        $pwd = str_pad($this->AccountFields['Pass'], 16);
//        $pwd = str_pad('ACCOUNT_PASSWORD', 16);
        $mod = $this->http->FindPreg('/var mod1 = "([^"]+)";/');
        $expo = $this->http->FindPreg('/var expo1 = "([^"]+)";/');
        // rsa.setPublic(mod1, expo1);
        $n = gmp_init($mod, 16);
        $e = gmp_init($expo, 16);
        // var res = rsa.encrypt(pwd);
        $m = $this->pkcs1pad2($pwd, ($this->bitLen($n) + 7) >> 3);
        $c = gmp_powm($m, $e, $n);
        $hash = gmp_strval($c, 16);

        $this->http->SetInputValue("password", $hash);
        $this->http->SetInputValue("PassWd", $hash);

        $this->http->SetInputValue("cPin_Id", 'H');
        $this->http->SetInputValue("Pin_Id", 'H');
        $this->http->SetInputValue("passpin", 'H');
        $this->http->SetInputValue("userflag", 'U'); // U or C

        return true;*/
    }

    /*function int2bin($num) {
        $result = '';
        do {
            $result .= chr(gmp_intval(gmp_mod($num, 256)));
            $num = gmp_div_q($num, 256);
        } while (gmp_cmp($num, 0));

        return $result;
    }

    function bitLen($num) {
        $tmp = $this->int2bin($num);
        $bit_len = strlen($tmp) * 8;
        $tmp = ord($tmp{strlen($tmp) - 1});
        if (!$tmp) {
            $bit_len -= 8;
        } else {
            while (!($tmp & 0x80)) {
                $bit_len--;
                $tmp <<= 1;
            }
        }

        return $bit_len;
    }

    function pkcs1pad2($data, $keysize) {
        $ba = array();
        $i = strlen($data) - 1;
        while ($i >= 0 && $keysize > 0) {
            $c = ord(substr($data, $i--, 1));
            if ($c < 128) { // encode using utf-8
                $ba[--$keysize] = $c;
            } else {
                if (($c > 127) && ($c < 2048)) {
                    $ba[--$keysize] = ($c & 63) | 128;
                    $ba[--$keysize] = ($c >> 6) | 192;
                } else {
                    $ba[--$keysize] = ($c & 63) | 128;
                    $ba[--$keysize] = (($c >> 6) & 63) | 128;
                    $ba[--$keysize] = ($c >> 12) | 224;
                }
            }
        }
        $ba[--$keysize] = 0;
        while ($keysize > 2) { // random non-zero pad
            $ba[--$keysize] = 1; // super secure random generator always give 1
        }
        $ba[--$keysize] = 2;
        $ba[--$keysize] = 0;
        $str = bin2hex(implode(array_map('chr', $ba)));

        return gmp_init($str, 16);
    }*/

    public function LoadLoginFormSingapore()
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification("citybank - refs #15306. Singapore was added");

        return false;
    }

    public function LoadLoginFormMexico()
    {
        $this->logger->notice(__METHOD__);
//        $this->sendNotification("citybank - refs #15039. Mexico was added");
        $this->http->GetURL('https://bancanet.banamex.com/MXGCB/JPS/portal/LocaleSwitch.do?locale=en_MX');

        if (!$this->http->ParseForm("preSignonForm")) {
            return false;
        }
        $this->http->FormURL = 'https://bancanet.banamex.com/MXGCB/apps/loginbnp/flow.action';
        $this->http->SetInputValue("username1", $this->AccountFields['Login']);
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("rsaDevicePrint", "version=3.4.1.0_1&pm_fpua=mozilla/5.0 (macintosh; intel mac os x 10_13_0) applewebkit/537.36 (khtml, like gecko) chrome/61.0.3163.100 safari/537.36|5.0 (Macintosh)|MacIntel&pm_fpsc=24|1440|900|832&pm_fpsw=&pm_fptz=5&pm_fpln=lang=en-US|syslang=|userlang=&pm_fpjv=0&pm_fpco=1&pm_fpasw=flash player&pm_fpan=Netscape&pm_fpacn=Mozilla&pm_fpol=true&pm_fposp=&pm_fpup=&pm_fpsaw=1440&pm_fpspd=24&pm_fpsbd=&pm_fpsdx=&pm_fpsdy=&pm_fpslx=&pm_fpsly=&pm_fpsfse=&pm_fpsui=&pm_os=Mac&pm_brmjv=61&pm_br=Chrome&pm_inpt=&pm_expt=");
        $this->http->SetInputValue("browserNameAndVersion", "Netscape 5.0 (Macintosh)");
        $this->http->SetInputValue("languageSelected", "en-US");
        $this->http->SetInputValue("ahnLabIndicatorFlag", "0");
        $this->http->SetInputValue("typeBN", "BNP");

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->http->ParseForm("preSignonForm2")) {
            return false;
        }
        $this->http->FormURL = 'https://bancanet.banamex.com/MXGCB/apps/loginbnp/flow.action';
        $this->http->SetInputValue("password1", $this->AccountFields['Pass']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("_eventId", "submit");
        $this->http->SetInputValue("browserNameAndVersion", "Netscape 5.0 (Macintosh)");
        $this->http->SetInputValue("languageSelected", "en-US");
        $this->http->SetInputValue("ahnLabIndicatorFlag", "0");
        $this->http->SetInputValue("typeBN", "BNP");
        $this->http->SetInputValue("dateLogin", time() . date("B"));
        $this->http->SetInputValue("windowId", time() . date("B"));

        return true;
    }

    public function LoadLoginFormAustralia()
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->GetURL('https://www.citibank.com.au/AUGCB/JSO/signon/DisplayUsernameSignon.do');
        $this->http->setCookie("sessionCheck", $this->http->FindPreg("/windowName\s*=\s*\'([^\']+)/ims"), "www.citibank.com.au");
        $this->http->setCookie("testcookie", "testcookie", "www.citibank.com.au");
        $this->http->setCookie("s_cc", "true", ".citibank.com.au");
        $this->http->setCookie("style", "null", "www.citibank.com.au");
        $this->http->setCookie("AdTrack", "pageHistory|Signon.713.200", "www.citibank.com.au");

        if (!$this->http->ParseForm("SignonForm")) {
            return false;
        }

        // secret keys
        $XXX_ExtraName = $this->http->FindPreg("/XXX_Extra\s*value=\'\s*\+\s*([^\+\s]+)/");
        $key = $this->http->FindPreg("/{$XXX_ExtraName}\s*=\s*([\d\w]+)\.substring\([\d\w]+\.length\s*-\s*[\d\w]+\);/ims");
        $lengthName = $this->http->FindPreg("/{$XXX_ExtraName}\s*=\s*[\d\w]+\.substring\([\d\w]+\.length\s*-\s*([\d\w]+)\);/ims");
        $length = $this->http->FindPreg("/{$lengthName}\s*=\s*\'([^\']+)/ims");
        $this->logger->debug("Key: $key | length: $length");
        $XXX_Extra = substr($key, -$length);
        $this->http->SetInputValue('XXX_Extra', $XXX_Extra);

        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->unsetInputValue('remember');

        return true;
    }

    public function LoadLoginFormBrazil()
    {
        $this->logger->notice(__METHOD__);

        $this->CheckError("It seems that Citibank was replaced by Itaú Unibanco in Brazil.", ACCOUNT_PROVIDER_ERROR);

        $this->http->removeCookies();
        $this->http->GetURL('https://www.citibank.com.br/BRGCB/JPS/portal/Index.do');
        $this->http->setCookie("sessionCheck", $this->http->FindPreg("/var\s*windowName\s*=\s*\'([^\']+)/ims"), "www.citibank.com.br");
        $this->http->setCookie("testcookie", "testcookie", "www.citibank.com.br");
        $this->http->setCookie("style", "null", "www.citibank.com.br");

        if (!$this->http->ParseForm("SignonForm")) {
            return false;
        }
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        return true;
    }

    public function LoginIndia()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->PostForm()) {
            return false;
        }
        $this->CheckError($this->http->FindSingleNode("//b[contains(text(), 'Sorry! Your login attempt has failed')]"));

        return true;
    }

    public function LoginAustralia()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->PostForm()) {
            return false;
        }
        // select User ID
        if ($this->http->FindPreg("/Please select one User ID \(that you remember the password for\)/ims")
            && $this->http->ParseForm("SignonForm")) {
            $this->http->SetInputValue("username", $this->AccountFields['Login']);
            $this->http->PostForm();

            if (!$this->http->ParseForm("SignonForm")) {
                return false;
            }
            $this->http->SetInputValue("username", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);

            if (!$this->http->PostForm()) {
                return false;
            }
        }
        // redirect
        if ($this->http->FindPreg("/\/AUGCB\/JPC\/interstitial\/RemindLater\.do/ims")) {
            $this->http->GetURL("https://www.citibank.com.au/AUGCB/JPC/interstitial/RemindLater.do");
        }

        $message = $this->http->FindSingleNode("//div[@class = 'appMMWon']");
        // A session is already active for this s userID.
        if (strstr($message, 'A session is already active for this s userID')) {
            $this->CheckError($message, ACCOUNT_PROVIDER_ERROR);
        } else {
            $this->CheckError($message);
        }
        // I'm sorry, your sign on attempt has failed.
        if ($message = $this->http->FindPreg('/(I\'m sorry, your sign on attempt has failed\.)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($JFP_TOKEN = $this->http->FindPreg("/var JFP_TOKEN = '([^\']+)';/")) {
            $this->http->setDefaultHeader("Accept", "application/json, text/javascript, */*; q=0.01");
            $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
            $this->http->GetURL("https://www.citibank.com.au/AUGCB/REST/welcome/welcomeMsgContent?JFP_TOKEN={$JFP_TOKEN}");
            $response = $this->http->JsonLog();

            if (isset($response->USERNAME)) {
                $this->SetProperty("Name", beautifulName($response->USERNAME));
            }
//            // get panel with links
//            $this->http->PostURL("https://www.citibank.com.au/AUGCB/CBOL/uti/quilnk/flow.action?screenID=Dashboard", array());
            // get Configuration (how it work on the site)
            $this->http->PostURL("https://www.citibank.com.au/AUGCB/REST/COACommon/getOverlayConfiguration", []);
            // get popup with rewards
            $this->http->setDefaultHeader("Accept", "Accept	*/*");
            $this->http->PostURL("https://www.citibank.com.au/AUGCB/ICARD/rewhom/displaySummary.do", []);

            return true;
        }

        return false;
    }

    public function LoginBrazil()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//a[@id = 'link_lkLogoffWithSummaryRecord']/@id")) {
            return true;
        }
        // Invalid credentials
        $this->CheckError($this->http->FindSingleNode("//p[contains(text(), 'Desculpe, mas não reconheço esta informação')]"));
        /*
         * Prezado cliente, desculpe mas este serviço não está disponível no momento.
         * Por favor contate o CitiPhone Banking.
         * Capitais e regiões metropolitanas : 4004 2484. Demais localidades : 0800-701-2484 - 0800-701-CITI
         */
        $this->CheckError($this->http->FindPreg("/Prezado cliente, desculpe mas este serviço não está disponível no momento. Por favor contate o CitiPhone Banking\./"), ACCOUNT_PROVIDER_ERROR);
        /*
         * Você já é cliente Itaú ;-)
         *
         * Precisa de informações sobre a sua conta corrente?
         *
         *  Acesse www.itau.com.br e faça o login com seus novos números de agência e conta, impressos no verso do seu cartão de débito Itaú. Para acessar, use a senha eletrônica de 6 dígitos, a mesma do CitiPhone Banking. Se você a esqueceu, poderá criar uma nova.
         *
         * ------------------------------------------------------------
         * Todos os seus cartões de crédito já foram migrados.
         * Agora, você pode consultar informações sobre eles nos canais de atendimento.
         *
         */
        if ($message = $this->http->FindPreg("/(Acesse www.itau.com.br e faça o login com seus novos números de agência e conta, impressos no verso do seu cartão de débito Itaú. Para acessar, use a senha eletrônica de 6 dígitos, a mesma do CitiPhone Banking. Se você a esqueceu, poderá criar uma nova.|Todos os seus cartões de crédito já foram migrados. Agora, você pode consultar informações sobre eles nos canais de atendimento\.)/")) {
            $this->CheckError("Você já é cliente Itaú ;-). " . $message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function LoginMexico()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindSingleNode("//a[@id = 'link_logout']/@href")) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[@id = 'errorMsg']")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Incorrect information, please verify.')
                || strstr($message, 'Please verify your user or password and try again.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'We are working on it and we will get it fixed as soon as we can. Please try again later: 001644.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'SEG*: APLICACION NO DISPONIBLE, INTENTE MAS TARDE.') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }// if ($message = $this->http->FindSingleNode("//div[@id = 'errorMsg']"))
        // Esta información no esta disponible en este momento
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Esta información no esta disponible en este momento')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Access blocked temporarily
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'Access blocked temporarily')
                or contains(text(), 'Too many failed attempts entering your customer ID or password.')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Sorry this information is not available at this time.
        if (($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry this information is not available at this time.')]"))
            && !strstr($this->http->currentUrl(), '_flowExecutionKey')) {
            $this->logoutMexico();

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        $this->logoutMexico();

        return false;
    }

    public function Parse()
    {
        if (in_array($this->AccountFields['Login2'], ['India', 'Australia', 'Brazil', 'Mexico'])) {
            call_user_func([$this, "Parse" . $this->AccountFields['Login2']]);
        }
    }

    public function ParseMexico()
    {
        $this->logger->notice(__METHOD__);
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(text(), 'Welcome')]", null, true, "/Welcome\s*([^<]+)/")));

        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
        ];
        $this->http->PostURL("https://bancanet.banamex.com/MXGCB/REST/accountsPanel/getCustomerAccounts.jws?ttc=742&JFP_TOKEN=TITNBAMP", [], $headers);
        $response = $this->http->JsonLog();

        if (isset($response->summaryViewBeanList)) {
            foreach ($response->summaryViewBeanList as $summary) {
                if (isset($summary->rewardsDetailViewObj->rewardsList)) {
                    foreach ($summary->rewardsDetailViewObj->rewardsList as $reward) {
                        foreach ($reward->rewardsAccountList as $rewardsAccount) {
                            $displayName = strip_tags($rewardsAccount->sponsorAccountList[0]->accountName);
                            $balance = $rewardsAccount->rewardsDetailsList[0]->value;

                            if (isset($balance)) {
                                $this->AddSubAccount([
                                    'Code'        => 'citybankMexico' . str_replace(' ', '', $displayName),
                                    'DisplayName' => $displayName,
                                    'Balance'     => $balance,
                                ]);
                            }
                        }// foreach ($reward->rewardsAccountList as $rewardsAccount)
                    }
                }// foreach ($summary->rewardsDetailViewObj->rewardsList as $reward)
            }
        }// foreach ($response->summaryViewBeanList as $summary)

        if (!empty($this->Properties['SubAccounts'])) {
            $this->SetBalanceNA();
        }

        $this->logoutMexico();
    }

    public function logoutMexico()
    {
        // logout
        $this->http->GetURL("https://bancanet.banamex.com/MXGCB/apps/logout/flow.action?logOutType=manual&source=singleTab");
    }

    public function ParseIndia()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.citibank.co.in/IbsJsps/newpersonalise.jsp");

        if ($this->http->ParseForm("dlfnvform")) {
            $this->http->Form['url'] = '/servlet/AccountSummaryServlet';
            $this->http->PostForm();
        }

        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'cardinfotxtlg']")));
        $this->SetProperty("Number", $this->http->FindPreg("/XXXXXXXXXXXX(\d{4})/ims"));
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Reward Points Balance')]/following-sibling::span[@class = 'applabelFalt']", null, false, "/[\d\,\.]+/ims"));
    }

    public function ParseAustralia()
    {
        $this->logger->notice(__METHOD__);
        $cards = $this->http->XPath->query("//div[@class = 'cA-rewHom-displaySummaryCard']");
        $this->logger->debug("Total {$cards->length} cards were found");
        $detectedCards = $subAccounts = [];

        for ($i = 0; $i < $cards->length; $i++) {
            $card = $cards->item($i);
            $code = $this->http->FindSingleNode("div[1]", $card, true, "/XXXXXXXXXXXX(\d{4})/ims");
            $displayName = $this->http->FindSingleNode("div[1]", $card);
            $balance = $this->http->FindSingleNode("div[2]", $card);

            if (!empty($displayName) && !empty($code)) {
                if (isset($balance)) {
                    $cardDescription = C_CARD_DESC_ACTIVE;
                } else {
                    $cardDescription = C_CARD_DESC_DO_NOT_EARN;
                }
                $detectedCards[] = [
                    "Code"            => 'citybankAustralia' . $code,
                    "DisplayName"     => $displayName,
                    "CardDescription" => $cardDescription,
                ];
                $subAccount = [
                    'Code'        => 'citybankAustralia' . $code,
                    'DisplayName' => $displayName,
                    'Balance'     => $balance,
                    "Number"      => $code,
                ];
                $subAccounts[] = $subAccount;
            }// if (!empty($displayName) && !empty($code))
        }// for ($i = 0; $i < $cards->length; $i++)
        // detected cards
        if (!empty($detectedCards)) {
            $this->SetProperty("DetectedCards", $detectedCards);
        }

        if (!empty($subAccounts)) {
            // Set Sub Accounts
            $this->logger->debug("Total subAccounts: " . count($subAccounts));
            // SetBalance n\a
            $this->SetBalanceNA();
            // Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if(isset($subAccounts))
        /*
         * Credit Card Rewards
         *
         * We are unable to process your request at the moment.
         * If you continue to encounter this problem,
         * please call our 24-Hour Citiphone Banking at 13 24 84 or at +61 2 8225 0615
         * if you are calling from overseas.
         */
        elseif (($this->http->FindSingleNode("//div[contains(text(), 'We are unable to process your request at the moment.')]")
                /*
                 * Credit Card Rewards
                 *
                 * To access Cards Services, you need to have an eligible product.
                 */
                || $this->http->FindSingleNode("//div[contains(text(), 'To access Cards Services, you need to have an eligible product.')]")
                /*
                 * Credit Card Rewards
                 *
                 * We apologise, an error has occurred whilst processing your request. For assistance, please contact us on 1800 801 732.
                 */
                || $this->http->FindSingleNode("//div[contains(text(), 'We apologise, an error has occurred whilst processing your request.')]"))
                && !empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        // click "Sign Out"
        $this->logger->notice('click "Sign Out"');
        $this->http->GetURL("https://www.citibank.com.au/AUGCB/JSO/signoff/SummaryRecord.do?logOff=true");
    }

    public function ParseBrazil()
    {
        $this->logger->notice(__METHOD__);
        // get all card details
//        $this->http->PostURL("https://www.citibank.com.br/BRGCB/REST/accountsPanel/getCustomerAccounts.jws?ttc=742", array());

        $JFP_TOKEN = $this->http->FindPreg("/var\s*JFP_CSRF_TOKEN\s*=\s*\'([^\']+)/");
        // Cartões
        if ($link = $this->http->FindSingleNode("//a[@id = 'link_liCartoesCreditoCBOL']/@href", null, true, "/\'([^\']+)\'\)/")) {
            https://www.citibank.com.br/BRGCB/SC.do?FS-ID=ICARD&FS-FUNC=ICARD
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);

            if ($this->http->ParseForm("ICARD")) {
                $this->http->PostForm();
                // Programa de Recompensa
                $this->http->GetURL("https://cardsonline.citibank.com.br/BRGCB/ICARD/rewreg/getRegistration.do");

                if (!$this->http->FindSingleNode("//td[contains(text(), 'Pontos disponíveis:')]/following-sibling::td[1]")) {
                    $this->http->Log("Try load another card");
                    $card = $this->http->FindSingleNode("//select[@name = 'ActiveIndex']//option[not(@selected)]/@value");

                    if ($card && $this->http->ParseForm("ICardCommon/ICARD/icardRewardRegistrationContext")) {
                        $this->http->SetInputValue("ActiveIndex", $card);
                        $this->http->PostForm();
                    }// $card && $this->http->ParseForm("ICardCommon/ICARD/icardRewardRegistrationContext"))
                }// if (!$this->http->FindSingleNode("//td[contains(text(), 'Pontos disponíveis:')]/following-sibling::td[1]"))
                // Balance - Pontos disponíveis
                $this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'Pontos disponíveis:')]/following-sibling::td[1]"));
                // Name
                $this->SetProperty("Name", $this->http->FindSingleNode("//td[@class = 'cardinfobgLine']//div[@class = 'cardinfotxtlg']"));

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    // Este cartão não participa nos pontos e no inquérito do bônus.
                    if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Este cartão não participa nos pontos e no inquérito do bônus.')]")) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }
                    // Maintenance
                    if ($this->http->FindPreg("/O Internet Banking est.+temporariamente indispon.+vel\. Por favor tente mais tarde\./")) {
                        throw new CheckException("O Internet Banking está temporariamente indisponível. Por favor tente mais tarde.", ACCOUNT_PROVIDER_ERROR);
                    }

                    // AccountID: 2511735
                    if ($JFP_TOKEN) {
                        $this->http->PostURL("https://www.citibank.com.br/BRGCB/REST/accountsPanel/getCustomerAccounts.jws?ttc=742&JFP_TOKEN={$JFP_TOKEN}", []);
                        $response = $this->http->JsonLog(null, true, true);
                        $summaryViewBeanList = ArrayVal($response, 'summaryViewBeanList', []);

                        foreach ($summaryViewBeanList as $viewBeanList) {
                            $rewardsList = ArrayVal($viewBeanList['rewardsDetailViewObj'], 'rewardsList', []);

                            foreach ($rewardsList as $list) {
                                $rewardsAccountList = ArrayVal($list, 'rewardsAccountList', []);

                                foreach ($rewardsAccountList as $accountList) {
                                    $rewardsDetailsList = ArrayVal($accountList, 'rewardsDetailsList', []);
                                    $balance = ArrayVal($rewardsDetailsList[0], 'value');
                                    $sponsorAccountList = ArrayVal($accountList, 'sponsorAccountList', []);
                                    $displayName = ArrayVal($sponsorAccountList[0], 'accountName', []);

                                    $code = $this->http->FindPreg("/([\dX]+)\s*$/ims", false, $displayName);

                                    if (!empty($displayName) && !empty($code)) {
                                        $subAccount = [
                                            'Code'        => 'citybankBrazil' . $code,
                                            'DisplayName' => $displayName,
                                            'Balance'     => $balance,
                                            "Number"      => $code,
                                        ];
                                        $subAccounts[] = $subAccount;
                                    }// if (!empty($displayName) && !empty($code))
                                }// foreach ($rewardsAccountList as $accountList)
                            }// foreach ($rewardsList as $list)

                            // Seu cartão não está habilitado a acessar esta função (AccountID: 4077336)
                            if ($rewardsList === [] && $this->http->FindPreg("/\"rewardsDetailViewObj\":\{\"fekName\":null,\"fek\":null,\"eiName\":null,\"rewardsList\":\[\],\"rewardTotalBalViewBeanList\":null\}\}\]/")) {
                                $this->SetBalanceNA();
                            }
                        }// foreach ($summaryViewBeanList as $viewBeanList)

                        if (!empty($subAccounts)) {
                            // Set Sub Accounts
                            $this->http->Log("Total subAccounts: " . count($subAccounts));
                            // SetBalance n\a
                            $this->SetBalanceNA();
                            // Set SubAccounts Properties
                            $this->SetProperty("SubAccounts", $subAccounts);
                        }// if(isset($subAccounts))
                    }// if ($JFP_TOKEN)
                }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
            }// if ($this->http->ParseForm("ICARD"))
        }// if ($link = $this->http->FindSingleNode("//a[@id = 'link_liCartoesCreditoCBOL']/@href", null, true, "/\'([^\']+)\'\)/"))
        elseif ($JFP_TOKEN) {
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'userInfo-content']//span[@class = 'strong']")));

            $this->http->PostURL("https://www.citibank.com.br/BRGCB/REST/globalalerts/getGlobalAlerts.jws?JFP_TOKEN={$JFP_TOKEN}", []);

            if ($message = $this->http->FindPreg("/(Informamos\s*que o pagamento dos débitos relativos ao DETRAN-SP ficará indisponível de 16\/04 às 12h até o dia 17\s*\/04 às 12h\.)/")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // Info not loaded (AccountID: 3102956)
            if ($message = $this->http->FindPreg("/^(\{\"fekName\":null,\"fek\":null,\"eiName\":null,\"globalAlertsViewObj\":\{\"fekName\":null,\"fek\":null,\"eiName\":null,\"globalAlertDetailBean\":\[\]\}\})$/")) {
                $this->SetBalanceNA();
            }

            // AccountID: 2715406
//            if ($this->http->FindPreg("/(Informamos que o Citi não faz retirada de cartões de crédito em domicílio. Sua senha é confidencial, não a disponibilize a terceiros\.)/")
//                // AccountID: 2715406
//                || $this->http->FindPreg("/\{\"fekName\":null,\"fek\":null,\"eiName\":null,\"globalAlertsViewObj\":\{\"fekName\":null,\"fek\":null,\"eiName\":null,\"globalAlertDetailBean\":\[\]\}\}/")
//                // AccountID: 2715406
//                || $this->http->FindPreg("/Aproveite seu tempo<\/strong> aqui no Citibank Online e resolva sua vida financeira sem filas e sem espera\./")
//                // AccountID: 2715406
//                || $this->http->FindPreg("/<strong>Importante: <\/strong>Reforçamos que sua conta corrente, cartões de crédito, produtos e serviços continuam sendo administrados unicamente pelo Citi,\",\"linkID\"/"))
            // AccountID: 2715406
            if ($this->AccountFields['Login'] == 'gouriques') {
                $this->SetBalanceNA();
            }
        }// elseif ($JFP_TOKEN)
    }
}
