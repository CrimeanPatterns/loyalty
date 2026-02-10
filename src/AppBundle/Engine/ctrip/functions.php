<?php

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Component\Field\Field;

class TAccountCheckerCtrip extends TAccountChecker
{
    use PriceTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    private $seleniumURL;

    public static function GetAccountChecker($accountInfo)
    {
        //if (in_array($accountInfo['Login'], ['veresch80@yahoo.com','ve30@hotmail.com'])) {
        require_once __DIR__ . '/TAccountCheckerCtripSelenium.php';

        return new TAccountCheckerCtripSelenium();
        //}

        return new static();
    }

    public static function FormatBalance($fields, $properties)
    {
        if ((isset($properties['SubAccountCode']) && (strstr($properties['SubAccountCode'], "ctripCmoney")))
            || (isset($properties['Currency']) && 'CNY' == $properties['Currency'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "CNY %0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        // $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $result = $this->loginSuccessful();
        $this->http->RetryCount = 2;

        if ($result) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        //$this->http->GetURL("http://english.ctrip.com");

        if (!$this->selenium()) {
            return false;
        }
        /*$this->http->GetURL("https://accounts.ctrip.com/global/english/signin");

        if ($this->http->Response['code'] != 200 && $this->http->FindPreg("/您要找的资源已被删除、已更名或暂时不可用。/"))
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

//        $captcha = $this->parseCaptcha();
//        if ($captcha === false)
//            return false;
//        $form['Captcha'] =  $captcha;
        $form['Captcha']  = '';
        $form['CodeType'] = '4';
        // Check Credentials
        if (!$this->checkCredentials($form))
            return false;
        $this->http->Form                   = array();
        $this->http->Form['Captcha']        = $form['Captcha'];
        $this->http->Form['CodeType']       = $form['CodeType'];
        $this->http->FormURL                = "https://accounts.ctrip.com/global/english/Check";
        $this->http->Form['UserName']       = $this->AccountFields['Login'];
        $this->http->Form['Password']       = $this->AccountFields['Pass'];
        $this->http->Form['uid']            = '';
        $this->http->Form['hidToken']       = '';
        $this->http->Form['oid']            = '';
        $this->http->Form['responsemethod'] = 'GET';*/

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://accounts.ctrip.com/global/english/Membercenter/ProfileInfo/Profile';

        return $arg;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//input[@id="msg"]/@value')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // What is error-06 ???
        if ($this->http->FindPreg('/\s*(error-06)\s*<!DOCTYPE/ims')) {
            throw new CheckException("System error", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        return false;
    }

    public function Parse($host = null)
    {
        if (!isset($host)) {
            $host = $this->http->getCurrentHost();
            $this->logger->debug("host: $host");
        }

        $response = $this->http->JsonLog(null, 0);
        $this->SetProperty("Name", beautifulName(trim($response->data->givenName . ' ' . $response->data->surName)));

        if ($host == 'ct.ctrip.com') {
            $this->logger->notice("Corporate account");
            $this->http->GetURL("http://english.corp.ctrip.com/member/memberinfo.asp");
            // Name
            $this->SetProperty("Name", $this->http->FindSingleNode("//th[contains(text(), 'Name:')]/following-sibling::td[1]"));
            // Ctrip card No
            $this->SetProperty("ID", $this->http->FindSingleNode("//th[contains(text(), 'Ctrip card No:')]/following-sibling::td[1]"));
            // Sign-up date
            $this->SetProperty("Since", $this->http->FindSingleNode("//th[contains(text(), 'Sign-up date:')]/following-sibling::td[1]"));
        } else {
            $this->logger->notice("Personal account");

            $this->http->GetURL("https://us.trip.com/safecenter/accountpassword");
            // Member since
            $this->SetProperty("Since", $this->http->FindSingleNode("//label[contains(text(), 'Member since')]/following-sibling::div[1]"));

            $this->http->GetURL("https://us.trip.com/customer/tierpoints");
            // Tier Points
            $this->SetProperty("TierPoints", $this->http->FindSingleNode("//span[contains(text(), 'Tier Points')]/following-sibling::span[1]"));

            // Trip Coins
            $this->logger->info('Trip Coins', ['Header' => 3]);
            $headers = [
                'Accept'       => '*/*',
                'Content-Type' => 'application/json',
            ];
            $this->http->PostURL('https://us.trip.com/restapi/soa2/14184/bjjson/getUserProperty', '{"head":{"currency":"USD","locale":"en_us"}}', $headers);
            $response = $this->http->JsonLog();
            $this->SetProperty("TripCoins", $response->coinsToAmountForShow ?? null);
            // level
            $this->SetProperty("Level", $response->gradeName ?? null);

//            $this->http->GetURL('https://www.trip.com/customer/points');

            /*
            $this->http->PostURL('https://www.trip.com/m/commonapi/proxy/14184/bjjson/getUserPoints', '{"head":{"locale":"en-US","currency":"USD"}}', $headers);
            $response = $this->http->JsonLog();
            */

            $coins = $response->coins ?? null;
            $this->SetBalance($coins);
            $coinsForShow = str_replace(',', '', $response->coinsForShow ?? null);

            if (property_exists($response, 'coinsForShow') && $coins != $coinsForShow) {
                $this->sendNotification('possible pending points // BS');
            }

            // Expiration date // refs #19483
            $this->logger->info('Expiration date', ['Header' => 3]);

            $this->http->PostURL('https://us.trip.com/restapi/soa2/14184/bjjson/getUserPointList', '{"head":{"locale":"en-US","currency":"USD"},"pageIndex":1,"pageSize":10}', $headers);
            $response = $this->http->JsonLog(null, 2);
            $userPointsList = $response->userPointsList ?? [];
            $expList = [];

            foreach ($userPointsList as $userPoints) {
                if (empty($userPoints->expireTimeDisaply)) {
                    $this->logger->notice("expireTimeDisaply not found");

                    continue;
                }
                $date = $this->http->FindPreg("/\s+on\s+(.+)/", false, $userPoints->expireTimeDisaply);

                if (!$date) {
                    $this->logger->notice("date not found");

                    continue;
                }
                $date = strtotime($date);

                if (!isset($expList[$date])) {
                    $expList[$date] = $userPoints->earnedAmount;
                } elseif (isset($expList[$date])) {
                    $expList[$date] += $userPoints->earnedAmount;
                }
            }// foreach ($userPointsList as $userPoints)
            $this->logger->debug(var_export($expList, true), ['pre' => true]);
            ksort($expList);
            $this->logger->debug(var_export($expList, true), ['pre' => true]);

            if (!empty($expList)) {
                $this->SetExpirationDate(key($expList));
                // Expiring balance
                $this->SetProperty("ExpiringBalance", current($expList));
            }
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $auth = $this->http->getCookieByName('cticket', '.trip.com');
        $this->http->PostURL(
            'https://www.trip.com/restapi/soa2/10098/GetOrderWithBM.json',
            '{"OrderStatusClassify":"All","PageIndex":1,"PageSize":15,"ClientVersion":"99.99","Channel":"IBUOnline","Locale":"en-XX","head":{"cid":"09031045214967373362","ctok":"","cver":"1.0","lang":"01","sid":"8888","syscode":"09","auth":"' . $auth . '","extension":[{"name":"protocal","value":"https"},{"name":"sequence","value":"16245295430403286"}]}}',
            [
                'Accept'       => 'application/json, text/plain, */*',
                'Content-Type' => 'application/json;charset=UTF-8',
            ]
        );
        $response = $this->http->JsonLog(null, 2);

        if (empty($response->OrderEnities)) {
            // no itineraries
            if (
                isset($response->TotalCount)
                && $response->TotalCount == 0
                && $this->http->FindPreg("/\{\"OrderEnities\":\[\].*?,\"TotalCount\":0/")
            ) {
                return $this->noItinerariesArr();
            }

            return $result;
        }// if (empty($response->OrderEnities))

        if (isset($response->TotalCount)) {
            $this->logger->debug("Total {$response->TotalCount} itineraries were found");
        }

        foreach ($response->OrderEnities as $item) {
            if ($item->OrderStatusCode == 'HOTEL_CANCELLED') {
                $this->logger->info("Parse Hotel #{$item->OrderID}", ['Header' => 3]);
                $h = $this->itinerariesMaster->createHotel();
                $h->general()->confirmation($item->OrderID);
                $h->general()->status(beautifulName($item->OrderStatusName));
                $h->general()->cancelled();
                $this->logger->info('Parsed Itinerary:');
                $this->logger->info(var_export($h->toArray(), true), ['pre' => true]);
            }

            $actions = $item->OrderActions ?? [];

            if (empty($actions)) {
                $this->logger->error('As a rule, these are reservations without details in the status of waiting for something');

                continue;
            }

            $url = null;

            foreach ($actions as $action) {
                $code = $action->StandardCode ?? null;

                if ($code === 'Detail') {
                    $url = $action->ActionURLH5 ?? null;

                    break;
                }
            }

            if (!$url) {
                $this->sendNotification('check itinerary urls // MI');

                break;
            }
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);

            /*if ($this->http->FindSingleNode("//p[contains(text(),'加载错误，请稍后再试试吧')]")) {
                $this->logger->error("Skip {$item->BizType}: Loading error, please try again later");

                continue;
            }*/

            if (isset($item->OrderID)) {
                $this->logger->notice("BizType: $item->BizType");

                switch ($item->BizType) {
                    case 'Lipin':
                    case 'Activity':
                        $this->logger->notice("Skip boarding pass / voucher");

                        break;

                    case 'FlightDomestic':
                    case 'FlightInternational':
                    case 'FlightHotel':
                        $headers = [
                            'Accept'       => 'application/json, text/plain, */*',
                            'Content-Type' => 'application/json;charset=UTF-8',
                        ];
                        $data = '{"oid":"' . $item->OrderID . '","opType":6,"ver":271,"flag":0,"channel":"flightdetail","head":{"cid":"09031045214967373362","ctok":"","cver":"1.0","lang":"01","sid":"8888","syscode":"09","auth":null,"locale":"en_xx","extension":[{"name":"pvid","value":"9"},{"name":"sid","value":"1"},{"name":"vid","value":"1624529100307.3fi0bt"},{"name":"ts","value":"1624530091374"},{"name":"isOnline","value":"T"},{"name":"clienttype","value":"trip.com"},{"name":"i18n.locale","value":"en_xx"},{"name":"transId","value":"1003566d-31cd-4752-acf9-ec7f4d18bba5"},{"name":"protocal","value":"https"}]},"contentType":"json"}';
                        $this->http->PostURL('https://www.trip.com/restapi/soa2/12923/flightOrderDetailSearch?_fxpcqlniredt=09031045214967373362',
                            $data, $headers);
                        $detail = $this->http->JsonLog(null, 2, false, 'flight');

                        if (isset($detail->errMsg) && $detail->errMsg == 'This booking does not exist') {
                            $this->sendNotification("{$detail->errMsg} // MI", 'awardwallet');

                            break;
                        }
                        $this->fetchJsonBookingFlight($detail);

                        break;

                    case 'HotelDomestic':
                    case 'HotelInternational':
                        $order =
                            $this->http->FindPreg('#window.IBU_HOTEL=\s*(\{.+?\});\s+//SET_IBU_HOTEL_END;#s')
                            ?? $this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json"[^>]*>(.+?)</script>#')
                        ;
                        $order = $this->http->JsonLog($order, 2, false, 'order');

                        $this->fetchJsonBookingHotel($item, $order);

                        break;

//                    case 'RailsIntl':
//                        // no number train
//                        $this->fetchBookingRail($item);
//
//                        break;
                    case 'RailsIntl':
                    case 'Train':
                        $order = $this->http->FindPreg('/__NEXT_DATA__\s*=\s*(\{.+?\});__NEXT_LOADED_PAGES__/s');

                        if (!$order) {
                            $order = $this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json" crossorigin="anonymous">(.+?)</script>#');
                        }

                        $detail = $this->http->JsonLog($order, 2, false, 'order');

                        $this->fetchJsonBookingTrain($item, $detail);

                        break;

                    case 'Car':
                        $order = $this->http->FindPreg('/window.orderDetails\s*=\s*(\{.+?\});\n/s');
                        $order = $this->http->JsonLog($order, 2, false, 'order');

                        if (!$order) {
                            $props = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json" crossorigin="anonymous">(.+?)</script>#'), 1);
                            $data = '{
                              "orderId": "' . $props->query->envObj->routerObj->orderNumber . '",
                              "baseRequest": {
                                "sourceFrom": "' . $props->props->basicinfo->sourceFrom . '",
                                "channelId": "' . $props->props->basicinfo->channelId . '",
                                "locale": "' . $props->props->basicinfo->culture->locale . '",
                                "currencyCode": "' . $props->props->basicinfo->culture->currency . '",
                                "site": "' . $props->props->basicinfo->culture->site . '",
                                "language": "' . $props->props->basicinfo->culture->language . '",
                                "sessionId": null,
                                "sourceCountryId": ' . ($props->props->basicinfo->sourceCountryId ?? $props->props->initialReduxState->residency->sourceCountryId) . ',
                                "allianceInfo": {
                                  "allianceId": "' . $props->props->basicinfo->union->allianceId . '",
                                  "ouid": "' . ($props->props->basicinfo->union->ouid ?? '') . '",
                                  "sid": "' . $props->props->basicinfo->union->sid . '"
                                },
                                "extraMaps": {
                                  "sourceFrom": "' . $props->props->initialReduxState->basic->sourceFrom . '",
                                  "channelId": "' . $props->props->basicinfo->channelId . '",
                                  "karabiVersion": "1",
                                  "snapshotVersion": "online/v1",
                                  "kayakInfo": "",
                                  "testStatus": "0",
                                  "poiNewVersion": "2",
                                  "tripDetailVersion": "1"
                                },
                                "extraTags": {
                                  "depositVersion": "1.0",
                                  "poiNewVersion": "2"
                                },
                                "now": "' . date('Y-m-d\TH:i:s.000:sp\Z') . '",
                                "channelType": 7,
                                "requestId": "8af8cfa3ec6f4888b07bf21709be5281"
                              },
                              "noBaseRequest": false,
                              "useBasicRequest": false,
                              "useSoa": true,
                              "serviceCode": 18862,
                              "methodname": "OSDQueryOrder",
                              "head": {
                                "cid": "' . $props->query->envObj->cookie->GUID . '",
                                "ctok": "",
                                "cver": "1.0",
                                "lang": "01",
                                "sid": "8888",
                                "syscode": "09",
                                "auth": "",
                                "xsid": "",
                                "extension": []
                              }
                            }';
                            $headers = [
                                'Accept'       => '*/*',
                                'Content-Type' => 'application/json',
                            ];
                            $host = parse_url($this->http->currentUrl(), PHP_URL_HOST);
                            $this->http->PostURL("https://{$host}/restapi/soa2/18862/OSDQueryOrder?_fxpcqlniredt=09031113419608273226&x-traceID=09031113419608273226-1685086819436-3479301", $data, $headers);
                            $order = $this->http->JsonLog(null, 2, false, 'order');
                        }

                        $this->fetchJsonBookingRental($item, $order);

                        break;

                    case 'Transfer':
                        //$order = $this->http->JsonLog($this->http->FindPreg('#<script id="__NEXT_DATA__" type="application/json">(.+?)</script>#'), 1);
                         //$this->fetchJsonBookingTransfer($item, $order);
                        $headers = [
                            'Accept'       => 'application/json, text/plain, */*',
                            'Content-Type' => 'application/json;charset=UTF-8',
                        ];
                        $data = '{"reqhead":{"protocol":"http:","appid":"100013580","host":"www.trip.com","locale":"en_xx","cury":"usd","channelid":235269,"biztype":33,"pttype":17,"ptgroup":17,"sf":"trip_online","cid":"","os":"","mode":"web","gps":{},"token":"","wlver":"0.202301311658","stntype":0,"union":{"sid":"","aid":"","ouid":""},"ubt":{"pageid":0,"pvid":19,"sid":2,"vid":"1675247008456.2nyv7k"}},"oid":"' . $item->OrderID . '","head":{"cid":"","ctok":"","cver":"1.0","lang":"01","sid":"8888","syscode":"09","auth":"","xsid":"","extension":[]}}';
                        $this->http->PostURL('https://www.trip.com/restapi/soa2/20353/triponlinequeryorderdetail.json?_fxpcqlniredt=09031080416128394436&x-traceID=09031080416128394436-1675417387736-8759413',
                            $data, $headers);
                        $detail = $this->http->JsonLog(null, 3, false, 'car');

                        $this->fetchJsonBookingTransfer($item, $detail);

                        break;

                    case 'Piao':
                        $headers = [
                            'Accept'       => 'application/json, text/plain, */*',
                            'Content-Type' => 'application/json;charset=UTF-8',
                        ];
                        $data = '{"contentType":"json","head":{"cid":"09031174314973102325","ctok":"","cver":"1.0","lang":"01","sid":"8888","syscode":"09","auth":"","extension":[{"name":"protocal","value":"https"}]},"orderId":' . $item->OrderID . ',"showEx":0,"currency":"USD","locale":"en_US","pageid":10650069065,"platformId":24,"ver":"7.50.2","aid":"","sid":""}';
                        $this->http->PostURL('https://www.trip.com/restapi/soa2/14921/json/getOrderDetailV1?subEnv=fat30&_fxpcqlniredt=09031174314973102325',
                            $data, $headers);
                        $detail = $this->http->JsonLog(null, 3, false, 'car');

                        $this->fetchJsonBookingEvent($item, $detail);

                        break;

                    default:
                        $this->sendNotification('ctrip - new itinerary type ' . $item->BizType);

                        break;
                }// switch ($item->BizType)
            }
        }// foreach ($response->OrderEnities as $item)

        return $result;
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"          => "*/*",
            "Accept-Language" => "en-US,en;q=0.5",
            "Accept-Encoding" => "gzip, deflate, br",
            "locale"          => "en-XX",
            "currency"        => "USD",
            "x-traceID"       => "1711515141163.efd8kfO3P4R6-1711520911032-1780314385",
            "trip-trace-id"   => "1711515141163.efd8kfO3P4R6-1711520911032-1780314385",
            "Content-Type"    => "application/json",
            "Referer"         => "https://www.trip.com/?locale=en-XX&curr=USD",
        ];
        $this->http->PostURL('https://www.trip.com/restapi/soa2/27147/run?x-traceID=1711515141163.efd8kfO3P4R6-1711520911032-1780314385', '{"name":"header-user-property","args":{"head":{"bizType":"IBU","group":"trip","source":"Online","locale":"en-XX","currency":"USD"}}}', $headers);

        // hard code
        if (
            $this->http->FindPreg("/<form method ='post' action='(http:\/\/www.trip.com\/account\/signin\?locale=en_us&currency=USD&language=EN&backurl=https%3A%2F%2Fwww.trip.com%2Fmembersinfo%2FselmemberinfoAjax&responsemethod=post)/")
            || $this->http->FindPreg("/<form method ='post' action='(https:\/\/www.trip.com\/account\/signin\?locale=en-US&currency=USD&language=EN&backurl=https%3A%2F%2Fwww.trip.com%2Fmembersinfo%2FselmemberinfoAjax&responsemethod=post)/")
        ) {
            // AccountID: 4494390
            if ($this->AccountFields['Login'] == '2110965957') {
                throw new CheckException("Please sign in to the simplified Chinese version of www.ctrip.com.", ACCOUNT_PROVIDER_ERROR);
            }

            return false;
            /*
            throw new CheckRetryNeededException();
            */
        }
        $response = $this->http->JsonLog();
        // Access is allowed
        if (
            !empty($response->data->showAmount)
            || !empty($response->data->gradeName)
            || $this->http->FindSingleNode("//div[contains(@class, 'user_name')]")
            || $this->http->FindSingleNode("//a[contains(@href, 'logout')]")
            || $this->http->FindSingleNode("//div[@class = 'user_level']")
            || $this->http->FindSingleNode("//div[@id = 'oldemail']")
        ) {
            return true;
        }

        return false;
    }

    // TODO: need to rewrite \SeleniumCheckerHelper::clickCaptchaCtrip
    protected function clickCaptchaCtrip($selenium = null, $attemptCount = 5, $recognizeTimeout = 180, $increaseTimeLimit = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$selenium) {
            $selenium = $this;
        }

        $submit = null;

        for ($attempt = 0; $attempt < $attemptCount; $attempt++) {
            $this->logger->info(sprintf('solving attempt #%s', $attempt));
            $chooser = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "cpt-choose-box")] | //div[contains(@class, "slider")]/div[contains(@class, "container")]'), 5);

            if ($chooser) {
                $pathToScreenshot = $this->takeScreenshotOfElement($chooser, $selenium);
            } else {
                $this->logger->info('chooser not found');

                return false;
            }

            $data = [
                'coordinatescaptcha' => '1',
                'textinstructions'   => 'select the text from the top picture in correct order on the bottom picture / выберите текст из картинки вверху в правильном порядке на картинке внизу',
            ];

            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
            $recognizer->RecognizeTimeout = $recognizeTimeout;

            try {
                $captcha = $recognizer->recognizeFile($pathToScreenshot, $data);
            } catch (CaptchaException $e) {
                $this->logger->warning("exception: " . $e->getMessage());
                // always solvable for ctrip
                if ($e->getMessage() == 'server returned error: ERROR_CAPTCHA_UNSOLVABLE') {
                    $recognizer->reportIncorrectlySolvedCAPTCHA();

                    continue;
                } else {
                    return false;
                }
            } finally {
                unlink($pathToScreenshot);
            }

            if ($increaseTimeLimit) {
                $this->increaseTimeLimit($increaseTimeLimit);
            }

            $letterCoords = $this->parseCoordinates($captcha);

            if (!$letterCoords) {
                continue;
            }

            if (count($letterCoords) == 1) {
                $recognizer->reportIncorrectlySolvedCAPTCHA();

                continue;
            }

            $html = $selenium->driver->findElement(WebDriverBy::xpath('//body'));
            $bodyCoords = $html->getCoordinates();

            $coords = $chooser->getCoordinates()->inViewPort();
            $chooserCoords = ['x' => $coords->getX(), 'y' => $coords->getY()];

            $mouse = $selenium->driver->getMouse();
            $mover = new MouseMover($selenium->driver);
            $mover->moveToCoordinates($chooserCoords);

            foreach ($letterCoords as $point) {
                $x = intval($chooserCoords['x'] + $point['x']);
                $y = intval($chooserCoords['y'] + $point['y']);
                $mover->moveToCoordinates(['x' => $x, 'y' => $y]);
                $mouse->mouseMove($bodyCoords, $x, $y);
                $mover->click();
            }

            $submit = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@class, "cpt-choose-submit")] | //span[contains(@class, "cpt-submit-text")]'), 3);

