<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerCopaairSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headers = [
        "X-Requested-With" => "XMLHttpRequest",
        "Accept"           => "application/json, text/plain, */*",
        "Content-Type"     => "application/json",
        "currentLang"      => "en",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);
//        $this->useFirefox();
//        $this->setKeepProfile(true);

//        $request = FingerprintRequest::firefox();
//        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
//        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
//
//        if ($fingerprint !== null) {
//            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
//            $this->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
//            $this->http->setUserAgent($fingerprint->getUseragent());
//        }

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.copaair.com/api/auth/login?lng=en");

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'username']"), 10);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@name="action" and not(@style)]'), 0);

        if (!$login || !$pass || !$btn) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return $this->checkErrors();
        }

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->steps = rand(10, 30);

        try {
            $mover->moveToElement($login);
            $mover->click();
            $mover->sendKeys($login, $this->AccountFields['Login'], 5);
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $mouse = $this->driver->getMouse();
            $mouse->mouseMove($login->getCoordinates());
            $mouse->click();
            $login->sendKeys($this->AccountFields['Login']);
        }

        try {
            $mover->moveToElement($pass);
            $mover->click();
            $mover->sendKeys($pass, $this->AccountFields['Pass'], 5);
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $mouse = $this->driver->getMouse();
            $mouse->mouseMove($pass->getCoordinates());
            $mouse->click();
            $pass->sendKeys($this->AccountFields['Pass']);
        }

        $this->saveResponse();
        $captcha = $this->parseReCaptcha();

        if ($captcha !== false) {
            $this->driver->executeScript("document.getElementsByName('captcha').value = '{$captcha}';");
        }

        $this->logger->debug("click by btn");
//        $mover->moveToElement($btn);
//        $mover->click();
        $btn->click();
//        $this->driver->executeScript("document.querySelector('form button[name=action]').click()");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $logout = $this->waitForElement(WebDriverBy::xpath('//burron[@aria-label="headerLoggedInButtonWCAG"]'), 20);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        return true;

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);

        $this->driver->executeScript('
            await fetch("https://members.copaair.com/services/members/profile", {
                "credentials": "include",
                "headers": {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json, text/plain, */*",
                    "Content-Type": "application/json",
                    "currentLang": "en",
                },
                "referrer": "https://www.copaair.com/en/web/gs/hub",
                "mode": "cors"
            }).then((response) => {
                    response
                    .clone()
                    .json()
                    .then(body => localStorage.setItem("profileData", JSON.stringify(body)));
            })
        ');
        sleep(2);
        $profileData = $this->driver->executeScript("return localStorage.getItem('profileData');");
        $this->logger->info("[Form profileData]: " . $profileData);

        $response = $this->http->JsonLog($profileData, 3, true);
        $profile = ArrayVal($response, 'profile');
        $memberCard = ArrayVal($profile, 'memberCard');
        // Expiration date
        $exp = $this->http->FindPreg("/key\":\"expire_date\",\"value\":\"([^\"]+)/");
        $exp = $this->ModifyDateFormat($exp);

        if (strtotime($exp)) {
            $this->SetExpirationDate(strtotime($exp));
        }
        // Miles to next level
        $this->SetProperty("MilesToNextLevel", $this->http->FindPreg("/key\":\"req_points_for_next_tier\",\"value\":\"([^\"]+)/"));
        // Segments to next level
        $this->SetProperty("SegmentsToNextLevel", $this->http->FindPreg("/key\":\"req_segments_for_next_tier\",\"value\":\"([^\"]+)/"));

        // Balance - Mileage Balance
        $this->SetBalance(ArrayVal($memberCard, 'awardMiles')); // refs #18570
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"         => "PostingDate",
            "Description"  => "Description",
            "Status Miles" => "Info",
            "Segments"     => "Info",
            "Awards Miles" => "Miles",
            "Bonus"        => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $page = 0;
        $endDate = date('d-m-Y');

        $this->driver->executeScript('
            await fetch("https://www.copaair.com/services/members/activities?endDate=' . $endDate . '&page=1&pageSize=10000&startDate=01-01-2000", {
                "credentials": "include",
                "headers": {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json, text/plain, */*",
                    "Content-Type": "application/json",
                    "currentLang": "en",
                },
                "referrer": "https://www.copaair.com/en/web/gs/hub",
                "mode": "cors"
            }).then((response) => {
                    response
                    .clone()
                    .json()
                    .then(body => localStorage.setItem("historyData", JSON.stringify(body)));
            })
        ');
        sleep(2);
        $historyData = $this->driver->executeScript("return localStorage.getItem('historyData');");
        $this->logger->info("[Form historyData]: " . $historyData);
        $page++;
        $this->logger->debug("[Page: {$page}]");

        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($historyData, $startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function unicodeString($str, $encoding = null)
    {
        if (is_null($encoding)) {
            $encoding = ini_get('mbstring.internal_encoding');
        }

        return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/u', function ($match) use ($encoding) {
            return mb_convert_encoding(pack('H*', $match[1]), $encoding, 'UTF-16BE');
        }, $str);
    }

    public function ParsePageHistory($historyData, $startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog($historyData);
        $total = $response->total ?? null;
        $activities = $response->activities ?? [];
        $this->logger->debug("Total {$total} history items were found");

        foreach ($activities as $activity) {
            $dateStr = $activity->activity->transactionDate;
            $postDate = strtotime($dateStr);

            if ((isset($startDate) && $postDate < $startDate) || $dateStr == '') {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $activity->activity->activity;

            if (trim($result[$startIndex]['Description']) == '-') {
                $result[$startIndex]['Description'] = $activity->activity->definition;
            }
            $result[$startIndex]['Description'] = $this->unicodeString($result[$startIndex]['Description']);
            $result[$startIndex]['Status Miles'] = $activity->activity->tierPoints;
            $result[$startIndex]['Segments'] = $activity->activity->flightSectors ?? null;

            if ($this->http->FindPreg('/Bonus/i', false, $activity->activity->definition)) {
                $result[$startIndex]['Bonus'] = $activity->activity->awardPoints;
            } else {
                $result[$startIndex]['Awards Miles'] = $activity->activity->awardPoints;
            }

            $startIndex++;
        }

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $key = $this->http->FindSingleNode("//div[@data-captcha-sitekey]/@data-captcha-sitekey");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
