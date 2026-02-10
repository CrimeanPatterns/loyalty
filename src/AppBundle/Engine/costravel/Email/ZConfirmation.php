<?php

namespace AwardWallet\Engine\costravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ZConfirmation extends \TAccountChecker
{
    public $mailFiles = "costravel/it-10189111.eml, costravel/it-10191501.eml, costravel/it-2844507.eml, costravel/it-29943415.eml, costravel/it-30158299.eml, costravel/it-3076439.eml, costravel/it-45671903.eml, costravel/it-52941521.eml, costravel/it-59286885.eml, costravel/it-79451562.eml, costravel/statements/it-76610924.eml";
    public static $dictionary = [
        "en" => [
            'Costco Travel Confirmation Number:' => ['Costco Travel Confirmation Number:', 'Costco Travel: Confirmation #'],
            'Pick-up:'                           => ['Pick-up:', 'Pick-Up:'],
            'Drop-off:'                          => ['Drop-off:', 'Drop-Off:'],
            'cancellationPhrases'                => [
                'Your reservation has been successfully cancelled with',
                'Your reservation has been successfully canceled with',
            ],
            'statusVariants' => ['cancelled', 'canceled'],
        ],
    ];

    private $detectFrom = "customercare@costcotravel.com";
    private $detectSubject = [
        "en" => ["Confirmation #", "Final documents for your"],
    ];
    private $detectCompany = 'Costco Travel';
    private $detectBody = [
        "en" => ["Traveler Information", "Thank you for choosing Costco Travel", "Thank you for booking with Costco Travel"],
    ];

    private $lang = "en";

    /** @var PlancakeEmailParser */
    private $parser;
    private $from;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        foreach($this->reBody2 as $lang=>$re){
        //			if(strpos($this->http->Response["body"], $re) !== false){
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $this->parser = $parser;
        $this->from = $parser->getCleanFrom();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseHtml($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers['subject'])) {
            return false;
        }

        if ($this->detectEmailFromProvider($headers['from']) === false
                && stripos($headers['subject'], 'Costco Travel') === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers['subject'], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response["body"];

        if (strpos($body, $this->detectCompany) === false && stripos($parser->getSubject(), $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            foreach ($dBody as $reBody) {
                if (strpos($body, $reBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email): void
    {

        $this->parseFlights($email);
        $this->parseHotels($email);
        $this->parseCruises($email);
        $this->parseRentals($email);

        // Travel Agency
        $membershipNumber = $this->http->FindSingleNode('//text()[' . $this->eq("Costco Membership #:") . ']/following::text()[normalize-space(.)][1][ ./ancestor::*[self::b or self::strong] ]');

        if (!empty($membershipNumber)) {
            $email->ota()
                ->account($membershipNumber, false);

            if ($this->from === 'customercare@costcotravel.com' && count($email->getItineraries()) > 0) {
                // не убирать условие count($email->getItineraries()) > 0,
                // без него в парсер попадают письма другого формата, резервации не парсятся и остается только стейтмент
                $st = $email->createStatement();
                $st
                    ->setMembership(true)
                    ->setNoBalance(true)
                    ->addProperty('Login', $membershipNumber);
            }
        }

        $conf = $this->nextText($this->t("Costco Travel Confirmation Number:"));

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode('//text()[' . $this->starts($this->t("Costco Travel Confirmation Number:")) . ']', null, true, "#:?\s*([A-Z\d]{5,})#");
        }

        $subj = $this->parser->getSubject();

        if (empty($conf) && preg_match('/[:\#]?\s*([A-Z\d]{5,})/', $subj, $m)) {
            $conf = $m[1];
        }

        $email->ota()
            ->confirmation($conf, 'Costco Travel Confirmation Number');

        // Price
        $totalPrice = $this->nextText("Total Package Price");
        // $16,125.62 | $X,XXX.67 | $2,441.42 CAD
        if (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d]{1,5}?)\s*$#", $totalPrice, $m)
            || preg_match("#^\s*(?<currency>[^\d]{1,5}?)\s*(?<amount>\d[\d\., ]*)\s*$#", $totalPrice, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $email->price()
                ->total(PriceHelper::parse($m['amount']), $currency)
                ->currency($currency)
            ;
        }

        $travellers = [];
        $travellerRows = $this->http->XPath->query("//text()[(starts-with(translate(normalize-space(),'1234567890','dddddddddd'),'Traveler d') or normalize-space()='Primary Traveler') and not(preceding::text()[normalize-space()='Price Summary']) and not(preceding::text()[normalize-space()='Theme Parks'])]/following::text()[normalize-space()][1]/..");

        foreach ($travellerRows as $tRow) {
            $travellerText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $tRow));

            if (preg_match("/^\s*(?:Master\s+)?([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[ ]*(?:\n|$)/u", $travellerText, $m)) {
                $travellers[] = $m[1];
            }
        }
        $bookingDate = $this->normalizeDate($this->nextText("Booking Date:"));

        if (!empty($travellers) || !empty($bookingDate)) {
            foreach ($email->getItineraries() as $value) {
                if (!empty($travellers)) {
                    $value->general()->travellers($travellers, true);
                }

                if (!empty($bookingDate)) {
                    $value->general()->date($bookingDate);
                }
            }
        }

    }

    private function parseFlights(Email $email): void
    {
        $xpath = "//img[contains(@src, '/rightArrow.png')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            return;
        }
        $this->logger->debug("[XPATH-flight]: " . $xpath);
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        // Segments
        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $date = $this->normalizeDate(implode(" ", $this->http->FindNodes("./td[1]//text()", $root)));

            if (empty($date)) {
                return;
            }

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./td[6]/descendant::text()[normalize-space(.)][1]", $root))
                ->number(str_replace("Flight ", "", $this->http->FindSingleNode("./td[6]/descendant::text()[normalize-space(.)][last()]", $root)))
            ;
            $rl = $this->http->FindSingleNode("./following-sibling::tr//text()[" . $this->eq("Airline Confirmation:") . "]/following::text()[normalize-space(.)][1]", $root);

            if (empty($rl)) {
                $rl = $this->http->FindSingleNode("./following-sibling::tr//text()[" . $this->starts("Airline Confirmation:") . "]", $root, true, "#Airline Confirmation:\s*(.+)#");
            }

            if (empty($rl)) {
                $rl = $this->http->FindSingleNode("./following::text()[" . $this->contains("Airline Confirmation:") . "][1]/following::text()[normalize-space(.)][1]", $root);
            }
            $s->airline()
                ->confirmation($rl)
            ;
            $operator = $this->http->FindSingleNode("./..//text()[" . $this->starts("Operated by:") . "]", $root, true, "#Operated by:\s*(.+)#");

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }

