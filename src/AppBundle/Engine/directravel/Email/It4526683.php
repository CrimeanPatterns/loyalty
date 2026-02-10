<?php

namespace AwardWallet\Engine\directravel\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It4526683 extends \TAccountChecker
{
    public $mailFiles = "directravel/it-12365386.eml, directravel/it-15638026.eml, directravel/it-33412610.eml, directravel/it-33727705.eml, directravel/it-4526683.eml, directravel/it-6002058.eml, directravel/it-6076758.eml, directravel/it-8197453.eml";

    public static $detectHeaders = [
        'directravel' => [
            'from'    => 'direct2uitinerary@dt.com',
            'subject' => [
                "en" => "Ticketed Direct2U Itinerary for",
                "Invoiced Direct2U Itinerary for",
            ],
        ],
        'otg' => [
            'from'    => 'ovationtravel.com',
            'subject' => [
                "en" => "Ticketed Travel Itinerary for",
                "Ticketed Exchange Travel Itinerary",
            ],
        ],
        'frosch' => [
            'from'    => '@vwti.com',
            'subject' => [
                "en" => "Travel Reservation to ",
            ],
        ],
        'camelback' => [
            'from'    => '@camelbacktravel.com',
            'subject' => [
                "en" => "Invoiced Direct2U Itinerary for ",
            ],
        ],
    ];

    public $detectCompany = [
        'camelback' => [
            'choosing Camelback Odyssey Travel',
        ],
        'directravel' => [
            'directtravel.streamthru.com',
            'for choosing Direct Travel',
            'les services Direct Travel',
            'Thank you for choosing Direct ATPI Global Travel',
            'itinerary and contact Direct Travel',
            'Invoiced Direct2U Itinerary for',
            'Direct Travel Intelligence Hub',
            'Allegis Travel',
            'DIRECTTRAVEL',
        ],
        'otg' => [
            'ovation.streamthru.com',
            'lawyers.streamthru.com',
        ],
        'frosch' => [
            '.valeriewilsontravel.com',
        ],
    ];
    public $detectBody = [
        "en" => [
            "Complete details for your trip are below",
            "View your itinerary on our app",
            "Travel arrangements for",
            "Please review your itinerary and contact",
            "Important information before you travel",
        ],
    ];

    public static $dictionary = [
        "en" => [
            "Total:" => ["Total:", "Total Charge:"],
        ],
    ];

    public $lang = "en";

    private $providerCode;
    private $pax;
    private $keywords = [
        'rentacar' => [
            'Enterprise',
        ],
        'hertz' => [
            'Hertz',
        ],
        'national' => [
            'National',
        ],
    ];

