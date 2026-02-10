<?php

namespace AwardWallet\Engine\airfrance;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\ElementNotFoundException;
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
use function AwardWallet\ExtensionWorker\beautifulName;

class AirfranceExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface, ParseHistoryInterface
{
    use TextTrait;
    private $sha256HashProfileFlyingBlueBenefitsQuery = 'ee0498f9ac6236f86f09013c8621ab2894e36e17dd0d0d8fb80b856514b23379';
    // reservation
    private $sha256HashReservations = '4e3f2e0b0621bc3b51fde95314745feb4fd1f9c10cf174542ab79d36c9dd0fb2';
    private $sha256HashReservation = '8ceaed40ef2387f278f78846e6b23f5861483a3277b65adacf0408f9f4a9c9a0';
    private $sha256HashTripReservationTicketPriceBreakdownQuery = '2645ba4eec72a02650ae63c2bd78d14a3f0025dddfca698f570b96a630667fe0';
    // history
    private $sha256HashProfileFlyingBlueTransactionHistoryQuery = 'a4da5deea24960ece439deda2d3eac6c755e88ecfe1dfc15711615a87943fba7';
    private int $currentItin = 0;
    public string $host = 'wwws.airfrance.us';


    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://$this->host/";
        //return "https://$this->host/profile/flying-blue/dashboard";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $tab->evaluate('//button[contains(@class,"bwc-logo-header__login-button")]
        | //button[contains(@class,"bwc-logo-header__user-profile-info")]');
        $tab->gotoUrl("https://$this->host/profile/flying-blue/dashboard?showredirectnotice=us");
        sleep(3);
        $result = $tab->evaluate('
        //a[@aria-label="Log in with your password instead?"]
        | //button[contains(@class,"bwc-logo-header__login-button")]
        | //button[contains(@class,"bwc-logo-header__user-profile-info")]
        | //p[@class="bw-fb-membership-card__number-text" and contains(text(),"Flying Blue number:")]');

        return
            str_contains($result->getAttribute('class'), 'bwc-logo-header__user-profile-info')
            || $result->getNodeName() == 'P';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//p[@class="bw-fb-membership-card__number-text" and contains(text(),"Flying Blue number:")]/strong');
    }

