<?php

namespace AwardWallet\Engine\british;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseHistoryInterface;
use AwardWallet\ExtensionWorker\ParseHistoryOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Common\Statement;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class BritishExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface, ParseHistoryInterface
{
    use TextTrait;

    private $activityIgnore = [
        "Expired Avios",
        "Points Reset for New Membership Year",
        "Combine My Avios",
        "Manual Avios Adjustment",
        "Redemption Redeposit",
        "Avios Adjustment",
        "Tier Points Adjustment",
    ];
    private $itCount = 0;

    public function getStartingUrl(AccountOptions $options): string
    {
        //return $this->getCountryUrl('https://www.britishairways.com/travel/home/public/%s', $options);
        return $this->getCountryUrl(
            'https://www.britishairways.com/travel/viewaccount/execclub/_gf/%s',
            $options
        );
    }

    public function getCountryUrl($url, AccountOptions $options): string
    {
        $country = $options->login2;

        if (strlen($country) > 0) {
            $country = "en_$country";
        } else {
            $country = "en_us";
        }

        return sprintf($url, strtolower($country));
    }

    public function isLoggedIn(Tab $tab): bool
    {
//        $this->logger->debug("attr " .$tab->findText('//input[@id = "username"]/@id'));
//        $tab->evaluate("//h1/h1");
        $inputOrLogout = $tab->evaluate('
        //form[@id = "execLoginrForm"] 
        | //main/section//form[@method="POST"]
        | //h1[contains(text(),"You are already logged in with a different account")]
        | //ba-button/span[starts-with(.,"Log in")] 
        | //ba-button[starts-with(.,"Log out")] 
        | //li/a[contains(text(),"Log out")]
        | //h3[contains(text(),"Log in to your British Airways account")]
        | //h3[contains(text(),"Login to your British Airways account")]',
            EvaluateOptions::new()->nonEmptyString());
        $this->logger->debug("inputOrLogout: {$inputOrLogout->getInnerText()}");

        return str_starts_with($inputOrLogout->getInnerText(), 'You are already logged')
            || str_starts_with($inputOrLogout->getInnerText(), 'LOG OUT');
    }

    public function getLoginId(Tab $tab): string
    {
        $number = $tab->findText(
            '//p[contains(text(), "Your membership number")] | //p[contains(text(), "Membership number:")]/following-sibling::p
            | //span[@id="membershipNumberValue"]',
            FindTextOptions::new()->preg('/(\d+)$/')
        );
        $this->logger->debug("number: $number");

        return $number;
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//a[starts-with(text(),"Log out")] | //ba-button[starts-with(.,"Log out")] | //li/a[contains(text(),"Log out")]')->click();
        // Sometimes the redirect happens to the authorization form and sometimes to the main one
        $result = $tab->evaluate('//form[@id="execLoginrForm"] | //app-searchbar | //form//input[@id="username"]', EvaluateOptions::new()->timeout(90));
        $this->logger->debug("getNodeName: {$result->getNodeName()}");

        if ($result->getNodeName() != 'APP-SEARCHBAR') {
            $tab->gotoUrl('https://www.britishairways.com/');
            $tab->evaluate('//app-searchbar');
        }
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        $tab->evaluate('//input[@id="username"] | //input[@id="membershipNumber"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@id="password"] | //input[@id="input_password"]')->setValue($credentials->getPassword());
        $captcha = $tab->evaluate('//div[@id="ulp-hcaptcha"]/iframe', EvaluateOptions::new()->allowNull(true)->timeout(5));

        if ($captcha) {
            $this->logger->notice('show captcha');
            $tab->showMessage(Tab::MESSAGE_RECAPTCHA);
        } else {
            $tab->evaluate('//button[contains(text(),"Continue")] | //button[@id="ecuserlogbutton"]')->click();
        }
        sleep(3);
        $errorOrLogout = $tab->evaluate('
            //span[@id="error-element-captcha" or @id="error-element-password"]
            | //ba-button[starts-with(.,"Log out")] | //li[@class="logout"]/a[contains(text(),"Log out")]
            | //h1[contains(text(),"Verify Your Identity") or contains(text(),"Verify your identity")]',
            EvaluateOptions::new()->nonEmptyString()->timeout(60)->allowNull(true));

        if ($errorOrLogout === null && $tab->evaluate('//div[@id="ulp-hcaptcha"]/iframe', EvaluateOptions::new()->allowNull(true)->timeout(0))) {
            return LoginResult::captchaNotSolved();
        }

        $this->logger->debug("[errorOrLogout]: {$errorOrLogout->getInnerText()}");
        if (str_starts_with($errorOrLogout->getAttribute('id'), 'error-element-')) {
            return LoginResult::invalidPassword($errorOrLogout->getInnerText());
        } elseif (str_starts_with(mb_strtoupper($errorOrLogout->getInnerText()), 'LOG OUT')) {
            return new LoginResult(true);
        } elseif (str_starts_with(mb_strtolower($errorOrLogout->getInnerText()), 'verify your identity')) {
            $this->logger->warning('loading 2fa');

            $errorOrLogout = $tab->findTextNullable('//ba-button[starts-with(.,"Log out")] | //li[@class="logout"]/a[contains(text(),"Log out")]',
                FindTextOptions::new()->timeout(120));

            if (str_starts_with(mb_strtoupper($errorOrLogout), 'LOG OUT')) {
                return new LoginResult(true);
            } else {
                // We've sent a text message to:
                // We've sent an email with your code to
                $questionMessage = $tab->findTextNullable('//p[contains(text(),"We\'ve sent a text message to")]') ??
                    $tab->findTextNullable('//p[contains(text(),"We\'ve sent an email with your code to")]');
                $questionPhone = $tab->findTextNullable('//p[contains(text(),"We\'ve sent a text message to")]/following::span[contains(text(),"XXXXXX")]') ??
                    $tab->findTextNullable('//p[contains(text(),"We\'ve sent an email with your code to")]/following::span[contains(text(),"****")]');

                if ($questionMessage && $questionPhone) {
                    return LoginResult::question("{$questionMessage} {$questionPhone}");
                    //return new LoginResult(false, "{$questionMessage} {$questionPhone}", 'question', ACCOUNT_QUESTION);
                }
            }
        }

        // return new LoginResult(false, $errorOrLogout->getInnerText(), null, ACCOUNT_INVALID_PASSWORD);
        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $tab->saveScreenshot();

        //$tab->evaluate('//ba-button[contains(@href,"/travel/echome/execclub/_gf/")]')->click();
        $customers = $this->getCustomers($tab);
        $detail = $customers->customer->registeredCustomer->loyaltyAccountDetails;
        $master->createStatement()->setNumber($detail->loyaltyAccountNumber);
        $st = $master->getStatement();
        $st
            // Balance - Avios
            ->setBalance($detail->individualAccountBalance->balance)
            // Tier Point collection year ends
            ->addProperty('YearEnds', date('j F Y', strtotime($detail->tierLevelEndDate)))
            // Membership card expiry:
            ->addProperty('CardExpiryDate', date('j F Y', strtotime($detail->execCardExpiryDate)))
            // Tier Points
            ->addProperty('TierPoints', $detail->renewalTierPointsThreshold)
            // My Lifetime Tier Points
            ->addProperty('LifetimeTierPoints', $detail->lifeTimeTierPoints->balance)
            // TODO - not found
            // Eligible Flights To Next Tier (1 block) v.3
            //->addProperty('EligibleFlightsToNextTier', $detail->lifeTimeTierPoints->balance);
            // Date of joining the club
            // ->addProperty('DateOfJoining', $detail->lifeTimeTierPoints->balance);
        ;

        // Date of joining the club
        switch ($detail->tierLevel) {
            case 'EXECUTIVE_BLUE':
                $st->addProperty('Level', 'Blue Member');

                break;
        }

        $this->logger->info('Expiration date', ['Header' => 3]);
        $tab->gotoUrl($this->getCountryUrl('https://www.britishairways.com/travel/viewtransaction/execclub/_gf/%s?eId=106012&prim=execcl', $accountOptions));
        $exp = $tab->evaluateAll("//div[@class='info-detail-main-transaction-row']");
        $this->logger->debug("Total transactions found: " . count($exp));

        foreach ($exp as $row) {
            // Description
            $activity = $tab->findText("div[starts-with(@id,'resultRow')][3]/p",
                FindTextOptions::new()->contextNode($row));
            // refs #7665 - ignore certain activities
            if (!$this->ignoreActivity($activity)) {
                $date = str_replace('Transaction:', '', $tab->findText("div[starts-with(@id,'resultRow')][1]/p[1]",
                    FindTextOptions::new()->contextNode($row)));
                // refs #9168 - ignore row with empty avios
                $avios = $tab->findText("div[starts-with(@id,'resultRow')][5]/p[2]",
                    FindTextOptions::new()->contextNode($row));
                $this->logger->debug("Date $date / $avios");

                // refs #7665 - ignore certain activities, part 2
                $reference = $this->findPreg("/Reference:\s*([^\s]+)/ims", $activity);

                if (strpos($activity, "Avios refund") !== false || isset($ignoreBookings[$reference])) {
                    $this->logger->debug("Booking Reference: {$reference}");

                    if (isset($ignoreBookings[$reference])) {
                        if ($ignoreBookings[$reference] == -floatval($avios)) {
                            $this->logger->notice("Skip Avios refund: {$reference}");

                            continue;
                        } else {
                            $this->logger->notice("First transaction not found: {$reference}");
                        }
                    } else {
                        $this->logger->notice("Add Avios refund to ignore transactions: {$reference}");
                        $ignoreBookings[$reference] = $avios;

                        continue;
                    }
                }

                if ($avios != '' && $avios != '-') {
                    $st->addProperty('LastActivity', $date);
                    $exp = strtotime($date);

                    if ($exp != false) {
                        $st->setExpirationDate(strtotime("+3 year", $exp));
                    }

                    break;
                }
            }
        }

        $this->logger->info('My eVouchers', ['Header' => 3]);
        $tab->gotoUrl($this->getCountryUrl('https://www.britishairways.com/travel/membership/execclub/_gf/%s?eId=188010',
            $accountOptions));
        $tab->evaluate('//h2[contains(text(),"My eVouchers")]');
        $vouchers = $tab->evaluateAll("//div[@id = 'unusedVouchers']/div[@class='table-body']");
        $this->logger->debug("Total vouchers found: " . count($vouchers));

        foreach ($vouchers as $row) {
            $code = $tab->findText("p[contains(@class,'voucher-list-number')]/span[not(contains(text(), 'number'))]",
                FindTextOptions::new()->contextNode($row));
            //# Type
            $displayName = $tab->findText("p[contains(@class,'voucher-list-type')]",
                FindTextOptions::new()->contextNode($row));
            //# Expiry
            $exp = $tab->findText("p[contains(@class,'voucher-list-details') and span[normalize-space(text())='Expiry']]/span[@class='text']",
                FindTextOptions::new()->contextNode($row));

            if (strtotime($exp) && isset($displayName, $code)) {
                $subAccounts[] = [
                    'Code'           => 'britishVouchers' . $code,
                    'DisplayName'    => "Voucher #$code - $displayName",
                    'Balance'        => null,
                    'ExpirationDate' => strtotime($exp),
                ];
            }
        }

        if (isset($subAccounts)) {
            $this->logger->debug("Total subAccounts: " . count($subAccounts));
            $st->addSubAccount($subAccounts);
        }
    }

    public function parseItineraries(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void {
        $tab->gotoUrl($this->getCountryUrl('https://www.britishairways.com/travel/viewaccount/execclub/_gf/%s?eId=106010',
            $options));
        $tab->evaluate('//h3[contains(text(),"We can\'t find any bookings for this account")] 
        | //a[contains(@class, "small-btn") and span[contains(text(), "Manage My Booking")]]'
        );

        if ($tab->findTextNullable('//h3[contains(text(),"We can\'t find any bookings for this account")]')) {
            if ($parseItinerariesOptions->isParsePastItineraries()) {
                //$this->parsePastItineraries();

                if (count($master->getItineraries()) > 0) {
                    return;
                }
            }// if ($this->ParsePastIts)
            $master->setNoItineraries(true);

            return;
        }

        // View all bookings
        //a[span[contains(text(), 'View all bookings')]]/@href
        if ($allBookingsUrl = $tab->findTextNullable("//a[span[contains(text(), 'View all bookings') or contains(text(), 'View all current flight bookings')]]/@href")) {
            $this->logger->notice(">>> Get page with all bookings");
            $tab->gotoUrl($allBookingsUrl);
            $tab->evaluate("//section[@id='idContentUpcomingFlights']");
        }

        if ($error = $tab->findTextNullable("//h1[text()='Sorry']/following-sibling::p[1][starts-with(normalize-space(),'Sorry, there seems to be a technical problem')]")) {
            $this->logger->error($error);

            return;
        }

        if ($error = $tab->findTextNullable("//h1[text()='Sorry']/following-sibling::p[1][starts-with(normalize-space(),'We regret to advise that this section of the site is temporarily unavailable')]")) {
            $this->logger->error($error);

            return;
        }
        $links = $tab->evaluateAll("//a[contains(@class, 'small-btn') and span[contains(text(), 'Manage My Booking')]]");
        $this->logger->notice(">>> Total " . count($links) . " reservations were found");
        $preParseCancelled = [];
        $pnrs = [];

        foreach ($links as $link) {
            $url = $link->getAttribute('href');
            $pnr = $tab->findText("./ancestor::div[count(./descendant::text()[normalize-space()='Booking Reference'])=1][1]/descendant::text()[normalize-space()='Booking Reference']/following::text()[normalize-space()!=''][1]",
                FindTextOptions::new()->contextNode($link)->preg("/^[A-Z\d]{5,}$/")->timeout(1));
            $pnrs[$url] = $pnr;
        }
        $this->logger->debug("[pnrs]: " . var_export($pnrs, true), ['pre' => true]);

        foreach ($pnrs as $url => $pnr) {
            $this->logger->debug("[PNR]: " . $pnr);
            $this->logger->debug($url);

            if ($this->findPreg("#https://www\.britishairways\.com\/travel#", $url)) {
                $this->logger->notice('Second reservation parsing variant');
                $tab->gotoUrl($url);

                $tab->findTextNullable('//h1[starts-with(normalize-space(),"Booking") and ./strong]', FindTextOptions::new()->timeout(15));
                $tab->saveScreenshot();

                $errorOrDetail = $tab->findTextNullable('
                //li[contains(.,"We\'re sorry, but ba.com is very busy at the moment, and couldn")]
                | //li[contains(text(), "we are unable to display your booking")]
                | //li[contains(text(), "Sorry, We are unable to find your booking.")]
                | //h3[contains(text(), "Sorry, we can\'t display this booking")]
                | //span[not(contains(@class, "wrapText")) and contains(text(), "There are no confirmed flights in this booking")]
                | //li[contains(text(), "Sorry, we can\'t display this booking")]
                | //li[contains(text(), "There was a problem with your request, please try again later.")] ');

                if (!empty($errorOrDetail)) {
                    $this->logger->error($errorOrDetail);

                    if (stripos($errorOrDetail, 'We\'re sorry, but ba.com is very busy at the moment, and couldn')) {
                        continue;
                    }

                    if (isset($preParseCancelled[$pnr])) {
                        $this->logger->info("[{$this->itCount}] Parse Flight #{$pnr}", ['Header' => 3]);
                        $this->itCount++;
                        $r = $master->add()->flight();
                        $r->general()
                            ->confirmation($pnr)
                            ->status('Cancelled')
                            ->cancelled();
                        $this->getSegmentFromPreParse($r, $preParseCancelled[$pnr]);
                    }

                    continue;
                }
                $nonFlightLink = $tab->findTextNullable('(//span[contains(text(), "Print non-flight voucher")])[1]/ancestor::a[1]/@href');

                $msgCancelled =
                    $tab->findTextNullable("//h3[contains(@class, 'refund-progress')][contains(normalize-space(),'We are currently processing a cancellation and refund for this booking')]") ?:
                        $tab->findTextNullable("//span[contains(@class, 'wrapText') and normalize-space()='There are no confirmed flights in this booking']/following::text()[normalize-space()!=''][1][normalize-space()='There are no confirmed flights in this booking.']");

                $cntBefore = count($master->getItineraries());

                if ($msgCancelled) {
                    $this->logger->info("[{$this->itCount}] Parse Flight #{$pnr}", ['Header' => 3]);
                    $this->itCount++;

                    $this->logger->warning($msgCancelled);
                    $r = $master->add()->flight();
                    $r->general()
                        ->confirmation($pnr)
                        ->status('Cancelled')
                        ->cancelled();

                    if (isset($preParseCancelled[$pnr])) {
                        $this->getSegmentFromPreParse($r, $preParseCancelled[$pnr]);
                    }
                    $result = [];
                } else {
                    $result = $this->parseItinerary($tab, $master, $pnr, $preParseCancelled);
                }

                if (!is_string($result) && (count($master->getItineraries()) - $cntBefore) > 0) {
                    $this->logger->debug('Reservation parsed');
                    $its = $master->getItineraries();
                    $itLast = end($its);

                    if ($nonFlightLink && !$itLast->getCancelled()) {
                        // TODO - not implemented
                        //$this->parseVouchers($nonFlightLink);
                    }
                } elseif (isset($result) && is_string($result)) {
                    $this->logger->error($result);
                } else {
                    $this->logger->error("something went wrong");
                }
                sleep(1);
            }
        }
        $this->logger->info('[ParseItineraries. date: ' . date('Y/m/d H:i:s') . ']');

        // TODO - not implemented
//        if ($parseItinerariesOptions->isParsePastItineraries()) {
//            $this->parsePastItineraries();
//        }
    }

    public function parseHistory(Tab $tab, Master $master, AccountOptions $accountOptions, ParseHistoryOptions $historyOptions): void
    {
        $startDate = $historyOptions->getStartDate();
        $this->logger->debug('[History start date: ' . ($startDate ? $startDate->format('Y/m/d H:i:s') : 'all') . ']');
        $startDate = isset($startDate) ? $startDate->format('U') : 0;
        $statement = $master->getStatement() ?? $master->createStatement();

        $tab->gotoUrl($this->getCountryUrl('https://www.britishairways.com/travel/viewtransaction/execclub/_gf/%s?eId=172705', $accountOptions));
        sleep(1);
        $tab->evaluate('//input[@id="dateRangeRadio"]')->click();
        $tab->evaluate('//select[@id="from_day"]')->setValue(date("d", strtotime("+1 day", time())));
        $tab->evaluate('//select[@id="from_month"]')->setValue(date("m"));
        $tab->evaluate('//select[@id="from_year"]')->setValue(date("Y", strtotime("-3 year", time())));
        $tab->evaluate('//form[@id="transForm"]//input[@value = "Search"]')->click();
        sleep(2);
        $this->parsePageHistory($tab, $statement, $startDate);

        /*$options = [
            'method'  => 'post',
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'from_day'         => date("d", strtotime("+1 day", time())),
                'from_month'       => date("m"),
                'from_year'        => date("Y", strtotime("-3 year", time())),
                'search_type'      => 'D',
                'to_day'           => date("d"),
                'to_month'         => date("n"),
                'to_year'          => date("Y"),
                'transaction_type' => '0',
            ]),
        ];
        $this->logger->debug($options['body']);

        $loyaltyStatement = $tab->fetch("https://www.choicehotels.com/webapi/user-account/loyalty-statement",
            $options)->body;

        $options = [
            'method'  => 'post',
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'from_day'         => 'val',
            ]),
        ];
        $tab->fetch("https://www.british.com/loyalty-statement", $options)->setBody();*/
    }

    private function parsePageHistory(Tab $tab, Statement $statement, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $tab->evaluate("(//div[@class='info-detail-main-transaction-row'])[1]", EvaluateOptions::new()->timeout(30));
        $nodes = $tab->evaluateAll("//div[@class='info-detail-main-transaction-row']");
        $this->logger->debug("Total transactions found: " . count($nodes));

        foreach ($nodes as $node) {
            // ---------------------- Cabin Bonus, Tier Bonus, Flights ----------------------- #
            // TODO: wtf?
            $postDate = $tab->findTextNullable("./div[@class='info-detail-item post']/p", FindTextOptions::new()->visible(false)->contextNode($node));

            if ($postDate == '') {
                /*$k = $i;

                while ($this->http->FindSingleNode("div[starts-with(@id,'resultRow')][2]/p", $nodes->item($k)) == '' && $k > 0) {
                    $k--;
                }
                $postDate = strtotime(str_replace('Transaction:', '', $this->http->FindSingleNode("div[starts-with(@id,'resultRow')][2]/p[1]", $nodes->item($k))));
                $transactionDate = strtotime(str_replace('Transaction:', '', $this->http->FindSingleNode("div[starts-with(@id,'resultRow')][1]/p[1]", $nodes->item($k))));*/
            } else {
                $postDate = strtotime($postDate);
                $transactionDate = strtotime(str_replace('Transaction:', '',
                    $tab->findText("./div[starts-with(@id,'resultRow')][1]/p[1]",
                        FindTextOptions::new()->contextNode($node))));
            }
            // ----------------------------------------------------------------------------- #

            if (isset($startDate) && $postDate < $startDate) {
                continue;
            }
            $result['Transaction date'] = $transactionDate;
            $result['Posted date'] = $postDate;
            $result['Description'] = $tab->findText("div[starts-with(@id,'resultRow')][3]/p",
                FindTextOptions::new()->contextNode($node));
            $result['Tier Points'] = intval(str_replace(',', '',
                $tab->findText("div[starts-with(@id,'resultRow')][4]/p[last()]",
                    FindTextOptions::new()->contextNode($node))));
            $result['Avios'] = intval(str_replace(',', '', $tab->findText("div[starts-with(@id,'resultRow')][5]/p[2]",
                FindTextOptions::new()->contextNode($node))));
            $statement->addActivityRow($result);
        }
    }

    private function ignoreActivity($activity)
    {
        foreach ($this->activityIgnore as $ignore) {
            if (strpos($activity, $ignore) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getCustomers(Tab $tab)
    {
        $cookies = $tab->getCookies();
        $this->logger->debug("[token]: {$cookies['token']}");
        $options = [
            'method'  => 'get',
            'headers' => [
                'Accept'                    => 'application/json, application/javascript',
                'Content-Type'              => 'application/json',
                'Ba_api_context'            => 'https://www.britishairways.com/api/sc4',
                'Ba_client_applicationname' => 'ba.com',
                'Ba_client_devicetype'      => 'DESKTOP',
                'Ba_client_organisation'    => 'BA',
                //'Ba_client_sessionid' => '97f1773d-93cc-45ed-b5fb-46050d2b061f',
                //'Ba_provider_service_override' => '',
                'Authorization' => "Bearer {$cookies['token']}",
            ],
        ];

        try {
            $json = $tab->fetch('https://www.britishairways.com/api/sc4/badotcomadapter-bdca/rs/v1/customers;dataGroups=loyalties;businessContext=HomePage?locale=en_GB&locale=en_US',
                $options)->body;
            $this->logger->info($json);

            return json_decode($json);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return null;
    }

    private function getSegmentFromPreParse(\AwardWallet\Schema\Parser\Common\Flight $r, array $preParse)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($preParse['airline'])) {
            return;
        }
        $s = $r->addSegment();
        $s->airline()
            ->name($preParse['airline'])
            ->number($preParse['flight']);
        $s->departure()
            ->noCode()
            ->name($preParse['depName'])
            ->date($preParse['depDate']);
        $s->arrival()
            ->noCode()
            ->name($preParse['arrName'])
            ->date($preParse['arrDate']);
    }

    private function parseItinerary(Tab $tab, Master $master, ?string $pnr = null, ?array $preParseCancelled = []): ?string
    {
        $this->logger->notice(__METHOD__);
        $tab->evaluate("//h1[starts-with(normalize-space(),'Booking') and ./strong]");

        if ($err = $tab->findTextNullable("//ul/li[contains(text(), 'Not able to connect to AGL Group Loyalty Platform and IO Error Recieved')]")) {
            $this->logger->error("Skipping: $err");

            return $err;
        }

        if ($tab->findTextNullable("//h1[starts-with(normalize-space(),'Booking') and ./strong]")) {
            return $this->parseItinerary2021($tab, $master, $pnr, $preParseCancelled);
        }

        return null;
    }

    private function parseItinerary2021(Tab $tab, Master $master, ?string $pnr = null, ?array $preParseCancelled = []): ?string
    {
        $this->logger->notice(__METHOD__);
        $segments = $tab->evaluateAll("//div[starts-with(normalize-space(@data-modal-name),'flight')]");

        if (count($segments) == 0 && $tab->findTextNullable("//h2[starts-with(normalize-space(),'Where will your eVoucher take you?')]")) {
            $this->logger->notice('Skip: Where will your eVoucher take you?');

            return null;
        }

        $conf = $tab->findText("//h1[starts-with(normalize-space(),'Booking')]/strong");
        $r = $master->add()->flight();
        $r->general()->confirmation($conf, 'Booking');

        if ($tab->findTextNullable("//p[contains(text(),\"We're replacing your booking with the voucher, so you'll no longer be able to use your\")]/strong") === $conf) {
            $this->logger->debug($tab->findTextNullable("//p[contains(text(),\"We're replacing your booking with the voucher, so you'll no longer be able to use your\")]"));
            $r->general()
                ->status('Cancelled')
                ->cancelled();

            if (isset($preParseCancelled[$conf])) {
                $this->getSegmentFromPreParse($r, $preParseCancelled[$conf]);
            }
        }
        $pax = array_unique(array_filter(
            $tab->findTextAll("(//div[starts-with(normalize-space(@data-modal-name),'flight')])/descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[2]//h5",
            FindTextOptions::new()->visible(false))
        ));

        if (!empty($pax) && !$r->getCancelled()) {
            $r->general()->travellers($pax, true);
        }
        $this->logger->info(sprintf("[%s] Parse Flight #%s", $this->itCount++, $conf), ['Header' => 3]);

        $flightsArrayList = explode('/trackFlightsArrayList.push(trackflightArray);/',
            $tab->findText('//div[contains(@class,"js-main-content is-visible")]/script[contains(text(),"trackFlightsArrayList.push")]',
                FindTextOptions::new()->visible(false)));
        $this->logger->debug("flightsArrayList " . count($flightsArrayList) . ' found');

        $segments = $tab->evaluateAll('//div[starts-with(normalize-space(@data-modal-name),"flight")]',
            EvaluateOptions::new()->visible(false));
        $this->logger->debug("segments " . count($segments) . ' found');

        foreach ($segments as $i => $segment) {
            $s = $r->addSegment();
            $route = $tab->findText("./descendant::div[contains(@class,'flight-itinerary')][1]", FindTextOptions::new()->contextNode($segment)->visible(false));
            $points = explode(' to ', $route);

            if (count($points) !== 2) {
                $this->logger->error("check parse segment $i");
            }
            $flight = $tab->findText("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p/following-sibling::div[2]/span[1]", FindTextOptions::new()->contextNode($segment)->visible(false));
            $operator = $tab->findText("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p/following-sibling::div[3]/span[contains(.,'Operated by')]",
                FindTextOptions::new()->contextNode($segment)->preg("/Operated by (.+)/")->visible(false));

            if (strlen($operator) > 50) {
                if (stripos($operator, 'AMERICAN AIRLINES (AA) ') !== false) {
                    $operator = 'American Airlines';
                }
            }
            $s->airline()
                ->name($this->findPreg("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/", $flight))
                ->number($this->findPreg("/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", $flight))
                ->operator($operator)
                ->confirmation($tab->findText("./descendant::text()[normalize-space()!=''][1]/ancestor::h1", FindTextOptions::new()->contextNode($segment)->visible(false)));

            if (isset($flightsArrayList[$i]) && $this->findPreg("/\.flightnumber = '{$flight}';/", $flightsArrayList[$i])) {
                $this->logger->debug($flightsArrayList[$i]);
                $s->departure()
                    ->code($this->findPreg("/\.airportfrom = '([A-Z]{3})';/", $flightsArrayList[$i]));
                $s->arrival()
                    ->code($this->findPreg("/\.airportto = '([A-Z]{3})';/", $flightsArrayList[$i]));
                $s->extra()
                    ->bookingCode($this->findPreg("/\.sellingclass = '([A-Z]{1,2})';/", $flightsArrayList[$i]));
            } else {
                $s->departure()->noCode();
                $s->arrival()->noCode();
            }
            $depDate = $tab->findText("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[1]",
                FindTextOptions::new()->contextNode($segment)->preg("/Depart at (.+)/")->visible(false));
            $arrDate = $tab->findText("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[contains(.,'Arrive')][1]",
                FindTextOptions::new()->contextNode($segment)->preg("/Arrive at (.+)/")->visible(false));
            $stop = $tab->findTextNullable("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[2]",
                FindTextOptions::new()->contextNode($segment)->preg("/^(\d+) stop/")->visible(false));

            if ($stop) {
                $s->extra()->stops($stop);
            }
            $s->departure()
                ->date2(preg_replace("/^(\d+:\d+), (.+)$/", '$2, $1', $depDate))
                ->name($tab->findText("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[1]/following-sibling::div[1]/descendant::text()[1]", FindTextOptions::new()->contextNode($segment)->visible(false)))
                ->terminal($tab->findTextNullable("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[1]/following-sibling::div[1]//span",
                    FindTextOptions::new()->contextNode($segment)->preg("/Terminal\s+(.+)/")->visible(false)), false, true);
            $s->arrival()
                ->date2(preg_replace("/^(\d+:\d+), (.+)$/", '$2, $1', $arrDate))
                ->name($tab->findText("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[contains(.,'Arrive')][1]/following-sibling::div[1]/descendant::text()[1]", FindTextOptions::new()->contextNode($segment)->visible(false)))
                ->terminal($tab->findTextNullable("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[contains(.,'Arrive')][1]/following-sibling::div[1]//span", FindTextOptions::new()->contextNode($segment)->preg("/Terminal\s+(.+)/")->visible(false)), false, true);
            $s->extra()
                ->duration($tab->findTextNullable("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p/following-sibling::div[3]/span[contains(@class,'duration')][1]", FindTextOptions::new()->contextNode($segment)->visible(false)), false, true)
                ->cabin(preg_replace('/\([\w\s]+\)/', '', $tab->findTextNullable("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p/following-sibling::div[2]/span[2]", FindTextOptions::new()->contextNode($segment)->visible(false))), true, true)
                ->status($tab->findTextNullable("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p/following-sibling::div[2]/span[3][not(contains(.,'Information only'))]", FindTextOptions::new()->contextNode($segment)->visible(false)), true, true)
                ->aircraft($tab->findTextNullable("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p/following-sibling::div[2]/span[4]", FindTextOptions::new()->contextNode($segment)->visible(false)), false, true)
                ->seats(array_unique($tab->findTextAll("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[2]//h5/following-sibling::div[1]//h6[contains(.,'Seating')]/following::p[1]/span[2]", FindTextOptions::new()->contextNode($segment)->visible(false))))
                ->meals($tab->findTextAll("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[2]//h5/following-sibling::div[1]//h6[contains(.,'Meal')]/following::p[1][not(contains(.,'Please try again later'))]", FindTextOptions::new()->contextNode($segment)->visible(false)));

            if (stripos($s->getStatus(), 'cancelled') !== false || stripos($s->getStatus(), 'canceled') !== false) {
                $s->extra()->cancelled();
            }
        }
        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return null;
    }
}
