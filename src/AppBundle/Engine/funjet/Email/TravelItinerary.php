<?php

namespace AwardWallet\Engine\funjet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelItinerary extends \TAccountChecker
{
    public $mailFiles = "funjet/it-11477945.eml, funjet/it-11515253.eml, funjet/it-11547008.eml, funjet/it-17993681.eml, funjet/it-17999010.eml, funjet/it-18133596.eml, funjet/it-33039596.eml, funjet/it-48822789.eml, funjet/it-74256918.eml, funjet/it-392515635.eml, funjet/it-629217104-airmilesca.eml, funjet/it-631545628-airmilesca.eml";

    public static $dict = [
        'en' => [
            'Funjet Reservation #'   => ['Funjet Reservation #', 'Funjet Vacations Reservation #', 'Travel Impressions Reservation #', 'Reservation Number', 'Reservation #', 'Southwest Vacations Reservation #', 'Apple Vacations Reservation #'],
            'travellers'             => ['Travellers', 'Travelers', 'Passengers'],
            'Hotel Details'          => ['Hotel Details', 'HOTEL INFORMATION', 'HOTEL DETAILS', 'Accommodation Details'],
            'Ground Transportation'  => ['Ground Transportation', 'GROUND TRANSPORTATION'],
            'Flight#:'               => ['Flight#:', 'Flight:'],
            'stops'                  => ['Number of Stops', 'Stops'],
            'Class:'                 => ['Class:', 'Class/Fare:'],
        ],
    ];

    private $reFrom = [
        'funjet'       => ['@funjetvacations.com', "@tntvacations.com", "@AMResorts.com", "@vaxvacationaccess.com"],
        'airmilesca'   => ['@airmiles.ca'],
        'etihad'       => ['@etihad.'],
        'mileageplus'  => ['@unitedvacations.com'],
        'rapidrewards' => ['@southwestvacations.com'],
        'travimp'      => ['@travimp.com'],
        'ccaribbean'   => ['@cheapcaribbean.com'],
        'appleva'      => ['@applevacations.com'],
    ];

    private $reSubject = [
        'Travel Itinerary',
        'E-Travel Document',
        'Purchase Confirmation',
        'Email Itinerary',
    ];

    private $providerCode = '';
    private $providerDetect = [
        'airmilesca'   => [
            'Thanks for booking with the AIR MILES', 'Thanks for booking with the Air Miles',
            'visit airmiles.ca', 'call us at 1-888-AIR-MILES',
        ],
        'appleva'      => ['Thank you for choosing Apple Vacations', '.applevacation.com', 'Apple Vacations Email Itinerary', 'with Apple Vacations'],
        'ccaribbean'   => ['Thank you for choosing CheapCaribbean', 'cheapcaribbean.com'],
        'travimp'      => ['Thank you for choosing Travel Impressions', 'www.travimp.com'],
        'mileageplus'  => ['unitedvacations.com', 'United Vacations'],
        'etihad'       => ['@etihad.', 'Etihad Holidays'],
        'rapidrewards' => ['southwestvacations.com'],
        'funjet'       => [
            'Funjet Reservation', 'Funjet Vacations', 'HOTEL DETAILS', 'Now Resorts & Spas',
            // below items is unknown providers
            'TNT Vacations',
            'VAX Hotel Vacations',
            '@blueskytours.com', 'Thank you for choosing Blue Sky Tours',
        ],
        // funjet - last item!
    ];

    private $reBody = [
        'en'  => 'Travel Itinerary',
        'en2' => 'HOTEL DETAILS',
        'en3' => 'Booking Confirmation',
        'en4' => 'Email Itinerary',
    ];
    private $lang = '';
    private $keywords = [
        'hertz' => [
            'Hertz',
        ],
        'alamo' => [
            'Alamo',
        ],
    ];
    private $travellers = [];

