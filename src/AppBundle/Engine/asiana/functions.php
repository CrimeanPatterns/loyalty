<?php

include_once 'Crypt/RSA.php';

include_once 'Crypt/BigInteger.php';

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAsiana extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://flyasiana.com/I/US/EN/MyasianaDashboard.do?menuId=CM201803060000729176";

    public $regionOptions = [
        ""       => "Select your login type",
        "ID"     => "ID",
        "Number" => "Membership Number",
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        $this->enableProxy();

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->logger->debug("[Login Type] -> {$this->AccountFields['Login2']}");

        // Membership No. has 9 digit numbers.
        if ($this->AccountFields['Login2'] == 'Number' && (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false)) {
            throw new CheckException("Membership No. has 9 digit numbers.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://flyasiana.com/I/US/EN/viewLogin.do?callType=IBE&menuId=CM201802220000728453');

        $this->enableProxy(true);
        $this->enableProxy(true, true);

        if (!$this->http->ParseForm(null, "//h3[contains(text(), 'Log-in')]/following-sibling::form")) {
            $this->callRetries();

            return $this->checkErrors();
        }

        $modulus = $this->http->FindPreg('/var publicKeyModulus\s*=\s*"(.+?)";/');
        $exponent = $this->http->FindPreg('/var publicKeyExponent\s*=\s*"(.+?)";/');
        // $rsaUniqueKey = $this->http->FindPreg("/rsaUniqueKey\s*:\s*'(.+?)',/");
        // if (!$modulus || !$exponent || !$rsaUniqueKe)
        if (!$modulus || !$exponent) {
            return $this->checkErrors();
        }

        if ($this->AccountFields['Login2'] == 'Number' || (empty($this->AccountFields['Login2']) && is_numeric($this->AccountFields['Login']))) {
            $loginType = "A";
        } else {
            $loginType = "I";
        }

        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
            "Referer"          => $this->http->currentUrl(),
            'Accept-Encoding'  => 'gzip, deflate, br, zstd',
        ];

//        $key = $this->sensorSensorData();
        if ($this->attempt >= 0) {
            $this->selenium();
            $key = 1000;

            return true;
        } else {
            $abck = [
                // 0
                "06281C33C675683EFEFA7A0D40F37C1A~-1~YAAQMvAVAtzYcUSMAQAAnuFhVwvghG7esL9cHL882DyOSGtmTnlSMh8UUCF4ijqTI80eW0fwbV+qY6W5Ngc5JzXDjUSSNsnerw/0PaoV7CN39Nmh8VqiZTPeU/hrjQzjUFgKxWnCnuFufzkmM325LIzlf4/yuFQG8ry3T/6rfoRalS1RqAMaEqSeDFRSjcr091Q7VHrhD6zvp/da5UbcTiQcslbmsTBTUeDi9r0Dje8m+puT+wIP7Uwc+I6S7vitZJ2/n2w+sq2Vdx+2L45Ykn8mEP0zSeoESEZEvtZDklWSxJdvY/gjDRioJDQ3Q/5k+EEg9tYOUWn0MCIoT1jHfudshxWPdthQSVfRjrJxjWRkoldqEQcKRrmZOYMriTHcgGp//H7n3Zth3q808pYvB4nVNHrhQZiRPgcyZYNRRrs0I7Q17OVdirA=~-1~-1~-1",
                // 1
                "572E3F5B4FA31048AD9A8FB38F3F3160~-1~YAAQLvAVAjDJVtODAQAATF6YHQgr4Mtj5VebE2aFSW334A+BEiGmehpsHh7lM5cSfyAkMlInFupwUGXjNyh0Qlko0dpv7UoYIroTGfjrF6Sjq6cy/lRjJJ8I6DgMLb+VYzsw+JoWTA6mRpMaGcNExX8wnFqqQIgaynmFPxv/2zYgypoiXl8xSHkb+xRWyIYmBIAUFMnHzLJyHev/BUDMubxY9faIA8XiVFzVTO+QuScfSm0k8e5HTR25zLgUO4frHvZtp1C8dE8xgHp9hq7BCBJNsxOosjQeRkdMLLm7lPNEsrINmLqKrC8v8/uBav0TAoLoklkCXZfJ/xauOVvLLqQdK2tDA19u1i5tkI7r3sV4xFDisiWxNBRI/8kSNEtOK1LbIyR5xGyQ70eN/jeb8u4wqburQjgfidw4B29JYJNU+pCJqVzuIg==~-1~-1~-1",
                // 2
                "BE1F29E1DFF82298DE2A2DF0FE73EC02~-1~YAAQLvAVAnHUVtODAQAAbFGZHQhRDX/oN6gYkjeN56dyCFSwvxcmtHUl8mun49rPtF9cTy9GUY8aBvoLKhyXNrftej5+Vx41rl0S2DmDqOreGhPl3hIUQDlQoVx4H6nX5Nfom4vTiHeTegdmnsjwl1BMN+deZITYt5KlhppOtW94Jn+2w9D00keBVV5mZtoYjg0f2XS9doy4eFled8f4Ry9XFYye2xVM1epU/Bd+3mug2Hw5k+y1J4B5+GhlUqQDVBOIu6mzOYkDVukDCCs0tnV925wKoy0JtXcZLvESqDrncWXz1V1xYH1All8iTL4fSXVieu+Drhvltz5vdYeEvpqvEX5JtmGxgoaqliei9U6buXTc/RKG5fmzDpKasBCLhH417CLxS8yKA7VDHv4LXiOv+QzHCMi8wm8IPsxtYkGCBJvAO+y1oA==~-1~-1~-1",
                // 3
                "F632A6A8DDA0AA5E3DAC4C1A669705BD~-1~YAAQLvAVAue/V9ODAQAAddawHQgScXOZeYxS3n7bYeSCQJpoWMAwhDH8wnrVK0Tivul9RsHmW7MBtJd15s/mpX9RcCcMm85+fn3lM9csYI4tUNQ48p1fLoGrRXht7MxvfBL/YsSBJ1Tf7O7JdSAr4m4SZCQ3G2x0eAf/mg/diJdM9S6VOzDD5Y9b5UC1aOvfS7WLR0GjcmOjIJN/2eGaFqLCOarJ1ipEeX2NZ/h4W6B3GdYuCH+Kbl2Y6ptc0wyaTud7HZLpGbj8T687QcgL2TXCF302UrK4821dVM2kBsXfJzwz4m2NopXh6jyPQSC6p9b7ucx09AHlNv+ZW8QAKR7YgouJ45bLO3qQse1JbYEEw70VcLcvLr7vfiiaq4/EvhVqMsZcj8Rh+yVBBtlzJefAQTqKMgzjlDUiG4bolelviCPfSkSfcw==~-1~-1~-1",
                // 4
                "1ABA1EB85D0AEBC31B886D21DD60A012~-1~YAAQLvAVAoINV9ODAQAAbP6eHQj5TSeEfb792JbIqr8sFam4F78eJ/2hvuoG5QihenJc5Qwi6/QiK5LKuDp8dAC/SIpNUhLvUODXr6+mXWmn5Bv/lP9iHFqoGnR/Lk2OCzfLrE8Ab6IB1McG1gF6pGkHJZM+hBiCFGotFh7ruN+DWw9be62nq587WbpNsxRSaebEnQZbXygIkf1paefaM8Oh7ByJlRPAz+MW938K8ka94UP3CCUjDPHAljXuaHM/q2GQfH2qIcSIJRzt1X5qBzHzE1aF6DqUofRnADnSB4hZKW6WUEeC5xZqkRredpA79rst5B3JIEFWazBWygaTtnTYEW6N+RZxyu49egbPv8YFeBCoKdbQPYZxunkOXeYJ3pCuM8PFShIbcxYod0suKbzeb3UNT6qgUPI76+vQntRIIabEtQ==~-1~-1~-1",
                // 5
                "033E5BB163996F76E59D467544D0DBF8~-1~YAAQLvAVAi8TV9ODAQAAUYqfHQi7IMvMH4FOf6r7E0r4ibAEj4WPLCfQA6gvNc2NA/ziq0I3RGv5pN5lFXlMNnEwX8+LAjNplXaqER0UuFPpE2EA8Wd1J7U639le/19wLZ4zrbjLcY5a5OFM4WhmVLEdHKCXDJOdIgw8dop82cviDkdUvoglK9dq9MWRfoxPk7/p0BDXGWmr5JjL26AmBcnLZahl512PZiTWHG8PRcG99Z/e7qaHRextmxqmdsZyQEbk657s+x9XZQaH9BZHuLonNOeNKBfjhLSGHVl/ucNXLbGx91JKODZTN3ocEZW/89qa93HbzTn0c/c9e7Yz0oL08IZ0f5sol4J76L/YcARUix8+8RKBmg7JqSyeZTW6i/Bv/nbQNK4//qjpiXESJZGO4n1MU77qDkBuyulS/DvnUgMkTBw5teE=~-1~-1~-1",
                // 6
                "844AF4F3F9122D03F7ACF4071E860A1F~-1~YAAQMvAVAtzKlRyEAQAA2uKfHQhpuY//Dr3BVe0Qm1eIg4EDePWocvHVD3vkXs556fhXlYsPPp3ncVN13tPdr5Ei1VryTz/dlbs83RplzqeDUaqFOAOG7e8MO2ARYrrONeqWpApS3ssh3q/ZaD44m8XA19VC5k6+f4h5n2KbZY4bQ3SmR8GcvDPsuOkz84upEzYFEKFFP2JobPcF8/VkJ0zkxs/eKMe3vIF6MVdek9zlyymUbCz/ahuTjg5kb1Sw6OLeil7hJDAiMyeXAH/B/xjooNu1nfjlRtqASm7hAj2IMF/umQ4qV0mw19tbH5yknkpibDOhGZtv7qwYvu6IgLo5bueNu8m4IdsOX1/rpqkeIr0IrVTAMjL0ni7G8XdfQbqDdxRuRFVijJ9iHVS+LIXHzDBq/ObauO2090FOFlsxWfbKgMoQMoo=~-1~-1~-1",
                // 7
                "9D20A8A6298D3D564A7F6A90FA64FD9D~-1~YAAQhQVJFxcG7RyEAQAAZR+vHQgBUggUrEZiLRSUHiELuSkVjB8x04DWJZ8U6E7xk19GLaVJtuDUpd6DnmUnbaVAtJv1Cs/4y9QtLpT4ulcKW526tyV32rLinyR3Z0t6XovpYBBiTjiqqBEL/r9nN13kaB5OEp09CWd4BRRKpoWvHoYmxPZiBC9X+ib+NB6/LXkAx4SoVYltj3fGk+pu2A/5NIAIUPKOy1VxwwuN3V3+St6UoGdqGtL9Kib8gpEuMR2fGNJ0wSRCUYi9vrLRjSlq2PoOTK3BLdN1XDJ+hQtM8yLmit/rwbeMpfSmzkBJ+JA3bYrKzwZyKSfSkK7Id+Ez6tUJb8iZxeHWzLyXciS5sENLD1g7e4te5eV99kz4SdHceDQDblnTE9nichM=~-1~-1~-1",
            ];
            $key = array_rand($abck);
            $this->logger->notice("key: {$key}");
            $this->DebugInfo = "key: {$key}";
            $this->http->setCookie("_abck", $abck[$key]); // todo: sensor_data workaround
        }

        $this->http->RetryCount = 0;
        $data = [
            'loginType'        => $loginType,
            'loginIdentity'    => $this->encryptRSA($modulus, $exponent, $this->AccountFields['Login']),
            'loginPW'          => $this->encryptRSA($modulus, $exponent, $this->AccountFields['Pass']),
            // 'rsaUniqueKey' => $rsaUniqueKey,
            'callType'         => 'IBE',
            'movePage'         => '',
            'sessionUniqueKey' => $this->generateUUID(),
        ];
        $this->http->PostURL('https://flyasiana.com/I/US/EN/goLogin.do?n$eum=60655549866017880', $data, $headers);

        $this->callRetries();

        $this->http->RetryCount = 2;

        $retry = false;
//        if ($this->http->Response['code'] == 403) {
//            $this->sendStatistic(false, $retry, $key);
//            throw new CheckRetryNeededException(2, 10);
//        }

        if ($this->http->Response['code'] == 403) {
            $this->sendStatistic(false, $retry, $key);
        } else {
            $this->sendStatistic(true, $retry, $key);
        }

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://eu.flyasiana.com/I/EN/Login.do';
        $arg['SuccessURL'] = 'https://eu.flyasiana.com/I/en/MyAsianaMain.do';
        $arg['PreloadAsImages'] = true;

        return $arg;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // Change your password
        /*
        if (isset($response->actionUrl) && $response->actionUrl == 'PasswordChangeCheck.do')
            $this->throwProfileUpdateMessageException();
        */
        // Success
        if (isset($response->userData->errorCode) && in_array($response->userData->errorCode, ['0000', ''])) {
            return true;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $response->userData->errorMessage ?? $response->errorMessage ?? null;

        if ($message) {
            $this->logger->error($message);
            // There is an ID or password error. The ID or password you entered is not registered or was entered incorrectly. Please re-enter after confirming.
            if (in_array($message, ['로그인 요청자의 PASSWORD가 올바르지 않음.', '회원정보가 미존재', '홈페이지 회원도 아니고 면세회원도 아닌 경우 (로그인 세션 NULL)'])) {
                throw new CheckException('There is an ID or password error. The ID or password you entered is not registered or was entered incorrectly. Please re-enter after confirming.', ACCOUNT_INVALID_PASSWORD);
            }
            // AccountID: 4929156
            if ($message == 'Your request cannot be processed online. Contact our internet desk or regional offices for assistance.' && strlen($this->AccountFields['Login']) > 9) {
                throw new CheckException('Membership No. has 9 digit numbers.', ACCOUNT_INVALID_PASSWORD);
            }
        }// if (isset($response->userData->errorMessage))
        // captcha for invalid accounts
        elseif (isset($response->loginFailuresCnt) && $response->loginFailuresCnt >= 5) {
            throw new CheckException('The login or password you entered is not registered or was entered incorrectly. Please re-enter after confirming.', ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(@class, "col_red")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Password entry has exceeded more than')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        /*
        if (
            $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
            && $this->http->Response['code']
        ) {
            throw new CheckException('The login or password you entered is not registered or was entered incorrectly. Please re-enter after confirming.', ACCOUNT_INVALID_PASSWORD);
        }
        */

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->RetryCount = 1;
            $this->http->GetURL(self::REWARDS_PAGE_URL);
            $this->http->RetryCount = 2;
        }
        // Balance - Mileage Balance
        $this->SetBalance($this->http->FindSingleNode('//span[contains(text(), "Mileage Balance")]/following-sibling::p'));
        // Member No.
        $this->SetProperty("Number", $this->http->FindSingleNode("//div[@class = 'my_card']/p/a/span"));
        // Membership Status
        $status = $this->http->FindSingleNode("//div[@class = 'my_card']/p/a/text()[1]");

        if (isset($status)) {
            $this->logger->debug("EliteStatus => " . $status);

            if ($status == 'MAGIC MILES') {
                $this->SetProperty("MagicMilesMember", "Yes");
            } else {
                $this->SetProperty("EliteStatus", $status);
            }
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@class = 'my_name']/a/text()[last()]")));
        // Family Miles
        $this->SetProperty("FamilyMiles", $this->http->FindSingleNode("//p[contains(text(), 'Family mileage')]/following-sibling::p[1]/text()[1]"));

        $this->http->FilterHTML = false;
        $this->http->GetURL("https://flyasiana.com/I/US/EN/GetMemberGradeInfo.do");
        $this->http->FilterHTML = true;
        // Date of Sign-Up
        $this->SetProperty("DateofSubscription", $this->http->FindSingleNode("//dt[p[contains(text(), 'Date of Sign-Up')]]/following-sibling::dd[1]"));

        // 24-month record

        // Number of Asiana Boardings
        $this->SetProperty("QualifyingFlights", $this->http->FindSingleNode("//div[@id = 'div_24_month']//dt[p[contains(text(), 'Number of boardings on Asiana Airlines')]]/following-sibling::dd[1]"));
        // Boarding mileage on Asiana Airlines+Star Alliance
        $this->SetProperty('QualifyingMiles', $this->http->FindSingleNode("//div[@id = 'div_24_month']//dt[p[contains(text(), 'Boarding mileage on Asiana Airlines+Star Alliance')]]/following-sibling::dd[1]"));
        // Status expiration
        $this->SetProperty("StatusExpiration", $this->http->FindSingleNode("//div[@id = 'div_24_month']//dt[p[contains(text(), 'Evaluation') and contains(text(), 'period')]]/following-sibling::dd[1]", null, true, "/~\s*([^<]+)/"));
        // Miles to Next Level
        $this->SetProperty("MilesToNextLevel", $this->http->FindSingleNode("//div[@id = 'div_24_month']//span[contains(text(), 'mile(s) to go')]", null, true, "/\+\s*([\d,.]+)/"));
        // Flights to Next Level
        $this->SetProperty("FlightsToNextLevel", $this->http->FindSingleNode("//div[@id = 'div_24_month']//span[contains(text(), 'boardings remaining')]", null, true, "/\+\s*([\d]+)/"));

        // Total records from the sign-up date

        // Boarding mileage on Asiana Airlines+Star Alliance
        $this->SetProperty('TotalQualifyingMiles', $this->http->FindSingleNode("//div[@id = 'div_reg_month']//dt[p[contains(text(), 'Boarding mileage on Asiana Airlines+Star Alliance')]]/following-sibling::dd[1]"));
        // Number of boardings on Asiana Airlines
        $this->SetProperty("TotalQualifyingFlights", $this->http->FindSingleNode("//div[@id = 'div_reg_month']//dt[p[contains(text(), 'Number of boardings on Asiana Airlines')]]/following-sibling::dd[1]"));

        // AccountID: 3426592
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Balance - Mileage Balance
            $this->SetBalance($this->http->FindSingleNode("//a[@class = 'mile_more']/span[@class = 'num']"));
            // Member No.
            $this->SetProperty("Number", $this->http->FindSingleNode("//span[@class = 'mem_number']", null, true, "/([\d\s]+)/"));
            // Membership Status
            $status = $this->http->FindSingleNode("//span[@class = 'grade']");

            if (isset($status)) {
                $this->logger->debug("EliteStatus => " . $status);

                if ($status == 'MAGIC MILES') {
                    $this->SetProperty("MagicMilesMember", "Yes");
                } else {
                    $this->SetProperty("EliteStatus", $status);
                }
            }
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@class = 'en_name']")));

            // AccountID: 1738804
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->ParseForm("form1")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // Coupons
        $this->logger->info('Coupons', ['Header' => 3]);
        $ssoSessionId = $this->http->getCookieByName("ssoSessionId", '.flyasiana.com', '/', true);

        if ($ssoSessionId && isset($this->Properties["Number"])) {
            $this->http->PostURL("https://flyasiana.com/I/US/EN/getAllCouponCount.do", ["ssoId" => $ssoSessionId]);
            $couponCount = $this->http->JsonLog(null, 3, true);
            $number = trim(str_replace(' ', '', $this->Properties["Number"]));

            if (ArrayVal($couponCount, 'count') !== 0) {
                // Membership benefits
                $this->http->PostURL("https://flyasiana.com/I/US/EN/ViewBonusCouponList.do?n\$eum=146631259098812060", "acno={$number}&couponType=A&currentPage=1&countPerPage=6");
                $coupons = $this->http->XPath->query("//div[contains(@id, 'bonusCoupon_')]");
                $this->logger->debug("Total {$coupons->length} Membership benefits were found");

                foreach ($coupons as $coupon) {
                    $displayName = $this->http->FindSingleNode('.//div[contains(@class, \'coupon_info\')]/p/text()[1]', $coupon);
                    // Coupon number
                    $number = $this->http->FindSingleNode('.//a/@href', $coupon, true, "/:bonusCouponDetail\('[^']+',[^']+'([^']+)/");
                    $exp = $this->http->FindSingleNode('.//a/@href', $coupon, true, "/:bonusCouponDetail\('[^']+',[^']+'[^']+',[^']+'[^']+',[^']+'([\d]+)'/");
                    $this->logger->debug("exp: {$exp}");
                    $exp = preg_replace('/(\d{4})(\d{2})(\d{2})/', '$1/$2/$3', $exp);
                    $this->logger->debug("exp: {$exp}");
                    $this->AddSubAccount([
                        'Code'           => 'asianaMembershipBenefits' . $number,
                        'DisplayName'    => $displayName . " ({$number})",
                        'Balance'        => null,
                        'ExpirationDate' => strtotime($exp, false),
                    ]);
                }
                /*
                $this->http->PostURL("https://flyasiana.com/I/US/EN/GetOnlineCouponList.do?n\$eum=342336770618120300", "currentPage=1&countPerPage=6&countPerGroup=10&userAcno={$number}&couponType=MY");
                if (!$this->http->FindPreg("/\{\"onlineCouponList\":\[\]\}/") && $this->http->Response['code'] != 403) {
                    $this->sendNotification("asiana - refs #16626. Coupons were found", "awardwallet");
                }
                */
            }// if (ArrayVal($couponCount, 'count') !== 0)
        }// if ($ssoSessionId)

        // Expiration Date  // refs #13584
        if ($this->Balance > 0) {
            $this->getExpDate();
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $airports = $this->getAirports();
        $this->logger->debug(var_export($airports, true));
        $result = [];
        $uiSessionId = $this->http->getCookieByName("UISESSIONID");

        if (empty($uiSessionId)) {
            return [];
        }
        $uiSessionId = $this->http->FindPreg("/([^!]+)/", false, $uiSessionId);
        $data = [
            'sessionUniqueKey' => $this->genUuid(),
            'uiSessionId'      => $uiSessionId,
        ];
        $headers = [
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "Referer"          => "https://flyasiana.com/I/US/EN/RetrieveReservationList.do",
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
        ];
        $this->http->PostURL('https://flyasiana.com/I/US/EN/getRetrieveReservationList.do?n$eum=' . time() . date("B") . random_int(10000, 99999), $data, $headers);
        $response = $this->http->JsonLog(null, 2);

        if ($this->http->FindPreg('/"reservationListsData":\{"reservationListDatas":null,/')) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        if (!empty($response->reservationListsData->reservationListDatas)) {
            foreach ($response->reservationListsData->reservationListDatas as $reservationListData) {
                $this->logger->info('Parse Itinerary #' . $reservationListData->alphaPNR, ['Header' => 3]);
                $headers = [
                    'Accept-Encoding' => 'gzip, deflate, br',
                ];

                $data = [
                    "bizType"          => $reservationListData->bizType,
                    "depAirport"       => $reservationListData->segmentDataList[0]->departureCity,
                    "lastName"         => $reservationListData->lastName,
                    "officeId"         => $reservationListData->officeId,
                    "pnrAlpha"         => $reservationListData->alphaPNR,
                    "pnrNumeric"       => $reservationListData->numericPNR,
                    "uniqueNo"         => $reservationListData->listUniqueNo,
                    "staralliance"     => ($reservationListData->starAlliance == false) ? 'false' : 'true',
                    "uiSessionId"      => $uiSessionId,
                    "sessionUniqueKey" => $response->sessionUniqueKey,
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://flyasiana.com/I/US/EN/ViewReservationDetail.do", $data, $headers);
                $this->http->RetryCount = 2;

                if ($this->http->ParseForm('form1')) {
                    $this->http->PostForm();
                }

                if (!$this->parseItinerary($reservationListData->alphaPNR, $airports)) {
                    sleep(2);
                    $this->http->PostURL("https://flyasiana.com/I/US/EN/ViewReservationDetail.do", $data);

                    if ($this->http->ParseForm('form1')) {
                        $this->http->PostForm();
                    }
                    $this->parseItinerary($reservationListData->alphaPNR, $airports);
                }
                sleep(random_int(0, 7));
            }
        }// foreach ($response->reservationListsData->reservationListDatas as $reservationListData)

        return $result;
    }

    public function genUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"                        => "PostingDate",
            "Type"                        => "Description",
            "Point of earning/using"      => "Info",
            "Tier at the time of earning" => "Info",
            "Miles"                       => "Miles",
            "Expiration Date"             => "Info.Date",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();
        $toYear = date("Y") + 1;
        $params = [
            'fromDate'        => date("Ydm", strtotime("-29 year")),
            'fromDate0'       => '19950118',
            'fromMonthSearch' => "",
            'fromYear'        => date("Y", strtotime("-29 year")),
            'isAllSearch'     => "true",
            'isExpireSearch'  => "true",
            'isUpSearch'      => "true",
            'isUseSearch'     => "true",
            'option'          => "asianastaroalfmpetc",
            'pageNum'         => "1",
            'radio'           => "P",
            'searchType'      => "P",
            'toDate'          => date("Ydm"),
            "toYear"          => $toYear,
        ];
        $page = 0;
        $this->http->PostURL('https://flyasiana.com/I/US/EN/GetSavingUseMileageDetailList.do?n$eum=189585392425118530', $params);

        do {
            $page++;
            $this->logger->debug("[Page: {$page}]");
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        } while ($page < 1);

        usort($result, function ($a, $b) {
            return $b['Date'] - $a['Date'];
        });

        $this->getTime($startTimer);

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(@onclick, "logout.do")]')) {
            return true;
        }

        return false;
    }

    private function enableProxy($removeCookies = false, $newProxy = null)
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->Response['code'] == 403
            || $this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)
            || $this->http->Error == 'Network error 56 - Received HTTP code 503 from proxy after CONNECT'
            || $this->http->Error == 'Network error 28 - Unexpected EOF'
            || $this->http->Error == 'Network error 56 - Proxy CONNECT aborted'
        ) {
            /*
            if ($this->attempt == 2) {
                $this->setProxyGoProxies($newProxy);
            } else {
                $this->setProxyBrightData($newProxy);
            }
            */
            $this->setProxyNetNut($newProxy);

            if ($removeCookies === true) {
                $this->http->removeCookies();
            }
            $this->http->GetURL($this->http->currentUrl());
        }
    }

    private function callRetries()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg('/(?:Operation timed out after|^Network error 56 - Unexpected EOF|^Network error 35 - OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to flyasiana.com:443|^Network error 0 - $|^Network error 16 - $|Network error 7 - Failed to connect to \d+\.\d+\.\d+\.\d+ port \d+: Connection refused)/', false, $this->http->Error)
            || empty($this->http->Response['body'])
        ) {
            throw new CheckRetryNeededException(2, 10);
        }
    }

    private function sendStatistic($success, $retry, $key)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("asiana sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }

    private function encryptRSA($modulus, $exponent, $encryptStr)
    {
        $this->logger->debug('Modulus: ' . $modulus);
        $rsa = new Crypt_RSA();
        $modulus = new Math_BigInteger($modulus, 16);
        $exponent = new Math_BigInteger($exponent, 16);
        $rsa->loadKey(['n' => $modulus, 'e' => $exponent]);
        $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
        $encrypted = bin2hex($rsa->encrypt($encryptStr));

        return $encrypted;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($message = $this->http->FindSingleNode('
                //p[
                    contains(text(), "Asiana Airlines is undergoing a regular system maintenance every Sunday to provide stable internet services.")
                    or contains(text(), "Please understand that our service will be restricted due to conversion of Asiana Airlines’ Homepage/Mobile.")
                    or contains(text(), "Please understand that our service will be restricted due to maintenance of Asiana Airlines")
                ]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // maintenance
        if (strstr($this->http->currentUrl(), 'noticeSystemMaintenance.html')) {
            throw new CheckException("Asiana Airlines is undergoing a regular system maintenance to provide stable internet services.", ACCOUNT_PROVIDER_ERROR);
        }
        // Failure of Web Server bridge:
        if (
            $this->http->FindSingleNode('//h2[contains(text(), "Failure of Web Server bridge:")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "Internal Server Error - Read")]')
            || $this->http->FindSingleNode('//h2[contains(text(), "Error 404--Not Found")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        return false;
    }

    private function getExpDate()
    {
        $this->logger->debug(__METHOD__);
        $this->logger->info("Expiration Date", ['Header' => 3]);
        $this->http->GetURL('https://flyasiana.com/I/US/EN/GetMileageDetail.do');
        $toYear = date("Y") + 12;
        $params = [
            'fromDate'        => date("Ydm", strtotime("- month")),
            'fromDate0'       => '20000101',
            'fromMonthSearch' => "",
            'fromYear'        => date("Y"),
            'option'          => "",
            'pageNum'         => "1",
            'radio'           => "P",
            'searchType'      => "P",
            'toDate'          => date("Ydm"),
            "toYear"          => $toYear,
        ];
        $this->http->PostURL("https://flyasiana.com/I/US/EN/GetExtinctScheduledMileageList.do?n\$eum=56955302336613880", $params);
        $nodes = $this->http->XPath->query("//th[contains(text(), 'Valid date')]/ancestor::thead[1]/following-sibling::tbody/tr");
        $this->logger->debug("Total {$nodes->length} exp dates were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $date = $this->http->FindSingleNode("td[2]", $node);
            $points = $this->http->FindSingleNode("td[4]", $node);

            if (!empty($date) && !empty($points)) {
                $this->logger->debug("Date: {$date} / {$points}");
                // Expiration Date
                if ($exp = strtotime($date)) {
                    $this->SetExpirationDate($exp);
                }
                // Expiring balance
                $this->SetProperty("ExpiringBalance", $points);

                break;
            }//if (isset($date) && isset($points) && $points > 0)
        }// for ($i = 0; $i < $nodes->length; $i++)
    }

    private function dateFormat($date)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Date]: {$date}");
        $dateObj = DateTime::createFromFormat('Y.m.d', $date);

        if ($dateObj) {
            $date = $dateObj->format("m/d/Y");
        }

        $this->logger->debug("[Date]: {$date}");

        return $date;
    }

    private function parseItinerary($confNo, $airports): bool
    {
        $this->logger->notice(__METHOD__);

        if ($error = $this->http->FindSingleNode("//h4[contains(text(),'Ticket has not been issued correctly.')]")) {
            $this->logger->error($error);

            return true;
        }
        $f = $this->itinerariesMaster->add()->flight();

        $f->price()
            ->currency($this->http->FindPreg("/viewCurrencyCalcurate\('([A-Z]{3})','[^']+'/"), false, true)
            ->cost(PriceHelper::cost($this->http->FindPreg('/"fareData":\{"fareKind":"[^\"]+","segmentNo":"","amount":"[^\"]+","mileage":"[^\"]+","totalAmount":"[^\"]+","amountWithoutTax":"([^\"]+)","totalTax":"[^\"]+"/')), false, true)
            ;

        $total = PriceHelper::cost($this->http->FindPreg("/viewCurrencyCalcurate\('[A-Z]{3}','([^']+)'/"));
        // TODO: USD 512,501.30
        if ($total && $total < 500000) {
            $f->price()->total($total, false, true);
        }

        $tax = PriceHelper::cost($this->http->FindPreg('/"fareData":\{"fareKind":"[^\"]+","segmentNo":"","amount":"[^\"]+","mileage":"[^\"]+","totalAmount":"[^\"]+","amountWithoutTax":"[^\"]+","totalTax":"([^\"]+)"/'));

        if ($tax) {
            $f->price()->tax($tax);
        }

        $mileage = $this->http->FindPreg('/"fareData":\{"fareKind":"[^\"]+","segmentNo":"","amount":"[^\"]+","mileage":"([^\"]+)","totalAmount":"[^\"]+","amountWithoutTax":"[^\"]+","totalTax":"[^\"]+"/');

        if (!empty($mileage)) {
            $f->price()->spentAwards($mileage . " Miles");
        }

        $f->general()->confirmation($confNo, 'Reservation Number', true);

        $fullConfNo = $this->http->FindSingleNode("//h4[contains(text(), 'Reservation Number')]/span[contains(text(), '/')] | //div[contains(@class, 'txt_status') and contains(text(), '/')]", null, true, "/([^\/]+)/");

        if (!in_array($fullConfNo, [$confNo, '>', null])) {
            $f->ota()->confirmation($fullConfNo, "Reservation Number");
        }

        if (
            is_null($fullConfNo)
            && ($error = $this->http->FindSingleNode('//p[contains(text(), "We apologize for the inconvenience.")]'))
        ) {
            if ($error2 = $this->http->FindSingleNode('//p[contains(text(), "Your request cannot be processed online. Contact our internet desk or regional offices for assistance.")]')) {
                $this->logger->notice("Skip broken itinerary");
                $this->logger->error($error);
                $this->logger->error($error2);
                $this->itinerariesMaster->removeItinerary($f);

                return true;
            }
            $tempError = (
                $this->http->FindSingleNode('//p[contains(text(), "Please try again later.")]')
                ?: $this->http->FindSingleNode('//p[contains(text(), "We apologize for the inconvenience.")]')
                ?: $this->http->FindSingleNode('//p[contains(text(), "A problem occurred during processing of the requested service.")]')
            );

            if ($tempError) {
                $this->logger->error("Retrying: {$tempError}");
                $this->itinerariesMaster->removeItinerary($f);

                return false;
            }
        }

        if (is_null($fullConfNo)) {
            $this->logger->error("Retrying: {$this->http->Error}");

            if ($this->http->Error === 'empty body') {
                $this->itinerariesMaster->removeItinerary($f);

                return false;
            }
        }

        if (
            is_null($fullConfNo)
            && (
                $error = $this->http->FindSingleNode('//h4[contains(text(), "The itinerary for this reservation has been")]', null, true, "/The itinerary for this reservation has been completed or cancelled\./")
                ?? $this->http->FindSingleNode('//p[contains(text(), "This is a group reservation.You may check the schedule for group reservations through the location where you made the reservation.")]')
                ?? $this->http->FindSingleNode('//p[contains(text(), "number not found. Try again or contact the reservation center.")]', null, true, "/The booking number not found\.\s*Try again or contact the reservation center\./")
            )
        ) {
            $this->logger->notice("Skip completed or cancelled itinerary");
            $this->logger->error($error);
            $this->itinerariesMaster->removeItinerary($f);

            return true;
        }

        // Passengers Info
        $passengersInfo = $this->http->XPath->query("//table[@id = 'tblReservationInfo']//tr[td]");
        $this->logger->debug("Total {$passengersInfo->length} passengers were found");
        $accNumbers = [];

        foreach ($passengersInfo as $passengerInfo) {
            // Passengers
            $f->addTraveller(beautifulName($this->http->FindSingleNode("td[1]/text()[1]", $passengerInfo)), true);
            // Account Numbers
            $accNums = $this->http->FindNodes("td[2]/span", $passengerInfo);

            foreach ($accNums as $accNumber) {
                if (!in_array($accNumber, ['', 'Membership Number'])) {
                    $accNumbers[] = $accNumber;
                }
            }
            // TicketNumbers
            $ticket = Html::cleanXMLValue($this->http->FindSingleNode("td[3]", $passengerInfo));

            if (
                $ticket !== 'Unissued'
                && $ticket !== 'Choose status discount'
                && !strstr($ticket, 'Choose status discount')
            ) {
                $ticket = trim(str_replace('Unissued', '', $ticket));
                $f->addTicketNumber($ticket, false);
            }
        }// foreach ($passengersInfo as $passengerInfo)

        if (!empty($accNumbers)) {
            $f->setAccountNumbers(array_unique($accNumbers), false);
        }

        // segments v.1 / v.2

        $segments = $this->http->XPath->query("//table[@id = 'tblSegmentInfo']//tr[td]");
        $this->logger->debug("Total {$segments->length} segments v.1 were found");
        $version = 1;
        $skipCabin = false;

        if (
            $segments->length > 0
            && $this->http->FindSingleNode("//table[@id = 'tblSegmentInfo']//tr[1]/th[4]") == 'Fare Types'
        ) {
            $skipCabin = true;
        }

        if ($segments->length == 0) {
            $segments = $this->http->XPath->query("//table[@id = 'tblSegInfo']//tr[td]");
            $this->logger->debug("Total {$segments->length} segments v.2 were found");
            $version = 2;
        }

        for ($i = 0; $i < $segments->length; $i++) {
            $s = $f->addSegment();
            $segment = $segments->item($i);
            $nextDay = null;
            $diff = null;
            $date = $this->http->FindSingleNode('th[1]/p[2]/text()[1]', $segment, true, "/([^(]+)/");
            $date = $this->dateFormat($date);
            $prevDate = null;

            if (!$date) {
                $date = $this->http->FindSingleNode('//table[@id = "tblSegmentInfo"]//tr[' . ($i) . ']/th[1]/p[2]/text()[1] | //table[@id = "tblSegInfo"]//tr[' . ($i) . ']/th[1]/p[2]/text()[1]', $segment, true, "/([^(]+)/");
                $this->logger->notice("[Date From prev node]: {$date}");
                $date = $this->dateFormat($date);

                if ($date && isset($prevDate)) {
                    $this->logger->notice("[Date From prev segment]: {$prevDate}");
                    $diff = strtotime($prevDate) - strtotime($date);
                    $this->logger->debug("[Diff]: {$diff}");
                    $this->logger->debug("[Diff]: " . strtotime($prevDate) . " - " . strtotime($date) . " = " . $diff);
                }

                if ($date && isset($prevDate, $diff) && $diff > 0 && $diff < 260000) {
                    $date = $prevDate;
                    $this->logger->notice("[Date From prev segment, step 2]: {$date}");
                } elseif (!$date && isset($prevDate)) {
                    $this->logger->notice("[Date From prev segment, 3 step]: {$prevDate}");
                    $date = $prevDate;
                }
            }

            $s->airline()
                ->name($this->http->FindSingleNode('td[1]/a/span | td[1]/p[contains(@class, "flight_number")]/span | td[1]/p[contains(@class,"airline")]/preceding-sibling::span', $segment, null, "/([A-Z]+)/"))
                ->operator($this->http->FindSingleNode('td[1]/p[contains(@class, "flight_number")]/following-sibling::p[contains(@class, "airline")] | td[1]/p[contains(@class, "airline")]', $segment, true, "/(.+)\s*DBA\s*/"), false, true)

                ->number($this->http->FindSingleNode('td[1]/a/span | td[1]/p[contains(@class, "flight_number")]/span  | td[1]/p[contains(@class,"airline")]/preceding-sibling::span', $segment, true, '/[A-Z](\d+)$/'));

            // Cabin
            $cabin = null;

            if ($skipCabin === false) {
                $cabin = $this->http->FindSingleNode('td[2]', $segment, true, '/([^\(]+)/i');

                if (strlen(trim($cabin)) == 2) {
                    $cabin = null;
                }
                $s->extra()->bookingCode($this->http->FindSingleNode('td[2]/div[1]', $segment, true, '/\(([^\)]{1})\)$/i'), true, true);
                $s->extra()->miles($this->http->FindSingleNode('td[2]/div[contains(text(), "Miles") and normalize-space(text()) != "0Miles"]', $segment, false), false, true);
            }// if ($skipCabin === false)
            // Stops
            $stops = $this->http->FindSingleNode('th//ul/li[2]/p', $segment);
            $stops = ((stripos($stops, 'Non-stop') !== false) || (stripos($stops, '직항') !== false)) ? 0 : null;
            $s->extra()
                ->aircraft($this->http->FindSingleNode('td[1]/div', $segment), true)
                ->cabin($cabin, false, true)
                ->status($this->http->FindSingleNode('td[last()]', $segment), true)
                ->stops($stops, false, true);

            // DepDate
            $depTime = $this->http->FindSingleNode('th//ul/li[1]/p[@class = "time"]', $segment);
            $depNextDay = $this->http->FindSingleNode('th//ul/li[1]/p[@class = "nextday"]', $segment, false, "/\+(\d)day/ims");
            $this->logger->debug('DepDate: ' . $date . ' ' . $depTime . ' / +' . $depNextDay);

            if ($depDate = strtotime($date . ' ' . $depTime)) {
                $this->logger->debug('DepDate: ' . $depDate);

                if ($depNextDay) {
                    $depDate = strtotime("+$depNextDay day", $depDate);
                }
                $this->logger->debug('DepDate: ' . $depDate);
            }// if ($depDate = strtotime($date.' '.$depTime))
            $s->departure()
                ->date($depDate);
            $departureName = $this->http->FindSingleNode('th//ul/li[1]/p[@class = "title"]', $segment);

            if ($departureName) {
                $s->departure()
                    ->name($departureName);
            }

            // ArrDate
            $arrTime = $this->http->FindSingleNode('th//ul/li[3]/p[@class = "time"]', $segment);
            $arrNextDay = $this->http->FindSingleNode('th//ul/li[3]/p[@class = "nextday"]', $segment, false, "/\+(\d)day/ims");
            $this->logger->debug('ArrDate: ' . $date . ' ' . $arrTime . ' / +' . $arrNextDay);

            if ($arrDate = strtotime($date . ' ' . $arrTime)) {
                $this->logger->debug('ArrDate: ' . $arrDate);

                if ($arrNextDay) {
                    $arrDate = strtotime("+$arrNextDay day", $arrDate);
                }
                $this->logger->debug('ArrDate: ' . $arrDate);
            }// if ($arrDate = strtotime($date.' '.$arrTime))

            if ($arrDate < $depDate && $depNextDay) {
                $arrDate = strtotime("+ 1 day", $arrDate);
            }
            $s->arrival()
                ->date($arrDate);
            $arrivalName = $this->http->FindSingleNode('th//ul/li[3]/p[@class = "title"]', $segment);

            if ($arrivalName) {
                $s->arrival()
                    ->name($arrivalName);
            }

            // DepCode
            $depCode = $this->http->FindSingleNode('//button[contains(text(), "' . $s->getDepName() . '")]/@data-departurecode');

            if ($s->getDepName()) {
                if (!$depCode) {
                    $depCode = $this->http->FindPreg("/departureAirport\":\"([A-Z]{3})\",\"departureAirportName\":\"" . addcslashes($s->getDepName(), '"/') . "/");
                }

                if (!$depCode) {
                    $depCodeRe = sprintf(
                        '/"departureAirport":"([A-Z]{3})","arrivalAirport":"\w+","departureAirportDesc":"%s","arrivalAirportDesc":"%s"/',
                        addcslashes($s->getDepName(), "/'"),
                        addcslashes($s->getArrName(), "/'")
                    );
                    $depCode = $this->http->FindPreg($depCodeRe);
                }
            }

            if (!$depCode) {
                $depCode = $this->http->FindHTMLByXpath('td[1]/a', '/fltdepairport=\"([A-Z]+)\"/ims', $segment);
            }

            if ($depCode) {
                $s->departure()->code($depCode);
            } elseif (
                ($arrivalName && !$departureName)
                || ($arrivalName && $departureName && !$depCode)
            ) {
                if (isset($airports[$departureName])) {
                    $s->departure()->code($airports[$departureName]);
                } else {
                    $s->departure()->noCode();
                }
            }

            // ArrCode
            $arrCode = $this->http->FindSingleNode('//button[contains(text(), "' . $s->getArrName() . '")]/@data-departurecode', $segment);

            if ($s->getArrName()) {
                if (!$arrCode) {
                    $arrCode = $this->http->FindPreg("/arrivalAirport\":\"([A-Z]{3})\",\"arrivalAirportName\":\"" . addcslashes($s->getArrName(), '"/()') . "/");
                }

                if (!$arrCode) {
                    $arrCodeRe = sprintf(
                        '/"arrivalAirport":"([A-Z]{3})","departureAirportDesc":"%s","arrivalAirportDesc":"%s"/',
                        addcslashes($s->getDepName(), "/'"),
                        addcslashes($s->getArrName(), "/'")
                    );
                    $arrCode = $this->http->FindPreg($arrCodeRe);
                }
            }

            if ($arrCode) {
                $s->arrival()->code($arrCode);
            } elseif (!$arrCode && $version === 2) {
                if (isset($airports[$arrivalName])) {
                    $s->arrival()->code($airports[$arrivalName]);
                } else {
                    $s->arrival()->noCode();
                }
            } elseif ($version === 1 && !$arrivalName && $departureName) {
                $s->arrival()->noCode();
            } elseif (isset($airports[$arrivalName])) {
                $s->arrival()->code($airports[$arrivalName]);
            }

            $prevDate = date('m/d/Y', $s->getArrDate());

            if ($s->getArrCode() === $s->getDepCode() && $s->getDepDate() === $s->getArrDate()) {
                $f->removeSegment($s);
            }
        }

        // segments v.3 - You have schedule changes

        if ($segments->length == 0) {
            $segmentsV3 = $this->http->XPath->query("//h4[normalize-space(text())='Itinerary']/../following-sibling::table[1][contains(@class,'tb_flight_info')]//tr[td]");

            if ($segmentsV3->length === 0) {
                $segmentsV3 = $this->http->XPath->query("//h5[normalize-space(text())='Itinerary after changes']/following-sibling::table[1][contains(@class,'tb_flight_info')]//tr[td]");
            }
            $this->logger->debug("Total {$segmentsV3->length} segments v.3 were found");

            for ($i = 0; $i < $segmentsV3->length; $i++) {
                $s = $f->addSegment();
                $segment = $segmentsV3->item($i);
                $date = $this->http->FindSingleNode('td[1]/p[2]/text()[1]', $segment, true, "/([^(]+)/");
                $this->logger->debug("[Date]: {$date}");
                $dateObj = DateTime::createFromFormat('Y.m.d', $date);

                if ($dateObj) {
                    $date = $dateObj->format("m/d/Y");
                }
                $this->logger->debug("[Date]: {$date}");

                $s->airline()
                    ->name($this->http->FindSingleNode('td[2]/a/span | td[2]/p[contains(@class, "flight_number")]/span', $segment, null, "/([A-Z]+)/"))
                    ->number($this->http->FindSingleNode('td[2]/a/span | td[2]/p[contains(@class, "flight_number")]/span', $segment, true, '/[A-Z](\d+)$/'));

                // Cabin
                $cabin = $this->http->FindSingleNode('td[3]', $segment, true, '/([^\(]+)/i');

                if (strlen(trim($cabin)) == 2) {
                    $cabin = null;
                }
                // Stops
                $stops = $this->http->FindSingleNode('th//ul/li[2]/p', $segment);
                $stops = (stristr($stops, 'Non-stop') || stristr($stops, '직항')) ? 0 : null;
                $s->extra()
                    ->aircraft($this->http->FindSingleNode('td[2]/div', $segment), true)
                    ->cabin($cabin, false, true)
                    ->bookingCode($this->http->FindSingleNode('td[3]/div[1]', $segment, true, '/\(([^\)]{1})\)$/i'))
                    ->stops($stops, false, true);

                // DepDate
                $depTime = $this->http->FindSingleNode('th//ul/li[1]/p[@class = "time"]', $segment);
                $this->logger->debug('DepDate: ' . $date . ' ' . $depTime);
                $s->departure()
                    ->code($this->http->FindHTMLByXpath('td[2]/a', '/fltdepairport=\"([A-Z]+)\"/ims', $segment))
                    ->name($this->http->FindSingleNode('th//ul/li[1]/p[@class = "title"]', $segment))
                    ->date2($date . ' ' . $depTime);

                // ArrDate
                $arrTime = $this->http->FindSingleNode('th//ul/li[3]/p[@class = "time"]', $segment);
                $nextDay = $this->http->FindSingleNode('th//ul/li[3]/p[@class = "nextday"]', $segment, false, "/\+(\d)day/ims");
                $this->logger->debug('ArrDate: ' . $date . ' ' . $arrTime . ' / +' . $nextDay);

                if ($arrDate = strtotime($date . ' ' . $arrTime)) {
                    $this->logger->debug('ArrDate: ' . $arrDate);

                    if ($nextDay) {
                        $arrDate = strtotime("+$nextDay day", $arrDate);
                    }
                    $this->logger->debug('ArrDate: ' . $arrDate);
                }// if ($arrDate = strtotime($date.' '.$arrTime))
                $arrivalName = $this->http->FindSingleNode('th//ul/li[3]/p[@class = "title"]', $segment);

                if (isset($airports[$arrivalName])) {
                    $s->arrival()->code($airports[$arrivalName]);
                } else {
                    $s->arrival()->noCode();
                }
                $s->arrival()
                    ->name($arrivalName)
                    ->date($arrDate);
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return true;
    }

    private function ParsePageHistory($startIndex, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $nodes = $this->http->XPath->query("//th[contains(text(), 'Date')]/ancestor::thead[1]/following-sibling::tbody/tr");
        $this->logger->debug("Found {$nodes->length} items");

        for ($i = 0; $i < $nodes->length; $i++) {
            $dateStr = $this->http->FindSingleNode("td[2]", $nodes->item($i));
            $postDate = strtotime(str_replace('.', '/', $dateStr));

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                continue;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Type'] = $this->http->FindSingleNode("td[3]", $nodes->item($i));
            $result[$startIndex]['Point of earning/using'] = $this->http->FindSingleNode("td[4]", $nodes->item($i));
            $result[$startIndex]['Tier at the time of earning'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
            $result[$startIndex]['Miles'] = $this->http->FindSingleNode("td[6]", $nodes->item($i));
            $expDate = $this->http->FindSingleNode("td[7]", $nodes->item($i));
            $result[$startIndex]['Expiration Date'] = strtotime(str_replace('.', '/', $expDate));
            $startIndex++;
        }

        return $result;
    }

    private function sensorSensorData()
    {
        $this->logger->notice(__METHOD__);
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $referer = $this->http->currentUrl();

        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return null;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9112511.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395094,6818374,1536,872,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.572259410286,802883409187,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,566,0;0,-1,0,0,493,-1,0;1,0,0,0,519,-1,0;0,-1,0,0,1375,-1,0;0,0,0,0,909,-1,0;0,-1,0,0,2078,2078,0;0,-1,0,0,1859,1859,0;0,-1,0,0,1353,-1,0;-1,2,-94,-102,0,-1,0,0,-1,566,0;0,-1,0,0,493,-1,0;1,0,0,0,519,-1,0;0,-1,0,0,1375,-1,0;0,0,0,0,909,-1,0;0,-1,0,0,2078,2078,0;0,-1,0,0,1859,1859,0;0,-1,0,0,1353,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://flyasiana.com/I/US/EN/viewLogin.do?callType=IBE&menuId=CM201802220000728453-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1605766818374,-999999,17178,0,0,2863,0,0,2,0,0,F356437164F1AC881CB221B88FEC0D26~-1~YAAQrrUQAiHPJNx1AQAAiSgp3wRjTX1se33psr2SptcBA44ksUqfJo/9z85jM72idgtPx+6kGl2v00xiVEAumJuaC5TEjFvkJzq5bKIhnYNlWI5OeVOXAPnCxZGpp1+os4dH/reTiQ9G+iHliDh0i7ZlrNnRjTseYuFKV7pZsjQX6qP3vVkri1lnn02vL15QZ9ewBr8aDwFz54EIpfjL6GYztzx1i/hAkkdrZ0yh2Vcm7iqDHpqEiEsDGEw8Y6YknZT0JRSwjH2VfrqqniKT8MdZ451O1oiB/aNVWUJbJPPyo7HAi1gHMdWScLM6~-1~-1~-1,30003,-1,-1,30261693,PiZtE,69064,110-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,920480172-1,2,-94,-118,94968-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            // 1
            "7a74G7m23Vrp0o5c9112511.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:82.0) Gecko/20100101 Firefox/82.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,395095,7230538,1536,872,1536,960,1536,412,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6005,0.734515682367,802883615269,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,566,0;0,-1,0,0,493,-1,0;1,0,0,0,519,-1,0;0,-1,0,0,1375,-1,0;0,0,0,0,909,-1,0;0,-1,0,0,2078,2078,0;0,-1,0,0,1859,1859,0;0,-1,0,0,1353,-1,0;-1,2,-94,-102,0,-1,0,0,-1,566,0;0,-1,0,0,493,-1,0;1,0,0,0,519,-1,0;0,-1,0,0,1375,-1,0;0,0,0,0,909,-1,0;0,-1,0,0,2078,2078,0;0,-1,0,0,1859,1859,0;0,-1,0,0,1353,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://flyasiana.com/I/US/EN/viewLogin.do?callType=IBE&menuId=CM201802220000728453-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1605767230538,-999999,17178,0,0,2863,0,0,4,0,0,3DA16824C1ABEFFC0AFDE9F20373BF4B~-1~YAAQtY5lX94Oo7F1AQAAenIv3wTn0QDxChX7n8UjTeSaVxVTuls6ZlSRJ3bHGFe3D8CaNb9I/MBDxNFU6ElPcGK6yy24FPxP81hNW6icW1NOpATwDPjexxS26d3ItN2NEbg3dofhIyYiErZ6qdl1KXMWSAn9vJLXnFZxCwZXdY/uYjZwvYyiX5jw0q+v7yXjyZsz3ON/LssmIzaKbLhNgSYSvT8SRVbzRjBm/ok9UnAUK2RqEHWeYG1yC5SJI+ji3gtQIuGAmemnsRX4m1EtKcvcifVx3u4CMzG20JH1HhhiIHPgamkWPhmUX/p6~-1~-1~-1,30011,-1,-1,26067385,PiZtE,29446,86-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,195224520-1,2,-94,-118,92161-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            // 2
            "7a74G7m23Vrp0o5c9112511.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:82.0) Gecko/20100101 Firefox/82.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,395095,7268239,1536,872,1536,960,1536,412,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6005,0.977197378488,802883634118.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,566,0;0,-1,0,0,493,-1,0;1,0,0,0,519,-1,0;0,-1,0,0,1375,-1,0;0,0,0,0,909,-1,0;0,-1,0,0,2078,2078,0;0,-1,0,0,1859,1859,0;0,-1,0,0,1353,-1,0;-1,2,-94,-102,0,-1,0,0,-1,566,0;0,-1,0,0,493,-1,0;1,0,0,0,519,-1,0;0,-1,0,0,1375,-1,0;0,0,0,0,909,-1,0;0,-1,0,0,2078,2078,0;0,-1,0,0,1859,1859,0;0,-1,0,0,1353,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://flyasiana.com/I/US/EN/viewLogin.do?callType=IBE&menuId=CM201802220000728453-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1605767268237,-999999,17178,0,0,2863,0,0,3,0,0,D7BE58D3D13D515D6B3F70DE8FA71404~-1~YAAQtY5lXxwPo7F1AQAACgYw3wTv95iX52FaJVkRXmmwFuQDXkod8tTr+gF1JFHmrs7pqdy4iekEMsAcE3X8ppEo2e2j/edS+6XCibYgYv9BP20Heo8m7gLx9wzw2u15uX3DtgqvODik0fC4HeKVAwO+ZG3dhQXn9STSdOlhqaqHmNvE69JPAsrEz0o/6xRqObFNmoHdgXjcUUMfHDdht8SJMVhZp9+aYbqgg2rRPnkWiR89ZZ3bM+1tqIICehFrHjutFNXisFS8W3fg60bbO7jwgjrCFNNMCKwU0QWwceyex5TiXdolDdqrbbyG~-1~-1~-1,30301,-1,-1,26067385,PiZtE,53867,54-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,196242513-1,2,-94,-118,92581-1,2,-94,-129,-1,2,-94,-121,;2;-1;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9112511.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395094,6818374,1536,872,1536,960,1536,407,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.767299164383,802883409187,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,566,0;0,-1,0,0,493,-1,0;1,0,0,0,519,-1,0;0,-1,0,0,1375,-1,0;0,0,0,0,909,-1,0;0,-1,0,0,2078,2078,0;0,-1,0,0,1859,1859,0;0,-1,0,0,1353,-1,0;-1,2,-94,-102,0,-1,0,0,-1,566,0;0,-1,0,0,493,-1,0;1,0,0,0,519,-1,0;0,-1,0,0,1375,-1,0;0,0,0,0,909,-1,0;0,-1,0,0,2078,2078,0;0,-1,0,0,1859,1859,0;0,-1,0,0,1353,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://flyasiana.com/I/US/EN/viewLogin.do?callType=IBE&menuId=CM201802220000728453-1,2,-94,-115,1,32,32,0,0,0,0,520,0,1605766818374,12,17178,0,0,2863,0,0,522,0,0,F356437164F1AC881CB221B88FEC0D26~-1~YAAQrrUQAijPJNx1AQAAAywp3wR7bPQT78hA7tKlvGXrtvBDgxp4FGNIyq3cxcM6LAnjw7tcqBtywGl7Hqv39Sbb9tUsgf6+IPbFDJcZHpW7lSUqy1OYJfGs79WizlFpfYZ5CYLs5iK4VI4SbD11u+Z3sRaRulmWjtt4HaJP3deCWLWf2cHbSM4yoY75jot1LoVgwpi5i8c8dwKFD0Z1b7tGGF7Rq7H+qezRbQIIKSSL58lwkg5wc65ZAOrqYT3pgL063bP2EMH58hJeZ6EbB9i7L5t5CQ8ezwc2+/NEIw77smOV6hEXItKMiEtazLbKvsJ/11CZGPUEXCn9x/1NF4OrAtO1Tq44~-1~-1~-1,32308,696,-1626085566,30261693,PiZtE,44185,27-1,2,-94,-106,9,1-1,2,-94,-119,40,36,39,40,46,95,55,8,273,5,5,5,9,328,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,920480172-1,2,-94,-118,100502-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,,,,0-1,2,-94,-121,;6;6;0",
            // 1
            "7a74G7m23Vrp0o5c9112511.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:82.0) Gecko/20100101 Firefox/82.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,395095,7230538,1536,872,1536,960,1536,412,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6005,0.07052779035,802883615269,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,566,0;0,-1,0,0,493,-1,0;1,0,0,0,519,-1,0;0,-1,0,0,1375,-1,0;0,0,0,0,909,-1,0;0,-1,0,0,2078,2078,0;0,-1,0,0,1859,1859,0;0,-1,0,0,1353,-1,0;-1,2,-94,-102,0,-1,0,0,-1,566,0;0,-1,1,0,493,-1,0;1,0,0,0,519,-1,0;0,-1,0,0,1375,-1,0;0,0,0,0,909,-1,0;0,-1,0,0,2078,2078,0;0,-1,0,0,1859,1859,0;0,-1,0,0,1353,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,0,659;-1,2,-94,-112,https://flyasiana.com/I/US/EN/viewLogin.do?callType=IBE&menuId=CM201802220000728453-1,2,-94,-115,1,32,32,0,0,0,0,741,0,1605767230538,8,17178,0,0,2863,0,0,742,0,0,3DA16824C1ABEFFC0AFDE9F20373BF4B~-1~YAAQtY5lX94Oo7F1AQAAenIv3wTn0QDxChX7n8UjTeSaVxVTuls6ZlSRJ3bHGFe3D8CaNb9I/MBDxNFU6ElPcGK6yy24FPxP81hNW6icW1NOpATwDPjexxS26d3ItN2NEbg3dofhIyYiErZ6qdl1KXMWSAn9vJLXnFZxCwZXdY/uYjZwvYyiX5jw0q+v7yXjyZsz3ON/LssmIzaKbLhNgSYSvT8SRVbzRjBm/ok9UnAUK2RqEHWeYG1yC5SJI+ji3gtQIuGAmemnsRX4m1EtKcvcifVx3u4CMzG20JH1HhhiIHPgamkWPhmUX/p6~-1~-1~-1,30011,853,-464908302,26067385,PiZtE,81292,111-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,200,200,400,200,0,0,200,200,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,195224520-1,2,-94,-118,95794-1,2,-94,-129,d6350cc6832ca216bbeb88243f8742dbb972e8f7f09960559265cf71b2842f75,2,0,,,,0-1,2,-94,-121,;11;6;0",
            // 2
            "7a74G7m23Vrp0o5c9112511.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:82.0) Gecko/20100101 Firefox/82.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,395095,7268239,1536,872,1536,960,1536,412,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6005,0.968180620484,802883634118.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,566,0;0,-1,0,0,493,-1,0;1,0,0,0,519,-1,0;0,-1,0,0,1375,-1,0;0,0,0,0,909,-1,0;0,-1,0,0,2078,2078,0;0,-1,0,0,1859,1859,0;0,-1,0,0,1353,-1,0;-1,2,-94,-102,0,-1,0,0,-1,566,0;0,-1,1,0,493,-1,0;1,0,0,0,519,-1,0;0,-1,0,0,1375,-1,0;0,0,0,0,909,-1,0;0,-1,0,0,2078,2078,0;0,-1,0,0,1859,1859,0;0,-1,0,0,1353,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://flyasiana.com/I/US/EN/viewLogin.do?callType=IBE&menuId=CM201802220000728453-1,2,-94,-115,1,32,32,0,0,0,0,541,0,1605767268237,6,17178,0,0,2863,0,0,541,0,0,D7BE58D3D13D515D6B3F70DE8FA71404~-1~YAAQtY5lXxwPo7F1AQAACgYw3wTv95iX52FaJVkRXmmwFuQDXkod8tTr+gF1JFHmrs7pqdy4iekEMsAcE3X8ppEo2e2j/edS+6XCibYgYv9BP20Heo8m7gLx9wzw2u15uX3DtgqvODik0fC4HeKVAwO+ZG3dhQXn9STSdOlhqaqHmNvE69JPAsrEz0o/6xRqObFNmoHdgXjcUUMfHDdht8SJMVhZp9+aYbqgg2rRPnkWiR89ZZ3bM+1tqIICehFrHjutFNXisFS8W3fg60bbO7jwgjrCFNNMCKwU0QWwceyex5TiXdolDdqrbbyG~-1~-1~-1,30301,796,-964788003,26067385,PiZtE,78749,35-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,200,200,0,0,0,0,0,0,0,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,196242513-1,2,-94,-118,95311-1,2,-94,-129,d6350cc6832ca216bbeb88243f8742dbb972e8f7f09960559265cf71b2842f75,2,cd15889e4b58585ec9f3c796725c752f6dd7926965daec54124da062a5aaf8e1,,,,0-1,2,-94,-121,;7;3;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
            "Origin"       => "https://flyasiana.com",
            "Referer"      => $this->http->currentUrl(),
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);

        return $key;
    }

    private function getAirports(): array
    {
        $result = [];
        $this->logger->notice(__METHOD__);
        $cache = Cache::getInstance()->get('asiana_aircodes');

        if ($cache !== false && is_array($cache) && count($cache) > 1) {
            return $cache;
        }
//        $this->http->GetURL("https://flyasiana.com/C/US/EN/travel/airport");
//        $names = $this->http->FindPregAll('/data-airportNm="([^"]+)" data-airportCd="[^"]+"/');
//        $codes = $this->http->FindPregAll('/data-airportNm="[^"]+" data-airportCd="([^"]+)"/');
//        if (count($names) === count($codes)) {
//            $result = array_combine($names, $codes);
//        }
//        $this->http->PostURL("https://flyasiana.com/I/US/EN/AreaAirportInfo.do?n\$eum=8891076650481174",
        $this->http->PostURL("https://flyasiana.com/I/US/EN/AreaAirportInfo.do",
            "seg=dep1&bizType=REV&depArrType=DEP&depAirport=&depArea=&tripType=RT&domIntType=",
            [
                "Accept"       => "application/json, text/javascript, */*; q=0.01",
                "Content-Type" => "application/x-www-form-urlencoded; charset=UTF-8",
                "Referer"      => "https://flyasiana.com/I/US/EN/RevenueRegistTravel.do",
            ]);
        $resJson = $this->http->JsonLog();

        if (isset($resJson->RouteCityAirportData)) {
            foreach ($resJson->RouteCityAirportData as $data) {
                if (isset($data->cityAirportDatas)) {
                    foreach ($data->cityAirportDatas as $cityAirport) {
                        $result[$cityAirport->airportName] = $cityAirport->airport;
                    }
                }
            }
        }
        Cache::getInstance()->set('asiana_aircodes', $result, 24 * 60 * 60);

        return $result;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $key = 'asiana_abck';
        $result = Cache::getInstance()->get($key);

//        if (!empty($result) && $this->attempt == 0) {
//            $this->logger->debug("set _abck from cache: {$result}");
//
//            $this->http->setCookie("_abck", $result, ".flyasiana.com");
//
//            return null;
//        }

        $auth_data = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefoxPlaywright();
            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['linux']];
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            /*
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            */
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://flyasiana.com/I/US/EN/viewLogin.do?callType=IBE&menuId=CM201802220000728453");
            $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "btnLogin"]'), 7);

            if ($this->AccountFields['Login2'] == 'Number') {
                $loginType_ACNO = $selenium->waitForElement(WebDriverBy::xpath('//label[@for = "loginType_ACNO"]'), 2);
                $this->savePageToLogs($selenium);

                if (!$loginType_ACNO) {
                    return false;
                }

                $loginType_ACNO->click();

                $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@placeholder=\"Enter Membership Number\"]"), 5);
            } else {
                $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@placeholder=\"Enter ID\"]"), 5);
            }

            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'txtPW']"), 5);
            $btnLogin = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "btnLogin"]'), 7);
            $this->savePageToLogs($selenium);

            if (!$login || !$pass || !$btnLogin) {
                return false;
            }

            $this->logger->debug("set login");
            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);

            $this->logger->debug("set pass");
            $pass->click();
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->logger->debug("click btn");
            $this->savePageToLogs($selenium);

            $btnLogin->click();

            $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log Out")] | //p[contains(@class, "col_red")]'), 15);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);

                /*
                if (!in_array($cookie['name'], [
                    //                    'bm_sz',
                    '_abck',
                ])) {
//                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);

                    continue;
                }

                $result = $cookie['value'];
                $this->logger->debug("set new _abck: {$result}");
                Cache::getInstance()->set($key, $cookie['value'], 60 * 60 * 20);

                $this->http->setCookie("_abck", $result, ".flyasiana.com");
                */
            }
        } catch (NoSuchDriverException | WebDriverException $e) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $auth_data;
    }

    private function generateUUID()
    {
        $d = time();
        $uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx';
        $uuid = preg_replace_callback('/[xy]/', function ($c) use (&$d) {
            $r = ($d + mt_rand(0, 15)) % 16 | 0;
            $d = floor($d / 16);

            return $c[0] == 'x' ? dechex($r) : dechex(($r & 0x7 | 0x8));
        }, $uuid);

        return $uuid;
    }
}