    public function logout(Tab $tab): void
    {
        $tab->gotoUrl("https://$this->host/endpoint/v1/oauth/logout/cid");
        $tab->evaluate('//button[@aria-label="Log in"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        /*sleep(7);
        $tab->evaluate('//button[contains(@class,"bwc-logo-header__button-countrylang")]')->click();
        sleep(3);
        $tab->evaluate('//button[contains(text(),"United States")]', EvaluateOptions::new()->visible(false))->click();*/

        //sleep(1);
        $tab->evaluate('//a[@aria-label="Log in with your password instead?"]')->click();
        $login = $tab->evaluate('//input[@name="jloginId"]');
        $login->setValue($credentials->getLogin());

        $password = $tab->evaluate('//input[@name="jpassword"]');
        $password->setValue($credentials->getPassword());
        $tab->evaluate('//button[@aria-label="Log in"]')->click();
        sleep(3);
        $btn = $tab->evaluate('//button[@aria-label="Log in"]', EvaluateOptions::new()->allowNull(true)->timeout(2));
        if ($btn)
            $btn->click();


        $loginOrError = '//div[contains(@class,"login-field-assist login-element__")]/span 
        | //*[@class="bwc-form-error--email"]/div
        | //p[@class="bw-fb-membership-card__number-text" and contains(text(),"Flying Blue number:")]';
        try {
            $result = $tab->evaluate('//div[@formcontrolname="recaptchaResponse"]//iframe[@title="reCAPTCHA"]', EvaluateOptions::new()->timeout(3));
            if ($result) {
                $this->logger->notice('show captcha');
                $result = $tab->evaluate($loginOrError, EvaluateOptions::new()->allowNull(true)->timeout(90));
                if (!$result) {
                    return LoginResult::captchaNotSolved();
                }
            }
        } catch (ElementNotFoundException $e) {
            $check2fa = '//*[contains(@class,"login-form-converse-stmt-greeting") and contains(text(),"PIN code")]';
            $result = $tab->evaluate("$loginOrError | $check2fa");
            $error = $result->getInnerText();
            if (str_contains($error, "PIN code")) {
                $tab->showMessage(Tab::MESSAGE_IDENTIFY_COMPUTER);
                $result = $tab->evaluate('//*[@class="bwc-form-error--email"]/div
                    | //p[@class="bw-fb-membership-card__number-text" and contains(text(),"Flying Blue number:")]',
                    EvaluateOptions::new()->allowNull(true)->timeout(180));

                if (!$result) {
                    return LoginResult::identifyComputer();
                }
            }
        }

        if ($result->getNodeName() == 'P') {
            $this->logger->info('logged in');

            return new LoginResult(true);
        } else {
            $error = $result->getInnerText();
            $this->logger->info('[error logging in]: '. $error);
            // Please enter a valid e-mail address.
            if (str_contains($error, "Please enter a valid e-mail address.")) {
                return LoginResult::invalidPassword($error);
            }
            // Incorrect username and/or password. Please check and try again.
            if (str_contains($error, "Incorrect username and/or password. Please check and try again.")) {
                return LoginResult::invalidPassword($error);
            }
            // Sorry, an unexpected technical error occurred. Please try again or contact the Air France customer service team.
            if (str_contains($error, "Sorry, an unexpected technical error occurred. Please try again")) {
                return LoginResult::providerError($error);
            }
            return new LoginResult(false);
        }
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $st = $master->createStatement();
        // Name
        $st->addProperty('Name', $tab->findTextNullable('//h1[@id="bw-fb__contact-details-card-title"]'));
        // Balance - Award Miles balance
        $balance = $tab->findText('//*[contains(@class,"bw-profile-recognition-box__info--amount")]');
        //$balance = $tab->findText('//*[@id="bw-fb__miles-overview-miles"]');

        if (!empty($balance)) {
            $st->setBalance(str_replace(',', '', $this->findPreg('/^([\-\d.,\s]+)/', $balance)));
        } else {
            $this->logger->warning("Balance not found");

            if (!empty($tab->findTextNullable('//button[contains(text(),"Join Flying Blue") or contains(text(),"Become a Flying Blue member"]'))) {
                $notMember = 'You are not a member of this loyalty program.';
                $this->logger->warning($notMember);
                $this->warningLogger->addWarning($notMember);
            }

            return;
        }
        // Status
        $st->addProperty('Status', beautifulName(
            $tab->findText('//img[contains(@class,"bwc-logo--flyingblue") and contains(@alt,"Flying Blue")]/@alt',
                FindTextOptions::new()->preg('/Flying Blue\s+(.+)/'))));
        // Number
        $st->addProperty('Number', $tab->findText('//*[contains(@class,"bw-fb-membership-card__number-text")]/strong',
            FindTextOptions::new()->preg('/^\w+$/')));
        // Experience Points - 164,690 Miles, 84 XP
        $experiencePoints = $tab->findTextNullable('//*[contains(@class,"bw-profile-recognition-box__info--amount")]');
        if ($experience = $this->findPreg('/Miles\s*,\s*([\d.,]+)\s*XP/', $experiencePoints)) {
            $st->addProperty('ExperiencePoints', $experience);
        }


        // Benefits
        $data = [
            "operationName" => "ProfileFlyingBlueBenefitsQuery",
            "variables"     => [
                "fbNumber" => $st->getProperties()['Number'],
            ],
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => $this->sha256HashProfileFlyingBlueBenefitsQuery,
                ],
            ],
        ];
        $accountOptions = [
            'method'  => 'post',
            'headers' => [
                'Accept'        => 'application/json, text/plain, */*',
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode($data),
        ];

        $json = $tab->fetch("https://$this->host/gql/v1?bookingFlow=LEISURE", $accountOptions)->body;
        $this->logger->info($json);
        $json = json_decode($json);

        if (isset($json->data->flyingBlueBenefits->currentBenefits)) {
            foreach ($json->data->flyingBlueBenefits->currentBenefits as $benefit) {
                if ($benefit->label == "Flying Blue Petroleum") {
                    $st->addProperty('PetroleumMembership', 'Yes');

                    break;
                }
            }
        }

        $this->logger->info('Expiration date', ['Header' => 3]);
        if (isset($st->getProperties()['Status']) && $st->getProperties()['Status'] == 'Explorer') {
            $response = $this->getHistory($tab, $st->getProperties()['Number'], 10);

            if (isset($response->data->flyingBlueTransactionHistory->milesValidities[0]->validityDate)) {
                $result = [];

                foreach ($response->data->flyingBlueTransactionHistory->milesValidities as $row) {
                    $result[$row->validityDate]['validityDate'] = $row->validityDate;

                    if (isset($result[$row->validityDate]['milesAmount'])) {
                        $result[$row->validityDate]['milesAmount'] += $row->milesAmount;
                    } else {
                        $result[$row->validityDate]['milesAmount'] = $row->milesAmount;
                    }
                }
                $result = array_values($result);

                usort($result, function ($a, $b) {
                    $a2 = strtotime($a['validityDate']);
                    $b2 = strtotime($b['validityDate']);

                    if ($a2 < $b2) {
                        return -1;
                    }

                    if ($a2 > $b2) {
                        return 1;
                    }

                    return 0;
                });
                $first = current($result);

                $this->logger->debug("Exp Date: {$first['validityDate']}");

                if ($exp = strtotime($first['validityDate'], false)) {
                    $st->setExpirationDate($exp);
                    $st->addProperty('ExpiringBalance', $first['milesAmount']);
                }
            }
        }elseif (isset($st->getProperties()['Status']) && in_array($st->getProperties()['Status'],
                ['Silver', 'Gold', 'Platinum', 'Ultimate', 'Platinum For Life', 'Ultimate Club 2000'])) {
            // TODO
//            $this->SetExpirationDateNever();
//            $this->SetProperty('AccountExpirationWarning', 'do not expire with elite status');
//            $this->ClearExpirationDate();
        }
    }