    public static function getEmailProviders()
    {
        return [
            'airmilesca', 'appleva', 'ccaribbean', 'travimp', 'mileageplus', 'etihad', 'rapidrewards',
            'funjet',
        ];
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider();
        $this->assignLang();

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        // Travel Agency
        $email->obtainTravelAgency();
        $tripNum = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Funjet Reservation #"))}]", null, true, "/{$this->opt($this->t('Funjet Reservation #'))}[:\s]*([A-Z\d]{5,})\s*$/");

        if (!empty($tripNum)) {
            $email->ota()
                ->confirmation($tripNum);
        }

        // Madison Speakman (Age: 17)
        $travellers = array_filter($this->http->FindNodes("//tr[ *[normalize-space()][1][{$this->eq($this->t('travellers'))}] ]/following-sibling::tr[normalize-space()]/*", null, '/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*\(|$)/u'));

        if (count($travellers)) {
            $this->travellers = array_unique($travellers);
        }

        // Price
        $xpathTotalPrice = "//tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Total Price'))}] and count(*[normalize-space()])>1 and following-sibling::tr[normalize-space()] ]";
        $totalPrice = $this->http->FindSingleNode($xpathTotalPrice . "/*[normalize-space()][last()]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $326.10
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $taxes = $this->http->FindSingleNode($xpathTotalPrice . "/following-sibling::tr[descendant::text()[normalize-space()][1][{$this->eq($this->t('Total Taxes'))}] and count(*[normalize-space()])>1]/*[normalize-space()][last()]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $taxes, $m)) {
                $email->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        $milesPaid = $this->http->FindSingleNode($xpathTotalPrice . "/following-sibling::tr[descendant::text()[normalize-space()][1][{$this->eq($this->t('Total Dream Miles Paid'))}] and count(*[normalize-space()])>1]/*[normalize-space()][last()]", null, true, '/^\d[,.‘\'\d ]*(?:Dream Miles)?$/iu');

        if ($milesPaid) {
            $email->price()->spentAwards($milesPaid);
        }

        $this->parseFlights($email);
        $this->parseHotels($email);
        $this->parseCars($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->assignProvider() && $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFroms) {
            foreach ($reFroms as $reFrom) {
                if (stripos($from, $reFrom) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseFlights(Email $email): void
    {
        $xpath = "//text()[{$this->starts($this->t('Flight#:'))}]/ancestor::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug("Flight segments didn't found by xpath: {$xpath}");

            return;
        }

        // FLIGHT
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        if (empty($this->travellers) && $this->providerCode === 'appleva') {
        } else {
            $f->general()
                ->travellers($this->travellers, true);
        }

        // Segments
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $airline = $flightNumber = null;
            $airlineFull = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Flight#:'))}]/ancestor::tr[1]/preceding::tr[normalize-space() and not(contains(normalize-space(),'Operating Airline'))][1]/*[1]", $root);
            $flightRow = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Flight#:'))}]", $root, true, "/{$this->opt($this->t('Flight#:'))}[:\s]*(.+?)[;\s]*$/");
            $flightRowParts = preg_split('/(\s*;\s*)+/', $flightRow);

            if (count($flightRowParts) === 2) {
                $flight = $flightRowParts[0];
                $class = preg_replace("/^.*{$this->opt($this->t('Class:'))}[:\s]*(.+)$/", '$1', $flightRowParts[1]);
            } else {
                $flight = $flightRow;
                $class = null;
            }

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                // DL 7012
                $airline = $m['name'];
                $flightNumber = $m['number'];
            } elseif (preg_match('/^\d+$/', $flight)) {
                // 7012
                $flightNumber = $flight;
            }

            $s->airline()->name($airline ?? $airlineFull)->number($flightNumber);

            $rl = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Airline Confirmation')]/ancestor::*[self::td or self::th][1]", $root, true, "#Airline Confirmation[\s:]+([A-Z\d]{5,})#");

            if (!empty($rl)) {
                $s->airline()->confirmation($rl);
            }

            $operator = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Flight#:'))}]/ancestor::tr[1]//preceding::tr[contains(normalize-space(), 'Operating Airline')][1]/td[1]", $root, true, "#.*(?:OPERATED BY|Operating Airline\s*:)\s*(.+)#i");

            if (!empty($operator)) {
                if (preg_match("#(.+) -- ([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,5})\s*$#", $operator, $mat)) {
                    $operator = trim($mat[1]);
                    $s->airline()
                        ->carrierName($mat[2])
                        ->carrierNumber($mat[3]);
                }
                $s->airline()->operator($this->re("/^[ ]*(?:COMMERCIAL DUPLICATE[ ]+-[ ]+)?(.+?)(?-i)(?:[ ]+DBA |[ ]+AS[ ]+\S|$)/i", $operator));
            }

            $date = $this->http->FindSingleNode("./preceding::text()[" . $this->starts(["Depart:", "Return:", "Depart ", "Return ", "Departure:", "Arrival:"]) . "][1]", $root, null, "#\w+[:\s]+(.+)#u");

            // Departure
            $node = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Departing')]", $root, true, "#:\s*(.+)#");

            if (preg_match("#(.+?)\s+\(([A-Z]{3})\)\s*(\d+:\d+(\s*[APapMm]{2})?)#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                ;

                if (!empty($date)) {
                    $s->departure()->date(strtotime($date . ' ' . $m[3]));
                }
            }

            $node = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Arriving')]", $root, true, "#:\s*(.+)#");

            if (preg_match("#(.+?)\s+\(([A-Z]{3})\)\s*(\d+:\d+(\s*[APapMm]{2})?)#", $node, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                ;

                if (!empty($this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'New Arrival Date')]", $root))) {
                    $date = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Arriving')]/following::text()[normalize-space()][1]", $root);
                }

                if (!empty($date)) {
                    $s->arrival()->date(strtotime($date . ' ' . $m[3]));
                }
            }

            // Extra
            if (preg_match('/^[A-Z]{1,2}$/', $class)) {
                // E
                $s->extra()->bookingCode($class);
            } elseif (preg_match('/^([A-Z]{1,2})\s+(.{2,})$/', $class, $m)) {
                // E Coach
                $s->extra()->bookingCode($m[1])->cabin($m[2]);
            } else {
                // Coach
                $s->extra()->cabin($class, false, true);
            }

            $s->extra()->stops($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('stops'))}][1]", $root, true, "/{$this->opt($this->t('stops'))}[:\s]+(\d{1,3})\b/"), false, true);

            $seats = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Seats')][1]", $root, null, "#:\s*([^-]+)#");

            if (preg_match_all("/\b(\d{1,3}[A-Z])\b/", $seats, $seatMatches)) {
                $s->extra()->seats(array_unique($seatMatches[1]));
            }
        }

        // Price
        $xpathTotalAirFees = "descendant::text()[normalize-space()][1][{$this->eq($this->t('Total Air Taxes and Fees'))}] and count(*[normalize-space()])>1 and preceding-sibling::tr[normalize-space()]";
        $totalAirFees = $this->http->FindSingleNode("//tr[{$xpathTotalAirFees}]/*[normalize-space()][last()]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalAirFees, $matches)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency']);

            $feesRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])>1 and preceding-sibling::tr[ *[normalize-space()][1][{$this->eq($this->t('Description'))}] and *[normalize-space()][last()][{$this->eq($this->t('Amount'))}] and preceding::tr[normalize-space()][1][{$this->starts($this->t('Air Taxes and Fees'))}] ] and following-sibling::tr[{$xpathTotalAirFees}] ]");

            foreach ($feesRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][last()]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $f->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }
    }

    private function parseHotels(Email $email): void
    {
        $xpath = "(//text()[{$this->eq($this->t('Hotel Details'))}]/following::text()[{$this->starts($this->t('Room Type'))} or {$this->starts($this->t('Room ∆'), 'translate(.,"0123456789","∆∆∆∆∆∆∆∆∆∆")')}]/ancestor::table[3])";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug("Hotel segments didn't found by xpath: {$xpath}");

            return;
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $confirmation = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Hotel Confirmation')]", $root)
                ?? $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'Vacation Provider Hotel Confirmation')]", $root)
            ;

            if (preg_match("#^([^:]+?)\s*[:]+\s*([A-z\d\/\-]+(?: [A-z\d\/]+){0,2})\s*$#", $confirmation, $m)) {
                // Hotel Confirmation: I 173973 1 -> I-173973-1
                $h->general()->confirmation(preg_replace("/\s+/", "-", $m[2]), $m[1]);
            } elseif ($this->http->XPath->query("descendant::text()[contains(normalize-space(),'Hotel Confirmation')]", $root)->length === 0) {
                $h->general()->noConfirmation();
            }

            if (empty($this->travellers) && $this->providerCode === 'appleva') {
            } else {
                $h->general()
                    ->travellers($this->travellers, true);
            }

            // Hotel
            $name = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Reserved For')]/preceding::text()[position()<5]/ancestor::a[contains(@href, 'HotelInformation')]", $root, true, "#^(.+?)(?:\s*-\s*All Inclusive|$)#");

            if (empty($name)) {
                $name = $this->http->FindSingleNode("(.//text()[starts-with(normalize-space(), 'Check-in')])[1]/following::text()[normalize-space(.) != ''][1][not(contains(normalize-space(), 'Reserved For'))]", $root, true, "#^(.+?)(?:\s*-\s*All Inclusive|$)#");
            }

            if (!empty($name)) {
                $h->hotel()->name($name);
            }

            $addressText = implode("\n", $this->http->FindNodes("descendant::text()[{$this->starts($this->t('Room Type'))} or {$this->starts($this->t('Room ∆'), 'translate(.,"0123456789","∆∆∆∆∆∆∆∆∆∆")')}]/ancestor::td[2]/following-sibling::*/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<address>[\s\S]+?)\n(?<phone>[+(\d][-+. \d)(]{5,}[\d)])$/", $addressText, $m)
                && strlen(preg_replace('/\D/', '', $m[2])) > 5
            ) {
                $h->hotel()
                    ->address(preg_replace('/([ ]*\n+[ ]*)+/', ', ', trim($m['address'], ', ')))
                    ->phone($m['phone']);
            } else {
                $h->hotel()->address(preg_replace('/([ ]*\n+[ ]*)+/', ', ', trim($addressText, ', ')));
            }

            // Booked
            $node = $this->http->FindSingleNode("(.//text()[starts-with(normalize-space(), 'Check-in')])[1]", $root);

            if (preg_match("#Check-in:?\s*(.+) - Check-out:?\s*(.+)#", $node, $m)) {
                $h->booked()
                    ->checkIn(strtotime($m[1]))
                    ->checkOut(strtotime($m[2]))
                ;
            }
            $h->booked()
                ->guests($this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Reserved For')]", $root, true, "/\b(\d{1,3})\s*Adult/i"), true, true)
                ->kids($this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Reserved For')]", $root, true, "/\b(\d{1,3})\s*Child/i"), true, true)
            ;

            // Rooms
            // TODO: need multi-rooms examples
            $h->addRoom()->setType($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Room Type'))} or {$this->starts($this->t('Room ∆'), 'translate(.,"0123456789","∆∆∆∆∆∆∆∆∆∆")')}]", $root, true, "/:\s*(.+)/"));
        }
    }

    private function parseCars(Email $email): void
    {
        $xpath = "(//text()[{$this->eq($this->t('Ground Transportation'))}]/following::text()[starts-with(normalize-space(), 'Pick up Date')]/ancestor::table[2])";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug("Car segments didn't found by xpath: {$xpath}");

            return;
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            // General
            $r->general()
                ->confirmation($this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Car Confirmation')]", $root, true, "#:\s*([A-Z\d]{5,})#"))
            ;

            if (empty($this->travellers) && $this->providerCode === 'appleva') {
            } else {
                $r->general()
                    ->travellers($this->travellers, true);
            }

            // Pick Up
            $r->pickup()
                ->date(strtotime($this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Pick up Date')]", $root, true, "#:(.+)#")));

            $node = implode("\n", array_filter($this->http->FindNodes(".//text()[starts-with(normalize-space(), 'Pick up and Drop off Address')]/ancestor::tr[1]/following-sibling::tr", $root)));

            if (!empty($node)) {
                if (preg_match("#([\s\S]+?)\n([\d \-\+\(\)]+)\s*$#", $node, $m) && strlen(preg_replace("#[^\d]+#", '', $m[2])) > 5) {
                    $node = trim($m[1]);
                    $r->pickup()
                        ->phone(trim($m[2]));
                }
                $r->pickup()
                    ->location(str_replace("\n", ", ", trim($node)));
                $r->dropoff()->same();
            } else {
                $pu = implode("\n", array_filter($this->http->FindNodes(".//text()[starts-with(normalize-space(), 'Pick up Address:')]/ancestor::tr[1]/following-sibling::tr", $root)));

                if (preg_match("#([\s\S]+?)\n([\d \-\+\(\)]+)\s*$#", $pu, $m) && strlen(preg_replace("#[^\d]+#", '', $m[2])) > 5) {
                    $pu = trim($m[1]);
                    $r->pickup()
                        ->phone(trim($m[2]));
                }
                $r->pickup()
                    ->location(str_replace("\n", ", ", trim($pu)));

                $do = implode("\n", array_filter($this->http->FindNodes(".//text()[starts-with(normalize-space(), 'Drop off Address:')]/ancestor::tr[1]/following-sibling::tr", $root)));

                if (preg_match("#([\s\S]+?)\n([\d \-\+\(\)]+)\s*$#", $do, $m) && strlen(preg_replace("#[^\d]+#", '', $m[2])) > 5) {
                    $do = trim($m[1]);
                    $r->dropoff()
                        ->phone(trim($m[2]));
                }
                $r->dropoff()
                    ->location(str_replace("\n", ", ", trim($do)));
            }

            // Drop Off
            $r->dropoff()
                ->date(strtotime($this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Drop off Date')]", $root, true, "#:(.+)#")));

            // Car
            $r->car()->type(trim($this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Reserved For')]/ancestor::tr[1]//preceding::tr[1]/td[1]", $root, true, "#^\w+\s+([^\-]+)#")));

            // Extra
            $r->extra()
                ->company($this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Reserved For')]/ancestor::tr[1]//preceding::tr[1]/td[1]", $root, true, "#^(\w+)\s+[^\-]+#"));

            if (!empty($keyword = $r->getCompany())) {
                $rentalProvider = $this->getRentalProviderByKeyword($keyword);

                if (!empty($rentalProvider)) {
                    $r->program()->code($rentalProvider);
                } else {
                    $r->extra()->company($keyword);
                }
            }
        }
    }

    private function getRentalProviderByKeyword(string $keyword): ?string
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignProvider(): bool
    {
        foreach ($this->providerDetect as $code => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$value}')]")->length > 0) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $reBody) {
            if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody}')]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