            $selenium->http->SaveResponse();

            if ($submit) {
                $submit->click();
                $this->logger->info('sleeping for a bit after submit click');
                sleep(5);
            } else {
                $this->logger->info(sprintf('could not click captcha submit'));
            }

            // If submit hasn't disappeared then captcha was not solved correctly
            $submit = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@class, "cpt-choose-submit")] | //span[contains(@class, "cpt-submit-text")]'), 3);
            $selenium->http->SaveResponse();

            if ($submit) {
                $recognizer->reportIncorrectlySolvedCAPTCHA();
            } else {
                $this->logger->info('successfully solved select captcha');

                break;
            }
        }

        if ($submit) {
            $this->logger->error('failed to solve select captcha');

            return false;
        }
        $infoBoard = $selenium->waitForElement(WebDriverBy::cssSelector('span.cpt-info-board'), 5);

        if ($infoBoard && $this->http->FindPreg('/Verification failed/i', $infoBoard->getText())) {
            throw new CheckRetryNeededException(3, 0);
        }

        return true;
    }

    private function fetchJsonBookingTrainV3($item, $detail): void
    {
        $this->logger->notice(__METHOD__);
        $t = $this->itinerariesMaster->createTrain();
        $order = $detail->props->viewProps->initialState->order ?? null;

        if (!isset($order->orderId, $order->itineraryList)) {
            $this->logger->error('Something went wrong');
            $this->sendNotification('fetchJsonBookingTrainV3 // MI', 'awardwallet');

            return;
        }

        $this->logger->info("Parse Train #{$order->orderId}", ['Header' => 3]);
        $t->general()->confirmation($order->orderId);
        $t->general()->date2($order->CreateDttm);

        $t->price()->total($order->orderPrice);
        $t->price()->currency($order->currency);
        $t->price()->cost($order->totalTicketPrice);

        if (isset($order->passengerInfoList)) {
            foreach ($order->passengerInfoList as $passengerInfo) {
                $t->general()->traveller($passengerInfo->passengerName);
            }
        }

        foreach ($order->itineraryList as $itineraryList) {
            foreach ($itineraryList->segments as $segment) {
                $s = $t->addSegment();
                $s->extra()->number($segment->trainNumber);
                $s->extra()->type($segment->trainType);
                $s->departure()->name($segment->departStationName);
                $s->departure()->date2($segment->departureDateTime);
                $s->arrival()->name($segment->arrivalStationName);
                $s->arrival()->date2($segment->arrivalDateTime);
                $s->extra()->duration($segment->duration, false, true);
            }
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($t->toArray(), true), ['pre' => true]);
    }

    private function fetchJsonBookingTrainV2($item, $detail): void
    {
        $this->logger->notice(__METHOD__);
        $order = $detail->props->viewProps->initialState->order ?? $detail->props->pageProps->initialState->order ?? null;

        if (!isset($order->OrderId, $order->BookedDetailP2pProductList)) {
            $this->logger->error('Something went wrong');
            $this->fetchJsonBookingTrainV3($item, $detail);

            return;
        }
        $t = $this->itinerariesMaster->createTrain();
        $this->logger->info("Parse Train #{$order->OrderId}", ['Header' => 3]);
        $t->general()->confirmation($order->OrderId);

        $t->price()->total($order->OrderPrice);
        $t->price()->currency($order->Currency);

        if (isset($order->PassengerInfoList)) {
            foreach ($order->PassengerInfoList as $passengerInfo) {
                if (!empty($passengerInfo->FirstName)) {
                    $t->general()->traveller("$passengerInfo->FirstName $passengerInfo->LastName");
                }
            }
        }

        foreach ($order->BookedDetailP2pProductList as $bookedDetailP2pProductList) {
            foreach ($bookedDetailP2pProductList->BookedP2pSegmentList as $ticket) {
                $s = $t->addSegment();
                $s->extra()->number($ticket->Train->Number);
                $s->departure()->name($ticket->DepartureLocation->Name);
                $s->departure()->date2($ticket->DepartureDateTime);
                $s->arrival()->name($ticket->ArrivalLocation->Name);
                $s->arrival()->date2($ticket->ArrivalDateTime);
                $s->extra()->duration($ticket->Duration, false, true);
            }
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($t->toArray(), true), ['pre' => true]);
    }

    private function fetchJsonBookingTrain($item, $detail): void
    {
        $this->logger->notice(__METHOD__);
        $order = $detail->props->pageProps->initialState->order ?? null;

        if (!isset($order->orderDetailInfo->orderID, $order->ticketsInfo)) {
            $this->logger->error('Something went wrong');
            $this->fetchJsonBookingTrainV2($item, $detail);

            return;
        }
        $t = $this->itinerariesMaster->createTrain();
        $conf = $order->orderDetailInfo->orderID;
        $this->logger->info("Parse Train #{$conf}", ['Header' => 3]);
        $t->general()->confirmation($conf);
        $t->general()->date($order->orderDetailInfo->orderDate);

        $t->price()->total($order->paymentInfo->payAmount);
        $t->price()->currency($order->paymentInfo->currency);

        if (isset($order->passengerInfo)) {
            foreach ($order->passengerInfo as $passengerInfo) {
                $t->general()->traveller($passengerInfo->passengerName);
            }
        }

        foreach ($order->ticketsInfo as $ticket) {
            $s = $t->addSegment();
            $s->extra()->number($ticket->trainNumber);

            $s->departure()->name($ticket->departurStationName);
            $s->departure()->date($ticket->departureDateTime);
            $s->arrival()->name($ticket->arrivalStationName);
            $s->arrival()->date($ticket->arrivalDateTime);
            $s->extra()->duration($this->dateFormat($ticket->duration), false, true);

            if (isset($order->props->pageProps->initialState->order->passengerInfo)) {
                foreach ($order->props->pageProps->initialState->order->passengerInfo as $passengerInfo) {
                    if (!empty($passengerInfo->ticketInfo) && $passengerInfo->routeSequence == $ticket->routeSequence) {
                        if (count($passengerInfo->ticketInfo) > 1) {
                            $this->sendNotification('ticketInfo > 1 // MI');
                        } else {
                            $s->extra()->car(str_replace('Carriage ', '', $passengerInfo->ticketInfo[0]->realCarriageNo));
                            $s->extra()->seat(str_replace('Seat no. ', '', $passengerInfo->ticketInfo[0]->realSeatNo));
                        }

                        break;
                    }
                }
            }
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($t->toArray(), true), ['pre' => true]);
    }

    private function dateFormat($duration)
    {
        if (empty($duration)) {
            return null;
        }
        $hours = floor($duration / 60);
        $minutes = $duration % 60;

        return $hours > 0 ? sprintf('%02dh %02dm', $hours, $minutes)
            : sprintf('%02dm', $minutes);
    }

    private function fetchJsonBookingRental($item, $detail): void
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->rental();
        $this->logger->info("Parse Rental #{$detail->orderBaseInfo->orderId}", ['Header' => 3]);
        $r->ota()->confirmation($detail->orderBaseInfo->orderId);
        $r->general()->noConfirmation();
        $r->general()->traveller(beautifulName($detail->driverInfo->name));
        $r->extra()->company($detail->vendorInfo->vendorName);
        $r->car()->type($detail->vehicleInfo->vehicleGroupName)
            ->model($detail->vehicleInfo->vehicleName)
            ->image(str_starts_with($detail->vehicleInfo->imageUrl, 'https')
                ? $detail->vehicleInfo->imageUrl
                : "https:{$detail->vehicleInfo->imageUrl}");

        $address = $detail->pickupStore->storeAddress ?? $detail->pickupStore->storeName;

        if (!empty($detail->pickupStore->cityName)) {
            $address .= ', ' . $detail->pickupStore->cityName;
        }

        if (!empty($detail->pickupStore->countryName)) {
            $address .= ', ' . $detail->pickupStore->countryName;
        }
        $r->pickup()
            ->phone(explode('/', $detail->pickupStore->storeTel)[0] ?? null, false, true)
            ->date2($detail->pickupStore->localDateTime)
            ->location($address);

        $address = $detail->returnStore->storeAddress ?? $detail->returnStore->storeName;

        if (!empty($detail->returnStore->cityName)) {
            $address .= ', ' . $detail->returnStore->cityName;
        }

        if (!empty($detail->returnStore->countryName)) {
            $address .= ', ' . $detail->returnStore->countryName;
        }
        $r->dropoff()
            ->phone(explode('/', $detail->returnStore->storeTel)[0] ?? null, false, true)
            ->date2($detail->returnStore->localDateTime)
            ->location($address);

        $r->price()->total($detail->orderPriceInfo->currentTotalPrice);
        $r->price()->currency($detail->orderPriceInfo->currentCurrencyCode);

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function fetchJsonBookingFlight($detail): void
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->add()->flight();
        $this->logger->info("Parse Flight #{$detail->oBscInfo->oid}", ['Header' => 3]);
        $f->ota()->confirmation($detail->oBscInfo->oid);
        $f->general()->noConfirmation();

        foreach ($detail->passengers as $passenger) {
            $f->general()->traveller(beautifulName($passenger->name));

            if (!empty($passenger->tNo)) {
                $f->issued()->tickets(explode(',', $passenger->tNo), false);
            }
        }

        foreach ($detail->segList as $list) {
            foreach ($list->seqs as $seq) {
                $s = $f->addSegment();

                if (!empty($seq->flight->basic->alRNum) && $seq->flight->basic->alRNum != 'null') {
                    $confs = preg_split('/[,、]\s*/u', $seq->flight->basic->alRNum);

                    foreach ($confs as $conf) {
                        $s->setConfirmation($conf);
                    }
                }
                $s->departure()->code($seq->flight->depart->pCode);
                $terminal = $this->http->FindPreg('/^.+?\s+T(\d+)/', false, $seq->flight->depart->portT);
                $s->departure()->name($seq->flight->depart->pName);
                $s->departure()->terminal($terminal, false, true);
                $s->departure()->date2($seq->flight->depart->time);

                $s->arrival()->code($seq->flight->arrive->pCode);
                $terminal = $this->http->FindPreg('/^.+?\s+T(\d+)/', false, $seq->flight->arrive->portT);
                $s->arrival()->name($seq->flight->arrive->pName);
                $s->arrival()->terminal($terminal, false, true);
                $s->arrival()->date2($seq->flight->arrive->time);

                if (preg_match('/^([A-Z\d]{2})(\d+)$/', $seq->flight->basic->fNo, $m)) {
                    $s->airline()->name($m[1]);
                    $s->airline()->number($m[2]);
                }
                $s->extra()->aircraft($seq->flight->basic->craft, true, true);
                $s->extra()->meal($seq->flight->basic->meal, true, true);
                $s->extra()->duration($this->dateFormat($seq->flight->basic->duration), false, true);
            }
        }
        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function fetchJsonBookingHotelV2($item, $detail): void
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->add()->hotel();
        $orderId = $detail->props->pageProps->orderId ?? null;
        $this->logger->info("Parse Hotel #{$orderId}", ['Header' => 3]);
        $h->general()->confirmation($orderId);
        $response = $detail->props->pageProps->response ?? null;

        foreach ($response->guestContact->guests ?? [] as $item) {
            $h->general()->traveller(beautifulName("{$item->guestName}"));
        }
        $time = $this->http->FindPreg('/After ([\d:]+)/', false, $response->stayInfo->checkInInfo->time);
        $h->booked()->checkIn2($response->stayInfo->checkInInfo->date . ', ' . $time);
        $time = $this->http->FindPreg('/Before ([\d:]+)/', false, $response->stayInfo->checkOutInfo->time);
        $h->booked()->checkOut2($response->stayInfo->checkOutInfo->date . ', ' . $time);

        $h->hotel()
            ->name($response->hotelInfo->hotelName)
            ->address($response->hotelInfo->hotelAddress)
            ->phone($response->hotelInfo->hotelBaseInfo->telInfos[0]->showTel ?? null, false, true);

        $r = $h->addRoom();
        $r->setType($response->roomInfo->roomName);

        $h->booked()->guests($response->roomInfo->searchGuestFilter->adult);

        $total = $response->priceInfo->priceSummary->summaryList[0]->amountText ?? null;
        $h->price()->total($this->http->FindPreg('/([\d.,]+)$/', false, $total));
        $h->price()->currency($this->http->FindPreg('/(.{1,5})\s*[\d.,]+/', false, $total));
        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function fetchJsonBookingHotel($item, $detail): void
    {
        $this->logger->notice(__METHOD__);

        $additions = $detail->initData->orderStatus->addition ?? null;

        if (!isset($additions)) {
            $this->logger->error('initData->orderStatus->addition not found');
            $this->sendNotification('check new it // MI');
            $this->fetchJsonBookingHotelV2($item, $detail);

            return;
        }
        $h = $this->itinerariesMaster->add()->hotel();

        foreach ($additions as $addition) {
            if ($addition->title == 'Booking No.') {
                $this->logger->info("Parse Hotel #{$addition->text}", ['Header' => 3]);
                $h->ota()->confirmation(str_replace('#', '', $addition->text));
            } elseif (in_array($addition->title, ['Hotel Confirmation No.'])) {
                foreach (explode(',', $addition->text) as $cnf) {
                    $cnf = str_replace(['+', '#'], '-', preg_replace('/\[[\w]+]/', '', $cnf));

                    if ($this->http->FindPreg(Field::CONFNO_REGEXP, false, $cnf)) {
                        $h->general()->confirmation($cnf);
                    }
                }
            } elseif ($addition->title == 'Booking Date') {
                // 22:52, Apr 11, 2022 (Your local time)
                $h->general()->date2($this->http->FindPreg('/^(\d+.+?)\s+\(Your local time\)/', false, $addition->text));
            }
        }

        if (isset($additions) && empty($h->getConfirmationNumbers())) {
            $h->general()->noConfirmation();
        }

        $h->general()->status($detail->initData->orderStatus->text);

        if (isset($additions, $detail->initData->orderStatus->text, $detail->initData->orderInfo->checkIn) && !isset($detail->initData->hotelDetail)) {
            $this->itinerariesMaster->removeItinerary($h);
            $this->logger->error('Skip: No hotel details');

            return;
        }

        if ($this->http->FindPreg('/canc/', false, $detail->initData->orderStatus->text)) {
            $this->sendNotification("{$detail->initData->orderStatus->text} // MI");
        }

        foreach ($detail->initData->guestInfo->userInfo as $userInfo) {
            $h->general()->traveller(beautifulName("{$userInfo->givenName} {$userInfo->surname}"));
        }

        $h->hotel()->name($detail->initData->hotelDetail->name);
        $h->hotel()->address($detail->initData->hotelDetail->address);

        $h->booked()->checkIn2($detail->initData->orderInfo->checkInString);
        $h->booked()->checkOut2($detail->initData->orderInfo->checkOutString);
        $h->booked()->guests($detail->initData->pixel->Guest);
        $r = $h->addRoom();
        $r->setType($detail->initData->pixel->RoomName);

        if (isset($detail->initData->money)) {
            $money = $detail->initData->money;
            $h->price()->total($money->total);
            $h->price()->currency($money->currency);

            foreach ($money->moneyItemInfo as $money) {
                if ($money->title == 'Taxes & fees') {
                    $h->price()->tax($money->price);
                }
            }
        } elseif (isset($detail->initData->newMoney)) {
            $money = $detail->initData->newMoney;

            if (isset($money->total->price)) {
                $h->price()->total(round($money->total->price, 2));
                $h->price()->currency($money->total->currency);
            }

            /*foreach ($money->moneyItemInfo as $money) {
                if ($money->title == 'Taxes & fees') {
                    $h->price()->tax($money->price);
                }
            }*/
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function fetchJsonBookingEvent($item, $detail)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info(sprintf('Parse Event #%s', $item->OrderID), ['Header' => 3]);

        $e = $this->itinerariesMaster->add()->event();
        $e->place()->type(Event::TYPE_EVENT);
        $e->ota()->confirmation($item->OrderID, "Booking number");
        $e->general()
            ->noConfirmation()
            ->date($this->http->FindPreg('/Date\((\d+)\+\d+/', false, $item->BookingDate) / 1000);

        foreach ($item->Passagers as $pass) {
            if (isset($pass)) {
                $e->general()->traveller(beautifulName(str_replace('/', ' ', $pass)));
            }
        }

        $e->price()
            ->total(PriceHelper::cost($item->OrderTotalPrice))
            ->currency($item->Currency);
        $e->place()->name($item->OrderName);

        $dataAddress = $detail->data->product->resources[0]->resourceUsage->pickupTicketStep->exchanges[0] ??
            $detail->data->product->resources[0]->resourceUsage->useTicketStep->uses[0] ?? null;

        if (isset($dataAddress)) {
            if (!empty($dataAddress->enAddress)) {
                $e->place()->address($dataAddress->enAddress);
            } else {
                $e->place()->address($dataAddress->address);
            }

            $useDate = $detail->data->product->resources[0]->useDate;
            $times = $dataAddress->ticketOpenTimes[0] ?? $dataAddress->ticketUseTimes[0] ?? null;
            $this->logger->debug("Date: $useDate");
            $this->logger->debug("Date start: $times->startTime");
            $this->logger->debug("Date end: $times->endTime");
            $e->booked()->start2($useDate . ", " . $times->startTime);
            $e->booked()->end2($useDate . ", " . $times->endTime);
        }

        foreach ($detail->data->product->resources[0]->addInfos as $addInfo) {
            $this->logger->debug($addInfo->subTitle);

            if ($addInfo->subTitle == 'Additional Information') {
                foreach ($addInfo->addDetails as $addDetail) {
                    if ($phones = $this->http->FindPreg('/Please call (.+?) for /', false, $addDetail->desc)) {
                        foreach (explode(' or ', $phones) as $phone) {
                            $e->place()->phone($phone);
                        }
                    }
                }
            }
        }
        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($e->toArray(), true), ['pre' => true]);
    }

    private function fetchJsonBookingCar($item): void
    {
        $this->logger->notice(__METHOD__);
    }

    private function fetchJsonBookingTransfer($item, $detail)
    {
        $this->logger->notice(__METHOD__);

        $this->logger->info(sprintf('Parse Transfer #%s', $item->OrderID), ['Header' => 3]);

        $r = $this->itinerariesMaster->add()->transfer();

        $r->ota()->confirmation($item->OrderID, "Booking number");

        $date = $this->http->FindPreg('/Date\((\d+)\+/', false, $item->BookingDate) / 1000;
        $r->general()
            ->noConfirmation()
            ->date($date);

        foreach ($item->Passagers as $pass) {
            $r->general()->traveller(beautifulName(str_replace('/', ' ', $pass)));
        }

        $r->price()
            ->total(PriceHelper::cost($item->OrderTotalPrice))
            ->currency($item->Currency);

        $s = $r->addSegment();

        // Pickup
        $this->logger->debug($this->http->FindPreg("/([^\(]+)/", false, $detail->car->udt));
        $date = $this->http->FindPreg("/([^\(]+)/", false, $detail->car->udt);
        $s->departure()
            ->date2($date)
            ->name($detail->car->dpoinm);
        $address = $detail->car->dpoiaddr;

        if ($address) {
            $s->departure()->address($address);
        } else {
            $s->departure()->code($this->http->FindPreg("/\(([A-Z]{3})/", false, $s->getDepName()));
        }

        $s->arrival()
            ->noDate()
            ->name($detail->car->apoinm);
        $address = $detail->car->apoiaddr;

        if ($address) {
            $s->arrival()->address($address);
        } else {
            $s->arrival()->code($this->http->FindPreg("/\(([A-Z]{3})/", false, $s->getArrName()));
        }

        $imageUrl = $detail->grp->img;

        if (!$this->http->FindPreg('/^https?/', false, $imageUrl)) {
            $imageUrl = sprintf('https:%s', $imageUrl);
        }
        $s->extra()
            ->model($detail->grp->nm)
            ->image($imageUrl)
            ->adults($detail->passenger->adult)
            ->kids($detail->passenger->child);

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function slideCaptcha($selenium = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$selenium) {
            $selenium = $this;
        }
        $mover = new MouseMover($selenium->driver);
        $mover->logger = $this->logger;
        $mover->duration = 30;
        $mover->steps = 10;
        $mover->enableCursor();
        $counter = 0;

        if (!$slider = $selenium->waitForElement(WebDriverBy::cssSelector('div.cpt-drop-btn'), 0)) {
            return;
        }

        do {
            if ($counter++ > 2) {
                /*
                $this->sendNotification('refs #23019 slider captcha not solved // BS');
                */

                break;
            }
            $this->saveToLogs($selenium);
            $mover->moveToElement($slider);
            $mouse = $selenium->driver->getMouse()->mouseDown();
            usleep(500000);
            $mouse->mouseMove($slider->getCoordinates(), 200, 0);
            usleep(500000);
            $mouse->mouseUp();
            $this->saveToLogs($selenium);
            sleep(2);
        } while ($slider = $selenium->waitForElement(WebDriverBy::cssSelector('div.cpt-drop-btn'), 0));
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            // $selenium->http->saveScreenshots = true;
            // refs #14821
            $resolutions = [
                [1152, 864],
                [1280, 768],
                [1360, 768],
                [1366, 768],
                [1440, 900],
            ];
            // $selenium->setScreenResolution($resolutions[array_rand($resolutions)]);
            // prevent "exception caught - ErrorException: Notice: Trying to access array offset on value of type null trace: #0 /www/awardwallet/vendor/awardwallet/service/old/browser/MouseMover.php(102): MouseMover->detectCorrection() #1 /www/awardwallet/vendor/awardwallet/service/old/browser/MouseMover.php(136): MouseMover->moveToCoordinates(Array, Array) #2 /www/awardwallet/engine/ctrip/functions.php(521): MouseMover->moveToElement(Object(RemoteWebElement), Array) #3 /www/awardwallet/engine/ctrip/functions.php(604): TAccountCheckerCtrip->slideCaptcha(Object(TAccountCheckerCtrip)) #4 /www/awardwallet/engine/ctrip/functions.php(51): TAccountCheckerCtrip->selenium() #5 /www/awardwallet/vendor/awardwallet/service/old/TAccountChecker.php(699): TAccountCheckerCtrip->LoadLoginForm() #6 /www/awardwallet/web/admin/debugParser.php(176): TAccountChecker->Check(false) #7 /www/awardwallet/web/app_dev.php(15): require('/www/awardwalle...') #8 {main}"
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $selenium->setScreenResolution([1920, 1080]);
            }

            $selenium->useChromium(SeleniumFinderRequest::CHROMIUM_80);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->driver->manage()->window()->maximize();
                $selenium->http->GetURL("https://www.trip.com/account/signin");
            } catch (StaleElementReferenceException | UnexpectedJavascriptException | UnknownServerException $e) {
                $this->logger->error("exception: " . $e->getMessage());

                if (
                    strstr($e->getMessage(), 'Timed out waiting for page load')
                    || strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')
                ) {
                    $selenium->driver->executeScript('window.stop();');
                    $this->saveToLogs($selenium);
                } else {
                    $retry = true;
                }
            }
            $selenium->driver->manage()->window()->maximize();
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@placeholder="Please enter an email address"]'), 10);
            // save page to logs
            $this->saveToLogs($selenium);

            if (!$loginInput) {
                $this->logger->error("something went wrong");

                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $this->saveToLogs($selenium);
            $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "ibu_login_submit"] | //button[contains(., "Continue")]'), 0)->click();

            $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "cpt-drop-btn")] | //input[@placeholder="Please enter your password"] | //*[self::div or self::span][contains(text(), "Sign in with Password")]'), 10);

            if ($signInWithPassword = $selenium->waitForElement(WebDriverBy::xpath('//*[self::div or self::span][contains(text(), "Sign in with Password")]'), 0)) {
                $this->saveToLogs($selenium);
                $signInWithPassword->click();
                $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "cpt-drop-btn")] | //input[@placeholder="Please enter your password"]'), 10);
                $this->saveToLogs($selenium);
            }

            // CAPTCHA 1
            $this->slideCaptcha($selenium);
            // CAPTCHA 2
