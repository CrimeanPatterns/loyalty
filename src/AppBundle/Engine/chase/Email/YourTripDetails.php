<?php

namespace AwardWallet\Engine\chase\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class YourTripDetails extends \TAccountChecker
{
    use ProxyList;
    public $mailFiles = "chase/it-11099415.eml, chase/it-11191597.eml, chase/it-2005864.eml, chase/it-2093524.eml, chase/it-2405726.eml, chase/it-2509417.eml, chase/it-2534674.eml, chase/it-260909004.eml, chase/it-2733618.eml, chase/it-3889341.eml, chase/it-425767548.eml, chase/it-427438993.eml, chase/it-525169680.eml, chase/it-525717508.eml, chase/it-529580711.eml, chase/it-5384553.eml, chase/it-6046174.eml, chase/it-6412090.eml, chase/it-6443377.eml, chase/it-6472291.eml, chase/it-6721821.eml, chase/it-8334262.eml, chase/it-8540669.eml"; // +2 bcdtravel(html)[en]

    private $reSubject = [
        'en' => ['trip details for', 'Check in now for', 'Travel Reservation Center Trip Id'],
    ];
    private $date = 0;
    private $text;
    private $year = null;
    private $lang = 'en';

    private $langDetectors = [
        'en' => ['Flight Confirmation #', 'Hotel Confirmation #', 'Car Confirmation #', 'Check with your airline for the most up-to-date flight', 'Activity Reservation'],
    ];

    private static $dictionary = [
        'en' => [
            'tripId'    => ['Trip ID:', 'Your Trip ID is:'],
            // HOTEL
            'Room Type'                => ['Room Type', 'ROOM TYPE'],
            'Check-In'                 => ['Check-In', 'CHECK-IN'],
            'Check-Out'                => ['Check-Out', 'CHECK-OUT'],
            'HOTEL RULES AND POLICIES' => ['HOTEL RULES AND POLICIES', 'Rules and Policies'],
            //Car
            "Car Driver:" => ["Car Driver:", "Lead Traveler:"],
            "Car Type"    => ["CAR TYPE", "Car Type"],
            "Pick-Up"     => ["PICK-UP", "Pick-Up"],
            "Drop-Off"    => ["DROP-OFF", "Drop-Off"],
        ],
    ];

//    private static $providerDetectors = [
//        'chase' => [
//            'chase'
//        ],
//    ];

    private static $providerItineraryDetectors = [
        'thrifty' => [
            'Thrifty',
        ],
        'dollar' => [
            'Dollar',
        ],
        'perfectdrive' => [
            'Budget',
        ],
    ];