            /*
                Miami (MIA)
                10:05 AM
            */
            $pattern['nameCodeTime'] = "/^\s*"
                . "(?<name>.{3,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)[ ]*\n+"
                . "[ ]*(?<time>.+?)\s*"
                . "$/";

            // Departure
            $departureText = $this->htmlToText($this->http->FindHTMLByXpath('*[2]', null, $root));

            if (preg_match($pattern['nameCodeTime'], $departureText, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date(strtotime($m['time'], $date))
                ;
            }

            // Arrival
            $arrivalText = $this->htmlToText($this->http->FindHTMLByXpath('*[4]', null, $root));

            if (preg_match($pattern['nameCodeTime'], $arrivalText, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date(strtotime($m['time'], $date))
                ;
            }

            if (!empty($s->getArrDate()) && !empty($this->http->FindSingleNode("./td[5]//text()[contains(normalize-space(.), 'Next Day')]", $root))) {
                $s->arrival()
                    ->date(strtotime("+1 day", $s->getArrDate()));
            }

            // Extra
            $extra = join("\n", $this->http->FindNodes("./..//text()[{$this->starts("Class of Service:")}]/ancestor::td[1]//text()", $root));
//            $this->logger->debug($extra);
            $cabin = $this->http->FindPreg('/Class of Service:\s*(.+?)\s*\([A-Z]\)/', false, $extra);
            $bookingCode = $this->http->FindPreg('/Class of Service:\s*.+?\s*\(([A-Z])\)/', false, $extra);
            $seats = $this->http->FindPreg("/Seat Number:\s*(.+)/", false, $extra);

            $s->extra()
                ->aircraft($this->http->FindSingleNode("./..//text()[" . $this->starts("Aircraft Type:") . "]", $root, true, "#Aircraft Type:\s*(.+)#"), true, true)
                ->cabin($cabin)
                ->bookingCode($bookingCode)
                ->duration($this->http->FindSingleNode("./td[5]/descendant::text()[normalize-space(.)][1]", $root))
                ->stops($this->http->FindSingleNode("./..//text()[" . $this->starts("Number of Stops:") . "]", $root, true, "#Number of Stops:\s*(.+)#"))
            ;

            if (!empty($seats) && preg_match_all("#\b(\d{1,3}[A-Z])\b#", $seats, $m)) {
                $s->extra()
                    ->seats($m[1]);
            }
        }
    }