//            $this->clickCaptchaCtrip($selenium, 5, 180, 180);
//            $this->saveToLogs($selenium);

            // password
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@placeholder="Please enter your password"]'), 0);

            if (!$passwordInput) {
                $this->saveToLogs($selenium);
                // CAPTCHA 2
                if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "cpt-choose-box")] | //div[contains(@class, "slider")]/div[contains(@class, "container")]'), 0)) {
                    return false;
                }
                $this->logger->error("something went wrong");

                if ($this->http->FindSingleNode('//div[contains(text(), "Set Password") or contains(text(), "Set Your Password")]')) {
                    $this->throwProfileUpdateMessageException();
                }

                if ($this->http->FindSingleNode('//div[contains(text(), "Create an Account")]')) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                $errorMessage = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "s_error_tips")]'), 0);

                if ($errorMessage) {
                    $message = $errorMessage->getText();
                    $this->logger->error("[Error]: {$message}");

                    if (
                        strstr($message, 'Password error, please try again')
                        || $message == 'Please enter a valid email'
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;
                }

                if ($this->AccountFields['Login'] == 'cfleejeff@gmail.com') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);
//            $selenium->waitForElement(WebDriverBy::id('ibu_login_submit'), 0)->click();
            $selenium->waitForElement(WebDriverBy::xpath('//button[contains(., "Sign In")]'), 0)->click();
            $this->saveToLogs($selenium);
            // $passwordInput->sendKeys('password');

            $success = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(@class, "account-username")] | //*[contains(text(), "An error occurred, please try again later")] | //div[@class="toast_modal"]'), 10);
            $this->saveToLogs($selenium);

            if ($success && strstr($success->getText(), 'An error occurred, please try again later')) {
                throw new CheckException($success->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            if (!$success) {
                // CAPTCHA 1
                $this->slideCaptcha($selenium);
                $this->saveToLogs($selenium);
                // CAPTCHA 2
                $this->clickCaptchaCtrip($selenium, 5, 180, 180);
                $this->saveToLogs($selenium);
            }

            if ($errorMessage = $selenium->waitForElement(WebDriverBy::xpath('//span[
                    contains(text(), "Incorrect username or password.") or
                    contains(text(), "Sorry! Your sign in details are incorrect. Please try again.") or
                    contains(text(), "Sorry, please sign in on the Ctrip simplified Chinese website and try again")
                    or contains(text(), "Oops! Something went wrong. Please try again.")
                    or contains(text(), "Sorry, please sign in on the Trip.com simplified Chinese website and try again.")
                    or contains(text(), "Please reset your password and sign in again.")
                ]
                | //div[contains(@class, "s_error_tips")]
                | //*[contains(text(), "An error occurred, please try again later")]
                | //*[contains(text(), "Your password may be incorrect, or this account may not exist.")]
                '), 0)
            ) {
                // save page to logs
                $this->saveToLogs($selenium);

                if ($errorMessage) {
                    $message = $errorMessage->getText();
                    $this->logger->error("[Error]: {$message}");

                    if (
                        strstr($message, 'Incorrect username or password')
                        || strstr($message, 'Incorrect password. Please try again.')
                        || strstr($message, 'Sorry, please sign in on the Trip.com simplified Chinese website and try again.')
                        || strstr($message, 'Sorry! Your sign in details are incorrect. Please try again.')
                        || strstr($message, 'Sorry, please sign in on the Trip.com simplified Chinese website and try again.')
                        || strstr($message, 'Please reset your password and sign in again.')
                        || strstr($message, 'Password error, please try again')
                        || strstr($message, 'Your password may be incorrect, or this account may not exist.')
                        || $message == 'Please enter a valid email'
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }
                    // Oops! Something went wrong. Please try again.
                    if (
                        strstr($message, 'Oops! Something went wrong. Please try again.')
                        || strstr($message, 'Sorry, please sign in on the Trip.com simplified Chinese website and try again.')
                        || strstr($message, 'An error occurred, please try again later')
                    ) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }
            }// if (!$success

            if (!$success && $selenium->waitForElement(WebDriverBy::xpath("
                    //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                "), 0)
            ) {
                $retry = true;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$this->seleniumURL}");
            $host = $selenium->http->getCurrentHost();
            $this->logger->debug("host: $host");

            if ($host == 'ct.ctrip.com') {
                $this->sendNotification('ctrip: Host ' . $host);
            }
            $result = true;
        } catch (WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());
            $retry = true;
        }
        /*
        catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer'))
                throw new CheckRetryNeededException(3, 10);
        }// catch (ScriptTimeoutException $e)
        */
        finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo:

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(4, 7);
            }
        }

        return $result;
    }

    private function saveToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }

    private function parseCaptcha()
    {
        /*** read in the BMP image ***/
        $file = $this->http->DownloadFile("https://accounts.ctrip.com/global/english/Check?CodeType=4&showCaptcha=true", "jpg");
//        $img = $this->ImageCreateFromBmp($file);
//        /*** write the new jpeg image ***/
//        $file = "/tmp/captcha-".getmypid()."-".microtime(true).".jpeg";
//        imagejpeg($img, $file);

        $this->logger->debug("exception: " . $file);
        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        return $captcha;
    }

    /**
     * @convert BMP to GD
     *
     * @param string $src
     * @param string|bool $dest
     *
     * @return bool
     */
    private function bmp2gd($src, $dest = false)
    {
        /*** try to open the file for reading ***/
        if (!($src_f = fopen($src, "rb"))) {
            return false;
        }

        /*** try to open the destination file for writing ***/
        if (!($dest_f = fopen($dest, "wb"))) {
            return false;
        }

        /*** grab the header ***/
        $header = unpack("vtype/Vsize/v2reserved/Voffset", fread($src_f, 14));

        /*** grab the rest of the image ***/
        $info = unpack("Vsize/Vwidth/Vheight/vplanes/vbits/Vcompression/Vimagesize/Vxres/Vyres/Vncolor/Vimportant",
            fread($src_f, 40));

        /*** extract the header and info into varibles ***/
        extract($info);
        extract($header);

        /*** check for BMP signature ***/
        if ($type != 0x4D42) {
            return false;
        }

        /*** set the pallete ***/
        $palette_size = $offset - 54;
        $ncolor = $palette_size / 4;
        $gd_header = "";

        /*** true-color vs. palette ***/
        $gd_header .= ($palette_size == 0) ? "\xFF\xFE" : "\xFF\xFF";
        $gd_header .= pack("n2", $width, $height);
        $gd_header .= ($palette_size == 0) ? "\x01" : "\x00";

        if ($palette_size) {
            $gd_header .= pack("n", $ncolor);
        }
        /*** we do not allow transparency ***/
        $gd_header .= "\xFF\xFF\xFF\xFF";

        /*** write the destination headers ***/
        fwrite($dest_f, $gd_header);

        /*** if we have a valid palette ***/
        if ($palette_size) {
            /*** read the palette ***/
            $palette = fread($src_f, $palette_size);
            /*** begin the gd palette ***/
            $gd_palette = "";
            $j = 0;
            /*** loop of the palette ***/
            while ($j < $palette_size) {
                $b = $palette[$j++];
                $g = $palette[$j++];
                $r = $palette[$j++];
                $a = $palette[$j++];
                /*** assemble the gd palette ***/
                $gd_palette .= "$r$g$b$a";
            }
            /*** finish the palette ***/
            $gd_palette .= str_repeat("\x00\x00\x00\x00", 256 - $ncolor);
            /*** write the gd palette ***/
            fwrite($dest_f, $gd_palette);
        }

        /*** scan line size and alignment ***/
        $scan_line_size = (($bits * $width) + 7) >> 3;
        $scan_line_align = ($scan_line_size & 0x03) ? 4 - ($scan_line_size & 0x03) : 0;

        /*** this is where the work is done ***/
        for ($i = 0, $l = $height - 1; $i < $height; $i++, $l--) {
            /*** create scan lines starting from bottom ***/
            fseek($src_f, $offset + (($scan_line_size + $scan_line_align) * $l));
            $scan_line = fread($src_f, $scan_line_size);

            if ($bits == 24) {
                $gd_scan_line = "";
                $j = 0;

                while ($j < $scan_line_size) {
                    $b = $scan_line[$j++];
                    $g = $scan_line[$j++];
                    $r = $scan_line[$j++];
                    $gd_scan_line .= "\x00$r$g$b";
                }
            } elseif ($bits == 8) {
                $gd_scan_line = $scan_line;
            } elseif ($bits == 4) {
                $gd_scan_line = "";
                $j = 0;

                while ($j < $scan_line_size) {
                    $byte = ord($scan_line[$j++]);
                    $p1 = chr($byte >> 4);
                    $p2 = chr($byte & 0x0F);
                    $gd_scan_line .= "$p1$p2";
                }
                $gd_scan_line = substr($gd_scan_line, 0, $width);
            } elseif ($bits == 1) {
                $gd_scan_line = "";
                $j = 0;

                while ($j < $scan_line_size) {
                    $byte = ord($scan_line[$j++]);
                    $p1 = chr((int) (($byte & 0x80) != 0));
                    $p2 = chr((int) (($byte & 0x40) != 0));
                    $p3 = chr((int) (($byte & 0x20) != 0));
                    $p4 = chr((int) (($byte & 0x10) != 0));
                    $p5 = chr((int) (($byte & 0x08) != 0));
                    $p6 = chr((int) (($byte & 0x04) != 0));
                    $p7 = chr((int) (($byte & 0x02) != 0));
                    $p8 = chr((int) (($byte & 0x01) != 0));
                    $gd_scan_line .= "$p1$p2$p3$p4$p5$p6$p7$p8";
                }
                /*** put the gd scan lines together ***/
                $gd_scan_line = substr($gd_scan_line, 0, $width);
            }
            /*** write the gd scan lines ***/
            fwrite($dest_f, $gd_scan_line);
        }
        /*** close the source file ***/
        fclose($src_f);
        /*** close the destination file ***/
        fclose($dest_f);

        return true;
    }

    /**
     * @ceate a BMP image
     *
     * @param string $filename
     *
     * @return bin string on success
     * @return bool false on failure
     */
    private function ImageCreateFromBmp($filename)
    {
        /*** create a temp file ***/
        $tmp_name = tempnam("/tmp", "GD");
        /*** convert to gd ***/
        if ($this->bmp2gd($filename, $tmp_name)) {
            /*** create new image ***/
            $img = imagecreatefromgd($tmp_name);
            /*** remove temp file ***/
            unlink($tmp_name);

            /*** return the image ***/
            return $img;
        }

        return false;
    }

    private function checkCredentials($form)
    {
        $http2 = clone $this->http;
        $http2->setDefaultHeader("Referer", 'https://accounts.ctrip.com/global/english/signin');
        $http2->PostURL("https://accounts.ctrip.com/global/english/Check/checkit",
            [
                "UserName" => $this->AccountFields['Login'],
                "Password" => $this->AccountFields['Pass'],
                "CodeType" => $form['CodeType'],
                "Captcha"  => $form['Captcha'],
            ]
        );

        switch ($http2->Response['body']) {
            case '0':
                throw new CheckException("Please provide your correct password", ACCOUNT_INVALID_PASSWORD);

                break;

            case '2':
                throw new CheckException("Incorrect username or password, please re-enter.", ACCOUNT_INVALID_PASSWORD);

                break;

            case '5':
                throw new CheckException("Sorry, the client doesn't support corporate users login.You can call 400-920-0670 to get help.", ACCOUNT_INVALID_PASSWORD);

                break;

            default:
                $this->logger->notice("Credentials are correct -> " . var_export($http2->Response['body'], true));
        }

        return true;
    }

    private function fetchBookingCar()
    {
        $this->logger->notice(__METHOD__);

        $confNo = $this->http->FindSingleNode("//span[contains(text(), 'Booking No.')]/following-sibling::em");

        if (empty($confNo) && !empty($this->http->FindSingleNode("//div[@class='order-content'][contains(.,'Booking No.')]"))) {
            $this->fetchBookingCar_2();

            return;
        }

        $this->logger->info(sprintf('Parse Car #%s', $confNo), ['Header' => 3]);
        $r = $this->itinerariesMaster->add()->rental();

        $r->ota()->confirmation($confNo, "Booking No.");

        $r->general()
            ->noConfirmation()
            ->status($this->http->FindSingleNode("//span[contains(text(), 'Status')]/following-sibling::em"))
            ->traveller(beautifulName($this->http->FindSingleNode('//ul[@class = "orderDriver"]/li[span[contains(text(), "Name:")]]/text()[last()]')), true);

        if (in_array($r->getStatus(), ['Canceled', 'Booking canceled'])) {
            $r->general()->cancelled();
        }

        $r->price()
            ->total(PriceHelper::cost($this->http->FindSingleNode("//span[contains(@class, 'main-price')]/em")))
            ->currency($this->http->FindSingleNode("//span[contains(@class, 'main-price')]/span"));

        // Pickup
        $r->pickup()
            ->date2($this->http->FindSingleNode('//li[@data-type="pickupStore"]/preceding-sibling::li[@class = "sched-item-date"]'))
            ->location($this->http->FindSingleNode('//li[@data-type="pickupStore"]/span'))
            ->phone($this->http->FindSingleNode('//li[@data-type="pickupStore"]/following-sibling::li[@class = "sched-item-contact"]/span[@class = "sched-item-value"]'))
            ->openingHours($this->http->FindSingleNode('//li[@data-type="pickupStore"]/following-sibling::li[@class = "sched-item-bushours"]/div[@class = "sched-item-value"]'));

        $r->dropoff()
            ->date2($this->http->FindSingleNode('//li[@data-type="returnStore"]/preceding-sibling::li[@class = "sched-item-date"]'))
            ->location($this->http->FindSingleNode('//li[@data-type="returnStore"]/span'))
            ->phone($this->http->FindSingleNode('//li[@data-type="returnStore"]/following-sibling::li[@class = "sched-item-contact"]/span[@class = "sched-item-value"]'))
            ->openingHours($this->http->FindSingleNode('//li[@data-type="returnStore"]/following-sibling::li[@class = "sched-item-bushours"]/div[@class = "sched-item-value"]'));

        // CarModel
        $r->car()->model($this->http->FindSingleNode('//div[contains(@class, \'vehInfo\')]//p[contains(@class, "name")]'));
        // CarImageUrl
        $r->car()->image('https:' . $this->http->FindSingleNode("//div[contains(@class, \"vehInfo-img\")]/img/@src"));

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function fetchBookingCar_2()
    {
        $this->logger->notice(__METHOD__);

        $confNo = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(), 'Booking No.')])[1]", null,
            true, "/Booking No. (\w+)$/");

        $this->logger->info(sprintf('Parse Car #%s', $confNo), ['Header' => 3]);
        $r = $this->itinerariesMaster->add()->rental();

        $r->ota()->confirmation($confNo, "Booking No.");

        $r->general()
            ->noConfirmation()
            ->traveller(beautifulName($this->http->FindSingleNode('//*[@class="driver-title"][contains(.,"Name")]/following-sibling::*[1]')),
                true);

        if ($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Confirmation sent to')]")) {
            $r->general()->status('Confirmed');
        }

        $r->price()
            ->total(PriceHelper::cost($this->http->FindSingleNode("(//p[@class='order-fee__total']/descendant::em[contains(@class,'price')])[1]")))
            ->currency($this->http->FindSingleNode("(//p[@class='order-fee__total']/descendant::span[contains(@class,'currency')])[1]"));

        // Pickup
        $r->pickup()
            ->date2($this->http->FindSingleNode('//div[@class="order-location__pickup"][count(./p)=2]/descendant::p[2]'))
            ->location($this->http->FindSingleNode('//div[@class="order-location__pickup"][count(./p)!=2]/descendant::text()[normalize-space()="Branch Address"]/following::text()[normalize-space()!=""][1]'))
            ->phone($this->http->FindSingleNode('//div[@class="order-location__pickup"][count(./p)!=2]/descendant::text()[normalize-space()="Contact Number"]/following::text()[normalize-space()!=""][1]'))
            ->openingHours(
                implode(
                    "; ",
                    $this->http->FindNodes('//div[@class="order-location__pickup"][count(./p)!=2]/descendant::text()[normalize-space()="Business Hours"]/following::*[1]/descendant::text()[normalize-space()!=""]')
                )
            );

        $r->dropoff()
            ->date2($this->http->FindSingleNode('//div[@class="order-location__dropoff"][count(./p)=2]/descendant::p[2]'))
            ->location($this->http->FindSingleNode('//div[@class="order-location__dropoff"][count(./p)!=2]/descendant::text()[normalize-space()="Branch Address"]/following::text()[normalize-space()!=""][1]'))
            ->phone($this->http->FindSingleNode('//div[@class="order-location__dropoff"][count(./p)!=2]/descendant::text()[normalize-space()="Contact Number"]/following::text()[normalize-space()!=""][1]'))
            ->openingHours(
                implode(
                    "; ",
                    $this->http->FindNodes('//div[@class="order-location__dropoff"][count(./p)!=2]/descendant::text()[normalize-space()="Business Hours"]/following::*[1]/descendant::text()[normalize-space()!=""]')
                )
            );

        // CarModel
        $r->car()->model($this->http->FindSingleNode('//h3[@class="order-vehicle__title"]'));
        // CarImageUrl
        $r->car()->image('https:' . $this->http->FindSingleNode("//div[@class='order-vehicle__image']/img/@src"));
        $r->extra()->company($this->http->FindSingleNode("//text()[normalize-space()='Service provided by']/following::text()[normalize-space()!=''][1]"));

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function fetchBookingTransfer($item)
    {
        $this->logger->notice(__METHOD__);

        $confNo = $this->http->FindSingleNode("//span[contains(text(), 'Booking No.')]/following-sibling::em");

        if (!$confNo) {
            $confNo = $item->OrderID ?? null;
            $this->logger->info("Parse Transfer #{$confNo}", ['Header' => 3]);

            $data = '{"head":{},"reqhead":{"protocol":"https","host":"www.trip.com","language":"EN","locale":"en_us","lang":"en_us","cury":"USD","channelid":' . $this->http->FindPreg("/channelid:\s*(\d+)/") . ',"pttype":17,"ptgroup":17,"biztype":33,"cid":"","did":"","sign":"","sf":"online","ubt":{"abtest":{},"pageid":0},"union":{"aid":"","sid":"","ouid":""},"token":"","rmstoken":""},"oid":"' . $confNo . '"}';
            $headers = [
                "Accept"       => "application/json, text/plain, */*",
                "Content-Type" => "application/json;charset=utf-8",
            ];
            $this->http->PostURL("https://www.trip.com/restapi/soa2/14543/orderDetail.json", $data, $headers);
            $response = $this->http->JsonLog();

            $r = $this->itinerariesMaster->add()->transfer();

            $r->ota()->confirmation($response->info->oid, "Booking number");

            $r->general()
                ->noConfirmation()
                ->status($response->state->nm)
                ->date2($response->info->bkdt)
                ->cancellation($response->info->tips)
                ->traveller(beautifulName($response->user->ufstnm . " " . $response->user->ulstnm), true);

            if (in_array($r->getStatus(), ['Canceled', 'Booking canceled'])) {
                $r->general()->cancelled();
            }

            $r->price()
                ->total(PriceHelper::cost($response->info->payamt))
                ->currency($response->info->cury);

            $s = $r->addSegment();

            // Pickup
            $s->departure()
                ->date2($this->http->FindPreg("/([^\(]+)/", false, $response->car->udt))
                ->name($response->car->dpoinm);
            $address = $response->car->dpoiaddr;

            if ($address) {
                $s->departure()->address($address);
            } else {
                $s->departure()->code($this->http->FindPreg("/\(([A-Z]{3})/", false, $s->getDepName()));
            }

            $s->arrival()
                ->noDate()
                ->name($response->car->apoinm);
            $address = $response->car->apoiaddr;

            if ($address) {
                $s->arrival()->address($address);
            } else {
                $s->arrival()->code($this->http->FindPreg("/\(([A-Z]{3})/", false, $s->getArrName()));
            }

            $imageUrl = $response->grp->img;

            if (!$this->http->FindPreg('/^https?/', false, $imageUrl)) {
                $imageUrl = sprintf('https:%s', $imageUrl);
            }
            $s->extra()
                ->model($response->grp->nm)
                ->image($imageUrl)
                ->adults($response->passenger->adult)
                ->kids($response->passenger->child);

            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

            return;
        }

        $this->logger->info(sprintf('Parse Transfer #%s', $confNo), ['Header' => 3]);
        $r = $this->itinerariesMaster->add()->transfer();

        $r->ota()->confirmation($confNo, "Booking No.");

        $r->general()
            ->noConfirmation()
            ->status($this->http->FindSingleNode("//div[contains(text(), 'booking status')]/following-sibling::div[1]"))
            ->date2($this->http->FindSingleNode("//div[contains(text(), 'Booking date')]/following-sibling::div[1]"))
            ->cancellation($this->http->FindSingleNode("//div[contains(text(), 'Refund Policy')]/following-sibling::div[1]"))
            ->traveller(beautifulName($this->http->FindSingleNode('//div[contains(text(), "Passenger Details")]/following-sibling::div[div[contains(text(), "Name")]]/div[contains(@class, "content")]/div')), true);

        if (in_array($r->getStatus(), ['Canceled', 'Booking canceled'])) {
            $r->general()->cancelled();
        }

        $r->price()
            ->total(PriceHelper::cost($this->http->FindSingleNode("//div[contains(text(), 'Total')]/following-sibling::span/span[@class = 'price']")))
            ->currency($this->http->FindSingleNode("//div[contains(text(), 'Total')]/following-sibling::span/span[@class = 'currency']"));

        $s = $r->addSegment();

        // Pickup
        $s->departure()
            ->date2($this->http->FindSingleNode("//div[contains(@class, \"detail-info\")]//div[contains(text(), 'Pick-up Time')]/following-sibling::div/div[1]", null, true, "/([^\(]+)/"))
            ->name($this->http->FindSingleNode('//div[contains(@class, "detail-info")]//div[contains(text(), "From")]/following-sibling::div/div[1]'));
        $address = $this->http->FindSingleNode('//div[contains(@class, "detail-info")]//div[contains(text(), "From")]/following-sibling::div/div[2]');

        if ($address) {
            $s->departure()->address($address);
        } else {
            $s->departure()->code($this->http->FindSingleNode('//div[contains(@class, "detail-info")]//div[contains(text(), "From")]/following-sibling::div/div[1]', null, true, "/\(([A-Z]{3})/"));
        }

        $s->arrival()
            ->noDate()
            ->name($this->http->FindSingleNode('//div[contains(@class, "detail-info")]//div[contains(text(), "To")]/following-sibling::div/div[1]'));
        $address = $this->http->FindSingleNode('//div[contains(@class, "detail-info")]//div[contains(text(), "To")]/following-sibling::div/div[2]');

        if ($address) {
            $s->arrival()->address($address);
        } else {
            $s->arrival()->code($this->http->FindSingleNode('//div[contains(@class, "detail-info")]//div[contains(text(), "To")]/following-sibling::div/div[1]', null, true, "/\(([A-Z]{3})/"));
        }

        $s->extra()
            ->model($this->http->FindSingleNode('//div[contains(@class, "ibu-detail-grp__name")]/b'))
            ->image('https:' . $this->http->FindSingleNode('//div[contains(@class, "ibu-detail-grp__img")]/@style', null, true, "/background-image: url\(\"([^\"]+)/"))
            ->adults($this->http->FindSingleNode('//div[contains(@class, "detail-info")]//div[contains(text(), "Passengers")]/following-sibling::div', null, true, "/(\d+)\s*Adult/"));

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function getBooking($link)
    {
        $this->logger->notice(__METHOD__);

        if (false === filter_var($link, FILTER_VALIDATE_URL)) {
            return false;
        }
        $this->http->GetURL($link);

        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'system is currently busy or undergoing system maintenance')]")) {
            return false;
        }

        if ($error = $this->http->FindSingleNode('//div[@id="main"]//strong[@class="error"]')
            || false != mb_strpos($this->http->Response['body'], '很抱歉，程序有误，需要重新选择目的地')
            || 500 == $this->http->Response['code']
        ) {
            //$this->logger->notice('Error site provider: ' . $error);
            return false;
        }

        if ($this->http->Response['code'] == 404) {
            if (strstr($this->http->currentUrl(), 'https://www.trip.com/www.trip.com/')) {
                $this->http->GetURL(str_replace('https://www.trip.com/www.trip.com/', 'https://www.trip.com/', $this->http->currentUrl()));
            } elseif ($this->http->currentUrl() == 'https://www.trip.com/hotels/404') {
                sleep(1);
                $this->http->GetURL($link);
            }
        }// if ($this->http->Response['code'] == 404)

        return true;
    }

    private function currency($currency)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Currency]: {$currency}");

        switch ($currency) {
            case '$':
            case 'US$':
                $currency = 'USD';

                break;

            case 'AU$':
                $currency = 'AUD';

                break;

            case 'HK$':
                $currency = 'HKD';

                break;

            case '€':
                $currency = 'EUR';

                break;

            case '£':
                $currency = 'GBP';

                break;

            case '₩':
                $currency = 'KRW';

                break;
        }

        return $currency;
    }

    private function fetchBookingFlight($passengers): void
    {
        $this->logger->notice(__METHOD__);

        $confNo = $this->http->FindSingleNode('//strong[contains(text(), "Booking Number:")]', null, true, "/\:\s*([^<]+)/");

        if (!$confNo) {
            $confNo = $this->http->FindSingleNode('//span[@class = "order-num" and contains(text(), "Booking No.")]', null, true, "/\.\s*([^<]+)/");
        }
        $this->logger->info('Parse Flight #' . $confNo, ['Header' => 3]);
        $f = $this->itinerariesMaster->add()->flight();

        $status = $this->http->FindSingleNode('//h2[@class = "ibu-order-head-title"] | //div[contains(@class, "order-status")]/p');
        $f->general()->status($status);

        if (in_array($f->getStatus(), ['Canceled', 'Booking canceled', 'Ticket(s) Canceled'])) {
            $f->general()->cancelled();
        }

        if ($status === 'Partially Canceled') {
            // hard code - exclude not cancelled infant tickets
            if ($this->http->FindSingleNode("//strong[starts-with(normalize-space(),'This is a multi-booking package')]")) {
                if (!empty($nextData = $this->http->FindPreg("/__NEXT_DATA__\s*=\s*(\{.+\}\});__NEXT_/"))) {
                    $data = $this->http->JsonLog($nextData);

                    if (isset($data->props, $data->props->pageProps, $data->props->pageProps->initialProps->orderStatus, $data->props->pageProps->initialProps->orderStatus->relatedOrders)) {
                        $onlyInfant = true;

                        foreach ($data->props->pageProps->initialProps->orderStatus->relatedOrders as $order) {
                            if ($order->type !== 'Infant ticket') {
                                $onlyInfant = false;

                                break;
                            }
                        }

                        if ($onlyInfant) {
                            $f->general()->cancelled();
                        }
                    }
                }

                if (!$f->getCancelled()) {
                    $this->sendNotification("check how Partially Canceled multi-booking package collected // ZM");
                }
            }
        }

        $currency = $this->http->FindSingleNode('(//div[not(contains(@style, "display:none"))]/span[contains(text(), "Total")]/following-sibling::span/i[1] | //dt[span[contains(text(), "Total Price")]]/following-sibling::dd/span/span)[1]');
        $f->price()
            ->currency($this->currency($currency))
            ->cost(PriceHelper::cost($this->http->FindSingleNode('//span[contains(text(), "Fare")]/following-sibling::span/em/i[2] | //dt[contains(text(), "Fare")]/following-sibling::dd/div/em')))
            ->tax(PriceHelper::cost($this->http->FindSingleNode('//span[contains(text(), "Taxes & fees")]/following-sibling::span/em/i[2] | //dt[contains(text(), "Taxes &")]/following-sibling::dd/div/em')))
            ->total(PriceHelper::cost($this->http->FindSingleNode('//div[not(contains(@style, "display:none"))]/span[contains(text(), "Total") and not(contains(text(), "Refund"))]/following-sibling::span/i[2] | //dt[span[contains(text(), "Total Price")]]/following-sibling::dd/span/em')));

        $f->ota()->confirmation($confNo, "Booking Number");

        $bookingReferenceList = array_unique($this->http->FindNodes('//div[@class = "ibu-order-pnr-col" and contains(text(), "Airline Booking Reference")]/strong'));

        if (count($bookingReferenceList)) {
            $primary = count($bookingReferenceList) == 1 ? true : false;

            foreach ($bookingReferenceList as $value) {
                $f->general()->confirmation($value, 'Booking reference', $primary);
            }
        } else {
            $f->general()->noConfirmation();
        }

        $ticketNumbers = array_unique($this->http->FindNodes('//div[@class = "ibu-order-pnr-col" and contains(text(), "E-ticket No.")]/strong'));

        if (!$ticketNumbers) {
            $ticketNumbers = array_unique($this->http->FindNodes('//span[contains(text(), "Flight ticket number")]/following-sibling::strong', null, "/(?:Depart|Returning)\s*(.+)/"));
        }

        if (count($ticketNumbers)) {
            $f->setTicketNumbers($ticketNumbers, false);
        }

        // travellers
        $passengerItems = $this->http->FindNodes('//div[@class = "ibu-order-pnr-col" and contains(text(), "Airline Booking Reference")]/preceding-sibling::div');

        if (!$passengerItems) {
            $passengerItems = array_values(array_filter(
                explode(', ', $this->http->FindSingleNode('//span[contains(text(), "Passenger name:")]/following-sibling::strong'))
            ));
        }

        if (!$passengerItems) {
            $pax = $this->http->FindPreg('/"passengers":\[(.+?)\]/');

            if (preg_match_all('/"name":"(.+?)"/', $pax, $m)) {
                $passengerItems = $m[1];
            } elseif (preg_match_all('/"(.+?)"/', $pax, $m)) {
                $passengerItems = $m[1];
            }
        }
        $passengers = array_filter(array_unique(array_map(function ($item) {
            return beautifulName($item);
        }, $passengerItems)));

        if (count($passengers)) {
            $f->general()->travellers($passengers, true);
        }

        if ($orderTime = $this->http->FindSingleNode('//span[@class = "order-time"]', null, true, "/(.+)\s+Created/")) {
            $f->general()->date2($orderTime);
        }

        $segments = $this->http->XPath->query('//div[@class = "ibu-flt-dts-seq"]');
        $this->logger->debug("Total {$segments->length} segments were found");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $depTerminal = $this->http->FindSingleNode('div[contains(@class, "body")]/div[1]/span[2]', $segment);

            if ($term = $this->http->FindPreg("/^T(\d+)$/", false, $depTerminal)) {
                $depTerminal = $term;
            }
            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode('div[contains(@class, "body")]/div[1]/span[1]', $segment))
                ->terminal($depTerminal, true, true)
                ->date2($this->http->FindSingleNode('div[contains(@class, "head")]/strong', $segment) . " " . $this->http->FindSingleNode('div[contains(@class, "head")]/span', $segment));

            $arrivalXpath = 'following-sibling::div[@class = "ibu-flt-dts-seq mt-5"]/';
            $arrTerminal = $this->http->FindSingleNode($arrivalXpath . 'div[contains(@class, "body")]/div[1]/span[2]', $segment, true, null, 0);

            if ($term = $this->http->FindPreg("/^T(\d+)$/", false, $arrTerminal)) {
                $arrTerminal = $term;
            }
            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode($arrivalXpath . 'div[contains(@class, "body")]/div[1]/span[1]', $segment, true, null, 0))
                ->terminal($arrTerminal, true, true)
                ->date2($this->http->FindSingleNode($arrivalXpath . 'div[contains(@class, "head")]/strong', $segment, true, null, 0) . " " . $this->http->FindSingleNode($arrivalXpath . 'div[contains(@class, "head")]/span', $segment, true, null, 0));

            $s->airline()
                ->name($this->http->FindSingleNode($arrivalXpath . 'div[contains(@class, "body")]/div[2]/span/span[1]', $segment, true, "/([A-Z]{1,2})/", 0))
                ->number($this->http->FindSingleNode($arrivalXpath . 'div[contains(@class, "body")]/div[2]/span/span[1]', $segment, true, "/[A-Z]{1,2}(\d+)/", 0));

            $s->extra()
                ->duration($this->http->FindSingleNode('div[contains(@class, "body")]/div[2]/span[2]', $segment))
                ->cabin($this->http->FindSingleNode($arrivalXpath . 'div[contains(@class, "body")]/div[2]/span/span[last()]', $segment, true, null, 0));

            if ((count($this->http->FindNodes($arrivalXpath . 'div[contains(@class, "body")]/div[2]/span/span', $segment)) % 3) == 0) {
                $s->extra()
                    ->aircraft($this->http->FindSingleNode($arrivalXpath . 'div[contains(@class, "body")]/div[2]/span/span[2]', $segment, true, null, 0));
            }
        }// foreach ($segments as $segment)

        $segments = $this->http->XPath->query('//div[contains(@class, "tripItem_box")]');
        $this->logger->debug("Total {$segments->length} segments v.2 were found");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $departure = $this->http->FindSingleNode('.//td[@class = "column_01"]/p[contains(@class, "city")]', $segment, false, "/(.+)T\d+$/");
            $departure = $departure ?? $this->http->FindSingleNode('.//td[@class = "column_01"]/p[contains(@class, "city")]', $segment);
            $s->departure()
                ->code($this->http->FindSingleNode('.//td[@class = "column_01"]/p[contains(@class, "pek")]', $segment))
                ->name($departure)
                ->terminal($this->http->FindSingleNode('.//td[@class = "column_01"]/p[contains(@class, "city")]', $segment, true, "/.+T(\d+)$/") ?? '', true)
                ->date2(str_replace('-', '/', $this->http->FindSingleNode('.//td[@class = "column_01"]/p[contains(@class, "time")]', $segment)));

            $arrival = $this->http->FindSingleNode('.//td[@class = "column_03"]/p[contains(@class, "city")]', $segment, false, "/(.+)T\d+$/");
            $arrival = $arrival ?? $this->http->FindSingleNode('.//td[@class = "column_03"]/p[contains(@class, "city")]', $segment);
            $s->arrival()
                ->code($this->http->FindSingleNode('.//td[@class = "column_03"]/p[contains(@class, "pek")]', $segment))
                ->name($arrival)
                ->terminal($this->http->FindSingleNode('.//td[@class = "column_03"]/p[contains(@class, "city")]', $segment, true, "/.+T(\d+)$/") ?? '', true)
                ->date2(str_replace('-', '/', $this->http->FindSingleNode('.//td[@class = "column_03"]/p[contains(@class, "time")]', $segment)));

            $s->airline()
                ->name($this->http->FindSingleNode('.//span[@class = "airType"]', $segment, true, "/([A-Z]{1,2})/"))
                ->number($this->http->FindSingleNode('.//span[@class = "airType"]', $segment, true, "/[A-Z]{1,2}(\d+)/"));

            $s->extra()
                ->duration($this->http->FindSingleNode('.//p[contains(@class, "duration")]', $segment, true, "/\:\s*([^<]+)/"))
                ->cabin($this->http->FindSingleNode('.//span[@class = "airType"]/following-sibling::span[1]', $segment));
        }// foreach ($segments as $segment)

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function fetchBookingHotel()
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->add()->hotel();
        // booking conf
        $confNo = (
            $this->http->FindSingleNode('//div[span[contains(text(), "Booking No.")]]/following-sibling::div/span')
            ?? $this->http->FindSingleNode('//div[contains(text(), "Booking No.")]/following-sibling::div')
        );
        $this->logger->info("Parse Hotel #{$confNo}", ['Header' => 3]);
        $h->ota()->confirmation($confNo, "Booking No.");
        // hotel conf
        $hotelConfNo = (
            $this->http->FindSingleNode('//div[span[contains(text(), "Hotel Confirmation No.")]]/following-sibling::div/span', null, false)
            ?? $this->http->FindSingleNode('//div[contains(text(), "Hotel Confirmation No.")]/following-sibling::div', null, false)
        );

        if ($hotelConfNo == 'RYa0axhts2_1;RYa0axhts2_2;RYa0axhts2_3') {
            $this->logger->notice('remove wrong number "RYa0axhts2_1;RYa0axhts2_2;RYa0axhts2_3"');
            $hotelConfNo = null;
        }

        if ($hotelConfNo) {
            $hotelConfNo = preg_replace('/\s+as per PIC.+/', '', $hotelConfNo);

            preg_match_all('/\b([\dA-Z]+)\b/', $hotelConfNo, $matches);
            $matches[1] = array_unique($matches[1]);

            if (count($matches[1]) === 1) {
                $h->general()->confirmation(array_shift($matches[1]), "Confirmation No.", true);
            } elseif (count($matches[1]) > 1) {
                foreach ($matches[1] as $confNoPart) {
                    $h->general()->confirmation($confNoPart, "Confirmation No.");
                }
            } else {
                $h->general()->confirmation($hotelConfNo, "Confirmation No.", true);
            }
        } else {
            $h->general()->noConfirmation();
        }

        $earnedRewards = $this->http->FindSingleNode('//p[contains(text(), "You will receive ") and contains(text(), "point")]', null, true, "/receive\s*(.+points?)/");

        if ($earnedRewards) {
            $h->ota()->earnedAwards($earnedRewards);
        }

        $totalInfo = $this->http->FindSingleNode('//li[@class = "total"]/span');
        $currency = $this->http->FindPreg("/([A-Z]{3})/", false, $totalInfo) ?? $this->http->FindPreg("/\"total\":[\d\.]+,\"currency\":\"([A-Z]{3})\"/");
        $h->price()
            ->total(PriceHelper::cost($this->http->FindPreg("/[A-Z]{3}\s*(.+)$/", false, $totalInfo) ?? $this->http->FindPreg("/\"total\":([\d\.]+),\"currency\":\"[A-Z]{3}\"/")))
            ->cost(PriceHelper::cost($this->http->FindSingleNode('//li[@class = "price-detail-list" and label[span[contains(text(), "Room")]]]//span[@class = "price"]')) ?? $this->http->FindPreg("/\"title\":\"[^\"]+Rooms?\",\"price\":([\d\.]+)/"))
            ->currency($currency);

        $taxes = PriceHelper::cost($this->http->FindSingleNode('//li[@class = "price-detail-list" and label[span[contains(text(), "Taxes")]]]//span[@class = "price"]'))
            ?? $this->http->FindPreg("/\"title\":\"Taxes & Fees\",\"price\":([\d\.]+)/")
            ?? null;

        if ($taxes) {
            $h->price()->tax($taxes);
        }

        $travelers = $this->http->FindSingleNode('//td[contains(text(), "Guest Names")]/following-sibling::td[1]', null, false);

        if (!$travelers) {
            $travelers = $this->http->FindSingleNode('//div[contains(text(), "Guest Names")]/following-sibling::div[1]', null, false);
        }
        $travelers = $travelers ? explode(',', $travelers) : [];
        $travelers = array_filter(array_map(function ($item) {
            return beautifulName($item);
        }, $travelers));

        $reservationDate = (
            $this->http->FindSingleNode('//div[span[contains(text(), "Booking Date")]]/following-sibling::div/span')
            ?: $this->http->FindSingleNode('//div[contains(text(), "Booking Date")]/following-sibling::div')
        );

        if ($reservationDate) {
            $reservationDate = $this->http->FindPreg('/^(.+?)\s*\(/', false, $reservationDate) ?: $reservationDate;
        }
        $h->general()
            ->status($this->http->FindSingleNode('//span[@class = "c-orderstatus_status_text" or @class = "m-orderProcessStatus_txt"]'))

            ->date2($reservationDate)
            ->cancellation(
                $this->http->FindSingleNode('//div[@class = "c-orderDetail_cancellation_text"]')
                ?? $this->http->FindSingleNode('//div[@class = "m-cancelPolicy_remark"]'),
                false,
                true
            )
            ->travellers($travelers, true);

        if (in_array($h->getStatus(), ['Canceled', 'Booking canceled'])) {
            $h->general()->cancelled();
        }

        $earnedRewardsIts = $this->http->FindSingleNode('//p[contains(text(), "You\'ll earn")]', null, true, "/earn\s*(.+Points?)/");

        if ($earnedRewardsIts) {
            $h->program()->earnedAwards($earnedRewardsIts);
        }

        $h->hotel()
            ->name(
                $this->http->FindSingleNode('//div[@class = "b-detail__describe"]/div[@class = "title"]')
                ?? $this->http->FindSingleNode('//div[@class = "m-hotelInfo-desc"]/h5[@class = "hotelName"]')
            )
            ->address(
                $this->http->FindSingleNode('//div[@class = "b-detail__describe"]/div[@class = "b-detail__address"]')
                ?? $this->http->FindSingleNode('//div[@class = "m-hotelInfo-desc"]/div[@class = "m-hotelInfo-address"]')
            );

        $checkIn = $this->http->FindSingleNode('//div[contains(text(), "Check-in")]/following-sibling::div[@class = "c-od_date-date-text" or @class = "time"]', null, true, "/\w{3}\,\s*(.+)/");

        if (!$checkIn) {
            $checkIn = $this->http->FindSingleNode('//div[contains(text(), "Check-in")]/following-sibling::div[@class = "c-od_date-date-text" or @class = "time"]', null, true, "/^\w{2}\,\s*(.+)/");
        }
        $checkInTime =
            $this->http->FindSingleNode('//td[contains(text(), "Arrival Time")]/following-sibling::td[1]', null, true, "/\,\s*([^\-]+)/")
            ?? $this->http->FindSingleNode('//div[contains(text(), "Arrival Time")]/following-sibling::div[1]', null, true, "/\,\s*([^\-]+)/")
        ;

        if ($this->http->FindPreg("/^[A-Z][a-z]{2}\s+\d+$/", false, $checkInTime)) {
            $checkInTime =
                $this->http->FindSingleNode('//td[contains(text(), "Arrival Time")]/following-sibling::td[1]', null, true, "/^(\d{2}:\d{2})\,/")
                ?? $this->http->FindSingleNode('//div[contains(text(), "Arrival Time")]/following-sibling::div[1]', null, true, "/^(\d{2}:\d{2})\,/")
            ;
        }
        $checkOut = $this->http->FindSingleNode('//div[contains(text(), "Check-out")]/following-sibling::div[@class = "c-od_date-date-text" or @class = "time"]', null, true, "/\w{3}\,\s*(.+)/");

        if (!$checkOut) {
            $checkOut = $this->http->FindSingleNode('//div[contains(text(), "Check-out")]/following-sibling::div[@class = "c-od_date-date-text" or @class = "time"]', null, true, "/^\w{2}\,\s*(.+)/");
        }
        $checkOutTime =
            $this->http->FindSingleNode('//td[contains(text(), "Check-out")]/following-sibling::td[1]', null, true, "/\,\s*(?:before)([^\-]+)/")
            ?? $this->http->FindSingleNode('//div[@class = "roomLable" and contains(text(), "Check-out")]/following-sibling::div[1]', null, true, "/\,\s*(?:before)([^\-(]+)/")
        ;

        $adults = $this->http->FindNodes('//span[contains(text(), "Max. guests per room")]/following-sibling::span');
        $countOfAdults = 0;

        foreach ($adults as $adult) {
            $countOfAdults += intval($adult);
        }

        $h->booked()
            ->checkIn2($checkIn . " " . $checkInTime)
            ->checkOut2($checkOut . " " . $checkOutTime)
            ->guests($countOfAdults)
            ->rooms(intval($this->http->FindSingleNode('//td[contains(text(), "Rooms")]/following-sibling::td[1]')));

        $deadline = $this->http->FindPreg("/You may cancel or change for free before ([^\(]+)/", false, $h->getCancellation());

        if ($deadline) {
            $this->logger->debug($deadline);
            $deadline = str_replace(',', '', $deadline);
            $this->logger->debug($deadline);
            $h->booked()->deadline2($deadline);
        }

        if (
            $this->http->FindSingleNode('//div[@class = "c-orderDetail_cancellation_text"]/preceding-sibling::span[contains(., "Non-refundable")]', null, false)
            || $this->http->FindSingleNode('//div[@class = "m-cancelPolicy_remark"]/preceding-sibling::div[contains(., "Non-refundable")]', null, false)
        ) {
            $h->booked()->nonRefundable();
        }

        $rooms = $this->http->XPath->query('//div[@class = "c-orderDetail_roomtype"]/div | //div[contains(@class, "m-roomInfo m-module-normal")]');
        $this->logger->debug("Total {$rooms->length} rooms were found");

        foreach ($rooms as $room) {
            $r = $h->addRoom();
            $r->setType($this->http->FindSingleNode('.//div[@class = "m-card-head-title"] | .//h3[@class = "m-module-normal_content"]', $room));
            $description = $this->http->FindNodes('.//td[contains(text(), "Special Requests")]/following-sibling::td[1] | .//div[contains(text(), "Special Requests")]/following-sibling::div[1]', $room);

            if (!empty($description)) {
                $r->setDescription(implode(", ", $description));
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function fetchBookingRail(object $order): void
    {
        $this->logger->notice(__METHOD__);
        $confNo = $order->OrderID ?? null;
        $this->logger->info("Parse Rail #{$confNo}", ['Header' => 3]);
        $train = $this->itinerariesMaster->createTrain();
        $train->addConfirmationNumber($confNo, 'Booking No.', true);

        $bookingDate = $order->BookingDate;

        if ($bookingDate) {
            $date = $this->http->FindPreg('/Date\((\d+)\d{3}\+/', false, $bookingDate);
            $train->setReservationDate($date ? intval($date) : null);
        }

        $train->price()->total($order->OrderTotalPrice ?? null);
        $train->price()->currency($order->Currency ?? null);
        $dates = $this->http->XPath->query("//h5[@class='major date']");

        foreach ($dates as $dateRoot) {
            $date = $dateRoot->nodeValue;
            $this->logger->debug("date: $date");
            $items = $this->http->XPath->query("./following-sibling::div//div[contains(@class,'intl-train-route-stations')]", $dateRoot);
            $this->logger->debug("Segment $items->length count");

            foreach ($items as $item) {
                $depName = $this->http->FindSingleNode("./p[@class='text-prima pad-l-station'][1]/text()[1]", $item);
                $depTime = $this->http->FindSingleNode("./p[@class='text-prima pad-l-station'][1]/span[@class='text-prima d-a-dates']",
                    $item);
                $arrName = $this->http->FindSingleNode("./p[@class='text-prima pad-l-station'][2]/text()[1]", $item);
                $arrTime = $this->http->FindSingleNode("./p[@class='text-prima pad-l-station'][2]/span[@class='text-prima d-a-dates']",
                    $item);
                $number = $this->http->FindSingleNode(".//div[contains(@class,'seg-info')]//p[@class='text-maj']", $item);

                if ($confNo && $depName && empty($number)) {
                    $this->itinerariesMaster->removeItinerary($train);
                    $this->logger->error("Skip not number");

                    return;
                }
//                $this->logger->debug("dep date name: $depName");
//                $this->logger->debug("dep date time: $depTime");
//                $this->logger->debug("arr date name: $arrName");
//                $this->logger->debug("arr date time: $arrTime");
//                $this->logger->debug("number train: $number");

                $seg = $train->addSegment();
                $seg->setNumber($number);
                $seg->setDepName($depName);
                $seg->setDepDate(strtotime($date . ', ' . $depTime));

                $seg->setArrName($arrName);
                $seg->setArrDate(strtotime($date . ', ' . $arrTime));
            }
        }
        $this->logger->debug('Parsed Train:');
        $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);
    }

    private function fetchBookingTrain(object $order): void
    {
        $this->logger->notice(__METHOD__);

        $confNo = $this->http->FindSingleNode('//span[contains(text(), "Booking no.")]', null, true, "/\.\s*(\w+)/");

        if (!$confNo) {
            $confNo = $order->OrderID ?? null;
            $this->logger->info("Parse Train #{$confNo}", ['Header' => 3]);
            $train = $this->itinerariesMaster->createTrain();
            $train->addConfirmationNumber($confNo, 'Booking No.', true);

            $bookingDate = $order->BookingDate;

            if ($bookingDate) {
                $date = $this->http->FindPreg('/Date\((\d+)\d{3}\+/', false, $bookingDate);
                $train->setReservationDate($date ? intval($date) : null);
            }

            $train->price()->total($order->OrderTotalPrice ?? null);
            $train->price()->currency($order->Currency ?? null);

            $items = $order->TrainInfo->Items ?? [];
            $travellers = [];

            foreach ($items as $item) {
                $seg = $train->addSegment();
                $seg->setNumber($item->TrainNumber ?? null);

                $seg->setDepName($item->DepartureStation ?? null);
                $seg->setDepDate(strtotime($item->DepartureDateStr ?? ''));

                $seg->setArrName($item->ArrivalStation ?? null);
                $seg->setArrDate(strtotime($item->ArrivalDateStr ?? ''));

                $seg->setCarNumber($item->CarriageNo ?? null);
                $seg->addSeat($this->http->FindPreg('/^(\w+)/', false, $item->SeatNo));
                $productType = $item->ProductType;

                if ($productType === '一等座') {
                    $seg->setCabin('1st Class');
                } elseif ($productType === '商务座') {
                    $seg->setCabin('Business Class');
                } elseif ($productType === '二等座') {
                    $seg->setCabin('2nd Class');
                } elseif ($productType === '软座') {
                    $seg->setCabin('Soft seat');
                } else {
                    $this->sendNotification("check cabin {$productType} // MI");
                }

                $travellers = array_merge($travellers, $item->Passagers ?? []);
            }
            $train->setTravellers($travellers);

            $this->logger->debug('Parsed Train:');
            $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);

            return;
        }

        $this->logger->info("Parse Train #{$confNo}", ['Header' => 3]);
        $t = $this->itinerariesMaster->add()->train();

        $t->ota()->confirmation($confNo, "Booking No.");

        $t->setTicketNumbers(array_filter($this->http->FindNodes('//div[@class = "pickup-number"]/h3/text()[last()] | //div[@class = "pickup-number"]/h3/b')), false);

        // Passengers
        $travellers = array_unique(array_map(function ($item) {
            return beautifulName($item);
        }, $this->http->FindNodes('//label[@class = "name"]')));
        $t->general()
            ->noConfirmation()
            ->status($this->http->FindSingleNode("//h2[contains(@class, 'status')]"))
            ->travellers($travellers, true);

        if (in_array($t->getStatus(), ['Canceled', 'Booking canceled'])) {
            $t->general()->cancelled();
        }

        $totalBlock = $this->http->FindSingleNode('//div[contains(@class, "ticket-price")]');
        $currency = $this->http->FindPreg("/[A-Z]{3}/", false, $totalBlock) ?? $this->http->FindPreg("/Total\s*([^\d]+)\s*\d+/", false, $totalBlock);
        $t->price()
            ->currency($this->currency($currency))
            ->cost(PriceHelper::cost(array_sum($this->http->FindNodes('//span[@class = "c-price"]', null, self::BALANCE_REGEXP))))
            ->total(PriceHelper::cost($this->http->FindSingleNode('//div[contains(@class, "ticket-price")]//span[contains(@class, "price")]')));

        if ($fee = $this->http->FindSingleNode("//dt[contains(text(), 'Booking fee')]/following-sibling::dd/span", null, true, self::BALANCE_REGEXP)) {
            $t->price()->fee("Booking fee", $fee);
        }

        $segments = $this->http->XPath->query('//div[contains(@class, "order-detail-body-table")]');
        $this->logger->debug("Total {$segments->length} segments were found");

        foreach ($segments as $segment) {
            $s = $t->addSegment();

            $s->extra()
                ->number($this->http->FindSingleNode('.//span[contains(@class, "trains-no")]/@data-trainnumber', $segment))
                ->car(implode(', ', array_unique($this->http->FindNodes('.//strong[contains(text(), "Carriage")]', $segment, "/Carriage\s*([^,]+)/"))), true, true)
                ->cabin(implode(', ', array_unique($this->http->FindNodes('.//label[@class = "id-type"]', $segment))))
                ->duration($this->http->FindSingleNode('.//div[contains(@class, "train-line")]//span[contains(@class, "hours")]', $segment))
                ->seats($this->http->FindPregAll('/Seat no\. (\w+)/', $segment->nodeValue));

            $departsDate = $this->http->FindSingleNode('.//span[contains(@class, "trains-no")]/@data-departdate', $segment);
            $date1 = $this->http->FindSingleNode('.//div[contains(@class, "train-from")]//span[contains(@class, "train-time")]', $segment);
            $time1 = $this->http->FindSingleNode('.//div[contains(@class, "train-from")]//span[contains(@class, "train-hour")]', $segment);
            $depDate = $date1 . ' ' . $time1;
            $depDate = EmailDateHelper::parseDateRelative($depDate, $departsDate);
            $this->logger->info(var_export([
                'date1'       => $date1,
                'time1'       => $time1,
                'departsDate' => $departsDate,
            ], true), ['pre' => true]);
            $s->departure()
                ->name($this->http->FindSingleNode('.//span[contains(@class, "station-from")]', $segment))
                ->date($depDate);

            $date2 = $this->http->FindSingleNode('.//div[contains(@class, "train-to")]//span[contains(@class, "train-time")]', $segment);
            $time2 = $this->http->FindSingleNode('.//div[contains(@class, "train-to")]//span[contains(@class, "train-hour")]', $segment);
            $arrDate = $date2 . ' ' . $time2;
            $arrDate = EmailDateHelper::parseDateRelative($arrDate, $departsDate);
            $this->logger->info(var_export([
                'date2'       => $date2,
                'time2'       => $time2,
                'departsDate' => $departsDate,
            ], true), ['pre' => true]);
            $s->arrival()
                ->name($this->http->FindSingleNode('.//span[contains(@class, "station-to")]', $segment))
                ->date($arrDate);
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($t->toArray(), true), ['pre' => true]);
    }
}
