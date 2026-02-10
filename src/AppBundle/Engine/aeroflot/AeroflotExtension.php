<?php

namespace AwardWallet\Engine\aeroflot;

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

class AeroflotExtension extends AbstractParser implements LoginWithIdInterface, ParseInterface, ParseItinerariesInterface
{
    use TextTrait;

    private int $stepItinerary = 0;
    private $headers = [
        "Accept"              => "application/json",
        "Content-Type"        => "application/json",
        "X-IBM-Client-Id"     => "52965ca1-f60e-46e3-834d-604e023600f2",
        "X-IBM-Client-Secret" => "rU0gE3yP1wV0dY6nJ8kY8pD6pI5dF7xP5nH5nR4cH3sC0rK2rR",
        "Origin"              => "https://www.aeroflot.ru",
    ];

    public function getStartingUrl(AccountOptions $options): string
    {
        return "https://www.aeroflot.ru/personal?_preferredLanguage=en";
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $result = $tab->evaluate('//input[@placeholder="Member ID, email address or phone number"] | //p[@id="loyalty-card-number"]',
            EvaluateOptions::new()->timeout(30));
        return $result->getNodeName() == 'P';
    }

    public function getLoginId(Tab $tab): string
    {
        return $tab->findText('//p[@id="loyalty-card-number"]', FindTextOptions::new()->preg('/^(\d+)$/'));
    }

    public function logout(Tab $tab): void
    {
        $tab->evaluate('//div[@class="main-module__profile-summary__container"]//button[contains(.,"Sign Out")]')->click();
        $tab->evaluate('//input[@placeholder="Member ID, email address or phone number"]',
            EvaluateOptions::new()->timeout(30));
    }

    private int $attemptLogin = 0;
    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $this->attemptLogin++;
        sleep(1);
        $tab->evaluate('//input[@placeholder="Member ID, email address or phone number"]')->setValue($credentials->getLogin());
        $tab->evaluate('//input[@placeholder="Password"]')->setValue($credentials->getPassword());
        $tab->evaluate('//button[@type="submit" and contains(text(),"Sign in")]')->click();

