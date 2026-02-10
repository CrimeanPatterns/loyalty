<?php

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\WeekTranslate;

class TAccountCheckerAna extends TAccountChecker
{
    use ProxyList;

    private $airports = [];
    private $currentItin = 0;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = "https://www.ana.co.jp/en/us/";

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://stmt.cam.ana.co.jp/psz/amcj/jsp/renew/mile/reference_e.jsp", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setCookie('isNotConf', '0/0', 'www.ana.co.jp');

        $this->http->GetURL("https://stmt.cam.ana.co.jp/psz/amcj/jsp/renew/mile/reference_e.jsp");

        if (!$this->http->ParseForm("f")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("cusnum", $this->AccountFields['Login']);
        $this->http->SetInputValue("logpass", $this->AccountFields['Pass']);
        $this->http->SetInputValue("login", "Login(Member's page)");

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // Confirmation post
        if ($this->http->ParseForm(null, "//form[contains(@action, 'include/amclogin_action.jsp')]")) {
            $this->http->PostForm();

            if ($this->http->FindPreg("/Your request cannot be accepted at this time due to heavy traffic or server maintenance./")) {
                $this->DebugInfo = "need to upd sensor_data";

                return false;
            }
        }
        /*
         * As of December 4, 2014, we have made it mandatory to verify your identity using a Web password, in order to enhance security.
         */
        if ($this->http->ParseForm("wpwparam")) {
            $this->http->PostForm();
            // Web password creation
            if ($this->http->FindSingleNode("//p[contains(text(), 'Please enter Web password.')]")
                && $this->http->FindNodes("//img[@alt = 'Web password Registration']/@alt | //span[contains(text(), 'Web Password Registration')]")
                && $this->http->ParseForm("input_ch_webpswd_en")) {
                throw new CheckException("All Nippon Airways (ANA Mileage Club) website is asking you to create your Web password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            } /*review*/
        }
        /*
         * We were unable to verify your membership number or password.
         * Please enter your 10-digit membership number.
         * **It is now mandatory to verify your identity using a Web password.**
         */
        if (
            $this->http->FindPreg("/We were unable to verify your membership number or password\.\s*<br>\s*Please enter your 10-digit membership number\.\s*<br>\s*\*\*It is now mandatory to verify your identity using a Web password\.\*\*/ims")
            || $this->http->FindPreg("/(We were unable to verify your membership number or password.<br>Please enter your 10-digit membership number\.)/")
            || $this->http->FindPreg("/(With your ANA number, you cannot use our service\.\s*<BR>For details, please contact the ANA Mileage Club Service Center\.)/")
        ) {
            throw new CheckException("We were unable to verify your membership number or password. Please enter your 10-digit membership number. **It is now mandatory to verify your identity using a Web password.**", ACCOUNT_INVALID_PASSWORD);
        }
        // Service for this card disabled
        if (
            $this->http->FindPreg('/This\s*service\s*is\s*not\s*available\s*for\s*this\s*particular\s*card./ims')
            || $this->http->FindPreg('/This service is not available for the provided card number\./ims')
        ) {
            throw new CheckException('This service is not available for this particular card.', ACCOUNT_PROVIDER_ERROR);
        }
        // The system may be undergoing maintenance
        if ($message = $this->http->FindPreg('/(?:The system may be undergoing maintenance|Currently, some pages on ANA SKY WEB are under system maintenance.)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $message = $this->http->FindPreg('/The customer number and password could not be verified/ims')
                ?? $this->http->FindPreg('/The customer information was not entered correctly\. Please confirm the information\./ims')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The ANA Number or Password(PIN) you entered was incorrect
        if ($message = $this->http->FindPreg("/The ANA Number or (?:4-digit\s*|\s*)Password\(PIN\) you entered was incorrect\./ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // For that reason,our services to you are suspended.
        if ($message = $this->http->FindPreg("/(For that reason,our services to you are suspended\.)/ims")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // The ANA Number or Password you entered was incorrect.
        if ($message = $this->http->FindPreg("/(The ANA Number or Password you entered was incorrect\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We were unable to verify your ANA number or password.
        if ($message = $this->http->FindPreg("/(We were unable to verify your ANA number or password\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//p[@class = 'login-name']")));
        // Balance - Mileage balance
        $this->SetBalance($this->http->FindSingleNode("//dd[@class = 'point-mile']"));
        // Total Premium points
        $this->SetProperty("TotalPremiumPoints", $this->http->FindSingleNode("//dd[@class = 'point-premium']"));
        // Premium Point
        $this->SetProperty('PremiumPoint', $this->http->FindSingleNode("//dd[@class = 'point-group']"));
        // Status
        $Status = $this->http->FindPreg('/src=\"\/wws_common-ver1\/images\/login\/members\/([a-z]+)\-logo/ims');
        $this->setStatus($Status);

        $this->http->GetURL("https://stmt.cam.ana.co.jp/psz/amcj/jsp/renew/mile/reference_e.jsp");

        // Your request cannot be accepted at this time due to heavy traffic or server maintenance
        if ($message = $this->http->FindPreg('/Your request cannot be accepted at this time due to heavy traffic or server maintenance/s')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/The address you entered in your profile may be incorrect./i")) {
            // provider bug fix (AccountID: 2083655)
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }// if ($message = $this->http->FindPreg("/The address you entered in your profile may be incorrect./i"))

        return true;
    }

    public function Parse()
    {
        // Balance - Mileage balance
        if (!$this->SetBalance($this->http->FindSingleNode("//dt[contains(text(), 'Mileage balance')]/following-sibling::dd[1]/strong", null, true, self::BALANCE_REGEXP_EXTENDED))) {
            /*
             * The requested service is temporarily unavailable.
             * More information may be available at the system maintenance information.
             * If not, please try again later.
             */
            if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'The requested service is temporarily unavailable.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if (!$this->SetBalance($this->http->FindSingleNode("//tr[th[contains(text(), 'Mileage balance')]]/td[1]/strong")))
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindPreg("/Hello ([^\.<]+)\. Thank you for visiting/ims")));
        //#
        $this->SetProperty("AccountNumber", $this->AccountFields['Login']);
        // Status
        $Status = $this->http->FindPreg('/src="https:\/\/www\.ana\.co\.jp\/amcservice\/members\/eng\/image\/service\/header\/n_([a-z]+)_mem/ims');
        $this->setStatus($Status);
        // Family Miles
        $this->SetProperty("FamilyMiles", $this->http->FindSingleNode("//h2[contains(text(), \"Family Mile Mileage Balance\")]/following-sibling::table//th[contains(text(), 'Family Mile')]/following-sibling::td[1]/strong/text()[1]"));
        // Total Premium points
        $this->SetProperty("TotalPremiumPoints", $this->http->FindSingleNode("//dt[contains(text(), 'Total Premium points')]/following-sibling::dd[1]/strong/text()[1]"));
        // Premium Points Previous year
        $this->SetProperty("PremiumPointsPrevYear", $this->http->FindSingleNode("//dt[contains(text(), 'Premium Points Previous year')]/following-sibling::dd[1]/strong/text()[1]"));
        // Total lifetime miles
        $this->SetProperty("TotalLifetimeMiles", $this->http->FindSingleNode("//th[p[contains(text(), 'Lifetime miles')]]/following-sibling::td/p/strong/text()[1]"));
        // Expiration date
        $this->logger->info('Expiration date', ['Header' => 3]);
        $nodes = $this->http->XPath->query('//tr[contains(@class, "mileage-expiration-date_total")]/td[normalize-space(text()) != "0 miles"]');
        $this->logger->debug("Total {$nodes->length} exp nodes were found");

        if ($nodes->length > 0) {
            $nodes = $this->http->XPath->query('//tr[contains(@class, "mileage-expiration-date_total")]//td');
            $this->logger->debug("Total {$nodes->length} exp nodes were found");

            for ($i = 0; $i < $nodes->length; $i++) {
                $x = $this->http->FindPreg("/(.+)\s+mile/", false, $nodes->item(0)->nodeValue);

                if ($x == 0) {
                    continue;
                }

                $d = strtotime(trim(str_replace(",", " 1,", str_replace('Valid until', '', $this->http->FindSingleNode('(//table[//tr[contains(@class, "mileage-expiration-date_total")]]/thead/tr/th[span])[' . ($i + 1) . ']')))));
                $this->SetProperty('ExpiringBalance', $x);

                if ($d !== false) {
                    $this->SetExpirationDate(strtotime("last day of", $d));
                }

                break;
            }
        }
        // refs #23004
        if ((!isset($this->Properties['ExpiringBalance']) && isset($i) && $i === 6) && $this->Balance > 0) {
            $this->http->GetURL("https://stmt.cam.ana.co.jp/psz/amcj/jsp/renew/mile/referenceDetail_e.jsp");
            $nodes = $this->http->XPath->query("//table[@class='mile-tbl']/descendant::tr[position() > 1]");
            $this->logger->debug("Total {$nodes->length} exp nodes were found");

            if ($message = $this->http->FindSingleNode("//strong[contains(normalize-space(text()), 'You have no mileage in your account.')]")) {
                $this->logger->notice(">>> " . $message);
            }
            $i = 0;

            while ($i < $nodes->length) {
                if ($x = $this->http->FindSingleNode("td[1]", $nodes->item($i), true, '/(.*?) miles/ims')) {
                    $x = str_replace(',', '', $x);

                    if ($x > 0) {
                        $this->SetProperty('ExpiringBalance', $x);
                        $exp = $this->http->FindSingleNode("th[1]", $nodes->item($i), true, '/until (.*)/ims');
                        $exp = str_replace(',', '', $exp);
                        $this->logger->debug("Exp.: " . $exp . " / " . strtotime($exp));

                        if (strtotime($exp)) {
                            $this->SetExpirationDate(strtotime("last day of", strtotime($exp)));
                        }
                        $i = $nodes->length;
                    }// if ($x > 0)
                }// if ($x = $this->http->FindSingleNode("td[1]", $nodes->item($i), true, '/(.*?) miles/ims'))
                $i++;
            }// while ($i < $nodes->length)
        }// if (!isset($this->Properties['ExpiringBalance']) && $this->Balance > 0)
    }

    public function ParseItineraries()
    {
        $result = [];

        if ($this->currentItin > 9) {
            $this->logger->notice('increaseTimeLimit=100');
            $this->increaseTimeLimit(100);
        }
        $this->airports = $this->getAirports();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.ana.co.jp/asw/global/include/wwsMembersOnlyList_g.jsp');
        $this->http->RetryCount = 2;
        // Network error 28
        if (strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false) {
            throw new CheckRetryNeededException(2, 1);
        }

        $randQueryString = $this->http->FindPreg("/var\s*randQueryString\s*=\s*\"\?([^\"]+)/");
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $this->http->GetURL('https://www.ana.co.jp/_shared-wws-top-oparate/tc1/us/e/data/members-mymenu.json', $headers);
        $data = $this->http->JsonLog(null, 0, true);

        if (isset($data['membersLink'][1]['childTabList'][0]['menuList'][0]['url'])) {
            $viewLink = $data['membersLink'][1]['childTabList'][0]['menuList'][0]['url'];
        } else {
            $viewLink = null;
        }

        if ($viewLink) {
            $randQueryString = $randQueryString ?? 'rand=' . date('YmdHis');
            // todo: prevent 403    // https://redmine.awardwallet.com/issues/16412#note-19
            $this->http->SetProxy($this->proxyReCaptcha(), false);
            $this->http->GetURL($viewLink . '&' . $randQueryString, $headers);
        }

        if ($msg = $this->http->FindSingleNode('//p[contains(text(), "No reservation could be found based on your membership number.")]')) {
            $this->logger->error($msg);

            return $this->noItinerariesArr();
        }

        if ($msg = $this->http->FindSingleNode('//form[contains(@id,\':cmnErrorMessageWindow:\')][contains(.,\'No reservation could be found based on your membership number\')]')) {
            $this->logger->error($msg);

            return $this->noItinerariesArr();
        }

        if ($msg = $this->http->FindPreg('/The AMC number you have entered to log in is not applicable for using this service. /')) {
            $this->logger->error($msg);

            return [];
        }

        if ($msg = $this->http->FindSingleNode("//div[@class='messageArea'][contains(.,'The AMC number you have entered to log in is not applicable for using this service')]")) {
            $this->logger->error($msg);

            return [];
        }

        if ($msg = $this->http->FindSingleNode("//div[@class='messageArea'][contains(.,'Sorry, your request could not be processed properly. Please try again later')]")) {
            $this->logger->error($msg);

            return [];
        }
        $this->handleJapanSite();

        $forms = $this->http->FindNodes('//input[@value = "Details"]/ancestor::form[1]');
        $this->logger->info(sprintf('Found %s itineraries', count($forms)));

        for ($index = 1; $index <= (count($forms) > 35 ? 35 : count($forms)); $index++) {
            if ($this->getItineraryDetails($viewLink, $index) === false) {
                continue;
            }
            /**
             * Selected reservation cannot be confirmed. (E_S01P03_0005).
             */
            if ($msg = $this->http->FindSingleNode("//li[contains(text(), 'Selected reservation cannot be confirmed. (E_S01P03_0005)')]")) {
                $this->logger->error($msg);

                continue;
            }
            /**
             * The selected reservation cannot be displayed.
             * Please contact your travel agency, or contact ANAThe page will open in a different window.
             * In the case of an external site, it may or may not meet accessibility guidelines..
             * Furthermore, if you have booked an inclusive tour (IT) fare through a travel agency,
             * it is possible to search with your ticket number from [Search by e-Ticket number].
             * Please try again from the [View Reservation] screen.(E_S01P02_0007).
             */
            if ($msg = $this->http->FindSingleNode("//strong[contains(text(), 'The selected reservation cannot be displayed. Please contact your travel agency, or contact ')]")) {
                $this->logger->error($msg);

                continue;
            }
            /*
            There is no reservation to display.

            [If this message appears on the Reservation List]
            This reservation may be displayed temporarily based on your internet usage history. However, it will be deleted automatically after a few hours.
            (E_S01P02_0012)
            */
            if ($msg = $this->http->FindSingleNode("//strong[contains(text(), 'There is no reservation to display.')]")) {
                $this->logger->error($msg);

                continue;
            }
            /**
             * This reservation cannot be processed online.
             * Quote the error code shown at the end of this message and please contact ANAThe page will open in a different window.
             * In the case of an external site, it may or may not meet accessibility guidelines..(E_S01P02_0003).
             */
            if ($msg = $this->http->FindSingleNode("//strong[contains(text(), 'This reservation cannot be processed online. Quote the error code shown at the end of this message and please contact')]")) {
                $this->logger->error($msg);

                continue;
            }
            /**
             * This reservation cannot be processed online.
             * Please contact ANA by phone and quote the error code shown at the end of this message.(E_S01P02_0003).
             */
            if ($msg = $this->http->FindSingleNode("//strong[contains(text(), 'This reservation cannot be processed online. Please contact')]")) {
                $this->logger->error($msg);

                continue;
            }

            if ($msg = $this->http->FindSingleNode("//li[contains(text(), 'This reservation cannot be processed online. Please contact')]")) {
                $this->logger->error($msg);

                continue;
            }
            /**
             * With regards to the operation of the browser's "Back" button and operation from a bookmarked screen, etc., there may be cases when the processing cannot be continued.
             * We apologize for the inconvenience, but please try again. (E_G02F25_0003).
             */
            if ($msg = $this->http->FindSingleNode("//li[contains(text(), 'With regards to the operation of the browser') and contains(text(), '\"Back\" button and operation from a bookmarked screen, etc')]")) {
                $this->logger->error($msg);

                continue;
            }
            /**
             * This reservation cannot be processed online.
             * Quote the error code shown at the end of this message and please contact ANAOpens in a new window.
             * In the case of an external site, it may or may not meet accessibility guidelines.. (E_S01P02_0003).
             */
            if (
                $this->http->FindSingleNode("//div[contains(text(), 'This reservation cannot be processed online. Quote the error code shown at the end of this message and please contact ')]")
                || strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
            ) {
                sleep(5);

                if ($this->getItineraryDetails($viewLink, $index) === false) {
                    continue;
                }
            }

            $result[] = $this->ParseItinerary();
        }

        // single itinerary
        if (!$forms && $this->http->FindSingleNode("//h1[contains(text(), 'Reservation Details')]")) {
            $this->logger->notice("single itinerary");
            $result[] = $this->ParseItinerary();
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation #",
                "Type"     => "string",
                "Size"     => 6,
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Size"     => 20,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 20,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://aswbe-i.ana.co.jp/international_asw/pages/servicing/reservation_confirm/login_search.xhtml?CONNECTION_KIND=LAX&LANG=en";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->removeCookies();
        $this->http->setHttp2(true);
        $this->airports = $this->getAirports();
        $this->http->GetURL($this->ConfirmationNumberURL($arFields), [], 40);

        if (
            $this->http->Response['code'] === 403
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after')
        ) {
            $this->http->removeCookies();
            $this->http->SetProxy($this->proxyDOP());
            $this->http->RetryCount = 0;
            $this->http->GetURL($this->ConfirmationNumberURL($arFields), [], 30);
            $this->http->RetryCount = 2;

            if (
                $this->http->Response['code'] === 403
                || strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            ) {
                $this->http->removeCookies();
                $this->http->SetProxy($this->proxyReCaptcha());
                $this->http->RetryCount = 0;
                $this->http->GetURL($this->ConfirmationNumberURL($arFields), [], 30);
                $this->http->RetryCount = 2;
            }
        }

        if (!$this->http->ParseForm('noMemberLogin')) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }
        $this->http->SetInputValue('recLoc', $arFields['ConfNo']);
        $this->http->SetInputValue('passportFirstName', $arFields['FirstName']);
        $this->http->SetInputValue('passportLastName', $arFields['LastName']);
        $this->http->SetInputValue('searchByRecLoc', 'Search');
        $this->http->PostForm();

        if ($message = $this->http->FindSingleNode('//div[(contains(@id, "cmnErrorMessageWindow") or contains(@id, "totalErrorMessageWindow")) and not(contains(., "Your reservation has been changed."))]')) {
            return $message;
        }

        if ($this->http->ParseForm(null, '//form[contains(@action, "reservation_confirm")]')) {
            $this->http->SetInputValue('detailMessages:0:detailRuleMessageCheckbox', 'on');
            $this->http->SetInputValue('forward', 'Next');
            $this->http->PostForm();
        }

        if ($msg = $this->http->FindSingleNode('//div[contains(text(), "The reservation cannot be confirmed.")]')) {
            return $msg;
        }
        $reservation = $this->ParseItinerary();

        if ($reservation) {
            $it = $reservation;
        } else {
            $this->sendNotification('failed to retrieve itinerary by conf #');
        }

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Used date"      => "PostingDate",
            "Flight number"  => "Info",
            "Details"        => "Description",
            "Boarding class" => "Info",
            "Fare type"      => "Info",
            "Earned miles"   => "Info",
            "Bonus miles"    => "Bonus",
            "Redeem miles"   => "Info",
            "Total"          => "Miles",
            "Premium Points" => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->http->GetURL("https://stmt.cam.ana.co.jp/psz/amcj/jsp/renew/mile/reference_e.jsp");
        $nodes = $this->http->XPath->query("//a[contains(@href, 'nextState')]/@href");
        $this->logger->debug("Total nodes found {$nodes->length}");

        for ($i = 0; $i < $nodes->length; $i++) {
            $this->logger->debug("[Page: {$i}]");
            $this->logger->debug("node " . $nodes->item($i)->nodeValue);
            $month = $this->http->FindPreg("/nextState\(\'\d+\'\,\'\d+\'\,\'(\d+)\'\)/ims", false, $nodes->item($i)->nodeValue);
            $type = $this->http->FindPreg("/nextState\(\'(\d+)\'\,\'\d+\'\,\'\d+\'\)/ims", false, $nodes->item($i)->nodeValue);
            $year = $this->http->FindPreg("/nextState\(\'\d+\'\,\'(\d+)\'\,\'\d+\'\)/ims", false, $nodes->item($i)->nodeValue);

            if (!$type || !$month || !$year) {
                $this->logger->error("skip -> {$nodes->item($i)->nodeValue}");

                continue;
            }

            if (isset($startDate) && strtotime("{$month}/01/{$year}") < strtotime(date("m/01/Y", $startDate))) {
                $this->logger->notice("break at date {$month}/01/{$year}");

                break;
            }

            $params = [
                'key_cnt'         => 'all',
                'key_refer_month' => $month,
                'key_refer_type'  => $type,
                'key_refer_year'  => $year,
                'key_start_e'     => '',
            ];
            $this->http->PostURL("https://stmt.cam.ana.co.jp/psz/amcj/jsp/renew/mile/reference_e.jsp#month", $params);
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        }

        $this->getTime($startTimer);

        return $result;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/Hello ([^\.<]+)\. Thank you for visiting/ims")) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // システムメンテナンス / System Maintenance
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Currently, some pages on ANA SKY WEB are under system maintenance. ')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Site maintenance
        if ($this->http->FindPreg('/Due\s*to\s*system\s*maintenance/ims')) {
            throw new CheckException('Thank you for choosing ANA. Due to system maintenance, the following services are not available at this time. We apologize for any inconvenience.', ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/(ANA Home Page Service is currently unavailable due to the system maintenance. We sincerely apologize for any inconvenience this may cause\.) <br><br>/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're sorry - the web server is currently busy or unavailable. Please try again later.
        if ($message = $this->http->FindPreg("/We're sorry - the web server is currently busy or unavailable\. Please try again later\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(normalize-space(text()), "Your request cannot be accepted at this time due to heavy traffic or server maintenance.")]')
            ?? $this->http->FindPreg("/Your request cannot be accepted at this time due to heavy traffic or server maintenance\./")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Request Timeout
        if ($this->http->Response['code'] == 408 && $this->http->FindSingleNode("//h1[contains(text(), 'Request Timeout')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error
        if ($this->http->Response['code'] == 500 && $this->http->FindSingleNode("//h1[contains(text(), 'Server Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function setStatus($Status)
    {
        switch ($Status) {
            case 'amc':
                $Status = 'Member';

                break;

            case 'sfc':
                $Status = 'Super Flyers Club';

                break;

            case 'platinum':
                $Status = 'Platinum';

                break;

            case 'dia':
                $Status = 'Diamond';

                break;

            default:
                $this->logger->notice("Unknown status: " . $Status);
        }
        $this->SetProperty("Status", $Status);
    }

    private function getItineraryDetails(string $viewLink, int $index)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL($viewLink);

        if ($this->http->FindPreg('/Operation timed out/', false, $this->http->Response['errorMessage'])) {
            $this->sendNotification('viewLink timeout // MI');

            return false;
        }

        if (!$this->http->ParseForm(null, "(//input[@value = 'Details'])[$index]/ancestor::form[1]")) {
            return false;
        }
        $name = $this->http->FindSingleNode("(//input[@value = 'Details'])[$index]/ancestor::form[1]/@name");
        $detailKey = sprintf('%s:detail', $this->http->FindPreg('/(\w+:\d+)/', false, $name));

        if ($detailKey) {
            $this->http->SetInputValue($detailKey, 'Details');
        }

        $this->increaseTimeLimit();
        $this->http->RetryCount = 0;
        $this->http->PostForm();
        $this->http->RetryCount = 2;
        $this->handleJapanSite();

        return true;
    }

    private function handleJapanSite()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->ParseForm(null, '//input[@value = "Next"]/ancestor::form[1]')) {
            $this->http->SetInputValue('forward', 'Next');
            $this->http->SetInputValue('detailMessages:0:detailRuleMessageCheckbox', 'on');
            $this->http->PostForm();
            $this->increaseTimeLimit();
        }
    }

    private function xpathQuery($query, ?DomNode $parent = null): DOMNodeList
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $parent);
        $this->logger->info("found {$res->length} nodes: {$query}");

        return $res;
    }

    private function getAirports()
    {
        $this->logger->notice(__METHOD__);
        $airports = Cache::getInstance()->get('ana_airports');

        if (!$airports) {
            $airports = $this->parseAirports();

            if ($airports) {
                Cache::getInstance()->set('ana_airports', $airports, 3600 * 24);
            }
        }

        return $airports;
    }

    private function parseAirports()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.ana.co.jp/module/air-booking/js/intwws_airport_json_EN.js');
        $text = $this->http->FindPreg('/var\s*json\s*=\s*(.+)$/s');
        $text = str_replace("'", '"', $text);
        $data = $this->http->JsonLog($text, 0, true);

        if (isset($data['data'])) {
            $data = $data['data'];
        } else {
            $this->sendNotification('failed to parse aiport codes // MI');

            return [];
        }

        $airports = [];

        foreach ($data as $datum) {
            $code = ArrayVal($datum, 'tlettr');
            $code = $this->http->FindPreg('/^([A-Z]{3})/', false, $code);
            $name = ArrayVal($datum, 'name');
            $airports[$name] = $code;
        }

        return $airports;
    }

    private function findAirCode($name)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("findAirCode -> " . $name);
        $code = null;

        if (empty($name)) {
            return $code;
        }

        if (!isset($this->airports[$name])) {
            $airport = $this->db->getAirportBy(['AirName' => $name]);

            if ($airport !== false) {
                $code = $airport['AirCode'];
            }

            if (empty($code)) {
                $airport = $this->db->getAirportBy(['AirName' => $name], true);

                if ($airport !== false) {
                    $code = $airport['AirCode'];
                }
            }// if (empty($code))

            if (empty($code)) {
                $bracketsName = $this->http->FindPreg('/\((.+?)\)/', false, $name);

                if ($bracketsName) {
                    $airport = $this->db->getAirportBy(['AirName' => $bracketsName], true);

                    if ($airport !== false) {
                        $code = $airport['AirCode'];
                    }

                    if (empty($code) && strlen($bracketsName) == 3) {
                        $airport = $this->db->getAirportBy(['AirCode' => $bracketsName], true);

                        if ($airport !== false) {
                            $code = $airport['AirCode'];
                        }
                    }
                }
            }

            if (empty($code)) {
                $airport = $this->db->getAirportBy(['CityName' => $name], true);

                if ($airport !== false) {
                    $code = $airport['AirCode'];
                }
            }

            if (empty($code)) {
                $code = $this->http->FindPreg("/\"airportCode\":\"([A-Z]{3})\",\"airportName\":\"{$name}\"/");
            }

            if (!empty($code)) {
                $this->airports[$name] = $code;
            }
        }

        if (isset($this->airports[$name])) {
            return $this->airports[$name];
        }
        $code = $this->http->FindPreg(sprintf('/"airportCode":"([A-Z]{3})","airportName":"%s"/', preg_quote($name)));

        if ($code) {
            return $code;
        }

        return $name;
    }

    private function ParseItinerary(): array
    {
        $this->logger->notice(__METHOD__);
        $res = ['Kind' => 'T'];
        // RecordLocator
        $res['RecordLocator'] = $this->http->FindSingleNode('//dt[contains(text(), "Reservation Number")]/following-sibling::dd[1]');
        $this->logger->info("[{$this->currentItin}] Parse Flight #{$res['RecordLocator']}", ['Header' => 3]);
        $this->currentItin++;

        if (empty($res['RecordLocator'])) {
            if ($msg = $this->http->FindSingleNode("//form[.//input[contains(@name,'cmnErrorMessageWindow')]]")) {
                $this->logger->error($msg);
                $this->sendNotification("check message// ZM");

                if (strpos($msg, 'The specified reservation cannot be confirmed') !== false) {
                    // skip reservation: The specified reservation cannot be confirmed as you are currently requesting a refund for it
                    return [];
                }
            }
        }
        // Total
        $total = $this->http->FindSingleNode('//div[@id = "paymentBreakdown"]//dt[normalize-space(text()) = "Total"]/following-sibling::dd[1]/em[1]');
        $res['TotalCharge'] = PriceHelper::cost($total);
        // Currency
        $res['Currency'] = $this->http->FindSingleNode('//div[@id = "paymentBreakdown"]//dt[normalize-space(text()) = "Total"]/following-sibling::dd[1]/span[1]');
        // SpentAwards
        $res['SpentAwards'] = Html::cleanXMLValue($this->http->FindSingleNode('//div[@id = "paymentBreakdown"]//dt[normalize-space(text()) = "Required mileage"]/following-sibling::dd[1]//em[@class = "price"]') . " " . $this->http->FindSingleNode('//div[@id = "paymentBreakdown"]//dt[normalize-space(text()) = "Required mileage"]/following-sibling::dd[1]//span[@class = "currencyCode"]'));
        // Passengers
        $passengers = $this->http->FindNodes('//th[contains(@id, "passportImmigrationInfo")]');
        $res['Passengers'] = array_map(function ($item) {
            return beautifulName($item);
        }, array_values(array_unique($passengers)));
        // TripSegments
        $segments = [];
        $nodes = $this->xpathQuery('//th[contains(@id, "itinerarySegNumber")]/ancestor::tr[1]');
        $dateUnix = null;
        $weekDay = null;

        foreach ($nodes as $node) {
            $seg = [];
            $dateInfo = $this->http->FindSingleNode('.//td[contains(@class, "itineraryDateCell")] | .//span[contains(@class, "visuallyHidden") and contains(text(), "(")]', $node);
            $day = $this->http->FindSingleNode('.//td[contains(@class, "itineraryDateCell")]/span[1]', $node);

            if (!$day) {
                $day = $this->http->FindSingleNode('.//span[contains(@class, "visuallyHidden") and contains(text(), "(")]', $node, true, "/\(([^\)]+)/");
            }
            $weekDay = $day ?: $weekDay;
            $date = $this->http->FindPreg('/(\w+\s+\d+)/', false, $dateInfo);
            $dateUnix = strtotime($date) ?: $dateUnix; // default to date from previous segment
            $this->logger->info(var_export([
                'date'     => $date,
                'weekDay'  => $weekDay,
                'dateUnix' => $dateUnix,
            ], true), ['pre' => true]);
            // DepDate
            $time1 = $this->http->FindSingleNode('.//div[contains(@class, "itineraryRoute")]/p[1]/span[contains(@class, "time")]', $node);
            $seg['DepDate'] = strtotime($time1, $dateUnix);
            $dateUnix = $seg['DepDate'] ?: $dateUnix; // default to date from previous segment
            // fixed year
            $weekNum = WeekTranslate::number1($weekDay);
            $seg['DepDate'] = EmailDateHelper::parseDateUsingWeekDay($seg['DepDate'], $weekNum);

            if (!$seg['DepDate'] && $date == 'Feb 29') {
                $this->logger->notice("fix for 29 Feb");
                $seg['DepDate'] = EmailDateHelper::parseDateRelative($time1 . " " . $date, strtotime("+1 year", strtotime($time1, $dateUnix)), false);
                $this->logger->info(var_export([
                    'Date'     => $time1 . " " . $date,
                    'DepDate'  => $seg['DepDate'],
                    'dateUnix' => strtotime($time1, $dateUnix),
                ], true), ['pre' => true]);
                $weekNum = WeekTranslate::number1($weekDay);
                $seg['DepDate'] = EmailDateHelper::parseDateUsingWeekDay($seg['DepDate'], $weekNum);
            }
            $dateUnix = $seg['DepDate'] ?: $dateUnix; // default to date from previous segment

            // ArrDate
            $time2 = $this->http->FindSingleNode('.//div[contains(@class, "itineraryRoute")]/p[2]/span[contains(@class, "time")]', $node);
            $seg['ArrDate'] = strtotime($time2, $dateUnix);
            // fixed year
            $this->logger->debug("[ArrDate] " . $seg['ArrDate'] . " ");
            $this->logger->debug("[DepDate] " . $seg['DepDate'] . " ");
            $this->logger->debug("[ArrDate] " . date('H:i d M', $seg['ArrDate']));
            $seg['ArrDate'] = EmailDateHelper::parseDateRelative(date('H:i d M', $seg['ArrDate']), strtotime(date("m/d/y", $seg['DepDate'])));

            if (strtotime('+6 months', $seg['DepDate']) < $seg['ArrDate']) {
                $seg['ArrDate'] = strtotime('-1 year', $seg['ArrDate']);
            }
            // DepName
            $seg['DepName'] = $this->http->FindSingleNode('.//div[contains(@class, "itineraryRoute")]/p[1]/span[contains(@class, "time")]/following-sibling::span/text()[last() and normalize-space(.)]', $node);
            // DepCode
            $seg['DepCode'] = $this->findAirCode($seg['DepName']);
            // ArrName
            $seg['ArrName'] = $this->http->FindSingleNode('.//div[contains(@class, "itineraryRoute")]/p[2]/span[contains(@class, "time")]/following-sibling::span/text()[last() and normalize-space(.)]', $node);
            // ArrCode
            $seg['ArrCode'] = $this->findAirCode($seg['ArrName']);

            // FlightNumber
            $flightInfo = $this->http->FindSingleNode('.//td[contains(@class, "itineraryFlight")]/text()[1]', $node);
            $seg['FlightNumber'] = $this->http->FindPreg('/^\s*\w{2}(\d+)/', false, $flightInfo);
            // AirlineName
            $seg['AirlineName'] = $this->http->FindPreg('/^\s*(\w{2})/', false, $flightInfo);
            // Operator
            $seg['Operator'] = $this->http->FindSingleNode('.//td[contains(@class, "itineraryFlight")]/a | .//td[contains(@class, "itineraryFlight")]/text()[contains(., "Operated by")]', $node, true, "/Operated\s*by\s*(.+)/ims");

            if (!$seg['Operator']) {
                $seg['Operator'] = $this->http->FindSingleNode('.//td[contains(@class, "itineraryFlight")]/img[not(contains(@alt, "Alliance"))]/@alt', $node, true, "/Operated\s*by\s*(.+)/ims");
            }
            $seg['Operator'] = preg_replace("/\s+DBA\s+.+/", "", $seg['Operator']);

            if (strstr($seg['Operator'], ' - ')) {
                $seg['Operator'] = $this->http->FindPreg("/\s+-\s+(.+)$/", false, $seg['Operator']);
            }
            // Aircraft
            $seg['Aircraft'] = $this->http->FindSingleNode('.//td[contains(@class, "itineraryFlight")]/span[1]', $node);
            // Seats
            $seats = $this->http->FindNodes('.//a[contains(@id, "seatNumber") and not(contains(text(), "--"))]', $node);

            if (!empty($seats)) {
                $seg['Seats'] = $seats;
            }
            // Cabin
            $cabin = explode(':', $this->http->FindSingleNode('.//td[contains(@class, "itineraryClass")]', $node));
            $seg['Cabin'] = $cabin[0];
            // BookingClass
            if (count($cabin) == 2) {
                $seg['BookingClass'] = $cabin[1];
            }
            $segments[] = $seg;
        }
        $res['TripSegments'] = $segments;

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($res, true), ['pre' => true]);

        return $res;
    }

    private function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//table[@id = 'meisaitable']//tr[td]");

        if ($nodes->length > 0) {
            $this->logger->debug("Found {$nodes->length} history items");

            for ($i = 0; $i < $nodes->length; $i++) {
                $dateStr = $this->http->FindSingleNode("td[1]", $nodes->item($i));
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    break;
                }
                $result[$startIndex]['Used date'] = $postDate;
                $result[$startIndex]['Flight number'] = $this->http->FindSingleNode("td[2]", $nodes->item($i));
                $result[$startIndex]['Details'] = $this->http->FindSingleNode("td[3]", $nodes->item($i));
                $result[$startIndex]['Boarding class'] = $this->http->FindSingleNode("td[4]", $nodes->item($i));
                $result[$startIndex]['Fare type'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
                $result[$startIndex]['Earned miles'] = $this->http->FindSingleNode("td[6]", $nodes->item($i));
                $result[$startIndex]['Bonus miles'] = $this->http->FindSingleNode("td[7]", $nodes->item($i));
                $result[$startIndex]['Redeem miles'] = $this->http->FindSingleNode("td[8]", $nodes->item($i));
                $result[$startIndex]['Total'] = $this->http->FindSingleNode("td[9]", $nodes->item($i));
                $result[$startIndex]['Premium Points'] = $this->http->FindSingleNode("td[10]", $nodes->item($i));
                $startIndex++;
            }
        }

        return $result;
    }

    /*
    private function getSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensor = [
            "7a74G7m23Vrp0o5c9001861.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,386171,7869983,1440,829,1440,900,1440,425,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8932,0.240040551120,784748934991,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,566,0;0,-1,0,0,1250,668,0;1,-1,0,0,1465,883,0;-1,2,-94,-102,0,0,0,0,630,566,0;0,-1,0,0,1250,668,0;1,-1,0,0,1465,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ana.co.jp/en/us/-1,2,-94,-115,1,1,0,0,0,0,0,2,0,1569497869982,-999999,16790,0,0,2798,0,0,4,0,0,FE7EFCDA2D36CD71DD2C24644297EFC0~-1~YAAQvVAXAjVoqxxtAQAAPOFcbQL069/AzUjLHCLJ2IstLcXCE1nsOOYI6AVdFXlCR0OGhmM9P3jzzeF1c/ZhNfN24JMHV1pEFtjs3/zs5l56fmcJM7RNd3smSTsYSOXcr0Wvedvw9uE+h397N6Yq/2UOrrt63oALgikXmcV+iN4CjrPgnZJPvdTWK5H72zfm9Ia3mP0Z9UK2qtdxBiNOwMdUZaUGM+Yqmrdl7dBbT3C+Bk5NWLU87Q0HonwNdru/IAmeTSvlU2Qmrd3aERxPV7ep1mPzNqE4ZYQV~-1~-1~-1,27546,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,70829824-1,2,-94,-118,76862-1,2,-94,-121,;2;-1;0",
            "7a74G7m23Vrp0o5c9001861.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,386171,7890562,1440,829,1440,900,1440,425,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8932,0.766543799383,784748945281,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,566,0;0,-1,0,0,1250,668,0;1,-1,0,0,1465,883,0;-1,2,-94,-102,0,0,0,0,630,566,0;0,-1,0,0,1250,668,0;1,-1,0,0,1465,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ana.co.jp/en/us/-1,2,-94,-115,1,1,0,0,0,0,0,2,0,1569497890562,-999999,16790,0,0,2798,0,0,3,0,0,B215DD63029A6330D8A82E7EE56625FE~-1~YAAQvVAXAuZoqxxtAQAA5DFdbQKWkj0lfre55gCBlT0vPmhJ9YCoJQaWeCf++G8a0tFWlBkAo2k9v72hMNCZ3VB0H2Grlj9jaSI3GmaonikFoVV5UPs9MQaYFZXMaqxxO1B5Rp/yNgI1MFder3Bf9yhPHf5fMbpfF4D5JUaV8JJTMzmncB/yW0EG2wVVcRVN51S0G0TMCcKYnbebslRfRCmvyT0/6xfDxUadn6RLCSx1hjpS0FWKiTxRxCw2BZ2k7J5ieVod+VJQjzWKPkFkp2E9zvDjOA1rVkZy~-1~-1~-1,27563,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,7890568-1,2,-94,-118,76892-1,2,-94,-121,;2;-1;0",
            "7a74G7m23Vrp0o5c9001861.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,386171,8065936,1440,829,1440,900,1440,425,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8932,0.13983091169,784749032968,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,566,0;0,-1,0,0,1250,668,0;1,-1,0,0,1465,883,0;-1,2,-94,-102,0,0,0,0,630,566,0;0,-1,0,0,1250,668,0;1,-1,0,0,1465,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ana.co.jp/en/us/-1,2,-94,-115,1,1,0,0,0,0,0,2,0,1569498065936,-999999,16790,0,0,2798,0,0,3,0,0,F01099B30272190B34BEB95BDC7D5923~-1~YAAQvVAXAmFuqxxtAQAAFN9fbQJ2M4eLGS3YeGExxpFRI+I2GMGq0c4q80fyBlzSlPeNVBynMtltFW2hZeCakcfmLuMVqRtcScVPMHhtcVoQ9dhcrxTfIJAi6P6RFUL3xQIwCQR+7hXh3QgP4dKQzJIJFHnIX83u9UerSNgU/6jms6tzbRPlW09sxh4vFNVx+axOcGwTM9qEftX55nXgA+a7k4CMdbodKmcuYNpBJTgkVHTMO2jRF8S+Raono9Q/7fAGDoRoehOly3V5CixCmuSLhvFdswslubil~-1~-1~-1,28161,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,8065934-1,2,-94,-118,77417-1,2,-94,-121,;2;-1;0",
            "7a74G7m23Vrp0o5c9001861.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,386171,8082841,1440,829,1440,900,1440,425,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8932,0.553343514276,784749041420,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,566,0;0,-1,0,0,1250,668,0;1,-1,0,0,1465,883,0;-1,2,-94,-102,0,0,0,0,630,566,0;0,-1,0,0,1250,668,0;1,-1,0,0,1465,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ana.co.jp/en/us/-1,2,-94,-115,1,1,0,0,0,0,0,2,0,1569498082840,-999999,16790,0,0,2798,0,0,3,0,0,E74BF5E1BC5B6AFEBEE9A8EF7596345F~-1~YAAQvVAXApJvqxxtAQAAfCFgbQJHrp/HvhzEnrSmETtgsGVoyd8gDrJgNWF3W7kULPWa1+9rjUrlBsluvLajEB7EaukoXS2xHAnse5SOyB84TNCishdUeVNNguXCWgIdX2bgLb7ty0Ancy4FUj1Ha30Lv+C6lyc89f328ovStLpX1j9cb914jdIGFi4xfo6L/DXD8OM9xOoCKnem2rq0vyaomFdyLz+ww3yUWPyFWcJuS1eskhdmktMSLVul6/J7OzjYmuTH2pkJs0dpcFwTPWbR+GWdYHG10rKP~-1~-1~-1,28418,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,3273551388-1,2,-94,-118,77695-1,2,-94,-121,;2;-1;0",
            "7a74G7m23Vrp0o5c9001861.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,386171,8099713,1440,829,1440,900,1440,425,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8932,0.825250468412,784749049856,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,0,0,0,630,566,0;0,-1,0,0,1250,668,0;1,-1,0,0,1465,883,0;-1,2,-94,-102,0,0,0,0,630,566,0;0,-1,0,0,1250,668,0;1,-1,0,0,1465,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ana.co.jp/en/us/-1,2,-94,-115,1,1,0,0,0,0,0,2,0,1569498099712,-999999,16790,0,0,2798,0,0,3,0,0,70DEEA07B7ECF388DC1C291229FD9314~-1~YAAQvVAXAmZwqxxtAQAALmNgbQKnoG0wDzf8vvlzU3x94DDim/5WBXtliRXOVMURgYip0y0/Ce+8m2pQTmdG1k1nWpe7ns+3B7XisKXRjnNtvns4Q8eEfMZUkhry0hD1vivwzTeDI/gD3ao/2oFexVINpvX6YsdRHw5MMd0zN9j/hhNUlsvtxXipAt6jJ5tgSv3oyIBtrlk73/cgr7zvMiXGGab5JVJK1JHzJW3JpV0aIuEbl+aGX836itfHfDVXSgYXecE/hX232z8GRkdq3eA0UUCpc8O8Wlzv~-1~-1~-1,28224,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,8099723-1,2,-94,-118,77528-1,2,-94,-121,;2;-1;0",
            "7a74G7m23Vrp0o5c9001861.43-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0 Safari/605.1.15,uaend,11011,20030107,en-US,Gecko,1,0,0,0,386171,7998316,1440,829,1440,900,1223,392,1223,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:0,bat:0,x11:0,x12:1,8823,0.03271177616,784748999158,loc:-1,2,-94,-101,do_dis,dm_dis,t_dis-1,2,-94,-105,0,0,0,0,630,566,0;0,-1,0,0,1250,668,0;1,-1,0,0,1465,883,0;-1,2,-94,-102,0,0,0,0,630,566,0;0,-1,0,0,1250,668,0;1,-1,0,0,1465,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.ana.co.jp/en/us/-1,2,-94,-115,1,1,0,0,0,0,0,1,0,1569497998316,-999999,16790,0,0,2798,0,0,1,0,0,4F8830C31C259EFA87499B95624CC1F2~-1~YAAQvVAXAvJrqxxtAQAAftRebQJUO+UORAugHHMTKqcXNUMeUJojffeLNLc8dL+kY/qVvndJRDy5klCVUdsHoWe692zjldSsPff13G7BiXtJ/yi8DFJ3DZT4s2t82wDs09v6qp7b9DBYRz2vsC3xuypRBXYr0NW5RjUZYHXL6QKLwBmEMp2BKqi0wAihubMVEXKANIxv8zBw9j5pgak+qSpn5w4mMFT9Pwfd5nC/4wwINaGxXOvAKgYBpTt1RMZyxJmcP/TrVpTShZVU0qkZlFu+CELXdBXUUDsy~-1~-1~-1,28093,-1,-1,26018161-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,71984773-1,2,-94,-118,77563-1,2,-94,-121,;1;-1;0",
        ];

        $sensor_data = $sensor[array_rand($sensor)];

        return $sensor_data;
    }
    */
}