    public function parseHistory(
        Tab                 $tab,
        Master              $master,
        AccountOptions      $accountOptions,
        ParseHistoryOptions $historyOptions
    ): void {
        $startDate = $historyOptions->getStartDate();
        $this->logger->debug('[History start date: ' . ($startDate ? $startDate->format('Y/m/d H:i:s') : 'all') . ']');

        if (isset($startDate)) {
            $startDate = $startDate->format('U');
        } else {
            $startDate = 0;
        }
        $statement = $master->getStatement();
        $data = $this->getHistory($tab, $statement->getProperties()['Number']);
        $this->ParsePageHistory($statement, $startDate, $data);
    }

    public function parseItineraries(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void {
        $trips = $this->getReservations($tab);
        if (empty($trips)) {
            $master->setNoItineraries(true);

            return;
        }
        $cntSkipped = 0;

        $this->logger->info(sprintf('Found %s itineraries', count($trips)));
        foreach ($trips as $trip) {
            $scheduledReturn = !empty($trip->scheduledReturn) ? $trip->scheduledReturn : $trip->scheduledDeparture;
            $this->logger->debug("[scheduledReturn]: '{$scheduledReturn}'");
            $isPast = strtotime($scheduledReturn) < strtotime(date("Y-m-d"));

            if (!$parseItinerariesOptions->isParsePastItineraries() && $scheduledReturn != '' && $isPast) {
                $cntSkipped++;
                $this->logger->notice("Skipping booking {$trip->bookingCode}: past itinerary");

                continue;
            }

            if ($trip->historical === true || $isPast) {
                $this->parseReservation($tab, $master, $trip->bookingCode, $trip, true);
            } else {
                $reservation = $this->getReservation($tab, $trip->bookingCode, $trip->lastName);

                if ($reservation === null) {
                    $this->logger->error("Skipping reservation: {$reservation}");

                    continue;
                }

                if (is_string($reservation)) {
                    $this->logger->error("Skipping reservation 2: {$reservation}");

                    continue;
                }
                $this->parseReservation($tab, $master, $trip->bookingCode, $reservation);
            }
        }

        if (count($trips) === $cntSkipped && count($master->getItineraries()) === 0) {
            $master->setNoItineraries(true);
        }
    }


    private function parseReservation(Tab $tab, Master $master, string $conf, $reservation, ?bool $fromTrip = false): ?string
    {
        $this->logger->notice(__METHOD__);

        // $reservation->thirdPartyOrderedProducts [hotelProduct|carProduct] not showing on the pages - skip it info

        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$conf}", ['Header' => 3]);
        $this->currentItin++;
        $f = $master->createFlight();
        $f->general()->confirmation($conf);
        $totalMiles = 0;
        $accounts = [];
        $passengers = [];
        $infants = [];

        foreach ($reservation->passengers ?? [] as $passenger) {
            if ($passenger->type == 'INFANT') {
                $infants[] = beautifulName($passenger->firstName . ' ' . $passenger->lastName);
            } else {
                $passengers[] = beautifulName($passenger->firstName . ' ' . $passenger->lastName);
            }

            if (!$fromTrip) {
                foreach ($passenger->ticketNumber as $ticketNumber) {
                    if (!in_array($ticketNumber, array_column($f->getTicketNumbers(), 0))) {
                        $f->issued()->ticket($ticketNumber, false);
                    }
                }
                $memberships = $passenger->memberships ?? [];

                foreach ($memberships as $membership) {
                    $accounts[] = $membership->number;
                }
                $totalMiles += $passenger->earnQuote->totalMiles ?? 0;
            }
        }

        if (!empty($infants)) {
            $f->general()->infants($infants, true);
        }

        if (!empty($passengers)) {
            $f->general()->travellers($passengers, true);
        }

        if (!empty($totalMiles)) {
            $f->program()->earnedAwards($totalMiles);
        }
        $accounts = array_values(array_unique($accounts));

        if (!empty($accounts)) {
            $f->program()->accounts($accounts, false);
        }

        // SpentAwards
        // TotalCharge and Currency
        $totalPrice = $reservation->ticketInfo->totalPrice ?? [];

        foreach ($totalPrice as $item) {
            $currencyCode = $item->currencyCode;
//             $currency = $this->currency($currencyCode);

            if ($currencyCode == 'MLS') {
                $f->price()->spentAwards($item->amount);

                continue;
            }

            if (empty($f->getPrice()) || empty($f->getPrice()->getTotal()) || $item->amount > $f->getPrice()->getTotal()) {
                $f->price()->total($item->amount);
                $f->price()->currency($currencyCode);
            }
        }

        $connections = $reservation->itinerary->connections ?? [];

        if (count($connections) === 0) {
            if (isset($reservation->messages[0], $reservation->messages[0]->code)
                && $reservation->messages[0]->code === 'EMD_CDET_REFUNDED_SINGLE_VOUCHER'
            ) {
                $this->logger->error($msg = ($reservation->messages[0]->description ?? ''));
                $master->removeItinerary($f);

                if (!empty($msg)) {
                    return $msg;
                }

                return null;
            }
        }

        foreach ($connections as $connection) {
            foreach ($connection->segments as $segment) {
                if ((isset($segment->flight->equipment->code) && $segment->flight->equipment->code === 'TRN')
                    || stripos($segment->destination->airportName, 'Railway Station') !== false
                    || stripos($segment->origin->airportName, 'Railway Station') !== false
                ) {
                    // train segment
                    if (!isset($train)) {
                        $train = $master->createTrain();
                        $train->general()->confirmation($conf);

                        if (!empty($passengers)) {
                            $train->general()->travellers($passengers, true);
                        }
                    }
                    $s = $train->addSegment();
                    $s->extra()->service($segment->flight->carrierName);

                    if (!empty($segment->flight->flightNumber)) {
                        $s->extra()->number($segment->flight->flightNumber);
                    } else {
                        $s->extra()->noNumber();
                    }
                } else {
                    // flight segment
                    $s = $f->addSegment();
                    $s->airline()
                        ->name($segment->flight->carrierCode);

                    if (!empty($segment->flight->flightNumber)) {
                        $s->airline()->number($segment->flight->flightNumber);
                    } else {
                        $s->airline()->noNumber();
                    }
                    $s->extra()->aircraft($segment->flight->equipment->name ?? null, false, true);
                }

                if ($segment->isCancelled) {
                    $s->setCancelled(true);
                }
                $s->departure()
                    ->code($segment->origin->airportCode)
                    ->name($segment->origin->airportName)
                ;

                if (!empty($segment->flight->newDepartureDate)) {
                    $s->departure()->date2($segment->flight->newDepartureDate);
                } else {
                    $s->departure()->date2($segment->flight->departureDate);
                }

                $s->arrival()
                    ->code($segment->destination->airportCode)
                    ->name($segment->destination->airportName);

                if (!empty($segment->flight->newArrivalDate)) {
                    $s->arrival()->date2($segment->flight->newArrivalDate);
                } elseif (!empty($segment->flight->arrivalDate)) {
                    $s->arrival()->date2($segment->flight->arrivalDate);
                } else {
                    $s->arrival()->noDate();
                }
                $duration = round($segment->flight->duration / 60) . 'h' . round($segment->flight->duration % 60) . 'm';
                $s->extra()
                    ->duration($duration)
                    ->cabin($segment->flight->cabinClass, false, true);
//                $segment->flight->newArrivalDate, $segment->flight->newDepartureDate - not print on page(site)
                if (!empty($segment->ancillaries->meals) && is_array($segment->ancillaries->meals)) {
                    foreach ($segment->ancillaries->meals as $meal) {
                        if (isset($meal->name)) {
                            $s->extra()->meal($meal->name);
                        }
                    }
                }

                if (isset($segment->ancillaries->seats)) {
                    $seatNumbers = [];

                    foreach ($segment->ancillaries->seats as $seat) {
                        if (isset($seat->seatNumbers)) {
                            foreach ($seat->seatNumbers as $seatNumber) {
                                if ($this->findPreg('#^[A-Z\d\-/]{1,7}$#i', $seatNumber)) {
                                    $seatNumbers[] = $seatNumber;
                                }
                            }
                        }
                    }
                    $seatNumbers = array_unique($seatNumbers);

                    if (!empty($seatNumbers)) {
                        $s->extra()->seats($seatNumbers);
                    }
                }
            }
        }

        if (count($f->getSegments()) > 0) {
            $allCanceled = true;

            foreach ($f->getSegments() as $s) {
                if (!$s->getCancelled()) {
                    $allCanceled = false;

                    break;
                }
            }

            if ($allCanceled) {
                $f->general()->cancelled();
            }
        }

        if (isset($train)) {
            if (count($f->getSegments()) === 0) {
                $this->logger->debug("no flight-segments --> delete flight");
                $master->removeItinerary($f);

                if (!empty($totalMiles)) {
                    $train->program()->earnedAwards($totalMiles);
                }

                if (!empty($accounts)) {
                    $train->program()->accounts($accounts, false);
                }
            } else {
                $this->logger->debug('Parsed Itinerary:');
                $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
            }
            $allCanceled = true;

            foreach ($train->getSegments() as $s) {
                if (!$s->getCancelled()) {
                    $allCanceled = false;

                    break;
                }
            }

            if ($allCanceled) {
                $train->general()->cancelled();
            }
            $this->logger->debug('Parsed Itinerary (Train):');
            $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);

            return null;
        }

