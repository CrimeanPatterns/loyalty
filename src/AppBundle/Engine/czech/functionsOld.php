<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerCzech extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://okplus.csa.cz/en/my-account';
    protected $endHistory = false;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL);
        $this->http->RetryCount = 1;

        // retries
        if ($this->http->Response['code'] == 0) {
            throw new CheckRetryNeededException(3, 10);
        }

        if (!$this->http->ParseForm("okplus_profile_login_form")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("okplus_profile_login_id", $this->AccountFields['Login']);
        $this->http->SetInputValue("okplus_profile_login_pwd", $this->AccountFields['Pass']);
        $this->http->SetInputValue("okplus_profile_login", "Log in");

        $captcha = $this->parseCaptcha();

        if ($captcha === null) {
            return false;
        }

        $this->http->SetInputValue("g-recaptcha-response", $captcha);

        return true;
    }

    public function checkErrors()
    {
        // Profile setup
        if ($this->http->FindPreg('#/ok_plus/okp_my_account/okp_my_profilee/okp_my_profile\.htm#', false, $this->http->currentUrl())
            && $this->http->FindSingleNode("//input[@id = 'agreeCheckbox']/@name")) {
            $this->throwAcceptTermsMessageException();
        }

        // OK Plus web side is currently out of order due to maintenance reason.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'OK Plus web side is currently out of order due to maintenance reason.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")
            && $this->http->FindPreg("/The server returned an invalid or incomplete response\./")) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }

        if ($this->http->currentUrl() == 'https://www.csa.cz/cz-cs/vernostni-programy/') {
            $this->http->GetURL("https://www.csa.cz/cz-en/frequent-flyers/ok-plus-loyalty-program/");

            if ($this->http->FindSingleNode("//p[contains(text(), 'We are about to launch a brand new website of our loyalty program.')]")) {
                throw new CheckException("Dear OK Plus Members, We are about to launch a brand new website of our loyalty program. The website will be more user-friendly than so far and available in the next few days. We appreciate your understanding and apologize for the temporary technical unavailability of the website.", ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm([], 100)) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindSingleNode("//span[@class = 'errorMessage']")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Login failed. Please check your login details, check reCAPTCHA')
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 9, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if (
                strstr($message, 'Login failed. Please check your login details.')
                || strstr($message, 'The password is not valid.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $this->checkErrors();

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Current balance
        $this->SetBalance($this->http->FindSingleNode('(//div[@class="current-balance-result"])[1]'));
        // Date of expiration of all miles on the account
        $exp = $this->http->FindSingleNode('//div[contains(text(), "Date of expiration of all miles")]/following-sibling::div');
        $exp = strtotime($exp);

        if ($exp != false) {
            $this->SetExpirationDate($exp);
        }

        // Membership Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//input[@name=\"okplus_okNumber\"]/@value"));
        // Your current card status
        $this->SetProperty('Status', $this->http->FindSingleNode('//div[contains(text(), "Your current card status")]/following-sibling::div'));
        // SkyTeam miles collected this year
        $this->SetProperty('Milesthisyear', $this->http->FindSingleNode('//div[contains(text(), "SkyTeam miles collected this year")]/following-sibling::div'));
        // SkyTeam segments collected this year
        $this->SetProperty('Segmentsthisyear', $this->http->FindSingleNode('//div[contains(text(), "SkyTeam segments collected this year")]/following-sibling::div'));
        // OK segments collected this year
        $this->SetProperty('OKsegmentsInThisYear', $this->http->FindSingleNode('//div[contains(text(), "OK segments collected this year")]/following-sibling::div'));
        $this->SetProperty('OKsegmentsInThisYearDuplicate', $this->http->FindSingleNode('//div[contains(text(), "OK segments collected this year")]/following-sibling::div'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[@id = "short-user-info"]//div[contains(@class, "name-surname-result")]')));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Profile setup
            if (strstr($this->http->currentUrl(), 'csa.cz/en/ok_plus/okp_my_account/okp_my_profilee/okp_my_profile.htm?send=1')
                && $this->http->FindSingleNode("//strong[contains(text(), 'The information in your OK Plus profile is not complete!')]")) {
                $this->throwProfileUpdateMessageException();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://okplus.csa.cz/en/ok_plus/okp_login_no_reg/okp_login.htm';

        return $arg;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"               => "PostingDate",
            "Description"        => "Description",
            "Miles"              => "Miles",
            "SkyTeam miles"      => "Info",
            "SkyTeam segment"    => "Info",
            "Bonus Miles"        => "Bonus",
            "Date of expiration" => "Info.Date",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $page = 0;

        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

//        $links = $this->http->FindNodes("//a[contains(text(), 'Overview of year')]/@href", null, "/ShowOverview\(\'([^\'<]+)\'/ims");
        $links = $this->http->FindPregAll("/ShowOverview\(\'(?<link>[^\'<]+)\'/ims", null, PREG_PATTERN_ORDER, true);
        $this->logger->debug(var_export($links, true), ['pre' => true]);

        do {
            $page++;
            $this->logger->debug("[Page: {$page}]");

            if ($page > 1) {
                $this->http->GetURL(array_shift($links));
            }
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        } while (!empty($links) && !$this->endHistory);

        $this->getTime($startTimer);

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//table[@class = 'rozvrzeni' or @class = 'small_font_list']//tr[td]");
        $this->logger->debug("Total {$nodes->length} history items were found");

        if ($nodes->length == 1 && ($message = $this->http->FindPreg("/(No actual data available)/ims"))) {
            $this->logger->notice(">>>> " . $message);

            return $result;
        }

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                if ($this->http->FindSingleNode("td[9]", $nodes->item($i))) {
                    $dateStr = $this->http->FindSingleNode("td[1]", $nodes->item($i));
                    $postDate = strtotime($dateStr);
                    $k = 1;

                    while ($postDate == false) {
                        $postDate = strtotime($this->http->FindSingleNode("td[1]", $nodes->item($i - $k)));
                        $k++;
                    }

                    if (isset($startDate) && $postDate < $startDate) {
                        $this->logger->notice("break at date {$dateStr} ($postDate)");
                        $this->endHistory = true;

                        break;
                    }
                    $result[$startIndex]['Date'] = $postDate;
                    $result[$startIndex]['Description'] = Html::cleanXMLValue(
                        $this->http->FindSingleNode("td[2]", $nodes->item($i))
                        . ' ' . $this->http->FindSingleNode("td[3]", $nodes->item($i))
                        . ' ' . $this->http->FindSingleNode("td[4]", $nodes->item($i))
                        . ' ' . $this->http->FindSingleNode("td[5]", $nodes->item($i)));

                    if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Description'])) {
                        $result[$startIndex]['Bonus Miles'] = $this->http->FindSingleNode("td[6]", $nodes->item($i));
                    } else {
                        $result[$startIndex]['Miles'] = $this->http->FindSingleNode("td[6]", $nodes->item($i));
                    }
                    $result[$startIndex]['SkyTeam miles'] = $this->http->FindSingleNode("td[7]", $nodes->item($i));
                    $result[$startIndex]['SkyTeam segment'] = $this->http->FindSingleNode("td[8]", $nodes->item($i));
                    $result[$startIndex]['Date of expiration'] = strtotime($this->http->FindSingleNode("td[9]", $nodes->item($i)));
                    $startIndex++;
                } // if ($this->http->FindSingleNode("td[8]", $nodes->item(0))){
                else {
                    $dateStr = $this->http->FindSingleNode("td[1]", $nodes->item($i));
                    $postDate = strtotime($dateStr);

                    if ($postDate == false) {
                        $postDate = strtotime($this->http->FindSingleNode("td[1]", $nodes->item($i - 1)));
                    }

                    if (isset($startDate) && $postDate < $startDate) {
                        $this->logger->notice("break at date {$dateStr} ($postDate)");
                        $this->endHistory = true;

                        break;
                    }
                    $result[$startIndex]['Date'] = $postDate;
                    $result[$startIndex]['Description'] = $this->http->FindSingleNode("td[2]", $nodes->item($i));

                    if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Description'])) {
                        $result[$startIndex]['Bonus Miles'] = $this->http->FindSingleNode("td[3]", $nodes->item($i));
                    } else {
                        $result[$startIndex]['Miles'] = $this->http->FindSingleNode("td[3]", $nodes->item($i));
                    }
                    $result[$startIndex]['SkyTeam miles'] = $this->http->FindSingleNode("td[4]", $nodes->item($i));
                    $result[$startIndex]['SkyTeam segment'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
                    $result[$startIndex]['Date of expiration'] = strtotime($this->http->FindSingleNode("td[6]", $nodes->item($i)));
                    $startIndex++;
                }// else
            }// for ($i = 0; $i < $nodes->length; $i++)
        }// if ($nodes->length > 0)

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//input[@value = 'Log out']/@value")) {
            return true;
        }

        return false;
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'okplus_profile_login_form']//div[@class = 'g-recaptcha']/@data-sitekey");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