    private function parseHotels(Email $email): void
    {
        $xpath = "//text()[{$this->starts("Check-In:")}]/ancestor::*[{$this->contains("Included Room Category")}][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-hotel]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $conf = $this->nextText("Confirmation #:", $root, "#^\s*([A-Z\d]{5,})\s*$#");

            if (!empty($conf)) {
                $h->general()
                    ->confirmation($conf);
            } else {
                $h->general()
                    ->noConfirmation();
            }
            $status = $this->nextText("Status:", $root);

            if (!empty($status)) {
                $h->general()->status($status);
            }

            // Hotel
            $hotelName = $it['HotelName'] = $this->http->FindSingleNode("./preceding::table[1]//h4", $root);

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode(".//table//h4", $root);
            }
            $address = $this->http->FindSingleNode("./preceding::table[1]//h4/following-sibling::*[normalize-space(.)][1]", $root, true, "#(.*?)\s+Ph:#");

            if (empty($address)) {
                $address = $this->http->FindSingleNode(".//table[1]//h4/following-sibling::*[normalize-space(.)][1]", $root, true, "#(.*?)\s+Ph:#");
            }

            $phone = $this->http->FindSingleNode("./preceding::table[1]//h4/following-sibling::*[normalize-space(.)][1]", $root, true, "#Ph:\s*(.+)#");

            if (empty($phone)) {
                $phone = $this->http->FindSingleNode(".//table[1]//h4/following-sibling::*[normalize-space(.)][1]", $root, true, "#Ph:\s*(.+)#");
            }

