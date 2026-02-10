<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerCvs extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const START_URL = "https://www.cvs.com/account/login-responsive.jsp";
//    private const START_URL = 'https://www.cvs.com/account/login/?icid=cvsheader:signin&screenname=%2F';

    private $extracareKey;

    private $fromIsLoggedIn = false;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerCvsSelenium.php";

        return new TAccountCheckerCvsSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;

        if ($this->attempt == 1) {
            $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_USA));
        }

        if (isset($this->State['User-Agent']) && $this->attempt != 2) {
            $this->http->setUserAgent($this->State['User-Agent']);
        } else {
            $this->http->setRandomUserAgent();
            $this->State['User-Agent'] = $this->http->userAgent;
        }

        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.cvs.com/home.jsp?_requestid=2236", [], 20);
        $this->http->RetryCount = 0;

        if ($this->loginSuccessful()) {
            $this->fromIsLoggedIn = true;

            return true;
        }
        $this->delay();

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::START_URL);
        $this->http->RetryCount = 2;

        if (
            $this->http->FindSingleNode("//iframe[@id = 'main-iframe']/@id")
            || $this->http->FindSingleNode("//script[@id = '__NEXT_DATA__']")
        ) {
            $this->selenium();

            return true;
        }

        if (
            $this->http->FindSingleNode("//div[@id = 'distilIdentificationBlock']/@id")
        ) {
//            $this->selenium();
            $this->distil();
            $this->distil();
        } else {
            // it works
            $retry = 0;

            while (
                $this->http->FindSingleNode("//body[contains(text(), '404 - Not Found')]")
                && $retry < 3
            ) {
                sleep(3);
                $this->http->GetURL(self::START_URL);
                $retry++;
            }
        }

        if (
            $this->http->FindSingleNode("//div[@id = 'distilIdentificationBlock']/@id")
            || $this->http->FindSingleNode("//body[contains(text(), '404 - Not Found')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'Your traffic behavior has been determined cause harm to this website. There are a few reasons this might happen')]")
        ) {
            throw new CheckRetryNeededException();
        }

        if ($message = $this->http->FindPreg("/We're sorry, but CVS.com is temporarily unavailable./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindPreg("#/webcontent/ngx-twoStepLogin/main#")
//            || strstr($this->AccountFields['Pass'], '<') /*AccountID: 3640080*/
            || strstr($this->AccountFields['Pass'], '%') /*AccountID: 3571877*/
            || strstr($this->AccountFields['Pass'], '&') /*AccountID: 4905025*/
            || $this->http->currentUrl() == 'https://www.cvs.com/account/login/'
        ) {
            $this->selenium();

            return true;
            $this->delay();

            $data = [
                "emailAddress" => $this->AccountFields['Login'],
            ];
            $headers = [
                "Accept"       => "application/json, text/plain, */*",
                "content-type" => "application/json",
            ];
            $this->http->PostURL("https://www.cvs.com/api/retail/account/user/v1/profile/digital/extracare-info", json_encode($data), $headers);

            if ($this->http->FindSingleNode("//iframe[@id = 'main-iframe']/@id")) {
                throw new CheckRetryNeededException(3);
            }

            $response = $this->http->JsonLog();
            $isExisting = $response->response->details->isExisting ?? null;
            $statusDesc = $response->response->header->statusDesc ?? null;

            if (!$isExisting) {
                // Enter a valid email address
                if ($isExisting === false) {
                    throw new CheckException("Email not found. Check your spelling and try again, or create an account.", ACCOUNT_INVALID_PASSWORD);
                }

                if ($statusDesc === 'Please enter a valid Email Address in the format: email@address.com.') {
                    throw new CheckException($statusDesc, ACCOUNT_INVALID_PASSWORD);
                }

                return false;
            }

            $this->delay();

            $data = [
                "request" => [
                    "userName"   => $this->AccountFields['Login'],
                    "password"   => $this->AccountFields['Pass'],
                    "rememberMe" => "Y",
                ],
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.cvs.com/retail/ProfileService?version=1.0&serviceName=ProfileService&operationName=loginUser&deviceType=DESKTOP&apiKey=a2ff75c6-2da7-4299-929d-d670d827ab4a&apiSecret=a8df2d6e-b11c-4b73-8bd3-71afc2515dae&serviceCORS=False&appName=CVS_WEB&lineOfBusiness=RETAIL&deviceToken=BLNK&channelName=WEB", json_encode($data), $headers);
            $this->http->RetryCount = 1;

            if (
                $this->http->FindSingleNode('//p[contains(text(), "Your traffic behavior has been determined cause harm to this website.")]')
                && $this->http->Response['code'] == 456
//                && strstr($this->AccountFields['Pass'], '&') /*AccountID: 4905025*/
            ) {
                throw new CheckException("We can't complete your request right now due to technical issues", ACCOUNT_PROVIDER_ERROR);
            }
        } else {
            if (!$this->http->ParseForm("login_val")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue('/atg/userprofiling/ProfileFormHandler.value.login', $this->AccountFields['Login']);
            $this->http->SetInputValue('/atg/userprofiling/ProfileFormHandler.value.password', $this->AccountFields['Pass']);
            $this->http->SetInputValue('/atg/userprofiling/ProfileFormHandler.loginSuccessURL', '/home.jsp');
            $this->http->SetInputValue('remmeopt', 'true');
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We're improving the site to give you a better experience.
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "re improving the site to give you a better experience.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We are working to enhance your digital experience.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our apologies for the interruption.  We're enhancing our site experience to better serve you.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Our apologies for the interruption.  We\'re enhancing our site experience to better serve you.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're sorry, but CVS.com is temporarily unavailable
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We\'re sorry, but CVS.com is temporarily unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're sorry but we're undergoing maintenance.
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We\'re sorry but we\'re undergoing maintenance.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're sorry but the site is unavailable at the moment.
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We\'re sorry but the site is unavailable at the moment.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * ExtraBucks® Rewards are here and everybody wants in!
         * Please keep this page open and you'll be browsing our site shortly.
         */
        if ($message = $this->http->FindSingleNode("//img[contains(@alt, 'ExtraBucks® Rewards are here and everybody wants in!')]/@alt")) {
            throw new CheckException("ExtraBucks® Rewards is temporarily busy. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/
        //# The page you are trying to reach is unavailable
        if ($message = $this->http->FindSingleNode('//p[contains(text(),"The page you are trying to reach is unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(The page you are trying to reach is unavailable)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error (404 Not Found) has occured in response to this request
        if ($message = $this->http->FindPreg("/(An error \(404 Not Found\) has occured in response to this request)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error has occurred
        if ($message = $this->http->FindSingleNode('//p[contains(text(),"An error has occurred")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An error occurred while processing your request.
        if ($message = $this->http->FindPreg('/(An error occurred while processing your request\.)/')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The page you are trying to reach is unavailable
        if ($this->http->Response['code'] == 503) {
            throw new CheckException("The page you are trying to reach is unavailable. We apologize for any inconvenience this may have caused.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (
            // if there isn't any error message
            !$this->http->FindPreg("/statusCode/")
            && !$this->http->FindSingleNode('//div[contains(@class, "errorContainer")]')
            && !$this->http->FindSingleNode('//div[contains(@class, "error-container")]')
            && !$this->http->FindSingleNode('//h1[contains(text(), "It\'s time to reset your password.")]')
            && !$this->http->FindPreg('/<p>Enter your date of birth to access your account.<\/p>/')
            && !$this->http->FindPreg('/<h1><span>Verify your date<\/span><span>of birth</')
            // but the form wasn't posted successfully
            && !$this->http->PostForm()
            // and we are not on the broken account
            && $this->AccountFields['Login'] != 'travelnotion@gmail.com'
            // and we have no cookie from selenium
            && !$this->http->getCookieByName("AUTH_LOGIN")
        ) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            $this->markProxySuccessful();

            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[@id = 'formerrors']", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//td[@id = 'errorscontainer']/div/ol/li[1]", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "errorContainer") or contains(@class, "error-container")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Email not found.You have an ExtraCare card. To sign in, provide a little more information to set up your profile.Sign in with a different email address')) {
                $this->throwProfileUpdateMessageException();
            }

            if (strstr($message, 'We\'re sorry For security reasons, your account has been temporarily locked.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($message, 'Enter a valid password')
                || strstr($message, 'Invalid password')
            ) {
                throw new CheckException("Invalid password. Check your spelling and try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Enter a valid email address')
            ) {
                throw new CheckException("Couldn't sign In. Enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Account not foundRe-enter your email addressOr create an account')) {
                throw new CheckException("Account not found. Re-enter your email address.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'An unexpected error occured')
                || strstr($message, 'An unexpected error occurred')
            ) {
//                throw new CheckException("An unexpected error occurred. Please try again", ACCOUNT_PROVIDER_ERROR);
                throw new CheckRetryNeededException(2, 0, "An unexpected error occurred. Please try again");
            }

            if (
                strstr($message, 'We\'re sorryWe can\'t complete your request right now due to technical issues.Please try again')
                || strstr($message, 'We\'re sorryWe can\'t complete your request right know due to technical issuesPlease try again')
            ) {
                throw new CheckRetryNeededException(2, 10, "We're sorry. We can't complete your request right know due to technical issues. Please try again");
            }

            if (strstr($message, 'Couldn\'t sign InThere was a problem with the email format you entered. Enter a valid email address')) {
                throw new CheckException("Couldn't sign In. There was a problem with the email format you entered. Enter a valid email address", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Account not foundYour email address does not match our records. Make sure you\'re typing your email address correctly. Re-enter your email addressOr create an account')) {
                throw new CheckException("Account not found. Your email address does not match our records. Make sure you're typing your email address correctly.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "It\'s time to reset your password.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($message = $this->http->FindPreg("/Attach your ExtraCare card/ims")) {
            throw new CheckException("To view your ExtraCare rewards, print Extra Bucks and to take advantage of convenient new online features, you need to Attach an ExtraCare card.", ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * For continued access to your existing CVS photos, you must upgrade to a free CVS.com account.
         * This upgrade allows you to manage your family's prescriptions, earn ExtraBucks and shop for everyday essentials.
         */
        if ($message = $this->http->FindPreg("/For continued access to your existing CVS photos, you must upgrade to a free CVS.com account. This upgrade allows you to manage your family's prescriptions, earn ExtraBucks and shop for everyday essentials./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // provider bug fix
        $this->logger->debug(">" . $this->http->FindSingleNode('//a[@id = "signInOverlay"]') . "<");

        // new auth
        $response = $this->http->JsonLog();
        $statusCode = $response->response->status->statusCode ?? null;
        $statusDesc = $response->response->status->statusDesc ?? null;

        if ($statusDesc == 'Success') {
            return true;
        }

        if ($statusCode == '1003') {
            throw new CheckException("Invalid password. Check your spelling and try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($statusCode == '1013') {
            throw new CheckException("Forgot Password? Your account will be locked after the next invalid attempt. Would you like to reset the password instead? Enter a valid password", ACCOUNT_INVALID_PASSWORD);
        }

        if ($statusCode == '1002' && $statusDesc) {
            throw new CheckException($statusDesc, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $question =
            $this->http->FindPreg('/<p>(Enter your date of birth to access your account\.)<\/p>/')
            ?? $this->http->FindPreg('/<h1><span>(Verify your date)<\/span><span>of birth</')
        ;

        if (!isset($question)/* || !$this->http->ParseForm("cvs-form-0") @deprecated */) {
            return false;
        }

        if ($question == 'Verify your date') {
            $question = 'Enter your date of birth to access your account.';
        }

        $this->Question = $question . " (MM/DD/YYYY)";
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
//        $this->http->SetInputValue("dob", $this->Answers[$this->Question]);
        $this->http->GetURL("https://www.cvs.com/account?dob={$this->Answers[$this->Question]}");
//        $this->http->PostForm();

//        if ($error = $this->http->FindSingleNode('")]')) {
//            $this->AskQuestion($this->Question, $error, 'Question');
//
//            return false;
//        }

        if (
            strstr($this->http->currentUrl(), 'https://www.cvs.com/retail-easy-account/create-account?page=/account/rx/rx-acc-combined-signup.jsp')
            && $this->selenium()
        ) {
            $this->Login();
        }

        return true;
    }

    public function Parse()
    {
        $this->http->PostURL("https://www.cvs.com/RETAGPV2/CvsHeaderConfigServicesActor/V1/getHeader", '{"request":{"header":{"apiKey":"a2ff75c6-2da7-4299-929d-d670d827ab4a","appName":"CVS_WEB","channelName":"WEB","deviceToken":"d9708df38d23192e","deviceType":"DESKTOP","responseFormat":"JSON","securityType":"apiKey","source":"CVS_WEB","lineOfBusiness":"RETAIL","type":"rmcra_com_p"}},"getConfigInfo":"Y","pageName":"globalHeader"}');
        $response = $this->http->JsonLog(null, 3);
        // Name
        if (isset($response->response->header->userDetails)) {
            $userDetails = $response->response->header->userDetails;
            $name = beautifulName($userDetails->firstName . " " . $userDetails->lastName);
            $this->SetProperty("Name", $name);
        }// if (isset($response->response->header->userDetails))
        else {
            $this->logger->notice("Name not found");
        }
        // ExtraCare Card Number
        if (isset($response->response->header->extracareInfo->extracareCardNo)) {
            $this->SetProperty("ExtraCareNumber", $response->response->header->extracareInfo->extracareCardNo);
        } else {
            $this->logger->notice("ExtraCare Card Number not found");
        }

        if (!isset($response->response->header->extracareInfo->redisKey)) {
            if (isset($response->response->header->userDetails->ecTied) && $response->response->header->userDetails->ecTied === "N") {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }

        $this->extracareKey = $extracareCardNumber ?? $response->response->header->extracareInfo->redisKey;

        $data = [
            "cusInfReq" => [
                "extraCareCard" => $extracareCardNumber ?? $response->response->header->extracareInfo->redisKey,
                "cardType"      => $response->response->header->extracareInfo->cardType,
                "xtraCard"      => ["xtraCardNbr", "encodedXtraCardNbr", "totYtdSaveAmt"],
                "xtraCare"      => ["cpns", "pts", "pebAvailPool", "mfrCpnAvailPool"],
                "xtraCarePrefs" => ["beautyClub", "phr", "carePass"],
            ],
            "header"    => [
                "lineOfBusiness" => "RETAIL",
                "appName"        => "CVS_WEB",
                "apiKey"         => "a2ff75c6-2da7-4299-929d-d670d827ab4a",
                "channelName"    => "MOBILE",
                "deviceToken"    => "BLNK",
                "deviceType"     => "OTH_MOBILE",
                "responseFormat" => "JSON",
                "securityType"   => "apiKey",
                "source"         => "CVS_WEB",
                "type"           => "retlegpost",
            ],
        ];
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json",
            "grid"         => "64f8c504-1b32-4613-80da-6a261102232",
            "Referer"      => "https://www.cvs.com/extracare/home",
            "cat"          => "SavingsAndRewards",
            "msg_src_cd"   => "W",
            "src_loc_cd"   => "90046",
            "user_id"      => "CVS.COM",
        ];
        $this->http->PostURL("https://www.cvs.com/RETAGPV3/ExtraCare/V2/getCustomerProfile", json_encode($data), $headers);
        $response = $this->http->JsonLog(null, 2);
        // ExtraSavings, ExtraBucks Rewards
        $rewards =
            $response->CUST_INF_RESP->XTRACARE->CPNS->ROW
            ?? $response->cusInfResp->cpns
            ?? []
        ;
        $this->logger->debug("Total " . count($rewards) . " ExtraSavings, ExtraBucks Rewards were found");

        $debug = false;

        if ($debug) {
            $this->logger->debug(var_export($rewards, true), ["pre" => true]);
            $cert = [];
        }
        $oldRewards = false;
        $subAccounts = [];

        foreach ($rewards as $reward) {
            // Sequence No.
            $number = $reward->cpn_seq_nbr ?? $reward->cpnSeqNbr;
            $balance = $reward->max_redeem_amt ?? $reward->maxRedeemAmt ?? null;
            $expirationDate = $reward->expir_dt ?? $reward->expirDt;
            $displayName = $reward->cpn_dsc ?? $reward->webDsc ?? $reward->cpnRecptTxt ?? null;

            $type =
                $reward->cmpgn_type_cd
                ?? $reward->cmpgnTypeCd
            ;

            if ($type == 'E') {
                $type = 'ExtraBucksRewards';
            } else { // 'C'
                $type = 'ExtraSavings';
            }
            // skip old or nor viewable rewards
            if (
                strtotime($expirationDate) < time()
                || (isset($reward->viewableInd) && $reward->viewableInd == 'N')
                || (isset($reward->redeemableInd) && $reward->redeemableInd == 'N')
                || (isset($reward->viewable_ind) && $reward->viewable_ind == 'N')
                || (isset($reward->redeemable_ind) && $reward->redeemable_ind == 'N')
                || (isset($reward->loadable_ind) && $reward->loadable_ind == 'Y')
//                || ($reward->cmpgn_subtype_cd == 'M' && $reward->cmpgn_type_cd != 'E')
            ) {
                $this->logger->notice("Skip old/not viewable reward/certificate");
                $oldRewards = true;

                continue;
            }// if (strtotime($expirationDate) < time())

            if ($debug) {
                $cert[] = $reward;
            }

            if (isset($balance, $displayName)) {
                $subAccounts[] = [
                    'Code'           => 'cvs' . $type . $number,
                    'DisplayName'    => $displayName,
                    'Balance'        => $balance,
                    'ExpirationDate' => strtotime($expirationDate),
                    'BarCode'        => $barCode ?? '',
                    'BarCodeNumber'  => $barCode ?? '',
                    "BarCodeType"    => BAR_CODE_EAN_13,
                    'SequenceNumber' => $number,
                ];
            }// if (isset($balance))
        }// foreach ($rewards as $reward)

        usort($subAccounts, function ($a, $b) { return $a['ExpirationDate'] - $b['ExpirationDate']; });
        $http2 = clone $this->http;
        $i = 0;

        foreach ($subAccounts as $subAccount) {
            $this->logger->info('Reward #' . $subAccount['SequenceNumber'], ['Header' => 3]);
            // barcode  // refs #8508
            $barCode = null;

            if ($subAccount['SequenceNumber']/* && $reward->printable_ind == 'Y'*/) {
                $this->logger->debug("Click print link -> {$subAccount['SequenceNumber']}");
                $http2->RetryCount = 0;
                $http2->GetURL("https://www.cvs.com/extracare/fragments/ec_print_coupon.jsp?ec=" . $subAccount['SequenceNumber']);
                $http2->RetryCount = 2;
                $barCodeUrl = $http2->FindSingleNode('//img[contains(@src, "cvs_coupon_barcode")]/@src', null, false);

                if ($barCodeUrl !== null) {
                    $this->logger->debug("barCodeUrl -> $barCodeUrl");
                    $barCode = $this->recognizeBarcodeByUrl($http2, 'https://www.cvs.com' . $barCodeUrl);
                }
            }// if ($number && $reward->printable_ind)
            $subAccount['BarCode'] = $barCode ?? '';
            $this->AddSubAccount($subAccount, true);
            $i++;

            if ($i > 40) {
                break;
            }
        }// foreach ($subAccounts as $subAccount)

        if ($debug) {
            $this->logger->debug(var_export($cert, true), ["pre" => true]);
        }

        if (isset($this->Properties['SubAccounts'])) {
            $this->logger->debug("Total subAccounts: " . count($this->Properties['SubAccounts']));
            $this->SetProperty("CombineSubAccounts", false);
        }// if (isset($this->Properties['SubAccounts']))

        // Year-to-Date Savings
        if (isset($response->CUST_INF_RESP->xtra_card->TOT_YTD_SAVE_AMT)) {
            $this->SetProperty("YTDSavings", isset($response->CUST_INF_RESP->xtra_card->TOT_YTD_SAVE_AMT) ? '$' . $response->CUST_INF_RESP->xtra_card->TOT_YTD_SAVE_AMT : '');
        } else {
            $this->SetProperty("YTDSavings", isset($response->cusInfResp->xtraCard->totYtdSaveAmt) ? '$' . $response->cusInfResp->xtraCard->totYtdSaveAmt : '');
        }

        // refs #19769
        $cardNumber =
            $response->CUST_INF_RESP->xtra_card->XTRA_CARD_CIPHER_TXT
            ?? $response->cusInfResp->xtraCard->xtraCardCipherTxt
            ?? null
        ;

        if ($cardNumber) {
            $this->logger->info("Rewards tracker", ['Header' => 2]);
            $headers = [
                "Accept"       => "application/json",
                "Content-Type" => "application/json",
                "Origin"       => "https://www.cvs.com",
                "x-api-key"    => "oKBmLu7mTm2B4mnEgeCzVCe5KxJ5SF7y",
            ];

            $this->http->GetURL("https://api.cvshealth.com/retail/extracare/v1/summary?cardNumber={$cardNumber}&cardType=0006", $headers);
            $tracker = $this->http->JsonLog();

            // Balance - Available rewards
            $this->SetBalance($tracker->extraCareCardSummary->availableExtraBucks ?? null);
            // to next $1 reward
            $this->SetProperty("ToNextReward", isset($tracker->quarterlyExtraBucks->pointsToNextThreshold) ? "$" . $tracker->quarterlyExtraBucks->pointsToNextThreshold : null);
            // Total saved
            $this->SetProperty("TotalSaved", isset($tracker->extraCareCardSummary->totalLifetimeSaving) ? "$" . $tracker->extraCareCardSummary->totalLifetimeSaving : null);
            // $3 ExtraBucks® Rewards every time you spend $30 on beauty. -> To next $3 ExtraBucks Reward
            if (
                isset($tracker->beautyClub->enrolled)
                && $tracker->beautyClub->enrolled
            ) {
                $this->SetProperty("ToNextThreeReward", "$" . (
                        $tracker->beautyClub->earningsProgress[0]->pointsToNextThreshold
                        ?? $tracker->beautyClub->earningsProgress[1]->pointsToNextThreshold
                ));
            }
            // $5 ExtraBucks® Rewards for every 10 credits you earn. -> Credits to next ExtraBucks Reward
            if (
                isset($tracker->pharmacyHealthRewards->enrolled)
                && $tracker->pharmacyHealthRewards->enrolled
                && $tracker->pharmacyHealthRewards->webDescription == "$5 Pharmacy and Health ExtraBucks Rewards"
            ) {
                $this->SetProperty("CreditsNeeded", $tracker->pharmacyHealthRewards->pointsToNextThreshold);
            }
        }// if ($cardNumber)

        // Balance - You have ... offers waiting.
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($oldRewards || (!isset($response->CUST_INF_RESP->XTRACARE->CPNS) && isset($response->CUST_INF_RESP->XTRACARE->PREFS))) {
                $this->SetBalanceNA();
            }
            // Sorry, your Extracare Savings and Rewards information is temporarily unavailable. Please check back later.
            elseif (isset($response->response->statusCode, $response->response->statusDesc)
                && (($response->response->statusCode == '4' && $response->response->statusDesc == "Inv Card")
                    || ($response->response->statusCode == '9001' && ($response->response->statusDesc == "ESL GetCustomerProfile service - Bad Request: Connection Timeout")
                    || $response->response->statusDesc == "GetConfiguration service failed"
                    || $response->response->statusDesc == "ESL GetCustomerProfile service - Internal Server Error")
                    || ($response->response->statusCode == '5' && $response->response->statusDesc == "Hot Card")
                    || ($response->response->statusCode == '5' && $response->response->statusDesc == "HOT XC Card")
                    || ($response->response->statusCode == '1' && $response->response->statusDesc == "XML not allowed:1785477242")
                    || ($response->response->statusCode == '2' && $response->response->statusDesc == "ERROR querying")
                    || ($response->response->statusCode == '-1' && $response->response->statusDesc == "Could not process request: comm issue"))) {
                $this->SetWarning("Sorry, your Extracare Savings and Rewards information is temporarily unavailable. Please check back later.");
            }
            // We're having trouble displaying your deals. We're working on it, and your deals should be back soon.
            elseif (isset($response->response->header->statusCode, $response->response->header->statusDesc)
                && $response->response->header->statusCode == '9999'
                && $response->response->header->statusDesc == "Unknown Error - Please contact the system administrator.") {
                throw new CheckException("We're having trouble displaying your deals. We're working on it, and your deals should be back soon.", ACCOUNT_PROVIDER_ERROR);
            }
            // We're having trouble displaying your deals. We're working on it, and your deals should be back soon.
            elseif (isset($response->response->statusCode, $response->response->statusDesc)
                && $response->response->statusCode == '9989'
                && $response->response->statusDesc == "ESL GetCustomerProfile service - Backend Service Not Available.") {
                throw new CheckException("We're having trouble displaying your deals. We're working on it, and your deals should be back soon.", ACCOUNT_PROVIDER_ERROR);
            }
            // Sorry, your ExtraCare savings and rewards information is temporarily unavailable. Please check back later.
            elseif (isset($response->response->statusCode, $response->response->statusDesc)
                && $response->response->statusCode == '9999'
                && $response->response->statusDesc == "Message Not Processed:Internal Server Error") {
                throw new CheckException("Sorry, your ExtraCare savings and rewards information is temporarily unavailable. Please check back later.", ACCOUNT_PROVIDER_ERROR);
            } else {
                $headers = [
                    'Authorization' => 'Basic ZWJrVDZFSEdJV2M4S3F6Vzk5YmVLaEFRNjRRYWJCOHE6Y3NDdXdXcEtoc2hNc1pQVg==',
                    'origin'        => 'https://www.cvs.com',
                    'referer'       => 'https://www.cvs.com/',
                ];

                $data = [
                    'grant_type' => 'client_credentials',
                ];

                $this->http->PostURL('https://api.cvshealth.com/oauth2/v1/token', $data, $headers);

                $tokenResponse = $this->http->JsonLog();
                $token = $tokenResponse->access_token ?? null;

                $headers = [
                    'Accept'        => 'application/json',
                    'Access_token'  => 'Basic ZWJrVDZFSEdJV2M4S3F6Vzk5YmVLaEFRNjRRYWJCOHE6Y3NDdXdXcEtoc2hNc1pQVg==',
                    'Adrum'         => 'isAjax:true',
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                    'origin'        => 'https://www.cvs.com',
                    'referer'       => 'https://www.cvs.com/extracare/home/',
                ];

                $data = [
                    "ecInfo" => [
                        "extracareCard" => $this->extracareKey,
                        "cardType"      => "0004",
                    ],
                    "requestType" => "extracare",
                    "prefs"       => [
                        "heroCoupons",
                        "addressInfo",
                        "subscriptionInfo",
                        "profileInfo",
                        "ecRewardsStats",
                        "carepassRewards",
                        "pastPurchases",
                    ],
                ];

                $this->http->PostURL('https://www.cvs.com/api/retail/p13n/v1/user/info', json_encode($data), $headers);

                $userInfo = $this->http->JsonLog();
                $userBalance = $userInfo->getCustInfo->extraBuckRewardsSummary->availableExtraBucks ?? null;

                if (isset($userBalance)) {
                    $this->SetBalance($userBalance);
                }
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function recognizeBarcodeByUrl(HttpBrowser $http, $url, $expectedFormat = null)
    {
        $this->logger->notice(__METHOD__);
        $http->RetryCount = 1;
        $http->GetURL($url);
        $http->RetryCount = 2;

        if (200 != $http->Response['code']) {
            return null;
        }

        $imageContent = $http->Response['body'];

        if (strlen($imageContent) > 1024 * 1024 || strlen($imageContent) === 0) {
            $this->logger->info('Invalid image content');

            return null;
        }

        if (false == file_put_contents($filename = sprintf('/tmp/barcode-%s-%s-%s-double', getmygid(), microtime(true), md5($imageContent)), $imageContent)) {
            return null;
        }

        $imageContentDoubled = null;

        try {
            // doubled image is saved to the file here
            $this->imageDoubleSize($filename);
            $imageContentDoubled = file_get_contents($filename);
        } catch (Exception $e) {
            return null;
        } finally {
            unlink($filename);
        }

        $res = $this->recognizeBarcode($imageContentDoubled, $expectedFormat);

        if (!$res) {
            $this->logger->info('Barcode was not recognized');
        }

        return $res;
    }

    public function imageDoubleSize($filename)
    {
        $this->logger->notice(__METHOD__);
        $percent = 2.0;

        // header('Content-type: image/png');
        [$width, $height] = @getimagesize($filename);

        if (!$width || !$height) {
            return null;
        }
        $newwidth = $width * $percent;
        $newheight = $height * $percent;

        // Load
        $thumb = imagecreatetruecolor($newwidth, $newheight);
        $source = imagecreatefrompng($filename);

        // Resize
        imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        imagepng($thumb, $filename);
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            /*
            if (!isset($this->State["Resolution"]) || $this->attempt == 1) {
//                $resolutions = [
//                    [1440, 900],
//                    [2560, 1440],
//                ];
                $resolutions = [
                    [1152, 864],
                    [1280, 720],
                    [1280, 768],
                    [1280, 800],
                    [1360, 768],
                    [1920, 1080],
                ];
                $this->State["Resolution"] = $resolutions[array_rand($resolutions)];
            }

//            $selenium->setScreenResolution($this->State["Resolution"]);
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
            $selenium->setKeepProfile(true);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
//            $selenium->http->setRandomUserAgent(3, true, false);

//            $selenium->useFirefox();
//            $selenium->setKeepProfile(true);
            $selenium->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);
//            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            */

            $resolutions = [
                //                [1152, 864],
                //                [1280, 720],
                [1280, 768],
                //                [1280, 800],
                //                [1360, 768],
                //                [1366, 768],
                //                [1920, 1080],
            ];

            if (!isset($this->State['Resolution']) || $this->attempt > 1) {
                $this->logger->notice("set new resolution");
                $resolution = $resolutions[array_rand($resolutions)];
                $this->State['Resolution'] = $resolution;
            } else {
                $this->logger->notice("get resolution from State");
                $resolution = $this->State['Resolution'];
                $this->logger->notice("restored resolution: " . join('x', $resolution));
            }
            $selenium->setScreenResolution($resolution);

            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_59);
            $selenium->setProxyGoProxies();
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->setKeepProfile(true);
            $selenium->disableImages();

            try {
                $selenium->http->start();
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $retry = true;

                return false;
            }
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

//            $selenium->http->GetURL(self::START_URL);
            try {
                $selenium->http->GetURL("https://www.cvs.com/account/login?icid=cvsheader:signin&screenname=/");
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $selenium->waitForElement(WebDriverBy::xpath('
                //input[@id = "emailField"]
                | //p[contains(text(), "Your traffic behavior has been determined cause harm to this website.")]
                | //iframe[contains(@src, "_Incapsula_Resource") and @id = "main-iframe"]/@src
                | //p[contains(., "is not available to customers or patients who are located outside of the United States or U.S. territories.")]
            '), 10);
            // login
            $loginInput = $selenium->waitForElement(WebDriverBy::id('emailField'), 0);
            $contBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$contBtn) {
                if ($this->http->FindSingleNode('//iframe[contains(@src, "_Incapsula_Resource") and @id = "main-iframe"]/@src | //p[contains(., "is not available to customers or patients who are located outside of the United States or U.S. territories.")]')) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return false;
            }

            if ($rememberMe = $selenium->waitForElement(WebDriverBy::xpath('//label[contains(., "Remember me")]'), 0)) {
                $rememberMe->click();
            }
            $this->savePageToLogs($selenium);

            $mover = new MouseMover($selenium->driver);
            $mover->logger = $selenium->logger;
            $mover->duration = rand(300, 1000);
            $mover->steps = rand(10, 20);
            $mover->moveToElement($loginInput);
            $loginInput->click();
            $mover->click();
            $loginInput->clear();
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);

            $mover->moveToElement($contBtn);
//            $mover->click();
//            $loginInput->sendKeys($this->AccountFields['Login']);
            $contBtn->click();

            // wait for loading
            $loadingSuccess = $selenium->waitFor(function () use ($selenium) {
                return is_null($selenium->waitForElement(WebDriverBy::xpath('//cvs-loading-spinner'), 0));
            }, 20);
            $this->savePageToLogs($selenium);

            if (!$loadingSuccess) {
                return false;
            }

            // password
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('cvs-password-field-input'), 3);
            $signInBtn = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Sign in")] | //button[contains(text(), "Sign in")]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$passwordInput || !$signInBtn) {
                $selenium->waitForElement(WebDriverBy::xpath('
                    //div[contains(@class, "errorContainer")]
                    | //div[contains(@class, "error-container")]
                    | //h1[contains(text(), "It\'s time to reset your password.")]
                '), 0);
                $this->savePageToLogs($selenium);

                return false;
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $signInBtn->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "user-profile-aerclub-card")]
                | //div[@id = "scroll_messages" and normalize-space(text()) != ""]
                | //div[contains(@class, "errorContainer")]
                | //div[contains(@class, "error-container")]
                | //h1[contains(text(), "It\'s time to reset your password.")]
                | //p[contains(text(), "Enter your date of birth to access your account.")]
                | //h1/span[contains(text(), "Verify your date")]
            '), 10);
            // save page to logs
            $this->savePageToLogs($selenium);
            $this->logger->debug("find sq question");

            $question = $this->http->FindSingleNode('//p[contains(normalize-space(text()), "Enter your date of birth to access your account.")] | //h1/span[contains(text(), "Verify your date")]');

            if ($question == 'Verify your date') {
                $question = 'Enter your date of birth to access your account.';
            }

            if ($question && isset($this->Answers[$question . " (MM/DD/YYYY)"])) {
                $this->markProxySuccessful();
                $dob = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "dob" or @id = "cvs-form-0-input-dob"]'), 10);
                $this->savePageToLogs($selenium);
                $dob->sendKeys($this->Answers[$question . " (MM/DD/YYYY)"]);
                $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Confirm and sign in")]'), 0)->click();

                $res = $selenium->waitForElement(WebDriverBy::xpath('
                    //div[contains(@class, "user-profile-aerclub-card")]
                    | //div[@id = "scroll_messages" and normalize-space(text()) != ""]
                    | //div[contains(@class, "errorContainer")]
                    | //div[contains(@class, "error-container")]
                '), 10);

                $this->savePageToLogs($selenium);
                $this->logger->debug("check results");

                if ($res) {
                    $this->logger->debug("[Error]: '{$res->getText()}'");

                    if (
                        strstr($res->getText(), 'Enter a valid 8-digit date of birth')
                        || strstr($res->getText(), 'That date of birth does not match our records')
                        || strstr($res->getText(), 'Enter an 8-digit date of birth')
                    ) {
                        unset($this->Answers[$question . " (MM/DD/YYYY)"]);
                        $this->AskQuestion($question . " (MM/DD/YYYY)", $res->getText(), 'Question');
                        $result = false;

                        return false;
                    }
                }// if ($res)
            }

            $this->logger->debug("get cookies");

            try {
                $cookies = $selenium->driver->manage()->getCookies();
            } catch (UnexpectedAlertOpenException $e) {
                $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

                try {
                    $error = $selenium->driver->switchTo()->alert()->getText();
                    $this->logger->debug("alert -> {$error}");
                    $selenium->driver->switchTo()->alert()->accept();
                    $this->logger->debug("alert, accept");
                } catch (NoAlertOpenException $e) {
                    $this->logger->debug("no alert, skip");
                }
            }

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            try {
                $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
            } catch (UnknownServerException $e) {
                $this->logger->error("exception: " . $e->getMessage());
            }

            if (!$this->http->FindSingleNode('
                    //div[contains(@class, "errorContainer")]
                    | //div[contains(@class, "error-container")]
                    | //h1[contains(text(), "It\'s time to reset your password.")]
                    | //p[contains(text(), "Enter your date of birth to access your account.")]
                    | //h1/span[contains(text(), "Verify your date")]
                ')
                && !$this->http->FindPreg('/<p>Enter your date of birth to access your account.<\/p>/')
                && !$this->http->FindPreg('/<h1><span>Verify your date<\/span><span>of birth</')
            ) {
                $this->markProxySuccessful();
                $this->http->GetURL(self::START_URL);
            }

            $result = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            (
                $this->http->FindSingleNode("//a[contains(text(), 'Sign Out')]")
                || ($this->http->getCookieByName("AUTH_LOGIN") && $this->http->Response['code'] != 403)
            )
            && !stristr($this->http->currentUrl(), "https://www.cvs.com/retail-easy-account/create-account")
        ) {
            return true;
        }

        return false;
    }

    private function delay()
    {
        $delay = rand(3, 10);
        $this->logger->debug("Delay -> {$delay}");
        sleep($delay);
    }

    private function distil($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $distilLink = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"\d+;\s*url=([^\"]+)/ims");
        $this->logger->debug("[distil link]: {$distilLink}");

        if (isset($distilLink)) {
            sleep(2);
            $this->http->NormalizeURL($distilLink);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($distilLink);
            $this->parseGeetestCaptcha($retry);
            $this->http->RetryCount = 2;
        }// if (isset($distil))

        if (!$this->http->ParseForm('distilCaptchaForm')) {
            return false;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $captcha = $this->parseFunCaptcha($retry);
        $key = null;

        if ($captcha !== false) {
            $key = 'fc-token';
        } elseif (($captcha = $this->parseReCaptchaDistil($retry)) !== false) {
            $key = 'g-recaptcha-response';
        } else {
            return false;
        }
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->SetInputValue($key, $captcha);

        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"          => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "Accept-Language" => "en-US,en;q=0.5",
        ];
        $this->http->PostForm($headers);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        $this->getTime($startTimer);

        return true;
    }

    private function parseGeetestCaptcha($retry = false)
    {
        $this->logger->notice(__METHOD__);
        $this->geetestFailed = false;

        $gt = $this->http->FindPreg("/gt:\s*'(.+?)'/");
        $apiServer = $this->http->FindPreg("/api_server:\s*'(.+?)'/");
        $ticket = $this->http->FindSingleNode('//input[@name = "dCF_ticket"]/@value');

        if (!$gt || !$apiServer || !$ticket) {
            $this->logger->notice('Not a geetest captcha');

            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        /** @var HTTPBrowser $http2 */
        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $url = '/distil_r_captcha_challenge';
        $this->http->NormalizeURL($url);
        $http2->PostURL($url, []);
        $challenge = $http2->FindPreg('/^(.+?);/');

        if (!$challenge) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters, $retry);
        $response = $this->http->JsonLog($captcha, 3, true);

        if (empty($response)) {
            $this->geetestFailed = true;
            $this->logger->error("geetestFailed = true");

            return false;
        }

        $verifyUrl = $this->http->FindSingleNode('//form[@id = "distilCaptchaForm"]/@action');
        $this->http->NormalizeURL($verifyUrl);
        $payload = [
            'dCF_ticket'        => $ticket,
            'geetest_challenge' => $response['geetest_challenge'],
            'geetest_validate'  => $response['geetest_validate'],
            'geetest_seccode'   => $response['geetest_seccode'],
        ];
        $this->http->PostURL($verifyUrl, $payload);

        return true;
    }

    private function parseReCaptchaDistil($retry)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'distilCaptchaForm']//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    private function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $key = $this->http->FindSingleNode("//div[@id = 'funcaptcha']/@data-pkey");

        if (!$key) {
            $key = $this->http->FindPreg('/funcaptcha.com.+?pkey=([\w\-]+)/');
        }

        if (!$key) {
            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        $postData = array_merge(
            [
                "type"             => "FunCaptchaTask",
                "websiteURL"       => $this->http->currentUrl(),
                "websitePublicKey" => $key,
            ],
            $this->getCaptchaProxy()
        );
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeAntiCaptcha($recognizer, $postData, $retry);

        // // RUCAPTCHA version
        // $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        // $recognizer->RecognizeTimeout = 120;
        // $parameters = [
        //     "method" => 'funcaptcha',
        //     "pageurl" => $this->http->currentUrl(),
        //     "proxy" => $this->http->GetProxy(),
        // ];
        // $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);

        $this->getTime($startTimer);

        return $captcha;
    }
}
