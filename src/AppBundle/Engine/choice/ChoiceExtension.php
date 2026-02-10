<?php

namespace AwardWallet\Engine\choice;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ParseHistoryInterface;
use AwardWallet\ExtensionWorker\ParseHistoryOptions;
use AwardWallet\ExtensionWorker\ParseItinerariesInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\Schema\Parser\Common\Statement;
use function AwardWallet\ExtensionWorker\beautifulName;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class ChoiceExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface, ParseHistoryInterface
{
    use TextTrait;

    private array $statements = [];
    private string $lastName = '';
    private string $loyaltyProgramId = '';
    private bool $endHistory = false;
    private string $guestId = '';
    private int $stepItinerary = 0;
    private bool $isMobile = false;

    public function getStartingUrl(AccountOptions $options): string
    {
        $this->isMobile = $options->isMobile;
        return 'https://www.choicehotels.com/choice-privileges/account';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $this->logger->info($tab->getUrl());
        $loginFieldOrLogOut = $tab->evaluate('//input[@id="cpSignInUsername"]
        | //button[@id="SignInFlyoutBTN"]
        | //a[@data-track-id="HM:UserNameClicked"]/div[contains(@class,"cp-user-name")] ');

        // Desktop
        return $loginFieldOrLogOut->getAttribute('id') === 'SignInFlyoutBTN'
            // Mobile
            || $loginFieldOrLogOut->getAttribute('class') === 'cp-user-name';
    }

    public function getLoginId(Tab $tab): string
    {
        // Member Number: MXK877113
        $accountNumber = $tab->findText('
        (//div[@class="member-loyalty-account"]/span)[1] 
        | //p/span[contains(text(),"Member Number")]/..
        | //p[normalize-space()="Member"]/following-sibling::p',
            FindTextOptions::new()->nonEmptyString()->preg('/\s*(\w{5,})$/'));

        return $accountNumber ?? '';
    }

    public function logout(Tab $tab): void
    {
        $this->logger->notice(__METHOD__);
        /*sleep(1);
        $tab->evaluate('//button[@id="SignInFlyoutBTN"] | //button[@data-track-id="hamburgerMenuButton"]')->click();
        usleep(500);
        $tab->evaluate('//button[@data-track-id="SignOutButton"] | //button[@data-track-id="SignOutBTN"]')->click();
        $tab->evaluate('//input[@id="cpSignInUsername"] | //button[@data-track-id="hamburgerMenuButton"]');
        $tab->gotoUrl('https://www.choicehotels.com/choice-privileges/account');*/

        $options = [
            'method'  => 'post',
            'headers' => [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
        ];

        $json = $tab->fetch("https://www.choicehotels.com/webapi/user-account/logout", $options)->body;
        $this->logger->info($json);
        //$tab->gotoUrl('https://www.choicehotels.com/choice-privileges/account');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        sleep(1);
        /*if ($this->isMobile) {
            $tab->evaluate('//button[@data-track-id="hamburgerMenuButton"]')->click();
            $tab->evaluate('//button[@aria-label="Sign In"]')->click();
        }*/
        $login = $tab->evaluate('//input[@id="cpSignInUsername"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@id="cpSignInPassword"]');
        $password->setValue($credentials->getPassword());
        sleep(1);
        $tab->evaluate('//button[@data-track-id="SignInBTN"]')->click();

        $result = $tab->evaluate('//div[contains(@class,"error-message-body")]/p 
        | //button[@id="SignInFlyoutBTN"]/div[contains(text(),"Points")] 
        | //a[@data-track-id="HM:UserNameClicked"]/div[contains(@class,"cp-user-name")] 
        | //p[contains(text(),"To keep your account secure, we need to verify")]');

        // Desktop
        if (str_contains($result->getInnerText(), 'Points')
            // Mobile
            || str_contains($result->getAttribute('class'), 'cp-user-name')) {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } elseif (str_contains($result->getInnerText(), "To keep your account secure, we need to verify")) {
            // TODO
            $tab->showMessage('It seems that Choice Hotels (Choice Privileges) needs to identify this computer before you can update this account.');
            $result = $tab->findTextNullable('//button[@id="SignInFlyoutBTN"]/div[contains(text(),"Points")]',
                FindTextOptions::new()->timeout(180));

            if (str_contains($result, 'Points')) {
                return new LoginResult(true);
            } else {
                // step page 1
                $questionMessage = $tab->findTextNullable('//p[contains(text(),"To keep your account secure, we need to verify")]');
                $questionPhone = $tab->findTextNullable('//span[contains(@class,"custom-radio-input")]/input[@checked]/../following-sibling::label/span[2]');

                if ($questionMessage && $questionPhone) {
                    return LoginResult::providerError('It seems that Choice Hotels (Choice Privileges) needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Choice Hotels (Choice Privileges) authentication options) to get this computer authorized.');
                    //return new LoginResult(false, "{$questionMessage} {$questionPhone}", 'question', ACCOUNT_QUESTION);
                } else {
                    // step page 2
                    /*$questionMessage = $tab->findTextNullable('//strong[contains(text(),"Keep this window open")]/following-sibling::text()');
                    // We’ve sent a one-time verification code to +1 XXX-XXX-0144. The code expires in 10 minutes
                    if (str_contains($questionMessage, 'XXX-XXX')) {
                        return new LoginResult(false, $questionMessage, 'PHONE', ACCOUNT_QUESTION);
                    }
                    // We’ve sent a one-time verification code to d...n@gmail.com. The code expires in 10 minutes.
                    if (str_contains($questionMessage, '@')) {
                        return new LoginResult(false, $questionMessage, 'EMAIL', ACCOUNT_QUESTION);
                    }*/
                }
            }
        } else {
            $error = $result->getInnerText();
            $this->logger->error("error $error");

            if (str_contains($error,
                    "We are unable to process your request. Please try again. If you continue to have difficulties, please contact a")
                || str_contains($error, "The username or password is incorrect.")) {
                return new LoginResult(false, $error, null, ACCOUNT_INVALID_PASSWORD);
            }
        }

        return new LoginResult(false);
    }


    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        $accountOptions = [
            'headers' => [
                'Accept' => 'application/json, application/javascript',
            ],
        ];
        $account = $tab->fetch('https://www.choicehotels.com/webapi/user-account?include=year_to_date_nights%2Cloyalty_account_forfeiture_date%2Cppc_status%2Cdgc_status&preferredLocaleCode=en-us&siteName=us',
            $accountOptions)->body;
        $this->logger->info($account);
        $account = json_decode($account);

        $this->loyaltyProgramId = $account->guestProfile->loyaltyProgramId;
        $this->lastName = $account->guestProfile->lastName;
        $this->guestId = $account->guestProfile->guestId;

        // Name
        $middleName = $account->guestProfile->middleName ?? '';
        $name = beautifulName(preg_replace('/\s{2,}/', ' ',
            "{$account->guestProfile->firstName} {$middleName} {$account->guestProfile->lastName}"));
        $st->addProperty('Name', $name);
        // Number
        $st->addProperty('Number', $account->guestProfile->loyaltyAccountNumber);

        $loyaltyAccounts = [];
        $loyaltyAccount = null;
        $accountBalanceUnits = 0;
        $loyaltyAccounts = $account->loyaltyAccounts ?? [];

        foreach ($loyaltyAccounts as $loyaltyAcc) {
            $accountBalanceUnit = $loyaltyAcc->accountBalanceUnits;

            if (
                $accountBalanceUnit === 'POINTS'
                && !in_array($loyaltyAcc->loyaltyProgramId, ['AT', 'VB'])
            ) {
                $loyaltyAccount = $loyaltyAcc;

                break;
            } elseif ($accountBalanceUnit === 'MILES') {
                $accountBalanceUnits++;
            }
        }

        if ($loyaltyAccount === null) {
            // AccountID: 413254
            // We're sorry, an unexpected error has occurred.
            if (($account->outputInfo->UNAVAILABLE_LOYALTY_ACCOUNT ?? null) === 'No loyalty account associated to this guest profile.') {
                $this->logger->notice("We're sorry, an unexpected error has occurred.");
                //provider.setError(["We're sorry, an unexpected error has occurred.", util.errorCodes.providerError], true);
                return;
            } // AccountID: 1334959 / 4441805 / 612540
            elseif ($accountBalanceUnits >= 1) {
                $this->logger->notice('set Balance NA, accountBalanceUnits >= 1');
                $st->setNoBalance(true);
            } // AccountID: 3714455
            elseif (
                count($loyaltyAccounts) === 1
                && ($loyaltyAccounts[0]->loyaltyProgramId ?? null) == 'AT'
            ) {
                $this->logger->notice('set Balance NA, loyaltyProgramId === "AT"');
                $st->setNoBalance(true);
            } // AccountID: 4543263, 3646209
            elseif (
                isset($st->getProperties()['Name'])
                && isset($st->getProperties()['Number'])
                && count($loyaltyAccounts) === 0
            ) {
                $this->logger->notice('set Balance NA, loyaltyAccounts.length === 0');
                $st->setNoBalance(true);
            }

            return;
        }

        // Balance - Choice Privileges Points
        $st->setBalance($loyaltyAccount->accountBalance ?? null);

        // Member Since - Feb 26, 2007
        $st->addProperty('MemberSince', date('M j, Y', strtotime($loyaltyAccount->memberSince)));

        // Exp Date
        $exp = $account->loyaltyAccountForfeitureDate ?? null;
        $this->logger->debug("Exp date from Profile: $exp");

        if ($exp) {
            $dateStr = str_replace('-', '/', $exp);
            $this->logger->debug("Exp date from Profile: $dateStr ($exp)");

            if (!empty($dateStr) && $unixtime = strtotime($dateStr)) {
                $st->setExpirationDate($unixtime);
            }
        }

        $yearToDateEliteNights = $account->yearToDateEliteNights ?? null;
        $this->logger->debug("YTD Elite Nights: $yearToDateEliteNights");
        $status = '';
        $nightsNeeded = 0;

        if ($yearToDateEliteNights === 0) {
            $this->logger->debug("Set Elite Status by default");
            $status = "None";
            $nightsNeeded = 10;
        } else {
            $this->logger->debug("Set Elite Status by progress");

            if ($yearToDateEliteNights < 10) {
                $status = "None";
                $nightsNeeded = 10;
            } elseif (($yearToDateEliteNights >= 10) && ($yearToDateEliteNights < 20)) {
                $status = "Gold";
                $nightsNeeded = 20;
            } elseif (($yearToDateEliteNights >= 20) && ($yearToDateEliteNights < 40)) {
                $status = "Platinum";
                $nightsNeeded = 40;
            } elseif ($yearToDateEliteNights >= 40) {
                $status = "Diamond";
                $nightsNeeded = 0;
            } else {
                $this->logger->debug("something went wrong");
            }
        }

        $this->logger->debug(">>> nightsNeeded = $nightsNeeded / status = $status");

        // Elite Status
        if (isset($loyaltyAccount->eliteLevel)) {
            $st->addProperty('ChoicePrivileges', $loyaltyAccount->eliteLevel);
        } else {
            $st->addProperty('ChoicePrivileges', $status);
        }
        // Nights to next status
        if ($nightsNeeded > 0) {
            $eligible = $nightsNeeded - $yearToDateEliteNights;
            $st->addProperty('Eligible', $eligible);
            $this->logger->debug("Nights to next status (Eligible):$eligible");
        }

        $this->logger->info('Expiration date', ['Header' => 3]);
        $summaries = null;

        try {
            $summaries = $tab->fetch("https://www.choicehotels.com/webapi/user-account/loyalty-statement-summaries?loyaltyAccountNumber={$account->guestProfile->loyaltyAccountNumber}&loyaltyProgramId={$account->guestProfile->loyaltyProgramId}&preferredLocaleCode=en-us&siteName=us",
                $accountOptions)->body;
            $this->logger->info($summaries);
            $summaries = json_decode($summaries);
            $this->statements = $summaries->statements;

            foreach ($summaries->statements[0]->expirations ?? [] as $expiration => $pointsExpiring) {
                $this->logger->debug("[{$expiration} / " . strtotime($expiration) . "]: expire {$pointsExpiring}");
                // Points expiring
                $st->addProperty("PointsExpiring", $pointsExpiring);

                if (!isset($expirationDate) || strtotime($expiration) < $expirationDate) {
                    $this->logger->notice("Set new Expiration Date: {$expiration}");
                    $expirationDate = strtotime($expiration);
                }
            }

            if (isset($expirationDate)) {
                $st->setExpirationDate($expirationDate);
            }

            // Beginning Balance
            $st->addProperty('BeginningBalance', $summaries->statements[0]->beginningBalance ?? null);
            // Points Earned
            $st->addProperty('PointsEarned', $summaries->statements[0]->earned ?? null);
            // Points Redeemed
            $st->addProperty('PointsRedeemed', $summaries->statements[0]->redeemed ?? null);
            // Points Adjusted
            $st->addProperty('PointsAdjusted', $summaries->statements[0]->adjusted ?? null);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e);
        }
    }

    public function parseHistory(
        Tab $tab,
        Master $master,
        AccountOptions $accountOptions,
        ParseHistoryOptions $historyOptions
    ): void {
        $startDate = $historyOptions->getStartDate();
        $this->logger->debug('[History start date: ' . ($startDate ? $startDate->format('Y/m/d H:i:s') : 'all') . ']');

        if (isset($startDate)) {
            $startDate = $startDate->format('U');
        } else {
            $startDate = 0;
        }
        $st = $master->getStatement();

        $page = 0;

        foreach ($this->statements as $statement) {
            $this->logger->debug("[Page: {$page}]");
            $this->logger->debug("Loading old statements...");
            $accountOptions = [
                'method' => 'post',
                'headers' => [
                    'Accept' => 'application/json, application/javascript',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query([
                    'loyaltyAccountLastName' => $this->lastName,
                    'loyaltyAccountNumber' => $st->getProperties()['Number'],
                    'loyaltyProgramId' => $this->loyaltyProgramId,
                    'preferredLocaleCode' => 'en-us',
                    'statementPeriodStartDate' => $statement->startDate,
                    'siteName' => 'us',
                ]),
            ];
            try {
                $loyaltyStatement = $tab->fetch("https://www.choicehotels.com/webapi/user-account/loyalty-statement",
                    $accountOptions)->body;
            } catch (\Exception $e) {
                $this->logger->warning($e->getMessage());
                break;
            }

            $this->logger->info($loyaltyStatement);
            $loyaltyStatement = json_decode($loyaltyStatement);

            $this->parseHistoryPage($st, $startDate, $loyaltyStatement);
            $page++;

            if ($this->endHistory) {
                break;
            }
        }
        /*usort($result, function ($a, $b) {
            $key = 'Activity Dates';
            return $a[$key] == $b[$key] ? 0 : ($a[$key] < $b[$key] ? 1 : -1);
        });*/
    }

    private function parseHistoryPage(Statement $statement, $startDate, $loyaltyStatement)
    {
        $result = [];
        $earned = $loyaltyStatement->earned ?? [];
        $this->logger->debug("Total " . count($earned) . " earned transactions were found");
        $redeemed = $loyaltyStatement->redeemed ?? [];
        $this->logger->debug("Total " . count($redeemed) . " redeemed transactions were found");
        $adjusted = $loyaltyStatement->adjusted ?? [];
        $this->logger->debug("Total " . count($adjusted) . " adjusted transactions were found");

        $hotels = $loyaltyStatement->hotels ?? [];
        // POINTS EARNED
        foreach ($earned as $e) {
            $dateStr = $e->startDate ?? null;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }
            $hotelId = $e->hotelId ?? null;

            if ($hotelId) {
                $this->logger->debug("hotelId: {$hotelId}");
                $activity = $hotelId;
                $hotelIdInfo = $hotels->$hotelId ?? null;

                if ($hotelIdInfo) {
                    $name = $hotelIdInfo->name ?? null;

                    if ($name) {
                        $activity .= '; ' . $name;
                    }
                    $address = $hotelIdInfo->address ?? null;
                    $city = $address->city ?? null;

                    if ($city) {
                        $activity .= '; ' . $city;
                    }
                    $subdivision = $address->subdivision ?? null;

                    if ($subdivision) {
                        $activity .= ', ' . $subdivision;
                    }
                }
            } else {
                $activity = $e->description;
            }
            $statement->addActivityRow([
                'Activity Dates' => $postDate,
                'Description' => $activity,
                'Points' => $e->points
            ]);
        }
        // POINTS REDEEMED
        foreach ($redeemed as $r) {
            $dateStr = $r->startDate ?? null;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }
            $hotelId = $r->hotelId ?? null;

            if ($hotelId) {
                $this->logger->debug("hotelId: {$hotelId}");
                $activity = $hotelId;
                $hotelIdInfo = $hotels->$hotelId ?? null;

                if ($hotelIdInfo) {
                    $name = $hotelIdInfo->name ?? null;

                    if ($name) {
                        $activity .= '; ' . $name;
                    }
                    $address = $hotelIdInfo->address ?? null;
                    $city = $address->city ?? null;

                    if ($city) {
                        $activity .= '; ' . $city;
                    }
                    $subdivision = $address->subdivision ?? null;

                    if ($subdivision) {
                        $activity .= ', ' . $subdivision;
                    }
                }

                $cancellation = $r->cancellation ?? null;

                if ($cancellation) {
                    $activity .= ' (cancelled)';
                }
            } else {
                $activity = $r->description;
            }
            $statement->addActivityRow([
                'Activity Dates' => $postDate,
                'Description' => $activity,
                'Points' => $r->points
            ]);
        }
        // POINTS ADJUSTED
        foreach ($adjusted as $adj) {
            $dateStr = $adj->startDate ?? null;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }
            $statement->addActivityRow([
                'Activity Dates' => $postDate,
                'Description' => $adj->description ?? null,
                'Points' => $adj->points
            ]);
        }

        return $result;
    }

    public function parseItineraries(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void {
        $st = $master->getStatement();
        $options = [
            'method' => 'get',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ];
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-2 year"));
        $params = http_build_query([
            'deviceType' => 'DESKTOP',
            'endDate' => $endDate,
            'startDate' => $startDate,
            'guestId' => $this->guestId,
            'include' => 'current_reservations',
            'loyaltyAccountNumber' => $st->getProperties()['Number'],
            'loyaltyProgramId' => 'GP',
            'preferredLocaleCode' => 'en-us',
            'reservationLookupStatusList' => 'RESERVED,CANCELLED',
            'siteName' => 'us',
        ]);
        try {
            $reservations = $tab->fetch("https://www.choicehotels.com/webapi/reservation/summaries?$params",
                $options)->body;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return;
        }
        $this->logger->info($reservations);
        $reservations = json_decode($reservations)->currentReservations ?? [];
        foreach ($reservations as $item) {
            $this->parseReservation($tab, $master, $item);
            break;
        }
    }

    private function parseReservation(Tab $tab, Master $master, object $item)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->stepItinerary++, $item->confirmationId),
            ['Header' => 3]);
        $h = $master->createHotel();
        $h->general()->confirmation($item->confirmationId, 'Confirmation Number');
        // Status
        $h->general()->status($item->reservationStatus);

        // Cancelled reservation
        if (stristr($item->reservationStatus, "Cancelled")) {
            $h->general()->cancelled();
            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
            return;
        }

        // HotelName
        $h->hotel()->name($item->hotelName);
        // Rooms
        $h->booked()->rooms($item->numberOfRooms);
        // CheckInDate
        $h->booked()->checkIn2($item->checkInDate);
        // CheckOutDate
        $h->booked()->checkOut2($item->checkOutDate);
        // Address
        $h->hotel()->address($item->hotelLocation);
        $address = $item->address;
        $line = '';
        if ($address->line1) {
            $line = $address->line1;
        }
        if ($address->line1) {
            $line .= ", $address->line1";
        }
        $h->hotel()->detailed()->address($line)
            ->city($address->city ?? null)
            ->zip($address->postalCode ?? null)
            ->country($address->country ?? null);
        if (isset($address->subdivision)) {
            $h->hotel()->detailed()->state($address->subdivision);
        }
        $h->hotel()->phone($item->phone);

        // TODO - more parts can be collected
        $options = [
            'method' => 'post',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'deviceType' => 'DESKTOP',
                'preferredLocaleCode' => 'en-us',
                'siteName' => 'us',
                'confirmOrCancelId' => $item->confirmationId,
                'guestDataSource' => 'RESERVATION',
                'lastName' => $this->lastName,
                'searchType' => 'CONFIRMATION',
            ])
        ];
        try {
            $detail = $tab->fetch("https://www.choicehotels.com/webapi/reservation",
                $options)->body;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return;
        }
        $this->logger->info($detail);
        $detail = json_decode($detail);
        if (empty($detail)) {
            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
        }

        $h->hotel()->fax($detail->hotel->fax, false, true);

        if (isset($detail->reservation->rooms)) {
            $r = $h->addRoom();
            foreach ($detail->reservation->rooms as $room) {
                $nights = $room->nights ?? null;
                $price = $room->avgNightlyPoints ?? null;
                (isset($room->description) ? $roomDescAr[] = $room->description : null);
                (isset($room->thumbCaption) ? $roomTypeAr[] = $room->thumbCaption : null);
                (isset($room->adults) ? $roomAdultAr[] = $room->adults : null);
//            isset($room->kids) ? $roomKidsAr[$i] = $room->kids : null;
                if (isset($nights, $price)) {
                    $arRate[] = ($nights * $price) . " pts";
                }
            }

            if (isset($arRate[0])) {
                $r->setRate(implode(' | ', $arRate));
            }

            if (isset($roomTypeAr[0])) {
                $r->setType(implode(' | ', $roomTypeAr));
            }

            if (isset($roomDescAr[0])) {
                $r->setDescription(implode(' | ', $roomDescAr));
            }

            if (isset($roomAdultAr[0])) {
                $h->booked()->guests(array_sum($roomAdultAr));
            }
        }

        // Cost
        if (isset($detail->reservation->totalBeforeTax)) {
            $h->price()->cost($detail->reservation->totalBeforeTax);
        }
        // Total
        if (isset($detail->reservation->totalAfterTax)) {
            $h->price()->total($detail->reservation->totalAfterTax);
        }
        // Taxes
        if (!empty($h->getPrice()) && !empty($h->getPrice()->getCost()) && !empty($h->getPrice()->getTotal())) {
            $h->price()->tax($h->getPrice()->getTotal() - $h->getPrice()->getCost());
        }

        if ($detail->reservation->currencyCode == 'XLY') {
            $h->price()->spentAwards($detail->reservation->totalPoints . " pts");
        } else {
            $h->price()->currency($detail->reservation->currencyCode ?? null);
        }


        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }
}