        // only for nearby reservations
        $payload = [
            "operationName" => "TripReservationTicketPriceBreakdownQuery",
            "variables"     => ["id" => $reservation->id],
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => $this->sha256HashTripReservationTicketPriceBreakdownQuery,
                ],
            ],
        ];
        $options = [
            'method'  => 'post',
            'headers' => [
                'Accept'        => 'application/json, text/plain, */*',
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode($payload),
        ];

        $json = $tab->fetch("https://$this->host/gql/v1?bookingFlow=LEISURE", $options)->body;
        $this->logger->info($json);
        $data = json_decode($json);

        if (!empty($data->data->reservation)) {
            $taxes = [];

            foreach ($data->data->reservation->ticketInformation->passengersTicketInformation as $information) {
                if (isset($information->taxes->totalPrice->amount)) {
                    $taxes[] = $information->taxes->totalPrice->amount;
                }
            }

            if (!empty($taxes)) {
                $f->price()->tax(array_sum($taxes));
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return null;
    }


    private function ParsePageHistory(Statement $statement, $startDate, $data)
    {
        $result = [];

        if (isset($data->data->flyingBlueTransactionHistory->transactions->transactionsList)
            && is_array($data->data->flyingBlueTransactionHistory->transactions->transactionsList)) {
            foreach ($data->data->flyingBlueTransactionHistory->transactions->transactionsList as $row) {
                $dateStr = $row->transactionDate;
                $postDate = strtotime($dateStr, false);

                if (!$postDate) {
                    $this->logger->notice("skip {$dateStr}");

                    continue;
                }

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    break;
                }// if (isset($startDate) && $postDate < $startDate)
                $result['Date'] = $postDate;

                $description = $row->description;
                // 'description' => 'My Trip to {#/transactions/transactionsList[46]/finalDestination}',
                $finalDestination = $row->finalDestination ?? null;
                // 'description' => 'Car & Taxi - {#/transactions/transactionsList[35]/complementaryDescriptionData[0]}',
                $complementaryDescriptionData = $row->complementaryDescriptionData ?? null;

                if (isset($finalDestination)) {
                    $transaction = preg_replace('/\{.+?finalDestination\}/i', $finalDestination, $description);
                } elseif (isset($complementaryDescriptionData)) {
                    for ($i = 0; $i < count($complementaryDescriptionData); $i++) {
                        $transaction = preg_replace("/\{.+?complementaryDescriptionData\[{$i}\]\}/i", trim($complementaryDescriptionData[$i]), $description);
                    }
                } else {
                    $transaction = preg_replace('/\{.+?\}/i', '', $description);
                }
                $this->logger->debug("[$transaction]");

                $details = $row->details ?? [];

                foreach ($details as $detail) {
                    $complementaryDescription = $detail->description ?? null;
                    $complementaryDetailDescriptionData = $detail->complementaryDetailDescriptionData ?? [];
                    $ancillaryLabelCategory = $detail->ancillaryLabelCategory ?? null;

                    if (
                        $complementaryDescription
                        && (
                            !empty($complementaryDetailDescriptionData)
                            || !empty($ancillaryLabelCategory)
                        )
                    ) {
                        for ($i = 0; $i < count($complementaryDetailDescriptionData); $i++) {
                            $complementaryDescription = preg_replace("/\{.+?complementaryDetailDescriptionData\[{$i}\]\}/i", trim($complementaryDetailDescriptionData[$i]), $complementaryDescription);
                        }

                        // https://redmine.awardwallet.com/issues/18358#note-8
                        if ($finalDestination && strpos($finalDestination, 'My Trip to') == 0) {
                            $result['Date'] = $postDate;
                            $result['Transaction'] = $transaction . "; " . $complementaryDescription;

                            $result['Travel Date'] = strtotime($detail->activityDate, false);

                            if ($this->findPreg('/Bonus/ims', $result['Transaction'])) {
                                $result['Bonus Miles'] = $detail->milesAmount;
                            } else {
                                $result['Award Miles'] = $detail->milesAmount;
                            }

                            $xpAmount = $detail->xpAmount ?? null;

                            if (isset($xpAmount)) {
                                $result['Experience Points'] = $xpAmount;
                            }

                            $statement->addActivityRow($result);

                            continue;
                        }// if ($finalDestination && strpos($finalDestination, 'My Trip to') == 0)

                        $transaction .= "; " . $complementaryDescription;
                    }// if ($complementaryDescription && !empty($complementaryDetailDescriptionData))
                    elseif (
                        $transaction === ''
                        && !empty($complementaryDescription)
                        && empty($complementaryDetailDescriptionData)
                    ) {
                        $transaction = $complementaryDescription;
                    }
                }

                if ($finalDestination && strpos($finalDestination, 'My Trip to') == 0) {
                    $this->logger->notice("skip {$finalDestination} / {$transaction}");

                    continue;
                }

                $result['Transaction'] = $transaction;

                if ($this->findPreg('/Bonus/ims', $result['Transaction'])) {
                    $result['Bonus Miles'] = $row->milesAmount;
                } else {
                    $result['Award Miles'] = $row->milesAmount;
                }

                $xpAmount = $row->xpAmount ?? null;

                if (isset($xpAmount)) {
                    $result['Experience Points'] = $xpAmount;
                }
                $statement->addActivityRow($result);
            }
        }
    }

    private function getHistory(Tab $tab, $number, int $size = 100)
    {
        $this->logger->notice(__METHOD__);
        $data = [
            "operationName" => "ProfileFlyingBlueTransactionHistoryQuery",
            "variables"     => [
                "size"     => $size,
                "offset"   => 1,
                "fbNumber" => $number,
            ],
            "extensions"    => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => $this->sha256HashProfileFlyingBlueTransactionHistoryQuery,
                ],
            ],
        ];
        $options = [
            'method'  => 'post',
            'headers' => [
                'Accept'        => 'application/json, text/plain, */*',
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode($data),
        ];

        $json = $tab->fetch("https://$this->host/gql/v1?bookingFlow=LEISURE", $options)->body;
        //$this->logger->info($json);

        return json_decode($json);
    }

    private function getReservations(Tab $tab, int $size = 180)
    {
        $this->logger->notice(__METHOD__);
        $payload = [
            'operationName' => 'TripReservationsQuery',
            'variables'     => [
                'daysBack' => $size,
            ],
            'extensions' => [
                'persistedQuery' => [
                    'version'    => 1,
                    'sha256Hash' => $this->sha256HashReservations,
                ],
            ],
        ];
        $options = [
            'method'  => 'post',
            'headers' => [
                'Accept'        => 'application/json, text/plain, */*',
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode($payload),
        ];

        $json = $tab->fetch("https://$this->host/gql/v1?bookingFlow=LEISURE", $options)->body;
        $this->logger->info($json);

        return json_decode($json)->data->reservations->trips ?? [];
    }

    private function getReservation(Tab $tab, string $conf, string $lastName)
    {
        $this->logger->notice(__METHOD__);

        if (!$conf || !$lastName) {
            return null;
        }

        $payload = [
            'operationName' => 'TripReservationQuery',
            'variables'     => [
                'bookingCode' => $conf,
                'lastName'    => $lastName,
            ],
            'extensions' => [
                'persistedQuery' => [
                    'version'    => 1,
                    'sha256Hash' => $this->sha256HashReservation,
                ],
            ],
        ];
        $options = [
            'method'  => 'post',
            'headers' => [
                'Accept'        => 'application/json, text/plain, */*',
                'Content-Type'  => 'application/json',
                'x-dtpc'               => '4$248262466_516h6vSVHCKULNKHQRIJPUBWKUNFSPCCTHWNHP-0e0',
                'country'              => 'US',
                'language'             => 'en',
                'afkl-travel-country'  => 'US',
                'afkl-travel-host'     => 'AF',
                'afkl-travel-language' => 'en',
            ],
            'body' => json_encode($payload),
        ];

        $json = $tab->fetch("https://$this->host/gql/v1?bookingFlow=LEISURE", $options)->body;
        $this->logger->info($json);
        $response = json_decode($json);

        if (!isset($response)) {
            return null;
        }

        $message = $response->data->reservation->messages[0]->name ?? null;

        if (
            $message === 'A travel voucher has been requested for this reservation.'
            || $message === 'A travel voucher has been issued for this reservation.'
            || $message === 'Multiple travel vouchers have been issued for this reservation'
            || $message === 'Refund Eligibility'
        ) {
            return $message;
        }

        return $response->data->reservation;
    }

}