            $h->hotel()
                ->name($hotelName)
                ->address($address)
                ->phone($phone)
            ;

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate(
                    $this->nextText("Check-In:", $root)
                    ?? $this->http->FindSingleNode("descendant::text()[{$this->starts("Check-In:")}]", $root, true, "/{$this->opt($this->t("Check-In:"))}\s*(.*\d.*)$/")
                ))
                ->checkOut($this->normalizeDate(
                    $this->nextText("Check-Out:", $root)
                    ?? $this->http->FindSingleNode("descendant::text()[{$this->starts("Check-Out:")}]", $root, true, "/{$this->opt($this->t("Check-Out:"))}\s*(.*\d.*)$/")
                ))
            ;
            $roomsCount = $this->http->XPath->query("descendant::text()[translate(normalize-space(),'1234567890','dddddddddd')='Room d of d:']", $root)->length;

            if ($roomsCount) {
                $h->booked()->rooms($roomsCount);
            }
            $guests = array_filter($this->http->FindNodes("descendant::text()[translate(normalize-space(),'1234567890','dddddddddd')='Room d of d:']/following::text()[normalize-space()][1]", $root, "/\b(\d{1,3})\s*adult/i"));

            if (count($guests)) {
                $h->booked()->guests(array_sum($guests));
            }
            $kids = array_filter($this->http->FindNodes("descendant::text()[translate(normalize-space(),'1234567890','dddddddddd')='Room d of d:']/following::text()[normalize-space()][1]", $root, "/\b(\d{1,3})\s*child/i"));

            if (count($kids)) {
                $h->booked()->kids(array_sum($kids));
            }

            if ($cancelDate = $this->normalizeDate($this->http->FindSingleNode("//tr[contains(., 'Cancel Dates') and contains(., 'Refundable')]/following-sibling::tr[td[2][contains(., '100%')]]/td[1]"))) {
                $h->booked()->deadline($cancelDate);
            }

            // Rooms
            $roomTypes = array_filter($this->http->FindNodes(".//text()[translate(normalize-space(.), '1234567890', 'dddddddddd') = 'Room d of d:']/following::text()[normalize-space(.)][1]", $root, "#(.*?)\s+for \d+ Nights#i"));

            foreach ($roomTypes as $type) {
                $h->addRoom()->setType($type);
            }
        }
    }

    private function parseCruises(Email $email): void
    {
        $xpath = "//text()[" . $this->eq("Cruise Line:") . "]/ancestor::*[" . $this->contains("Embarkation Port:") . "][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-cruise]: " . $xpath);
        }

        if ($nodes->length === 0 && !empty($conf = $this->nextText("Cruise Line Confirmation Number:"))) {
            if (!empty($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This cruise was cancelled')]"))) {
                $c = $email->add()->cruise();
                $c->general()
                    ->confirmation($conf)
                    ->cancelled()
                    ->status('cancelled');
            }
        }

        foreach ($nodes as $root) {
            $c = $email->add()->cruise();

            // General
            $conf = $this->nextText("Cruise Line Confirmation Number:");

            if (!empty($conf) && $nodes->length == 1) {
                $c->general()
                    ->confirmation($conf);
            } else {
                $c->general()
                    ->noConfirmation();
            }

            // Details
            $c->details()
                ->description($this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root, true, "#\d+ Nights? - .+\((.+)\)#"))
                ->ship($this->nextText("Ship Name:", $root))
                ->deck($this->http->FindSingleNode("./following::text()[normalize-space()='Stateroom'][1]/following::table[1]//text()[contains(normalize-space(), 'Deck')]", $root, true, "#Deck\s*([\dA-Z]+)\b#"), false, true)
                ->roomClass($this->http->FindSingleNode("./following::text()[normalize-space()='Stateroom'][1]/following::table[1]//text()[contains(normalize-space(), 'Category Code:')]", $root, true, "#Category Code:\s*([\dA-Z]+)\b#"))
                ->room($this->http->FindSingleNode("./following::text()[normalize-space()='Stateroom'][1]/following::table[1]//text()[starts-with(normalize-space(), 'Room #:')]", $root, true, "#Room \\#:\s*([\dA-Z]+)\b#"))
            ;
            $xpathS = "./following::text()[normalize-space()='Sailing Itinerary'][1]/following::*[(self::td or self::th) and normalize-space()='Port of Call']/ancestor::tr[1]/following-sibling::tr";
            $segments = $this->http->XPath->query($xpathS, $root);

            foreach ($segments as $rootS) {
                $date = $this->http->FindSingleNode("./td[1]", $rootS);
                $name = $this->http->FindSingleNode("./td[2]", $rootS);
                $time1 = $this->http->FindSingleNode("./td[3]", $rootS, true, "#.*\d.*#");
                $time2 = $this->http->FindSingleNode("./td[4]", $rootS, true, "#.*\d.*#");

                if (empty($time1) && empty($time2)) {
                    continue;
                }
                $s = $c->addSegment();

                if (!empty($time1)) {
                    $s
                        ->setName($name)
                        ->setAshore($this->normalizeDate($date . ' ' . $time1))
                    ;
                }

                if (!empty($time2)) {
                    $s
                        ->setName($name)
                        ->setAboard($this->normalizeDate($date . ' ' . $time2))
                    ;
                }
            }
        }
    }

    private function parseRentals(Email $email): void
    {
        // it-3076439.eml, it-45671903.eml, it-52941521.eml

        $xpath = "//text()[normalize-space()='Rental Car']/ancestor::table[1]/ancestor::*[position()<4][ descendant::tr[{$this->starts($this->t('Pick-up:'))}] ][ preceding-sibling::*[normalize-space()] ][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-rental]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            // General
            if (($confirmation = $this->http->FindSingleNode("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t('Confirmation #:'))}] ]/*[2]", $root, true, '/^[-A-Z\d]{5,}$/'))) {
                $confirmationTitle = $this->http->FindSingleNode("descendant::tr[count(*)=2]/*[1][{$this->eq($this->t('Confirmation #:'))}]", $root, true, '/^(.+?)[\s:：]*$/u');
                $r->general()->confirmation($confirmation, $confirmationTitle);
            } elseif (($confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation #:'))}]/following::text()[normalize-space()][1]", $root, true, '/^[-A-Z\d]{5,}$/'))) {
                // it-45671903.eml
                $confirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation #:'))}]", $root, true, '/^(.+?)[\s:：]*$/u');
                $r->general()->confirmation($confirmation, $confirmationTitle);
            } elseif ($this->http->XPath->query("descendant::*[{$this->eq($this->t('Confirmation #:'))}]", $root)->length === 0) {
                $r->general()->noConfirmation();
            }

            $status = $this->nextText("Status:", $root);

            if (!empty($status)) {
                $r->general()->status($status);
            }

            /*
                Dec. 26, 2015 Time: 12:00PM
                Kona International Airport, Kailua Kona (KOA),
                76-361 Kupipi St,
                Kailua Kona, HI 96740
                Ph: 808-329-8896
            */
            $pattern = "/^\s*(?<dateTime>.{10,}?)[ ]*\n+"
                . "[ ]*(?<address>[\s\S]{3,}?)[, ]*\n+"
                . "[ ]*Ph:(?:[ ]*(?<phone>[+(\d][-. \d)(]{5,}[\d)])|\n|$)"
                . "/";

            // Pick Up
            $pickUpHtml = $this->http->FindHTMLByXpath("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t('Pick-up:'))}] ]/*[2]", null, $root);
            $pickUp = $this->htmlToText($pickUpHtml);
            $this->logger->debug('$pickUp = '.print_r( $pickUp,true));

            if (preg_match($pattern, $pickUp, $m)) {
                $r->pickup()
                    ->date($this->normalizeDate($m['dateTime']))
                    ->location(preg_replace('/\s+/', ' ', $m['address']))
                    ->phone(empty($m['phone']) ? null : $m['phone'], false, true);
            }

            // Drop Off
            $dropOffHtml = $this->http->FindHTMLByXpath("descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t('Drop-off:'))}] ]/*[2]", null, $root);
            $dropOff = $this->htmlToText($dropOffHtml);

            if (preg_match($pattern, $dropOff, $m)) {
                $r->dropoff()
                    ->date($this->normalizeDate($m['dateTime']))
                    ->location(preg_replace('/\s+/', ' ', $m['address']))
                    ->phone(empty($m['phone']) ? null : $m['phone'], false, true);
            }

            // Car
            $r->car()
                ->type($this->http->FindSingleNode(".//text()[contains(., 'Rental Car')][1]/following::text()[normalize-space()][1]", $root, true, "#.+? - (.+)#"))
            ;

            $imageURL = $this->http->FindSingleNode("(.//img)[1][following::text()[contains(., 'Car Rental:')]]/@src[contains(normalize-space(), '://www.')]", $root);

            if (empty($imageURL)) {
                $imageURL = $this->http->FindSingleNode("(.//img)[1][following::text()[contains(., 'Car Rental:') or {$this->contains($this->t('Pick-up:'))}]]/@src[contains(normalize-space(), '://www.')]/following::img[1]/@src", $root);
            }

            $r->car()
                ->image($imageURL);

            $modelCar = preg_replace("#^\s*" . $this->re("#(.+?)\s*Car\s*$#", $r->getCarType()) . "#", '', $this->http->FindSingleNode(".//text()[contains(normalize-space(.), 'Car Rental:')]", $root, true, "#Car Rental:\s*(.+)#"));

            if (!empty($modelCar)) {
                $r->car()
                    ->model($modelCar);
            }

            $r->extra()
                ->company($this->http->FindSingleNode(".//text()[contains(., 'Rental Car')][1]/following::text()[normalize-space()][1]", $root, true, "#(.+?) - #"))
            ;
        }

        // it-79451562.eml
        $cancellationText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('cancellationPhrases'))}]");

        if (preg_match("/{$this->opt($this->t('cancellationPhrases'))}\s+(?<company>.{2,}?)[ ]*\./i", $cancellationText, $m)
            && ($code = $this->normalizeProvider($m['company']))
        ) {
            $r = $email->add()->rental();
            $r->program()->code($code);
            $r->general()->cancelled();

            if (preg_match("/\b({$this->opt($this->t('statusVariants'))})\b/i", $cancellationText, $matches)) {
                $r->general()->status($matches[1]);
            }
        }
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
            'rentacar'     => ['Enterprise'],
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
            '#^(\w+)\.\s*(\d{1,2}),\s*(\d{4})$#u', //Jan. 01, 2016
            '#^(\w+)\.?\s*(\d{1,2}),\s*(\d{4})\s*Time:\s*(\d+:\d+[APM]*)\b.*#u', //Jan. 1, 2016 Time: 12:00PM   |   Oct 28, 2019 Time: 01:00PM
            '#^\d{1,2}\/\d{1,2}\/\d{2}[ ]*\-[ ]*(\d{1,2})\/(\d{1,2})\/(\d{2})$#', // 6/18/19  - 7/27/19
        ];
        $out = [
            "$2 $3 $4, $1",
            '$2 $1 $3',
            '$2 $1 $3 $4',
            '$3-$1-$2',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'CA $'=> 'CAD',
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $regex = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $regex);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)).')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)).')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '('.implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)).')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
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