    public function parseHtml(Email $email)
    {
        $travellers = array_values(array_filter($this->http->FindNodes("//text()[contains(.,'Traveler Name') or contains(.,'Traveler name')]/ancestor::table[1]//tr[position()>1 and string-length(normalize-space(.))>2]/td[1]")));

        if (count($travellers) == 0) {
            $travellers[] = $this->http->FindSingleNode("//text()[contains(.,'Travel arrangements for')]/following::text()[string-length(normalize-space(.))>2][1]");
        }
        $this->pax = [];

        foreach ($travellers as $value) {
            $this->pax = array_merge($this->pax, array_map('trim', explode(",", $value)));
        }

        $email->ota()->confirmation($this->nextText(["Agency reference:", "Agency Reference:", "Agency Locator:", 'VWT Record Locator:', 'Agency locator:']));

        if (!empty($this->providerCode)) {
            $email->ota()->code($this->providerCode);
        }

        // FLIGHT
        if ($this->http->XPath->query("//text()[normalize-space(.)='Departure']/ancestor::tr[1]/preceding-sibling::tr[1][not(contains(normalize-space(.),'Route or Number')) and not(" . $this->contains(["Train number", "Service number"]) . ")]")->length > 0) {
            $this->parseFlight($email);
        }

        // BUSES
        if ($this->http->XPath->query("//text()[normalize-space(.)='Departure']/ancestor::tr[1]/preceding-sibling::tr[1][contains(normalize-space(.),'Route or Number')]")->length > 0) {
            $this->parseBus($email);
        }

        // TRAIN
        if ($this->http->XPath->query("//text()[normalize-space(.)='Departure']/ancestor::tr[1]/preceding-sibling::tr[1][" . $this->contains(["Train number", "Service number"]) . "]")->length > 0) {
            $this->parseTrain($email);
        }

        // CAR
        if ($this->http->XPath->query("//text()[normalize-space(.)='Pick up']/ancestor::tr[1]/..")->length > 0) {
            $this->parseCar($email);
        }
        // LIMO
        if ($this->http->XPath->query("//text()[normalize-space(.)='Date']/ancestor::tr[1]/..")->length > 0) {
            $this->parseLimo($email);
        }

        // HOTEL
        if ($this->http->XPath->query("//text()[" . $this->eq(["Rooms", "Room"]) . "]/ancestor::tr[1]/..")->length > 0) {
            $this->parseHotel($email);
        }

        $tot = $this->getTotalCurrency($this->nextCol(["Total:", "Total Invoiced Amount:", "Total invoiced amount:"]));

        if ($tot['Total'] !== null) {
            $email->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectHeaders as $provider => $dHeaders) {
            if (!empty($dHeaders['from']) && strpos($from, $dHeaders['from']) !== false) {
                $this->providerCode = $provider;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectHeaders as $provider => $dHeaders) {
            if (empty($dHeaders['from']) || empty($dHeaders['subject'])) {
                continue;
            }

            if (stripos($headers["from"], $dHeaders['from']) === false) {
                continue;
            }

            foreach ($dHeaders['subject'] as $dSubject) {
                if (strpos($headers["subject"], $dSubject) !== false) {
                    $this->providerCode = $provider;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = strip_tags($parser->getHTMLBody());

        $foundCompany = false;

        foreach ($this->detectCompany as $provider => $detectCompany) {
            foreach ($detectCompany as $dCompany) {
                if (stripos($body, $dCompany) !== false) {
                    $foundCompany = true;

                    if (empty($this->providerCode)) {
                        $this->providerCode = $provider;
                    }

                    break 2;
                } elseif ($this->http->XPath->query("//img[contains(@src, '" . $dCompany . "')]")->length > 0) {
                    $foundCompany = true;

                    if (empty($this->providerCode)) {
                        $this->providerCode = $provider;
                    }

                    break 2;
                }
            }
        }

        if ($foundCompany === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        if (empty($this->providerCode)) {
            foreach ($this->detectCompany as $provider => $detectCompany) {
                foreach ($detectCompany as $dCompany) {
                    if (stripos($this->http->Response["body"], $dCompany) !== false) {
                        $this->providerCode = $provider;

                        break 2;
                    }
                }
            }
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($this->http->Response["body"], $dBody) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseHtml($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectHeaders);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->noConfirmation()
            ->travellers($this->pax);

        $tickets = array_filter($this->http->FindNodes("//td[normalize-space()='Ticket:']/following-sibling::td[1]",
            null, "#^\s*(\d{7,})\s*$#"));

        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
        }

        $xpath = "//text()[normalize-space(.)='Departure']/ancestor::tr[1]/preceding-sibling::tr[1][not(contains(normalize-space(.),'Route or Number')) and not(contains(normalize-space(.),'Train number'))]/..";
        $nodes = $this->http->XPath->query($xpath);
        $accounts = [];

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            if (!empty($account = $this->nextCol(["Frequent Traveler", "Frequent flyer", "Frequent traveler"], $root)) && !in_array($account, $accounts)) {
                $accounts = array_merge(
                    $accounts,
                    array_filter(array_map("trim", explode(',', $account)))
                );
            }

            $s->airline()
                ->number($this->http->FindSingleNode("./tr[1]/td[2]", $root, true, "#^.*?\w{2}\s*(\d+)$#s"))
                ->name($this->http->FindSingleNode("./tr[1]/td[2]", $root, true, "#^.*?(\w{2})\s*\d+$#s"));

            $confNo = trim($this->nextText(["Airline check-in ID", "Airline check in ID"], $root));

            if (!empty($confNo1 = $this->re("#^([A-Z\d]{5,7})\b#", $confNo))) {
                $s->airline()
                    ->confirmation($confNo1);
            } elseif (strlen($confNo) == 12 && substr($confNo, 0, 6) === substr($confNo, 6)) { // Airline check-in ID  QQVTNHQQVTNH
                $s->airline()
                    ->confirmation(substr($confNo, 0, 6));
            }

            $s->departure()
                ->code($this->http->FindSingleNode("./tr[1]/td[3]/descendant::text()[normalize-space(.)!=''][last()]",
                    $root, true, "#\(([A-Z]{3})#"))
                ->terminal(preg_replace("#^\s*T-?([\dA-Z])\s*$#", '$1', $this->http->FindSingleNode("(.//td[" . $this->eq(["Terminal", "Departure terminal"]) . "])[1]/following-sibling::td[1]", $root)), true, true)
                ->date(strtotime($this->normalizeDate($this->nextText("Departure", $root))));

            $s->arrival()
                ->code($this->http->FindSingleNode("./tr[1]/td[5]/descendant::text()[normalize-space(.)!=''][last()]",
                    $root, true, "#\(([A-Z]{3})#"))
                ->terminal(preg_replace("#^\s*T-?([\dA-Z])\s*$#", '$1', $this->http->FindSingleNode("(.//td[normalize-space()='Terminal'])[2]/following-sibling::td[1] | (.//td[" . $this->eq(["Arrival terminal"]) . "])[1]/following-sibling::td[1]", $root)), true, true)
                ->date(strtotime($this->normalizeDate($this->nextText("Arrival", $root))));

            $seats = array_values(array_filter(
                array_map("trim", explode(",", $this->nextCol("Seat", $root))),
                function ($v) { if (preg_match("#^\d{1,3}[A-Z]$#", $v)) {return $v; } else {return false; }}
            ));

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }
            $s->extra()
                ->aircraft($this->nextCol("Equipment", $root), true, true)
                ->duration($this->nextCol("Duration", $root), true, true)
                ->meal($this->nextCol("Meal", $root), true, true)
                ->status($this->nextCol("Status", $root), true, true)
                ->miles($this->nextCol("Air miles", $root), true, true)
            ;

            if ($s->getStatus() == 'Waitlist') {
                $f->removeSegment($s);
            }

            if (empty($s->getDuration())) {
                $durMeal = explode("/", $this->nextCol("Duration/Meal service", $root));

                if (count($durMeal) === 2) {
                    $s->extra()
                        ->meal($durMeal[1], true)
                        ->duration($durMeal[0], true, true);
                }
            }

            $class = $this->nextCol("Class", $root);

            if (preg_match("#^\s*[A-Z]{1,2}\s*$#", $class)) {
                $s->extra()
                    ->bookingCode($class);
            } elseif (preg_match("#^\s*(.+)\s*\(\s*([A-Z]{1,2})\s*\)\s*$#", $class, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            } elseif (!empty($class)) {
                $s->extra()
                    ->cabin($class);
            }
        }
        $f->program()
            ->accounts(array_unique($accounts), false);
    }

    private function parseBus(Email $email)
    {
        $b = $email->add()->bus();
        $b->general()
            ->noConfirmation()
            ->travellers($this->pax);

        $xpath = "//text()[normalize-space(.)='Departure']/ancestor::tr[1]/preceding-sibling::tr[1][contains(normalize-space(.),'Route or Number')]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $b->addSegment();
            $s->extra()
                ->number($this->http->FindSingleNode("./tr[2]/td[4]", $root));

            if (!empty($code = $this->http->FindSingleNode("./tr[1]/td[3]/descendant::text()[normalize-space(.)!=''][last()]",
                $root, true, "#\(([A-Z]{3})#"))
            ) {
                $s->departure()
                    ->code($code);
            }
            $s->departure()
                ->name($this->http->FindSingleNode("./tr[1]/td[3]/descendant::text()[normalize-space(.)!=''][last()]",
                    $root, true, "#(.+?)\s*(?:\([A-Z]{3}\))?$#"))
                ->date(strtotime($this->normalizeDate($this->nextText("Departure", $root))));

            if (!empty($code = $this->http->FindSingleNode("./tr[1]/td[5]/descendant::text()[normalize-space(.)!=''][last()]",
                $root, true, "#\(([A-Z]{3})#"))
            ) {
                $s->arrival()
                    ->code($code);
            }

            $s->arrival()
                ->name($this->http->FindSingleNode("./tr[1]/td[5]/descendant::text()[normalize-space(.)!=''][last()]",
                    $root, true, "#(.+?)\s*(?:\([A-Z]{3}\))?$#"))
                ->date(strtotime($this->normalizeDate($this->nextText("Arrival", $root))));
        }
    }

    private function parseHotel(Email $email)
    {
        $xpath = "//text()[" . $this->eq(["Rooms", "Room"]) . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $conf = str_replace("NON SMKING CONF", '', $this->nextText(["Confirmation", "Reference"], $root));

            if (preg_match("#\bBARD\b#", $conf)) {
                $h->general()
                    ->noConfirmation();
            } elseif (preg_match("/([A-Z\d]{4,})$/", $conf, $m)) {
                $h->general()
                    ->confirmation($m[1]);
            }
            $h->general()
                ->travellers($this->pax)
                ->status($this->nextText("Status"));

            if (!empty($account = $this->re("#^([\w\-]{5,})$#", $this->nextText("Frequent Guest ID", $root)))) {
                $h->program()
                    ->account($account, false);
            }

            $h->hotel()
                ->name($this->http->FindSingleNode("./tr[1]/td[3]/descendant::text()[normalize-space(.)!=''][1]",
                    $root))
                ->phone($this->http->FindSingleNode(".//text()[" . $this->eq(["Phone", "Telephone no."]) . "]/ancestor::td[1]/following-sibling::td[1]",
                    $root), true)
                ->fax($this->http->FindSingleNode(".//text()[normalize-space() = 'Fax']/ancestor::td[1]/following-sibling::td[1]",
                    $root), true);

            $address = $this->http->FindSingleNode("./tr[1]/td[3]/descendant::text()[normalize-space(.)!=''][last()]", $root);

            if ($address === $h->getHotelName()) {
                $h->hotel()->noAddress();
            } else {
                $h->hotel()
                    ->address($address);
            }
            $h->booked()
                ->checkIn(strtotime($this->normalizeDate($this->nextText(["Check-in", "Check in"], $root))))
                ->checkOut(strtotime($this->normalizeDate($this->nextText(["Check-out", "Check out"], $root))))
                ->guests($this->re("#(\d+)#", $this->nextText(["Guests", "No of Guests", "No. of Guests", "No. of guests"], $root, true)))
                ->rooms($this->re("#(\d+)#", $this->nextText(["No of rooms", "No of Rooms", "No. of rooms"], $root, true)), false, true);
            $h->general()
                ->cancellation($this->nextCol("Cancellation Policy", $root), true, true);

            if (empty($h->getCancellation())) {
                $h->general()->cancellation($this->re("#^\s*(Cancel.+)#i", $this->nextCol("Remarks", $root)), true, true);
            }

            if (empty($h->getCancellation())) {
                $h->general()->cancellation($this->re("#^\s*(Cancel.+)#i", $this->nextCol("Special Info.", $root)), true, true);
            }

            if (empty($h->getCancellation())) {
                $h->general()->cancellation($this->re("#\s*(CANCEL.+)#i", $this->nextCol("Remarks", $root)), true, true);
            }

            if (!empty($node = $h->getCancellation())) {
                $this->detectDeadLine($h, $node);
            }

            $r = $h->addRoom();
            $rate = $this->http->FindSingleNode(".//text()[normalize-space() = 'Rate']/ancestor::td[1]/following-sibling::td[1]",
                $root, true, "#^(.+?)(\s*Approx. Total.*)?$#");

            if ($rate !== 'VARIED**') {
                $r->setRate($rate, true, true);
            } else {
                $rate = $this->http->FindNodes(".//text()[normalize-space() = 'Remarks']/ancestor::td[1]/following-sibling::td[1]/descendant::text()[{$this->contains(["between", "from"])}]",
                    $root);

                if (count(array_filter($rate)) > 0) {
                    $r->setRate(implode(', ', $rate));
                }
            }

            $roomType = $this->http->FindSingleNode(".//td[" . $this->eq(["Rooms", "Room"]) . "]/following-sibling::td[1]/descendant::text()[normalize-space(.)!=''][last()]",
                $root);

            if (!empty($roomType)) {
                if (stripos($roomType, 'TAXES AND SERVICE') !== false) {
                    $roomType = $this->http->FindSingleNode(".//td[" . $this->eq(["Rooms", "Room"]) . "]/following-sibling::td[1]/descendant::text()[normalize-space(.)!=''][contains(normalize-space(), 'BED')]",
                        $root);

                    if (!empty($roomType)) {
                        $r->setType($roomType);
                    }
                } else {
                    $r->setType($roomType, true, true);
                }
            }

            $totalText = $this->http->FindSingleNode(".//td[not(.//td) and contains(normalize-space(), 'Approx. Total')][1]", $root, true, "#Approx. Total\s*(.+)#");

            if (empty($totalText)) {
                $totalText = $this->nextCol("Approx. Total", $root);
            }
            $tot = $this->getTotalCurrency($totalText);

            if ($tot['Total'] !== null) {
                $h->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (preg_match("/CANCEL BEFORE (\d+)\s*DAYS PRIOR TO DAY OF ARRIVAL TO AVOID PENALTY/i", $cancellationText, $m)
            || preg_match("/CANCEL (\d+)\s*DAYS PRIOR TO ARRIVAL TO AVOID PENALTY/i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days', '00:00');
        } elseif (preg_match("/CANCEL (\d+)\s*HRS PRIOR TO ARRIVAL TO AVOID CHARGE/i", $cancellationText, $m)
            || preg_match("/CANCEL (\d+)HRS PRIOR/i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' hours', '00:00');
        } elseif (preg_match("/CANCEL BEFORE (\d+:\d+|\d+\s*[ap]m) LOCAL HOTEL TIME ON SCHEDULED DATE OF ARRIVAL TO AVOID PENALTY/i", $cancellationText, $m)
                || preg_match("/CANCEL BY (\d+:\d+|\d+\s*[ap]m) TO AVOID BILLING/i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('0 days', $m[1]);
        }
    }

    private function parseCar(Email $email)
    {
        $xpath = "//text()[normalize-space(.)='Pick up']/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $confNo = $this->nextCol("Confirmation", $root, 1, true, '#([A-Z\d]{4,})\b#');

            if (empty($confNo) && $this->nextText("Confirmation", $root) == "Phone") {
                $r->general()->noConfirmation();
            } else {
                $r->general()->confirmation($confNo);
            }
            $r->general()
                ->travellers($this->pax);

            $r->pickup()
                ->date(strtotime($this->normalizeDate($this->nextText("Pick up", $root))))
                ->location($this->nextCol("Rental location", $root))
                ->phone($this->nextCol("Phone", $root), true);

            $r->dropoff()
                ->date(strtotime($this->normalizeDate($this->nextText("Drop off", $root))));

            if (!empty($location = $this->nextCol("Return location", $root))) {
                $r->dropoff()
                    ->location($location);
            } else {
                $r->dropoff()
                    ->same();
            }

            $r->dropoff()
                ->phone($this->nextCol("Phone", $root), true);

            $r->car()
                ->type($this->nextText("Type", $root));

            $keyword = $this->http->FindSingleNode("./tr[1]/td[3]/descendant::text()[normalize-space(.)!=''][1]",
                $root);
            $rentalProvider = $this->getRentalProviderByKeyword($keyword);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            }/* else {
                $r->program()->keyword($keyword);
            }*/

            $account = $this->http->FindSingleNode(".//text()[normalize-space() ='Frequent Renter ID']/ancestor-or-self::td[1]/following-sibling::td[1]", $root, true, "#^\s*@?([A-Z\d]{5,})\s*$#");

            if (!empty($account)) {
                if (preg_match("#^X{4,}.*$#", $account, $m)) {
                    $r->program()
                        ->account($account, true);
                } else {
                    $r->program()
                        ->account($account, false);
                }
            }
            $tot = $this->getTotalCurrency($this->nextCol("Approx. Total", $root));

            if ($tot['Total'] !== null) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
    }

    private function parseTrain(Email $email)
    {
        $xpath = "//text()[normalize-space(.)='Departure']/ancestor::tr[1]/preceding-sibling::tr[1][" . $this->contains(["Train number", "Service number"]) . "]/..";
        $nodes = $this->http->XPath->query($xpath);
        $accounts = [];

        foreach ($nodes as $root) {
            $t = $email->add()->train();

            $confNo = $this->nextCol(["Confirmation", "Reference"], $root);

            if (empty($confNo)) {
                $t->general()
                    ->noConfirmation();
            } else {
                $t->general()
                    ->confirmation($confNo);
            }
            $t->general()
                ->status($this->nextCol("Status", $root))
                ->travellers($this->pax);

            $s = $t->addSegment();

            if (!empty($account = $this->nextCol(["Frequent Traveler", "Frequent flyer"], $root)) && !in_array($account, $accounts)) {
                $t->program()
                    ->account($account, false);
            }
            $s->extra()
                ->status($this->nextCol("Status", $root));

            $s->extra()
                ->cabin($this->nextCol("Class", $root))
                ->duration($this->nextCol("Duration", $root))
                ->service($this->nextCol(["Carrier", "Operator"], $root))
                ->seat($this->re("#^\s*(?:NA|(.+))\s*$#", $this->nextCol("Seat", $root)), true, true)
                ->number($this->nextCol(["Train number", "Service number"], $root));
            //	            ->number($this->http->FindSingleNode("./tr[2]/td[4]", $root))

            $s->departure()
                ->name($this->http->FindSingleNode("./tr[1]/td[3]/descendant::text()[normalize-space(.)!=''][last()]",
                    $root))
                ->date(strtotime($this->normalizeDate($this->nextCol("Departure", $root))));

            $s->arrival()
                ->name($this->http->FindSingleNode("./tr[1]/td[5]/descendant::text()[normalize-space(.)!=''][last()]",
                    $root, true, "#(.+?)\s*(?:\([A-Z]{3}\))?$#"))
                ->date(strtotime($this->normalizeDate($this->nextCol("Arrival", $root))));

            if ($s->getDepDate() === $s->getArrDate() && preg_match("/[0\:]+/", $s->getDuration())) {
                $email->removeItinerary($t);
            }
        }
    }

    private function parseLimo(Email $email)
    {
        $xpath = "//text()[normalize-space(.)='Date']/ancestor::tr[1][contains(.,'Company')]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->transfer();

            $r->general()
                ->confirmation(str_replace('*', '-', $this->nextText("Confirmation", $root))) // 3013799*1
                ->travellers($this->pax);

            $s = $r->addSegment();
            $s->departure()
                ->date(strtotime($this->normalizeDate($this->nextCol("Date", $root))))
                ->name($this->http->FindSingleNode("./tr[1]/td[3]/descendant::text()[normalize-space(.)!=''][last()]", $root));

            $s->arrival()
                ->noDate()
                ->name($this->http->FindSingleNode("./tr[1]/td[5]/descendant::text()[normalize-space(.)!=''][last()]", $root));

            $s->extra()
                ->type($this->http->FindSingleNode(".//text()[normalize-space(.)='Remarks']/ancestor::td[1]/following::td[1]//text()[contains(.,'VEHICLE-')]", $root, true, "#VEHICLE-(.+)#"), true, true);

            $keyword = $this->nextCol("Company", $root);
            $rentalProvider = $this->getRentalProviderByKeyword($keyword);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            } else {
//                $r->program()->keyword($keyword);
            }
            $r->program()->phone($this->nextCol("Phone", $root), $keyword);

            $tot = $this->getTotalCurrency($this->nextCol("Rate", $root));

            if ($tot['Total'] !== null) {
                $r->price()
                    ->total($tot['Total']);

                if (!empty($tot['Currency'])) {
                    $r->price()
                        ->currency($tot['Currency']);
                }
            }
        }
    }

    private function nextText($field, $root = null, $zerolength = false, $n = 1)
    {
        // strlen > 2 because of email 4526657
        if ($zerolength === true) {
            return $this->http->FindSingleNode("(.//text()[{$this->eq($field)}])[{$n}]/following::text()[string-length(normalize-space(.))>0][1]",
                $root);
        }

        return $this->http->FindSingleNode("(.//text()[{$this->eq($field)}])[{$n}]/following::text()[string-length(normalize-space(.))>2][1]",
            $root);
    }

    private function nextCol($field, $root = null, $n = 1, $empty = true, $regex = null)
    {
        if ($empty) {
            return $this->http->FindSingleNode("(.//td[({$this->eq($field)}) and not(.//td)])[{$n}]/following-sibling::td[1]",
                $root, true, $regex);
        } else {
            return $this->http->FindSingleNode("(.//td[({$this->eq($field)}) and not(.//td)])[{$n}]/following-sibling::td[normalize-space(.)!=''][1]",
                $root, true, $regex);
        }
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
        //$this->logger->warning($str);
        //		$year = date("Y", $this->date);
        $in = [
            "#^\w+\s+(\w+)\s+(\d+),\s+(\d{4})\s+(\d+:\d+\s+[AP]M)$#",
            "#^\w+\.?\s+(\w+)\.?\s+(\d+),\s+(\d{4})\s+Time\s+(\d+:\d+\s+[AP\.]+M\.?)$#iu",
            "#^\w+\s+(\w+)\s+(\d+),\s+(\d{4})$#",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function getTotalCurrency($node)
    {
        print_r($node);
        $node = str_replace("€", "EUR", $node);
        //$node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>[$])\s*(?<t>\d[.\d,\s]*\d*)#", $node, $m)
            || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function getRentalProviderByKeyword(?string $keyword): ?string
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                } else {
                    foreach ($kws as $kw) {
                        if (strpos($keyword, $kw) !== false) {
                            return $code;
                        }
                    }
                }
            }
        }

        return null;
    }
}
