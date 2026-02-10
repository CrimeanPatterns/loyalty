<?php

namespace AwardWallet\Engine\bankofamerica\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationFor extends \TAccountChecker
{
    public $mailFiles = "bankofamerica/it-229795732.eml, bankofamerica/it-270232232.eml, bankofamerica/it-271873792.eml, bankofamerica/it-271014686.eml, bankofamerica/it-275194285.eml";
    public $subjects = [
        'Confirmation for Order ID#',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'statusPhrases'  => ['your trip was'],
            'statusVariants' => ['booked'],
            'roomsEnd'       => ['Check-in & Checkout Instructions', 'Special Check-in Instructions', 'Cancellation Policy'],
        ],
    ];

    private $xpath = [
        'bold' => '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])',
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]',
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bactravelcenter.bofa.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(),'bankofamerica.com/travel')] | //text()[starts-with(normalize-space(),'©') and contains(normalize-space(),'Bank of America Corporation')]")->length === 0) {
            return false;
        }

        return $this->http->XPath->query("//table[normalize-space()='Booking Summary']")->length > 0
            && $this->http->XPath->query("//table[normalize-space()='Flight Details' or normalize-space()='Hotel Details' or normalize-space()='Car Rental Details']")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bactravelcenter\.bofa\.com$/', $from) > 0;
    }

    public function parseFlight(Flight $f): void
    {
        // examples: it-229795732.eml, it-270232232.eml

        $tickets = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Ticket Number:')]", null, "/^{$this->opt($this->t('Ticket Number:'))}\s*(\d+)/");

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_unique(array_filter($tickets)), false);
        }

        $travellers = array_unique(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Seat Number:')]/preceding::text()[normalize-space()][1]")));
        $f->general()->travellers($travellers, true);

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Departure' or normalize-space()='Connecting Flight']/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'Flight')][1]/ancestor::table[1]/descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<airlineName>[A-z\d\s]{2,})\s*Flight\s*(?<number>\d{2,4})(?:\s|$)/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['number']);
            }

            $depDate = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'Flight')][1]/ancestor::table[1]/descendant::text()[normalize-space()][2]", $root);
            $year = $this->re("/\s(\d{4})$/", $depDate);

            $cabinText = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'Flight')][1]/ancestor::table[1]/descendant::text()[normalize-space()][3]", $root);

            if (preg_match("/^{$this->opt($this->t('Operated by'))}\s*(?<operator>.+)\s*\-\s*(?<cabin>\w+)/", $cabinText, $m)) {
                $s->airline()
                    ->operator($m['operator']);
                $s->extra()
                    ->cabin($m['cabin']);
            } elseif (preg_match("/^(\w+[ ]*){1,2}$/", $cabinText)) {
                // Business    |    Business Special
                $s->extra()
                    ->cabin($cabinText);
            }

            $stopsText = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'Flight')][1]/ancestor::table[1]/descendant::text()[normalize-space()][4]", $root);

            if (preg_match("/{$this->opt($this->t('nonstop'))}/ui", $stopsText)) {
                $s->extra()
                    ->stops(0);
            } elseif (preg_match("/(?<stop>\d+)\s*{$this->opt($this->t('stop'))}/ui", $stopsText, $m)) {
                $s->extra()
                    ->stops($m['stop']);
            }

            $depText = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'From')][1]/ancestor::table[1]", $root);

            if (preg_match("/\((?<depCode>[A-Z]{3})\)\s*(?<time>[\d\:]+a?p?m)$/", $depText, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($depDate . ', ' . $m['time']));
            }

            $arrText = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'To')][1]/ancestor::table[1]", $root);

            if (preg_match("/\((?<arrCode>[A-Z]{3})\)\s*(?<time>[\d\:]+a?p?m)(?:\s*Arrives\s*(?<arrDate>.+)$|$)/u", $arrText, $m)) {
                if (!isset($m['arrDate'])) {
                    $s->arrival()
                        ->code($m['arrCode'])
                        ->date(strtotime($depDate . ', ' . $m['time']));
                } else {
                    $s->arrival()
                        ->code($m['arrCode'])
                        ->date(strtotime($m['arrDate'] . ' ' . $year . ', ' . $m['time']));
                }
            }

            foreach ($travellers as $traveller) {
                $seat = $this->http->FindSingleNode("//text()[normalize-space()='Detailed Traveler Information']/following::text()[{$this->contains($s->getFlightNumber())}]/following::text()[{$this->eq($traveller)}][1]/following::text()[starts-with(normalize-space(), 'Seat Number:')][1]", null, true, "/{$this->opt($this->t('Seat Number:'))}\s*(\d+\-\D)/");

                if (!empty($seat)) {
                    $s->extra()
                        ->seat($seat);
                }
            }

            $confSegment = $this->http->FindSingleNode("//text()[normalize-space()='Detailed Traveler Information']/following::text()[{$this->contains($s->getFlightNumber())}][1]/preceding::text()[starts-with(normalize-space(), 'Airline Confirmation')][1]/following::text()[string-length()>4][2]", null, true, "/^([A-Z\d]{6})$/");

            if (!empty($confSegment)) {
                $s->setConfirmation($confSegment);
            }
        }

        if ($nodes->length > 0) {
            $f->general()->noConfirmation();
        }
    }

    public function parseHotel(Hotel $h): void
    {
        // examples: it-271873792.eml

        $confNumbers = [];

        $xpathConfirmations = "*[1][{$this->eq($this->t('Confirmation Number'))}] and *[2][{$this->eq($this->t('Reference Number'))}]";

        $confirmation = $this->http->FindSingleNode("//tr[{$xpathConfirmations}]/following-sibling::tr[normalize-space()][1]/*[1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[{$xpathConfirmations}]/*[1]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
            $confNumbers[] = $confirmation;
        }

        $reference = $this->http->FindSingleNode("//tr[{$xpathConfirmations}]/following-sibling::tr[normalize-space()][1]/*[2]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($reference && !in_array($reference, $confNumbers)) {
            $referenceTitle = $this->http->FindSingleNode("//tr[{$xpathConfirmations}]/*[2]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($reference, $referenceTitle);
        }

        $hotelName = $address = null;

        $xpathCheckIn = "tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Check In'))}] ]";
        $hotelRows = $this->http->FindNodes("//tr[ not(.//tr) and normalize-space() and preceding::tr[normalize-space()]/preceding-sibling::tr[normalize-space()][1][{$xpathConfirmations}] and following::{$xpathCheckIn} ][not({$this->starts($this->t('Get Directions'))})]");

        if (count($hotelRows) > 1) {
            $hotelName = array_shift($hotelRows);
            $address = implode(', ', $hotelRows);
            $h->hotel()->name($hotelName)->address($address);
        }

        $checkIn = $this->http->FindSingleNode("//{$xpathCheckIn}/*[normalize-space()][2]", null, true, "/^.*\d.*$/");
        $h->booked()->checkIn2($checkIn);

        $checkOut = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Check Out'))}] ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");
        $h->booked()->checkOut2($checkOut);

        $roomsCount = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Number of Rooms'))}] ]/*[normalize-space()][2]", null, true, "/^\d{1,3}$/");
        $h->booked()->rooms($roomsCount);

        $adultCounts = $childCounts = [];

        $guestValues = $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Guests: Room'))}] ]/*[normalize-space()][2]");

        foreach ($guestValues as $guestVal) {
            if (preg_match("/\b(\d{1,3})[x\s]+{$this->opt($this->t('Adult'))}/i", $guestVal, $m)) {
                $adultCounts[] = $m[1];
            }

            if (preg_match("/\b(\d{1,3})[x\s]+{$this->opt($this->t('Child'))}/i", $guestVal, $m)) {
                $childCounts[] = $m[1];
            }
        }

        if (count($adultCounts) > 0) {
            $h->booked()->guests(array_sum($adultCounts));
        }

        if (count($childCounts) > 0) {
            $h->booked()->kids(array_sum($childCounts));
        }

        $roomTypeValues = [];

        foreach ((array) $this->t('roomsEnd') as $phrase) {
            $roomTypeValues = $this->http->FindNodes("//tr[ not(.//tr) and normalize-space() and preceding::tr[{$this->eq($this->t('Room(s)'))}] and following::tr[{$this->eq($phrase)}] ][{$this->xpath['boldOnly']}]");

            if (count($roomTypeValues) > 0) {
                break;
            }
        }

        foreach ($roomTypeValues as $roomTypeVal) {
            $room = $h->addRoom();
            $room->setType($roomTypeVal);
        }

        $travellers = array_filter($this->http->FindNodes("//tr[{$this->eq($this->t('Room(s)'))}]/following::tr[not(.//tr) and {$this->contains($this->t('Booked under'))}]", null, "/^{$this->opt($this->t('Booked under'))}\s+({$this->patterns['travellerName']})$/u"));

        if (count($travellers) > 0) {
            $h->general()->travellers($travellers, true);
        }

        $cancellation = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->eq($this->t('Cancellation Policy'))}]/following::tr[not(.//tr) and normalize-space()][1]");
        $h->general()->cancellation($cancellation, false, true);

        if ($hotelName) {
            $refundableIf = $this->http->FindSingleNode("//text()[{$this->eq($hotelName)}]/following::text()[normalize-space()][position()<6][{$this->eq($this->t('Refundable if cancelled by:'))}]/following::text()[normalize-space()][1]");
        } else {
            $refundableIf = null;
        }

        if ($refundableIf && preg_match("/^(?<time>{$this->patterns['time']})(?:\s+[A-Z]{3,})?\s+on\s+(?<date>.*\d.*)$/", $refundableIf, $m)) {
            $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));
        } elseif ($cancellation && preg_match("/^This rate is non-refundable(?:\s*[.!]|$)/i", $cancellation)) {
            $h->booked()->nonRefundable();
        }
    }

    public function parseCar(Rental $car): void
    {
        // examples: it-271014686.eml

        $confNumbers = [];

        $xpathConfirmations = "*[1][{$this->contains($this->t('Reference Number'))}] and *[2][{$this->contains($this->t('Reference Number'))}]";

        $reference1 = $this->http->FindSingleNode("//tr[{$xpathConfirmations}]/following-sibling::tr[normalize-space()][1]/*[1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($reference1) {
            $reference1Title = $this->http->FindSingleNode("//tr[{$xpathConfirmations}]/*[1]", null, true, '/^(.+?)[\s:：]*$/u');
            $car->general()->confirmation($reference1, $reference1Title);
            $confNumbers[] = $reference1;
        }

        $reference2 = $this->http->FindSingleNode("//tr[{$xpathConfirmations}]/following-sibling::tr[normalize-space()][1]/*[2]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($reference2 && !in_array($reference2, $confNumbers)) {
            $reference2Title = $this->http->FindSingleNode("//tr[{$xpathConfirmations}]/*[2]", null, true, '/^(.+?)[\s:：]*$/u');
            $car->general()->confirmation($reference2, $reference2Title);
        }

        if (count($confNumbers) === 0
            && ($reference = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Reference Number'))}]/following::tr[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/'))
        ) {
            // it-275194285.eml
            $referenceTitle = $this->http->FindSingleNode("//tr[ {$this->eq($this->t('Reference Number'))} and following::tr[normalize-space()][1][{$this->eq($reference)}] ]");
            $car->general()->confirmation($reference, $referenceTitle);
        }

        $xpathPickUp = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Pick-Up'))}] ]";

        $pickUpRows = $this->http->FindNodes($xpathPickUp . "/*[normalize-space()][2]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()][not({$this->starts($this->t('Get Directions'))})]");

        if (count($pickUpRows) === 2) {
            $car->pickup()->date2($pickUpRows[0])->location($pickUpRows[1]);
        }

        $pickUpHours = $this->http->FindSingleNode($xpathPickUp . "/following-sibling::tr[normalize-space()][position()<3][ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Hours of Operation'))}] ]/*[normalize-space()][2]");
        $car->pickup()->openingHours($pickUpHours, false, true);

        $pickUpPhone = $this->http->FindSingleNode($xpathPickUp . "/following-sibling::tr[normalize-space()][position()<3][ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Phone Number'))}] ]/*[normalize-space()][2]", null, true, "/^{$this->patterns['phone']}$/");
        $car->pickup()->phone($pickUpPhone, false, true);

        $xpathDropOff = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Return'))}] ]";

        $dropOffRows = $this->http->FindNodes($xpathDropOff . "/*[normalize-space()][2]/descendant-or-self::*[ *[normalize-space()][2] ][1]/*[normalize-space()][not({$this->starts($this->t('Get Directions'))})]");

        if (count($dropOffRows) === 2) {
            $car->dropoff()->date2($dropOffRows[0])->location($dropOffRows[1]);
        }

        $dropOffHours = $this->http->FindSingleNode($xpathDropOff . "/following-sibling::tr[normalize-space()][position()<3][ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Hours of Operation'))}] ]/*[normalize-space()][2]");
        $car->dropoff()->openingHours($dropOffHours, false, true);

        $dropOffPhone = $this->http->FindSingleNode($xpathPickUp . "/following-sibling::tr[normalize-space()][position()<3][ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Phone Number'))}] ]/*[normalize-space()][2]", null, true, "/^{$this->patterns['phone']}$/");
        $car->dropoff()->phone($dropOffPhone, false, true);

        $company = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Company'))}] ]/*[normalize-space()][2]");

        if (($code = $this->normalizeProvider($company))) {
            $car->program()->code($code);
        } else {
            $car->extra()->company($company);
        }

        $carModel = null;

        if ($company) {
            // it-275194285.eml
            $carModel = $this->http->FindSingleNode($xpathPickUp . "/preceding::tr[not(.//tr) and normalize-space()][1][{$this->eq($company)}]/preceding::tr[not(.//tr) and normalize-space()][1][{$this->xpath['boldOnly']}]");
        }

        if (!$carModel) {
            $carModel = $this->http->FindSingleNode($xpathPickUp . "/preceding::tr[not(.//tr) and normalize-space()][1][{$this->xpath['boldOnly']}]");
        }

        $car->car()->model($carModel, false, true);

        $vehicleType = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Vehicle Type'))}] ]/*[normalize-space()][2]");
        $car->car()->type($vehicleType);

        $driverName = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Driver Information'))}] ]/*[normalize-space()][2]", null, true, "/^({$this->patterns['travellerName']})(?:\s*[,]+\s*\d+)?$/");
        $car->general()->traveller($driverName, true);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('ConfirmationFor' . ucfirst($this->lang));

        $this->xpath['boldOnly'] = "descendant::text()[ancestor::*[{$this->xpath['bold']}]] and count(descendant::text()[normalize-space()])=1";

        $flights = $this->http->XPath->query("//table[normalize-space()='Flight Details']/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]");
        $hotels = $this->http->XPath->query("//table[normalize-space()='Hotel Details']/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]");
        $cars = $this->http->XPath->query("//table[normalize-space()='Car Rental Details']/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]");

        if ($flights->length === 1 && $hotels->length === 0 && $cars->length === 0) {
            $it = $email->add()->flight();
            $this->parseFlight($it);
        } elseif ($hotels->length === 1 && $flights->length === 0 && $cars->length === 0) {
            $it = $email->add()->hotel();
            $this->parseHotel($it);
        } elseif ($cars->length === 1 && $flights->length === 0 && $hotels->length === 0) {
            $it = $email->add()->rental();
            $this->parseCar($it);
        } else {
            $it = null;
        }

        if ($it === null) {
            return $email;
        }

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $it->general()->status($status);
        }

        $xpathOrder = "//tr[ *[1][{$this->eq($this->t('Booking Date'))}] and *[2][{$this->eq($this->t('Order ID'))}] ]";
        $bookingDate = $this->http->FindSingleNode($xpathOrder . "/following-sibling::tr[normalize-space()][1]/*[1]");
        $it->general()->date2($bookingDate);
        $orderId = $this->http->FindSingleNode($xpathOrder . "/following-sibling::tr[normalize-space()][1]/*[2]", null, true, '/^[A-Z\d]{5,}$/');
        $orderIdTitle = $this->http->FindSingleNode($xpathOrder . "/*[2]", null, true, '/^(.+?)[\s:：]*$/u');
        $email->ota()->confirmation($orderId, $orderIdTitle);

        $paymentPoints = $paymentAmount = $paymentCurrency = [];
        $xpathPrice = "descendant::*[{$this->eq($this->t('Payment Details'))}][1]/following-sibling::*[normalize-space()][1]";
        $payDetailsRows = $this->http->XPath->query($xpathPrice . "/descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][not(.//tr)] ]");

        foreach ($payDetailsRows as $payRow) {
            $payCharge = $this->http->FindSingleNode("*[normalize-space()][2]", $payRow);

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $payCharge, $matches)) {
                // $2,312.52
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $paymentCurrency[] = $matches['currency'];
                $paymentAmount[] = PriceHelper::parse($matches['amount'], $currencyCode);
            } elseif (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>Points?)$/iu', $payCharge, $matches)) {
                // 79,383 Points
                $paymentPoints[] = $matches['amount'] . ' ' . $matches['currency'];
            }
        }

        if (count($paymentPoints) === 1) {
            $it->price()->spentAwards($paymentPoints[0]);
        }

        if (count(array_unique($paymentCurrency)) === 1 && !in_array(null, $paymentAmount)) {
            $it->price()->currency($paymentCurrency[0])->total(array_sum($paymentAmount));
        }

        if ($it->getPrice() === null) {
            // it-229795732.eml
            $orderTotal = implode(' ', $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Order Total'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()]"));

            if (preg_match("/^.*\d.*[ ]+or[ ]+(.*\d.*)$/i", $orderTotal, $m)
                && preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $m[1], $matches)
            ) {
                // 907,916 Points or $9,079.16
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 3; // flight | hotel | car
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'avis'         => ['Avis'],
            'alamo'        => ['Alamo'],
            'perfectdrive' => ['Budget'],
            'dollar'       => ['Dollar'],
            'rentacar'     => ['Enterprise'],
            'europcar'     => ['Europcar'],
            'hertz'        => ['Hertz'],
            'national'     => ['National'],
            'sixt'         => ['Sixt'],
            'thrifty'      => ['Thrifty'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
    }
}