    private $xpath = [
        'time' => '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))',
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function parseHtmlFlight(Email $email, ?array $tripId): void
    {
        // examples: it-11099415.eml, it-11191597.eml, it-2005864.eml, it-2405726.eml, it-2509417.eml, it-2534674.eml, it-2733618.eml, it-3889341.eml, it-5384553.eml, it-6046174.eml, it-6412090.eml, it-6443377.eml, it-6472291.eml, it-8334262.eml, it-8540669.eml

        $f = $email->add()->flight();

        // RecordLocator
        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Flights'))}] ]/*[normalize-space()][2][{$this->contains($this->t('Flight Confirmation #'))}]");

        if (preg_match("/^({$this->opt($this->t('Flight Confirmation #'))})[:\s]*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        // TripNumber
        if (!empty($tripId)) {
            $f->ota()->confirmation($tripId[0], $tripId[1]);
        }

        $agencyRefNumbers = $agencyRefTitles = [];

        foreach ($this->http->XPath->query("//text()[{$this->eq($this->t('Agency Reference #'))}]") as $agRefRoot) {
            $agencyRefNumber = $this->http->FindSingleNode('following::text()[normalize-space()][1]', $agRefRoot, true, '/^[-A-Z\d]{5,}$/');

            if ($agencyRefNumber) {
                $agencyRefNumbers[] = $agencyRefNumber;
                $agencyRefTitles[] = $this->http->FindSingleNode('.', $agRefRoot, true, '/^(.+?)[\s:：]*$/u');
            }
        }

        $agencyRefTitle = count(array_unique($agencyRefTitles)) === 1 ? $agencyRefTitles[0] : null;

        foreach ($agencyRefNumbers as $agRefNumber) {
            $f->ota()->confirmation($agRefNumber, $agencyRefTitle);
        }

        // Passengers
        $passengers = array_filter($this->http->FindNodes('//text()[' . $this->eq($this->t("Passenger")) . ']/ancestor::tr[1]/following-sibling::tr[*[3]]/*[1][normalize-space()]', null, "/^{$this->patterns['travellerName']}$/u"));

        if (count($passengers) === 0) {
            $passengers = array_filter($this->http->FindNodes('//text()[' . $this->eq($this->t("Passenger")) . ']/ancestor::tr[1]/following-sibling::tr/*[1][normalize-space()]', null, "/^{$this->patterns['travellerName']}$/u"));
        }

        if (count($passengers) > 0) {
            $f->general()->travellers(array_unique($passengers), true);
        }

        $airlineConfNumbers = [];

        $xpath = "//tr[ count(*)=3 and *[1]/descendant::text()[{$this->xpath['time']}] and *[3]/descendant::text()[{$this->xpath['time']}] ]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug("segments root not found: {$xpath}");
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            if (count($f->getConfirmationNumbers()) === 0) {
                $segConfirmation = $this->http->FindSingleNode("preceding::tr[ *[normalize-space() and not(.//tr)][1][{$this->contains($this->t('Flight Confirmation #'))}] ][1]/descendant::text()[{$this->eq($this->t('Flight Confirmation #'))}]/following::text()[normalize-space()][1]", $root, true, '/^[A-Z\d]{5,}$/');

                if ($segConfirmation) {
                    $s->airline()->confirmation($segConfirmation);
                    $airlineConfNumbers[] = $segConfirmation;
                }
            }

            $airlineFull = $this->http->FindSingleNode("./preceding-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)][1]", $root);

            $flight = $this->http->FindNodes("./preceding-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)][position()=1 or position()=2]", $root);

            if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)/', $flight[1], $matches)) {
                $s->airline()->name($matches[1]);
                $s->airline()->number($matches[2]);
            }
            // it-2509417.eml
            elseif (preg_match('/^([\w\s]{5,20})\s+(\d+)/', join(' ', $flight), $matches)) {
                $s->airline()->name($matches[1]);
                $s->airline()->number($matches[2]);
            } elseif (preg_match('/\s([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', implode(' ', $flight), $matches)) {
                $s->airline()->name($matches[1]);
                $s->airline()->number($matches[2]);
            }

            // Seats
            $fn = $s->getFlightNumber();

            if ($airlineFull && isset($fn)) {
                $seats = $this->http->FindNodes('//text()[' . $this->eq("Passenger") . ']/ancestor::tr[1]/following-sibling::tr/td[2][contains(normalize-space(.),"' . $airlineFull . ' ' . $fn . '")]/following-sibling::td[1]', null, '/^(\d{1,2}[A-Z])$/');
                $seatValues = array_values(array_filter($seats));

                if (!empty($seatValues[0])) {
                    $s->extra()->seats($seatValues);
                }
            }

            /*
                New York
                13:00
                JFK
                Fri, Jun 09
            */
            $pattern = "/^\s*(?<city>.{2,}?)[ ]*\n+[ ]*(?<time>{$this->patterns['time']}).*\n+[ ]*(?<code>[A-Z]{3})[ ]*\n+[ ]*(?<date>.*\d.*?)\s*$/";

            // DepName
            // DepCode
            // DepDate
            $departure = $this->htmlToText($this->http->FindHTMLByXpath('*[1]', null, $root));

            if (preg_match($pattern, $departure, $m)) {
                $s->departure()->name($m['city'])->code($m['code'])->date($this->calculateDate($m['date'], $m['time']));
            }

            // ArrName
            // ArrCode
            // ArrDate
            $arrival = $this->htmlToText($this->http->FindHTMLByXpath('*[3]', null, $root));

            if (preg_match($pattern, $arrival, $m)) {
                $s->arrival()->name($m['city'])->code($m['code'])->date($this->calculateDate($m['date'], $m['time']));
            }

            // Aircraft
            // Cabin
            $aircraft = $this->http->FindSingleNode('./preceding-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)][position()>2][1]', $root);
            // it-5384553.eml
            if (preg_match('/(.{2,})\|\s*([\w\s\(\)]{3,20})?/', $aircraft, $matches)) {
                $s->extra()->aircraft($matches[1]);

                if (!empty($matches[2])) {
                    $s->extra()->cabin($matches[2]);
                } else {
                    $class = $this->http->FindSingleNode('./preceding-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)][position()>2][2][not(contains(normalize-space(.),"Operated by"))]', $root);

                    if ($class) {
                        $s->extra()->cabin($class);
                    }
                }
            } else {
                $extras = implode("\n",
                    $this->http->FindNodes('./preceding-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)][1]/ancestor::td[1]//text()[normalize-space()]', $root));

                if (preg_match("/^.+\n[A-Z\d]{2} \d{1,5}(\n.+)?\n(?<cabin>.+?) \((?<class>[A-Z]{1,2})\)\n(?<aircraft>.+)\n/", $extras, $m)) {
                    $s->extra()
                        ->cabin($m['cabin'])
                        ->bookingCode($m['class'])
                        ->aircraft('aircraft')
                    ;
                }
            }

            if (!empty($s->getCabin()) && !empty($aircraft) && preg_match("/^(.{2,})\s*\(\s*([A-Z]{1,2})\s*\)$/", $s->getCabin(), $m)) {
                // Economy (E)
                $s->extra()->cabin($m[1])->bookingCode($m[2]);
            }

            // Operator
            $operator = $this->http->FindSingleNode('preceding-sibling::tr[1]/td[2]/descendant::text()[normalize-space()][position()>2][contains(normalize-space(),"Operated by")]', $root, true, '/Operated by\s*(.{2,}?)(?:\s+DBA\s+.{2}|$)/i');
            $s->airline()->operator($operator, false, true);

            // Duration
            $s->extra()->duration($this->http->FindSingleNode('./td[2]/descendant::text()[normalize-space(.)][1]', $root, true, '/^([\d hrmin]{3,})$/i'));

            // Stops
            $stops = $this->http->FindSingleNode('./td[2]/descendant::text()[normalize-space(.)][2]', $root, true, '/^(.*stop.*)$/i');

            if (preg_match('/Non[- ]*stop/i', $stops)) {
                $s->extra()->stops(0);
            }

            if (count($airlineConfNumbers) === 0
                && (count($this->http->FindNodes("//text()[{$this->contains($this->t('Flight Confirmation #'))}]", null, "/{$this->opt($this->t('Flight Confirmation #'))}\s*[A-Z\d]{5,}/")) === 0
                    || $this->http->XPath->query("//text()[{$this->eq($this->t('Flight Confirmation #'))}]/following::text()[normalize-space()][1][{$this->eq('Agency Reference #')}]")->length > 0
                    || $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Flights'))}] and *[normalize-space()][2][{$this->eq($this->t('Flight Confirmation #'))}] ]")->length > 0
                )
            ) {
                // it-2405726.eml, it-2509417.eml, it-6443377.eml
                $f->general()->noConfirmation();
            } elseif (count($airlineConfNumbers) > 0) {
                // it-11099415.eml, it-2005864.eml, it-2534674.eml, it-2733618.eml, it-3889341.eml, it-5384553.eml, it-6046174.eml
                $f->general()->noConfirmation();
            }
        }

        /*$payment = $this->nextText("Amount Billed to Card:");

        if (preg_match('/^(?<currency>[^\d]+?)\s*(?<amount>\d[,.\d\s]*)$/', $payment, $matches)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }*/
        // SpentAwards
        /*$pointsRedeemed = $this->nextText('Points Redeemed:');

        if (preg_match('/^\d[,.\d\s]*$/', $pointsRedeemed)) {
            $f->price()->spentAwards($pointsRedeemed);
        }*/
    }