        sleep(5);
        $result = $tab->evaluate('
                //form/h2[contains(text(),"Confirm sign-in")] 
                | //p[@id="loyalty-card-number"] 
                | //p[@class="main-module__login__form__message-text"]
                | //button[@type="submit" and contains(text(),"Sign in") and not(@disabled)]
            ', EvaluateOptions::new()->timeout(30));
        $this->logger->notice("[RESULT NODE NAME]: {$result->getNodeName()}, [RESULT TEXT]: {$result->getInnerText()}");

        $nodeName = $result->getNodeName();
        if ($nodeName === 'P' && $result->getAttribute('id') !== 'loyalty-card-number') {
            return LoginResult::invalidPassword($result->getInnerText());
        }

        if ($nodeName == 'BUTTON' && $this->attemptLogin < 4) {
            $this->logger->notice("retry submit $this->attemptLogin");
            return $this->login($tab, $credentials);
        }

        if (str_starts_with($result->getInnerText(), "Confirm sign-in")) {
            $tab->evaluate('//a[@href="/account/"]',
                EvaluateOptions::new()->timeout(90));
        }
        if ($result->getNodeName() == 'P') {
            $this->logger->info('logged in');
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {

        $st = $master->createStatement();
        $profile = $this->getProfile($tab)->data;
        // Name
        $st->addProperty("Name",
            beautifulName(($profile->contact->firstName ?? '') . " " . ($profile->contact->lastName ?? '')));
        $loyaltyInfo = $profile->loyaltyInfo ?? null;
        // Level
        $st->addProperty("Level", beautifulName($loyaltyInfo->tierLevel ?? ''));

        if (!empty($loyaltyInfo->tierLevelExpirationDate)) {
            $st->addProperty("LevelExpirationDate", date("m/d/Y", strtotime($loyaltyInfo->tierLevelExpirationDate)));
        }
        // Aeroflot Bonus Number
        $st->setNumber($loyaltyInfo->loyaltyId ?? null);
        // Balance
        $st->setBalance($loyaltyInfo->miles->balance ?? null);
        // Qualifying Miles
        $st->addProperty("QualMiles", $loyaltyInfo->miles->qualifying ?? null);
        // Segments
        $st->addProperty("FlightSegments", $loyaltyInfo->currentYearStatistics->segments ?? null);
        // Enrollment date
        if (strtotime($loyaltyInfo->regDate ?? '')) {
            $st->addProperty("EnrollmentDate", date("m/d/Y", strtotime($loyaltyInfo->regDate)));
        }
        // Expiry date
        // Expiration Date   // refs #9808
        $exp = $loyaltyInfo->miles->expirationDate ?? null;
        $this->logger->debug("Miles activity date: {$exp}");

        if ($exp && strtotime($exp)) {
            $st->setExpirationDate(strtotime($exp));
        }
    }

    private function getProfile(Tab $tab)
    {
        $this->logger->notice(__METHOD__);
        $token = $tab->getFromLocalStorage('auth.accessToken');
        $options = [
            'method' => 'post',
            'headers' => $this->headers + [
                'Authorization' => "Bearer $token"
            ],
            'body' => '{"lang":"en","data":{}}'
        ];
        try {
            $json = $tab->fetch('https://gw.aeroflot.ru/api/pr/LKAB/Profile/v3/get', $options)->body;
            $this->logger->info($json);
            return json_decode($json);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return null;
    }


    public function getItineraries(Tab $tab)
    {
        $this->logger->notice(__METHOD__);
        $token = $tab->getFromLocalStorage('auth.accessToken');
        $options = [
            'method' => 'post',
            'headers' => $this->headers + [
                "Referer" => "https://www.aeroflot.ru/",
                'Authorization' => "Bearer $token"
            ],
            'body' => '{"lang":"en"}'
        ];
        try {
            $json = $tab->fetch('https://gw.aeroflot.ru/api/pr/SB/UserLoyaltyPNRs/v1/get', $options)->body;
            $this->logger->info($json);
            return $json;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return null;
    }

    public function parseItineraries(
        Tab $tab,
        Master $master,
        AccountOptions $options,
        ParseItinerariesOptions $parseItinerariesOptions
    ): void {
        $json = $this->getItineraries($tab);
        $itineraries = json_decode($json);

        if ($this->findPreg('/\{"data":\{"pnrs":\[\]\},"error":null/', $json)) {
            $master->setNoItineraries(true);
            return;
        }

        $pnrs = $itineraries->data->pnrs ?? [];
        $this->logger->info(sprintf('Total %s itineraries were found', count($pnrs)));
        $notActiveIts = 0;
        foreach ($pnrs as $data) {
            $active = $data->is_active ?? $data->isActive;
            if ($active === false) {
                $this->logger->error("Skipping inactive flight");
                $notActiveIts++;

                continue;
            }
            $this->parseItinerary($tab, $master, $data);
        }

        // there is not active itineraries in general list (without legs / tickets)
        if ($notActiveIts === count($pnrs) && $notActiveIts > 0) {
            $master->setNoItineraries(true);
        }
    }

    private function parseItinerary(Tab $tab, Master $master, $data)
    {
        $this->logger->notice(__METHOD__);
        $active = $data->is_active ?? $data->isActive;

        if ($active === false) {
            $this->logger->error("Skipping inactive flight");

            return [];
        }

        $legs = $data->legs;
        $tickets = $data->tickets?? [];

        if ($legs === [] && $data->tickets === []) {
            $this->logger->error("Skipping Itinerary without segment");

            return [];
        }

        $f = $master->add()->flight();
        $confNo = $data->pnr_locator ?? $data->pnrLocator;
        $this->logger->info(sprintf("[%s] Parse Itinerary #%s", $this->stepItinerary++, $confNo), ['Header' => 3]);
        $f->general()
            ->confirmation($confNo, "Booking code", true)
            ->date2($data->pnr_date ?? $data->pnrDate);

        // Passengers
        $passengers = $data->passengers ?? [];

        foreach ($passengers as $pass) {
            $firstName = $pass->first_name ??  $pass->firstName ?? '';
            $lastName = $pass->last_name ??  $pass->lastName ?? '';
            $name = trim(beautifulName("$firstName $lastName"));
            if (!empty($name)) {
                $f->addTraveller($name);
            }

            $loyalty_id = $pass->loyalty_id ?? null;
            if ($loyalty_id) {
                $f->addAccountNumber($loyalty_id, false);
            }

            foreach ($pass->ticketing_documents->tickets ?? [] as $ticketingDocument) {
                $tickets[] = $ticketingDocument->number;
            }
        }
        if (empty($tickets)) {
            $tickets = $data->tickets?? [];
        }
        $f->setTicketNumbers($tickets, false);
        $legs = $data->legsm?? [];
        $seats = $data->seatsm?? [];

        foreach ($legs as $leg) {
            $segments = $leg->segments ?? [];
            foreach ($segments as $seg) {
                $s = $f->addSegment();
                $s->airline()
                    ->name($seg->airline_code ?? $seg->airlineCode)
                    ->operator($seg->operating_airline_code ?? $seg->operatingAirlineCode)
                    ->number($seg->flight_number ?? $seg->flightNumber);
                $depCode = $seg->origin->airport_code ?? $seg->origin->airportCode;;
                $depName = $seg->origin->airport_name ?? $seg->origin->airportName;;
                $departureTerminal = $seg->origin->terminal_name ?? $seg->origin->terminalName;
                $depDate = $seg->departure ?? $seg->departureDateTime;

                $s->departure()
                    ->code($depCode)
                    ->name($depName)
                    ->terminal($departureTerminal, true)
                    ->date2($depDate);

                $arrivalCode = $seg->destination->airport_code ?? $seg->destination->airportCode;
                $arrivalName = $seg->destination->airport_name ?? $seg->destination->airportName;
                $arrivalTerminal = $seg->destination->terminal_name ?? $seg->destination->terminalName;
                $arrivalDate = $seg->arrival ?? $seg->arrivalDateTime;

                $s->arrival()
                    ->code($arrivalCode)
                    ->name($arrivalName)
                    ->terminal($arrivalTerminal, true)
                    ->date2($arrivalDate);

                $cabin = $seg->cabin_name ?? $seg->fareGroupName;
                $aircraft = $seg->aircraft_type_name ?? $seg->aircraftTypeName;
                $status = $seg->status_name ?? $seg->statusName;
                $bookingCode = $seg->booking_class ?? $seg->bookingClass;
                $duration = $seg->flight_time_name ?? $seg->flightTimeName;
                $meal = $seg->meal_names ?? null;
                $meals = $seg->mealNames ?? null;
                if (!empty($meal)) {
                    $s->extra()->meal($meal);
                } elseif (!empty($meals)) {
                    $s->extra()->meals($meals);
                }

                $s->extra()
                    ->aircraft($aircraft, true, true)
                    ->cabin($cabin)
                    ->bookingCode($bookingCode)
                    ->status($status)
                    ->duration($duration)
                ;

                foreach ($seats as $seat) {
                    if (
                        $seat->segment_number != $seg->segment_number
                        || $seat->segmentNumber != $seg->segmentNumber
                    ) {
                        continue;
                    }
                    $seatsNumbers = $seat->seat_number ?? $seat->seatNumber;

                    foreach ($seatsNumbers as $seatsNumber) {
                        $s->addSeat($seatsNumber);
                    }
                }
            }
        }

        if (count($f->getTicketNumbers()) == 0 && count($f->getSegments()) == 0) {
            $urlPrint = $data->tickets_doc_print_url;

            if (!empty($urlPrint)) {
                $this->logger->warning('try parse html');
                $this->parseItineraryHtml($tab, $master, $urlPrint);

                return [];
            } else {
                $this->logger->error('Skipping "No data" flight');
            }

            return [];
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return [];
    }

    private function parseItineraryHtml(Tab $tab, Master $master, $url)
    {
        $this->logger->notice(__METHOD__);
        $tab->gotoUrl($url);
        $r = $master->add()->flight();

        $tab->evaluate('(//div[starts-with(normalize-space(),"Booking code")]/following-sibling::div)[1]');
        $pnrs = array_unique($tab->findTextAll("//div[starts-with(normalize-space(),'Booking code')]/following-sibling::div[1]"));

        if (count($pnrs) !== 1) {
            $this->logger->notice("check booking code");

            return;
        }

        $r->general()
            ->confirmation(array_shift($pnrs), 'Booking Code')
            ->travellers($tab->findTextAll("//div[normalize-space()='E-ticket itinerary receipt']/following-sibling::div[1]/descendant::text()[normalize-space()!=''][1]"));
        $r->issued()
            ->tickets($tab->findTextAll("//div[normalize-space()='E-ticket number:']/following-sibling::div[1]"),
                false);
        $r->program()
            ->accounts($tab->findTextAll("//div[normalize-space()='Aeroflot Bonus:']/following-sibling::div[1]"),
                false);

        $phones = $tab->evaluateAll("(//div[normalize-space()='Contact details:'])[1]/following-sibling::div[div[@class='text-bold']]");
        $this->logger->debug('Phones: ' . count($phones));

        foreach ($phones as $item) {
            $phone = $tab->findText("./div[@class='text-bold']", FindTextOptions::new()->contextNode($item));
            $text = $tab->findText("./following-sibling::div[1]", FindTextOptions::new()->contextNode($item));
            $r->program()->phone($phone, $text);
        }

        $sums = $tab->findTextAll("//div[normalize-space()='Amount paid and payment method']/following-sibling::div[normalize-space() and not(contains(.,'***'))][1]");
        $total = 0.0;
        $currency = null;

        foreach ($sums as $sum) {
            $currency = $this->findPreg("/^([A-Z]{3})\s*\d[\d.]+$/", $sum);
            $total += \AwardWallet\Common\Parser\Util\PriceHelper::cost($this->FindPreg("/(?:[A-Z]{3})\s*(\d[\d.]+)$/", $sum));
        }
        $r->price()
            ->total($total)
            ->currency($currency);

        $segments = $tab->evaluateAll("(//div[normalize-space()='Itinerary']//ancestor::div[1])[1]/div[contains(@class,'route__flight')]");

        foreach ($segments as $segment) {
            $s = $r->addSegment();
            $flight = $tab->findText(".//span[@class='route__flight-number-data']", FindTextOptions::new()->contextNode($segment));
            $depDate = $tab->findText(".//div[contains(@class,'time-destination__date time-destination__date--left')]", FindTextOptions::new()->contextNode($segment));
            $depTime = $tab->findText(".//div[contains(@class,'time-destination__from')]/descendant::text()[normalize-space()!=''][1]", FindTextOptions::new()->contextNode($segment));
            $this->logger->debug("DepDate: $depDate, $depTime");

            if (empty($depDate) || empty($depTime)) {
                return;
            }
            $class = $tab->findText(".//span[normalize-space()='Class:']/following-sibling::span[1]",
                FindTextOptions::new()->contextNode($segment));
            $s->airline()
                ->name($this->findPreg("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/", $flight))
                ->number($this->findPreg("/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", $flight));
            $s->departure()
                ->name($tab->findText(".//div[@class='route__flight-city-from']", FindTextOptions::new()->contextNode($segment)))
                ->date(strtotime($depTime, strtotime($depDate)))
                ->code($tab->findText(".//div[contains(@class,'time-destination__from')]/descendant::text()[normalize-space()!=''][2]",
                    FindTextOptions::new()->contextNode($segment)->preg('/^[A-Z]{3}/')));
            $s->arrival()
                ->name($tab->findText(".//div[@class='route__flight-city-to']", FindTextOptions::new()->contextNode($segment)))
                ->code($tab->findText(".//div[contains(@class,'time-destination__to')]/descendant::text()[normalize-space()!=''][1]",
                    FindTextOptions::new()->contextNode($segment)->preg('/^[A-Z]{3}/')));

            $arrDate = $tab->findText(".//div[contains(@class,'time-destination__date time-destination__date--right')]", FindTextOptions::new()->contextNode($segment));
            $arrTime = $tab->findText(".//div[contains(@class,'time-destination__to')]/descendant::text()[normalize-space()!=''][2]", FindTextOptions::new()->contextNode($segment));
            $this->logger->debug("ArrDate: $arrDate, $arrTime");

            if (!empty($arrDate) && !empty($arrTime)) {
                $s->arrival()->date(strtotime($arrTime, strtotime($arrDate)));
            } elseif (trim($tab->findText(".//div[contains(@class,'time-destination__to')]/div[contains(@class,'time-destination__time')]", FindTextOptions::new()->contextNode($segment))) == '') {
                $s->arrival()->noDate();
            }
            $s->extra()
                ->status($tab->findText(".//span[normalize-space()='Status:']/following-sibling::span[1]",
                    FindTextOptions::new()->contextNode($segment)))
                ->cabin($this->findPreg("/^(.+)\s*\/\s*[A-Z]{1,2}$/", false, $class))
                ->bookingCode($this->findPreg("/^.+\s*\/\s*([A-Z]{1,2})$/", false, $class));
        }

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

}