    public function parseHtmlHotel(Email $email, ?array $tripId): void
    {
        $nodes = $this->http->XPath->query("//text()[{$this->contains('Hotel Reservation')}]");

        foreach ($nodes as $root) {
            // examples: it-6721821.eml
            $h = $email->add()->hotel();

            // TripNumber
            if (!empty($tripId)) {
                $h->ota()->confirmation($tripId[0], $tripId[1]);
            }

            // Status
            if ($this->http->XPath->query('//node()[contains(normalize-space(.),"We\'re pleased to confirm")]')->length > 0) {
                $h->general()->status('Confirmed');
            }

            // ConfirmationNumber
            $confirmationNumber = $this->http->FindSingleNode("./following::text()[{$this->eq('Hotel Confirmation #')}][1]/following::text()[normalize-space(.)][1]", $root, true, '/^([A-z\d][-A-z\d]{4,})$/');

            if (empty($confirmationNumber)) {
                $confirmationNumber = $this->http->FindSingleNode("./following::text()[{$this->eq('Hotel Confirmation #')}][1]/ancestor::td[1]", $root, true, '/Hotel Confirmation #\s*([A-z\d][-A-z\d]{4,})/');
            }

            if (!empty($confirmationNumber)) {
                $h->general()->confirmation($confirmationNumber);
            }

            // Cancellation Policy
            $cancellation = $this->http->FindNodes("./following::text()[{$this->eq($this->t('HOTEL RULES AND POLICIES'))}][1]/following::text()[string-length(normalize-space()) > 2][1]/ancestor::ul[1]/descendant::text()[normalize-space()][1]", $root);

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation(implode(' ', $cancellation));
            }

            $hotelName = $this->http->FindSingleNode("./following::text()[normalize-space()='Hotel'][1]/following::text()[normalize-space()][1]", $root);

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]", $root);
            }

            $hotelAddress = $this->http->FindSingleNode("./following::text()[normalize-space()='Hotel'][1]/following::text()[normalize-space()][2]", $root);

            if (empty($hotelAddress)) {
                $hotelAddress = $this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][2]", $root);
            }

            $h->hotel()
                ->name($hotelName)
                ->address($hotelAddress);

            // GuestNames
            $traveler = $this->nextText('Lead Traveler:');

            if ($traveler) {
                $h->general()->traveller($traveler);
            }

            // Guests
            $h->booked()->guests($this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Guest'))}][1][contains(., '|')]", $root, true, '/(\d+)\s+Guests?/'));

            $r = $h->addRoom();
            $roomType = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Room Type'))}][1]/following::text()[normalize-space(.)][1]", $root);

            if (empty($roomType)) {
                $roomType = $this->http->FindSingleNode("./following::node()[{$this->eq($this->t('Room Type'))}][1]/following::text()[normalize-space(.)][1]",
                    $root);
            }

            $r->setType($roomType);

            $checkIn = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Check-In'))}][1]/following::text()[normalize-space()][1]", $root);
            $checkOut = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Check-Out'))}][1]/following::text()[normalize-space()][1]", $root);

            $h->booked()->checkIn(strtotime($this->normalizeDate($checkIn)));
            $h->booked()->checkOut(strtotime($this->normalizeDate($checkOut)));

            $this->detectDeadLine($h);
        }

        /*if ($nodes->length == 1){
            $payment = $this->nextText('Amount Billed to Card:');

            if (preg_match('/^(?<currency>[^\d]+?)\s*(?<amount>\d[,.\d\s]*)$/', $payment, $matches)) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }

            $pointsRedeemed = $this->nextText('Points Redeemed:');

            if (preg_match('/^\d[,.\d\s]*$/', $pointsRedeemed)) {
                $h->price()->spentAwards($pointsRedeemed);
            }
        }*/
    }

    public function parseHtmlCar(Email $email, ?array $tripId): void
    {
        // examples: it-2093524.eml, it-260909004.eml

        $nodes = $this->http->XPath->query("//text()[normalize-space(.)='Car Confirmation #']");

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            //TripNumber
            if (!empty($tripId)) {
                $r->ota()->confirmation($tripId[0], $tripId[1]);
            }

            // Status
            if ($this->http->XPath->query('//node()[contains(normalize-space(.),"We\'re pleased to confirm")]')->length > 0) {
                $r->general()->status('Confirmed');
            }

            // Number
            $r->general()->confirmation($this->http->FindSingleNode('./following::text()[normalize-space()][1]', $root, true, '/^\s*([\-A-Z\d]+)\s*$/'));

            // RentalCompany
            $rentalCompany = $this->http->FindSingleNode('//text()[ ./preceding::text()[normalize-space(.)="Car Reservation"] and ./following::text()[normalize-space(.)="Car Driver:"] ][normalize-space(.)][./ancestor::b]', null, true, '/^([^#]{2,})$/');

            if (!$rentalCompany) {
                $rentalCompany = $this->nextText("Company");
            }

            if ($rentalCompany) {
                if (!empty($code = $this->getProviderByItinerary($rentalCompany))) {
                    $r->program()->code($code);
                }
            }
            $r->extra()->company($rentalCompany);

            // RenterName
            $carDriver = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Car Driver:'))}][1]/following::text()[normalize-space()][1]", $root);

            if ($carDriver) {
                $r->general()->traveller($carDriver);
            }

            // CarType
            $carType = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Car Type'))}][1]/following::text()[normalize-space()][1]", $root);

            if (preg_match('/^([-\w\s]+)$/', $carType)) {
                $r->car()->type($carType);
            }

            // CarModel
            $carModel = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Car Type'))}][1]/following::text()[normalize-space()][2]", $root);

            if (preg_match('/^([-\w\s]+)$/', $carModel)) {
                $r->car()->model($carModel);
            }

            // PickupDatetime
            $datetimePickup = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Pick-Up'))}][1]/following::text()[normalize-space()][1]", $root);

            if ($datetimePickup) {
                $r->pickup()->date(strtotime($this->normalizeDate($datetimePickup)));
            }

            // PickupLocation
            $locationPickup = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Pick-Up'))}][1]/following::text()[normalize-space()][2]", $root);

            if ($locationPickup && !preg_match('/^[\-+\d][^a-z]*[\-+\d]$/si', $locationPickup)) {
                $r->pickup()->location($locationPickup);
            }

            // PickupPhone
            $phonePickup = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Pick-Up'))}][1]/following::text()[normalize-space()][3]", $root);

            if (preg_match('/^[-+\d]{5,}$/s', $phonePickup)) {
                $r->pickup()->phone($phonePickup);
            }

            // DropoffDatetime
            $datetimeDropoff = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Drop-Off'))}][1]/following::text()[normalize-space()][1]", $root);

            if ($datetimeDropoff) {
                $r->dropoff()->date(strtotime($this->normalizeDate($datetimeDropoff)));
            }

            // DropoffLocation
            $locationDropoff = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Drop-Off'))}][1]/following::text()[normalize-space()][2]", $root);
            $locationsSame = preg_match('/Same As Pick[- ]*Up/i', $locationDropoff) ? true : false;
            $pickupLocation = $r->getPickUpLocation();

            if (isset($locationsSame, $pickupLocation)) {
                $r->dropoff()->location($pickupLocation);
            } elseif ($locationDropoff && !preg_match('/^[-+\d].*[-+\d]$/s', $locationDropoff)) {
                $r->dropoff()->location($locationDropoff);
            }

            // DropoffPhone
            $phoneDropoff = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Drop-Off'))}][1]/following::text()[normalize-space()][3]", $root);
            $pickupPhone = $r->getPickUpPhone();

            if (preg_match('/^[-+\d]{5,}$/s', $phoneDropoff)) {
                $r->dropoff()->phone($phoneDropoff);
            } elseif (isset($locationsSame, $pickupPhone)) {
                $r->dropoff()->phone($pickupPhone);
            }
        }
    }

    public function parseHtmlEvent(Email $email, ?array $tripId): void
    {
        $nodes = $this->http->XPath->query("//text()[normalize-space()='Activity Reservation']");

        foreach ($nodes as $root) {
            $e = $email->add()->event();

            $e->setEventType(EVENT_EVENT);

            $e->general()
                ->confirmation($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Activity Confirmation #'))}][1]/following::text()[normalize-space()][1]", $root))
                ->traveller($this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Lead Traveler:'))}][1]/following::text()[normalize-space()][1]", $root, true, "/([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])/"));

            $e->setName($this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Activity'))}][1]/following::text()[normalize-space()][1]", $root));

            $guests = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Travelers:'))}][1]/following::text()[normalize-space()][1]", $root, true, "/^(\d+)\s*{$this->opt($this->t('Adult'))}/");

            if (!empty($guests)) {
                $e->setGuestCount($guests);
            }

            $dateStart = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Activity Date'))}][1]/following::text()[normalize-space()][1]", $root);
            $e->booked()
                ->start(strtotime($dateStart));

            $dateEndInfo = $this->http->FindSingleNode("./following::text()[{$this->eq($this->t('Activity Option:'))}][1]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/\s*\-\s*(\d+)\s+(hours)\s*\d+\:\d+/", $dateEndInfo, $m)) {
                $dateEnd = strtotime('+' . $m[1] . '' . $m[2], $e->getStartDate());
                $e->setEndDate($dateEnd);
            } else {
                $e->setNoEndDate(true);
            }

            $url = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Click here to view your voucher'))}][1]/ancestor::a[1]/@href", $root);

            if (preg_match("/.*3Fcode\-3D(\d+)\-\d[A-Z]([\d\a-z]+)\-\d+embedResources\-3D((?:true|false))/u", $url, $m)) {
                $url = "https://viatorapi.viator.com/ticket?merchant=true&code={$m[1]}:{$m[2]}&embedResources={$m[3]}";
            } elseif (preg_match("/https\:\/\/viatorapi\.viator\.com\/.*(code\=\d{10}\:.*)/u", $url, $m)) {
                $url = "https://viatorapi.viator.com/ticket?merchant=true&" . $m[1];
            }

            $this->logger->debug($url);

            $http2 = clone $this->http;

            if (stripos($url, 'https://viatorapi.viator.com/ticket?merchant=true&code') !== false) {
                $http2->GetURL($url);
            } else {
                //it-529580711.eml
                $http2->setMaxRedirects(0);
                $http2->GetURL($url);

                $result = json_decode(json_encode($http2->Response), true);

                if (preg_match("/https\:\/\/viatorapi\.viator\.com\/.*(code\=\d{10}\:.*)/", $result['rawHeaders'], $m)) {
                    $url = "https://viatorapi.viator.com/ticket?merchant=true&" . $m[1];
                    $http2->GetURL($url);
                }
            }

            $address = $http2->FindSingleNode("//text()[normalize-space()='Meeting Point']/following::text()[normalize-space()][2]");

            if (empty($address)) {
                $address = $http2->FindSingleNode("//text()[normalize-space()='Meeting and pickup']/following::text()[normalize-space()][1]");

                if (stripos($address, 'Your booking includes pickup. Meet the driver/guide at the pickup point.') !== false) {
                    $email->removeItinerary($e);
                    $email->setIsJunk(true);
                }
            }

            if (empty($address)) {
                if ($http2->XPath->query("//text()[{$this->contains($this->t('Sorry, this is an old link'))}]")->length > 0) {
                    $email->removeItinerary($e);
                    $email->setIsJunk(true);
                }
            }

            if (stripos($address, 'Start time') !== false) {
                $email->removeItinerary($e);
                $email->setIsJunk(true);
            }

            if (!empty($address)) {
                $e->setAddress($address);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Chase Ultimate Rewards Travel') !== false || stripos($from, '@urtravel.chase.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('(//*)[1]')->length === 0
        ) {
            $body = $parser->getBodyStr();
            $body = strstr($body, 'Content-Type: text/html');

            if ($body && preg_match("/^(.*\n){1,5}\s*\n(?<body>[\s\S]+)/u", $body, $m)) {
                $this->http->SetEmailBody($m['body']);
            }
        }

        if ($this->http->XPath->query('//a[contains(@href,".chase.com/") or contains(@href,"www.chase.com") or contains(@href,"ultimaterewardspoints.chase.com") or contains(@href,"ultimaterewardstravel.chase.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"your Chase relationship") or contains(normalize-space(),"Chase Privacy Operat") or contains(normalize-space(),"Chase Travel Center") or contains(.,"www.chase.com") or (contains(., "your Chase relationship"))]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query('(//*)[1]')->length === 0
        ) {
            $body = $parser->getBodyStr();
            $body = strstr($body, 'Content-Type: text/html');

            if ($body && preg_match("/^(.*\n){1,5}\s*\n(?<body>[\s\S]+)/u", $body, $m)) {
                $this->http->SetEmailBody($m['body']);
            }
        }

        $this->date = strtotime($parser->getHeader('date'));

        $yearText = implode("\n", $this->http->FindNodes("//text()[contains(.,'©') and contains(.,'Chase')]"));

        if (preg_match("/©\s*(2\d{3})(?:\D|$)/", $yearText, $m)) {
            $this->year = $m[1];
        } else {
            $this->year = date('Y', $this->date);
        }

        $this->http->FilterHTML = false;
        $this->assignLang();

        $tripId = null;
        $tripIdNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('tripId'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($tripIdNumber) {
            $tripId = [$tripIdNumber, $this->http->FindSingleNode("//text()[{$this->eq($this->t('tripId'))}]", null, true, '/^(?:Your\s+)?(.+?)(?:\s+is)?[\s:：]*$/iu')];
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Flight Confirmation #'))} or {$this->contains($this->t('Check with your airline for the most up-to-date flight'))}]")->length > 0) {
            $this->parseHtmlFlight($email, $tripId);
        }

        if ($this->http->XPath->query('//text()[normalize-space(.)="Hotel Confirmation #"]')->length > 0) {
            $this->parseHtmlHotel($email, $tripId);
        }

        if ($this->http->XPath->query('//text()[normalize-space(.)="Car Confirmation #"]')->length > 0) {
            $this->parseHtmlCar($email, $tripId);
        }

        if ($this->http->XPath->query('//text()[normalize-space(.)="Activity Reservation"]')->length > 0) {
            $this->parseHtmlEvent($email, $tripId);
        }

        if ($email->getIsJunk() !== true) {
            // Currency
            // TotalCharge
            $payment = $this->nextText("Amount Billed to Card:");

            if (preg_match('/^(?<currency>[^\d]+?)\s*(?<amount>\d[,.\d\s]*)$/', $payment, $matches)) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }

            // SpentAwards
            $pointsRedeemed = $this->nextText("Points Redeemed:");

            if (preg_match('/^\d[,.\d\s]*$/', $pointsRedeemed)) {
                $email->price()->spentAwards($pointsRedeemed);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $types = 3; // flight | car | hotel
        $cnt = count(self::$dictionary) * $types;

        return $cnt;
    }

    public function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    /*public static function getEmailProviders()
    {
        return array_keys(self::$providerDetectors);
    }*/

    protected function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/This rate is non-refundable and cannot be changed or cancelled/", $cancellationText, $m)) {
            $h->booked()
                ->nonRefundable();
        }

        if (preg_match("/Cancellations made after\s*(?<month>\w+)\s*(?<day>\d{1,2})\,\s*(?<year>\d{4})\s*(?<time>[\d\:]+\s*A?P?M)\s*\(property local time\)/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
        }
    }

    private function calculateDate(string $dateStr, string $timeStr)
    {
        if (preg_match('/^(?<wday>[-[:alpha:]]+)[,\s]+(?<month>[[:alpha:]]+)[,\s]+(?<day>\d{1,2})$/u', $dateStr, $m) > 0
            && strtotime($m['day'] . ' ' . $m['month']) !== false
        ) {
            $weekDateNumber = WeekTranslate::number1(WeekTranslate::translate($m['wday'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['day'] . ' ' . $m['month'] . ' ' . $this->year, $weekDateNumber);

            if (!empty($date)) {
                $hours = preg_match("/^(\d+):/", $timeStr, $m2) ? (int) $m2[1] : null;

                if ($hours > 12 && stripos($timeStr, 'PM') !== false) {
                    $timeStr = str_ireplace('PM', '', $timeStr);
                } elseif ($hours === 0 && stripos($timeStr, 'AM') !== false) {
                    $timeStr = str_ireplace('AM', '', $timeStr);
                }

                $date = strtotime($timeStr, $date);
            }

            return $date;
        }

        return null;
    }

    private function getProviderByItinerary(string $keyword): ?string
    {
        if (!empty($keyword)) {
            foreach (self::$providerItineraryDetectors as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $n = 1): ?string
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+\s+[AP]M),\s+[^\d\s]+,\s*([^\d\s]+)\s+(\d+)$#",
        ];
        $out = [
            "$3 $2 $year, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
