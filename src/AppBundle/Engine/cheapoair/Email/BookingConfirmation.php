<?php

namespace AwardWallet\Engine\cheapoair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "cheapoair/it-10115674.eml, cheapoair/it-11091213.eml, cheapoair/it-31569768.eml, cheapoair/it-3233233.eml, cheapoair/it-3234183.eml, cheapoair/it-3234246.eml, cheapoair/it-3501231.eml, cheapoair/it-4917118.eml, cheapoair/it-4933085.eml, cheapoair/it-6095527.eml, cheapoair/it-6095534.eml, cheapoair/it-6117353.eml, cheapoair/it-79088797.eml, cheapoair/it-8559025.eml, cheapoair/it-8559913.eml, cheapoair/it-9122041.eml, cheapoair/it-94841973.eml";

    public $reFrom = "@cheapoair.com";
    public $reBody = [
        'es'  => ['Vuelo', 'Confirmación de Reservación'],
        'es2' => ['Vuelo', 'Nombre del viajero'],
        'en'  => ['Flight', 'Booking Confirmation'],
        'en2' => ['Flight', 'Booking Acknowledgement'],
    ];
    public $lang = '';
    public static $dict = [
        'es' => [ // it-94841973.eml
            'segment'                 => ['Vuelo de salida', 'Vuelo de regreso'],
            'CheapOair Booking'       => ['Reservación de CheapOair', 'Reservación de OneTravel'],
            'Airline Confirmation'    => 'Confirmación de la aerolínea',
            'Traveler Name'           => 'Nombre del viajero',
            'E-Ticket Number'         => 'Número de boleto',
            'Sex'                     => ['Masculino', 'Hombre'],
            'Booked on'               => ['Reservado en', 'Reservado el'],
            'Flight'                  => 'Vuelo',
            'Aircraft'                => 'Avión',
            'Nonstop'                 => 'Sin escalas',
            'Total Charge:'           => 'Total Booking Charges',
            'Total Charge'            => 'Cargo total',
            'Taxes and Fees'          => 'Impuestos y Cargos de vuelo',
            'All fares are quoted in' => 'Todas las tarifas están cotizadas en',
        ],
        'en' => [
            'CheapOair Booking'       => ['CheapOair Booking', 'OneTravel Booking'],
            'segment'                 => ['Departing Flight', 'Return Flight'],
            'Sex'                     => ['Male', 'Female'],
            'All fares are quoted in' => 'All fares are quoted in',
            'Booked on'               => ['Booked on', 'Booked On:'],
        ],
    ];

    private $headers = [
        'cheapoair' => [
            'from' => ['@cheapoair.com'],
            'subj' => [
                '#CheapOair\.com.+?Booking\s+receipt[\s\-]+Booking[\s\#]+\d+#',
                '#AIR TICKET NUMBER & AIRLINE CONFIRMATION. Booking[\s\#]+\d+#',
            ],
        ],
        'onetravel' => [
            'from' => ['@onetravel.com'],
            'subj' => [
                '#OneTravel\.com.+?Booking\s+receipt[\s\-]+Booking[\s\#]+\d+#',
            ],
        ],
    ];

    private $bodies = [
        'cheapoair' => [
            "//a[contains(@href,'www.cheapoair.com')]",
            "//img[@alt='CheapOair']",
            "CheapOair.com",
            "CheapOair.ca",
        ],
        'onetravel' => [
            "//a[contains(@href,'www.onetravel.com')]",
            "//img[@alt='OneTravel']",
            "OneTravel.com",
        ],
    ];

    public static function getEmailProviders()
    {
        return ['cheapoair', 'onetravel'];
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        $tripNum = $this->http->FindSingleNode("//text()[{$this->starts($this->t('CheapOair Booking'))}]", null, true, "#{$this->opt($this->t('CheapOair Booking'))}.*?[\s:]+([A-Z\d]{5,})$#");

        if (empty($tripNum)) {
            $tripNum = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('CheapOair Booking'))}])[1]/ancestor::*[1]", null, true, "#{$this->opt($this->t('CheapOair Booking'))}.*?[\s:]+([A-Z\d]{5,})$#");
        }

        if (empty($tripNum)) {
            $tripNum = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Acknowledgement'))}]/following::text()[{$this->starts($this->t('CheapOair Booking'))}][1]/ancestor::*[1]", null, true, "#{$this->opt($this->t('CheapOair Booking'))}.*?[\s:]+([A-Z\d]{5,})$#");
        }

        $email->ota()
            ->confirmation($tripNum);

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Flight'))}]")->length > 0) {
            $this->parseFlight($email);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Car'))}]")->length > 0) {
            $this->parseCar($email);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Hotel'))}]")->length > 0) {
            $this->parseHotel($email);
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total Charge:'))}]/ancestor::td[1]/following::td[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $email->price()
                ->total($tot['Total'])
                ->currency($currency ?? $tot['Currency']);
        }

        $statement = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Tier:'))}]");

        if (preg_match("#\|\s*(\d+)\s*{$this->opt($this->t('points'))}\s*\|\s*{$this->opt($this->t(' Tier:'))}\s*([^|]+)(?:\||$)#", $statement, $m)) {
            $st = $email->add()->statement();
            $st->setBalance($m[1]);
        }

        if ($code = $this->getProvider($parser)) {
            $email->setProviderCode($code);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (null !== $this->getProviderBody()) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (preg_match($subj, $headers['subject'])) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;
            }

            if ($byFrom) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from): bool
    {
        foreach ($this->headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
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

    private function getProvider(PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'cheapoair') {
                return null;
            } else {
                return $this->code;
            }
        }

        return $this->getProviderBody();
    }

    private function getProviderBody()
    {
        $providerBody = null;

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        $providerBody = $code;

                        break;
                    }
                }
            }
        }

        return $providerBody;
    }

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();
        $resDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booked on'))}]", null, true, "#{$this->opt($this->t('Booked on'))}\s+(.+?)\s*(?:\||$)#")));

        $isNameFull = null;
        $pax = array_values(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Traveler Name'))}]/ancestor::tr[1]/following-sibling::tr[{$this->contains($this->t('Sex'))}]/td[3]/descendant::text()[normalize-space(.)][1]")));

        if (empty($pax)) {
            $pax = $this->paxCollector($this->http->FindNodes("//text()[{$this->eq($this->t('First Name'))}]/ancestor::tr[1]/following-sibling::tr[{$this->contains($this->t('Sex'))}]/td"));

            if (count($pax)) {
                $isNameFull = true;
            }
        }

        $f->general()
            ->date($resDate)
            ->travellers($pax, $isNameFull);

        $tickets = [];

        foreach (array_values(array_filter(array_unique($this->http->FindNodes("//tr[ *[2][{$this->eq($this->t('E-Ticket Number'))}] ]/following-sibling::tr[{$this->contains($this->t('Sex'))}]/*[2][not({$this->contains($this->t('Pending'))})]")))) as $str) {
            $tickets = array_merge($tickets, array_map(function ($s) {
                return preg_match("/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+([A-Z\d]{5,})$/", $s, $m) ? $m[1] : $s;
            }, preg_split("/\s*,\s*/", $str)));
        }

        $nodes = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Frequent Flyer'))}]", null, "#{$this->opt($this->t('Frequent Flyer'))}\s+(.+)#", null, "/^.*\d.*$/"));

        if (count($nodes) == 0) {
            $nodes = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Frequent Flyer'))}]/following::div[1][not(contains(normalize-space(), '-UA'))]", null, "/^.*\d.*$/"));
        }
        $accs = [];

        foreach ($nodes as $node) {
            $accs = array_merge($accs, array_map("trim", explode(',', $node)));
        }
        $accountNums = array_values(array_filter(array_unique($accs)));

        if (count($accountNums) > 0) {
            $f->program()
                ->accounts($accountNums, false);
        }

        $airs = [];
        $xpath = "//text()[{$this->starts($this->t('segment'))}]/ancestor::tr[position()<=2]/following-sibling::tr[.//img[contains(@src, 'common/air')] or " . $this->contains($this->t("Aircraft")) . "]/descendant::text()[{$this->starts($this->t('Flight'))}]/ancestor::tr[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')][1]";
        $nodes = $this->http->XPath->query($xpath);

        $this->logger->debug("[XPATH]: " . $xpath);

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Airline Confirmation")) . "]/following::text()[normalize-space(.)!=''][1]", $root, true, "#^\s*([A-Z\d]+)\s*$#");

            if (!empty($rl)) {
                $airs[$rl][] = $root;
            } else {
                $airs[CONFNO_UNKNOWN][] = $root;
            }
        }
        $date = null;

        $ticketArray = [];

        foreach ($airs as $rl => $nodes) {
            if ($rl === CONFNO_UNKNOWN) {
                $f->general()->noConfirmation();
            } else {
                $f->general()->confirmation($rl);
            }

            if (count($tickets) > 0) {
                $tickets = array_filter($tickets, function ($v) use ($rl) {
                    return strpos($v, $rl) === false;
                });

                foreach ($tickets as $ticket) {
                    foreach ($pax as $traveller) {
                        $partName = $this->re("/^(\w+)/u", $traveller);

                        if (!empty($partName)
                            && $this->http->XPath->query("//text()[{$this->eq($ticket)}]/following::text()[string-length()>2][1][{$this->contains($partName)}]")->length > 0
                            && in_array($ticket, $ticketArray) === false) {
                            $f->addTicketNumber($ticket, false, $traveller);
                            $ticketArray[] = $ticket;
                        }
                    }

                    if (in_array($ticket, $ticketArray) === false) {
                        $f->addTicketNumber($ticket, false);
                        $ticketArray[] = $ticket;
                    }
                }
            }

            if (count($airs) == 1) {
                $xpathPrice = "//tr[{$this->eq($this->t('Flight Price Details'))}]/following-sibling::tr";

                $currency = $this->http->FindSingleNode("//text()[{$this->contains($this->t("All fares are quoted in"))}]", null, true, "/{$this->opt($this->t("All fares are quoted in"))}\s+([A-Z]{3})\b/");
                $tot = $this->getTotalCurrency($this->http->FindSingleNode($xpathPrice . "[ *[normalize-space()][1][{$this->starts($this->t('Flight Total'))}] ]/*[normalize-space()][2]"));

                if ($tot['Total'] !== '') {
                    $f->price()
                        ->total($tot['Total'])
                        ->currency($currency ?? $tot['Currency']);
                }
                $tot = $this->getTotalCurrency($this->http->FindSingleNode($xpathPrice . "[ *[normalize-space()][1][{$this->starts($this->t('Subtotal'))}] ]/*[normalize-space()][2]"));

                if ($tot['Total'] !== '') {
                    $f->price()
                        ->cost($tot['Total']);
                }
                $tot = $this->getTotalCurrency($this->http->FindSingleNode($xpathPrice . "[ *[normalize-space()][1][{$this->starts($this->t('Taxes and Fees'))}] ]/*[normalize-space()][2]"));

                if ($tot['Total'] !== '') {
                    $f->price()
                        ->tax($tot['Total']);
                }
            }

            $airlines = [];

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                $airlineNumber = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight'))}][1]", $root, null, "#{$this->opt($this->t('Flight'))}\s+(\d+)#");

                $airlineName = $this->http->FindSingleNode("./descendant::img/@alt[string-length(normalize-space(.))=2]", $root, null, "#^\s*([A-Z\d]{2})\s*$#");

                if (empty($airlineName)) {
                    $airlineName = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight'))}][1]/preceding::text()[normalize-space(.)!=''][1]", $root);
                }

                $airlines[] = $airlineName;
                $s->airline()
                    ->name($airlineName)
                    ->number($airlineNumber);

                if ($aircraft = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Aircraft'))}][1]/following::text()[normalize-space(.)!=''][1]", $root, null, "#(.+?)\s+(?:STD|STD\s+SEATS|SEATS)#")) {
                    $s->extra()
                        ->aircraft($aircraft);
                }

                if ($operator = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Operated by'))}][1]/following::text()[normalize-space(.)!=''][1]", $root)) {
                    if (stripos($operator, 'FOR') !== false) {
                        $operator = $this->re("/^(.+)\s*FOR/", $operator);
                    }
                    $s->airline()
                        ->operator($operator);
                }

                // text with Airport Codes starts with "XXX -"
                $startWithCode = "starts-with(normalize-space(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','XXXXXXXXXXXXXXXXXXXXXXXXXX')),'XXX -')";

                $dateDep = strtotime($this->normalizeDate($this->http->FindSingleNode("(./descendant::text()[{$startWithCode}])[1]/preceding::text()[normalize-space(.) and not(contains(., '['))][2]", $root)));

                if (false === $dateDep) {
                    $dateDep = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::tr[{$this->starts($this->t('segment'))}][1]/descendant::text()[normalize-space(.)][2]", $root)));
                }
                //remember date of segment (FE: 4917118.eml)
                if ($dateDep) {
                    $date = $dateDep;
                }
                //set date of segment
                $dateDep = $date;
                $s->departure()
                    ->name($this->http->FindSingleNode("(./descendant::text()[{$startWithCode}])[1]/preceding::text()[normalize-space(.)!=''][1]", $root));
                $node = $this->http->FindSingleNode("(./descendant::text()[{$startWithCode}])[1]", $root);

                if (preg_match("#((?-i)[A-Z]{3})[\s\-]+(\d+:\d+(?:\s*[ap]m)?)#i", $node, $m)) {
                    $s->departure()
                        ->code($m[1])
                        ->date(strtotime($m[2], $dateDep));
                }
                $dateArr = strtotime($this->normalizeDate($this->http->FindSingleNode("(./descendant::text()[{$startWithCode}])[2]/following::text()[normalize-space(.)!=''][1]", $root)));

                if (false === $dateArr) {
                    $dateArr = $dateDep;
                }
                $s->arrival()
                    ->name($this->http->FindSingleNode("(./descendant::text()[{$startWithCode}])[2]/preceding::text()[normalize-space(.)!=''][1]", $root));
                $node = $this->http->FindSingleNode("(./descendant::text()[{$startWithCode}])[2]", $root);

                if (preg_match("#((?-i)[A-Z]{3})[\s\-]+(\d+:\d+(?:\s*[ap]m)?)#i", $node, $m)) {
                    $s->arrival()
                        ->code($m[1])
                        ->date(strtotime($m[2], $dateArr));
                } elseif (!empty($node2 = $this->http->FindSingleNode("(./descendant::text()[{$startWithCode}])[3]", $root))
                    && preg_match("/\bStop\b/i", $s->getArrName())
                    && preg_match("#((?-i)[A-Z]{3})[\s\-]+(\d+:\d+(?:\s*[ap]m)?)#i", $node2, $m)
                ) {
                    $s2 = $f->addSegment();
                    $s2->airline()
                        ->name($airlineName)
                        ->number($airlineNumber);

                    if (preg_match("/((?-i)[A-Z]{3})[\s\-]+(.+)/i", $node, $m1)) {
                        $s->arrival()
                            ->code($m1[1])
                            ->name($m1[2])
                            ->noDate();

                        $s2->departure()
                            ->name($s->getArrName())
                            ->code($s->getArrCode())
                            ->noDate();
                    }
                    $s2->arrival()
                        ->code($m[1])
                        ->date(strtotime($m[2], $dateArr));
                }
                $node = implode(" ", $this->http->FindNodes("./descendant::text()[{$this->contains($this->t('Seats Selected'))}]/ancestor::tr[1]//text()[normalize-space(.)!='']", $root));

                if (preg_match_all("#\s*(\d+[A-Z])[\s,]*#i", $node, $m)) {
                    $s->extra()
                        ->seats($m[1]);
                }

                $node = implode("\n", $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Aircraft'))}]/ancestor::tr[1]/following-sibling::tr[2]/descendant::text()[contains(.,'|')][1]/ancestor::*[not(self::span)][1]/descendant::text()", $root));
                $node = preg_replace(["/\s*\|\s*/", "/\s+-\s+/"], [' | ', ' - '], trim($node));
                $node = preg_replace("/^(?:[^|]*\n)?\s*(.+)($|\n[\s\S]*)?/", '$1', $node);

                if (preg_match("#(.+?)[\s|]+[\|\s](.*)#", $node, $m)) {
                    $cabin = preg_replace("#(.+?)\s+-\s+.*#", '$1', $m[2]);

                    if (preg_match("/{$this->opt($this->t('Nonstop'))}/i", $m[1])) {
                        $s->extra()
                            ->stops(0);
                    } elseif (isset($s2)) {
                        if ($cabin) {
                            $s2->extra()
                                ->cabin($cabin);
                        }
                    }

                    if ($cabin) {
                        $s->extra()
                            ->cabin($cabin);
                    }
                }
            }
        }
    }

    private function parseCar(Email $email): void
    {
        $xpath = "//text()[normalize-space()='Car Details']/following::text()[starts-with(normalize-space(), 'Days')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $c = $email->add()->rental();

            $c->general()
                ->confirmation($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Car Confirmation'))}]/following::text()[normalize-space()][1]", $root, true, "/^([A-Z\d)]+)$/"))
                ->traveller($this->http->FindSingleNode("./following::table[1]/descendant::text()[starts-with(normalize-space(), 'Driver')]/ancestor::tr[1]/following-sibling::tr/descendant::td[2]", $root), true);

            $c->car()
                ->type($this->http->FindSingleNode("./following::table[1]/descendant::text()[starts-with(normalize-space(), 'Driver')]/ancestor::tr[1]/following-sibling::tr/descendant::td[1]/descendant::text()[normalize-space()][1]", $root))
                ->model($this->http->FindSingleNode("./following::table[1]/descendant::text()[starts-with(normalize-space(), 'Driver')]/ancestor::tr[1]/following-sibling::tr/descendant::td[1]/descendant::text()[normalize-space()][2]", $root, null, "/(.+?(?:\s*or similar|$))/"))
            ;
            $url = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Vehicle Provider:')]/following::img[1]/@src", $root);

            if (empty($url)) {
                $url = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Car Pick-Up')]/ancestor::td[2][not(.//img)]/preceding-sibling::td[1][count(.//img) = 2]/descendant::img[2]/@src", $root);
            }
            $c->car()
                ->image($url, true, true);

            $company = $this->http->FindSingleNode("/descendant::text()[starts-with(normalize-space(), 'Vehicle Provider:')]/following::text()[normalize-space()][1]", $root);

            if (!empty($company)) {
                $c->extra()
                    ->company($company);
            }

            $c->pickup()
                ->location($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Car Pick-Up')]/following::text()[normalize-space()][2]", $root) . ' ' . $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Car Pick-Up')]/following::text()[normalize-space()][3][not(starts-with(normalize-space(), 'Car Drop-Off'))]", $root))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Car Pick-Up')]/following::text()[normalize-space()][1]", $root))));

            $c->dropoff()
                ->location($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Car Drop-Off')]/following::text()[normalize-space()][2]", $root) . ' ' . $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Car Drop-Off')]/following::text()[normalize-space()][3][not({$this->starts($this->t('Car Confirmation'))})]", $root))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Car Drop-Off')]/following::text()[normalize-space()][1]", $root))));
        }
    }

    private function parseHotel(Email $email): void
    {
        $xpath = "//text()[starts-with(normalize-space(), 'Hotel Details')]/following::text()[starts-with(normalize-space(), 'Nights')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $hotelInfo = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Night')]/ancestor::tr[1]/following::tr[1]", $root);

            if (preg_match("/Check-In\s(.+)\sHotel Check-Out\s(.+)\sCheck-In Confirmation (?:Room\s*\d+\s*\:)?\s*([\d\-]+)\s*Check-in time starts at\s*([\d\:]+\s*A?P?M)\s*Check-out time starts at\s*([\d\:]+\s*A?P?M)/u", $hotelInfo, $m)) {
                $h->general()
                    ->confirmation($m[3]);

                $h->booked()
                    ->checkIn(strtotime($m[1] . ',' . $m[4]))
                    ->checkOut(strtotime($m[2] . ',' . $m[5]));
            }

            $h->booked()
                ->guests($this->http->FindSingleNode("./following::table[1]/descendant::text()[normalize-space()='Guest(s):']/following::text()[normalize-space()][1]", $root))
                ->rooms($this->http->FindSingleNode("./following::table[1]/descendant::text()[normalize-space()='Room(s):']/following::text()[normalize-space()][1]", $root));

            $roomInfo = $this->http->FindNodes("./following::table[1]/descendant::text()[starts-with(normalize-space(), 'Room ')]/ancestor::tr[1]/following-sibling::tr[1]", $root);

            foreach ($roomInfo as $roomDescription) {
                $room = $h->addRoom();
                $room->setDescription($roomDescription);
            }

            $h->general()
                ->travellers($this->http->FindNodes("./following::table[1]/descendant::text()[starts-with(normalize-space(), 'Room ')]/ancestor::tr[1]/td[2]", $root), true)
                ->cancellation($this->http->FindSingleNode("following::text()[contains(normalize-space(),'Please note:') or contains(normalize-space(),'*Please note:')][1]/ancestor::tr[1]", $root));

            $h->hotel()
                ->name($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Night')]/preceding::text()[normalize-space()][1]", $root))
                ->address($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Night')]/ancestor::tr[1]/following::tr[1]/td[1]", $root, true, "/^(.+)\s*{$this->opt($this->t('Phone:'))}/"))
                ->phone($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Night')]/ancestor::tr[1]/following::tr[1]/td[1]", $root, true, "/^.+\s*{$this->opt($this->t('Phone:'))}(.+)/"));

            if (preg_match("/You can cancel your reservation online anytime up to (\d+) hours/u", $h->getCancellation(), $m)) {
                $h->booked()->deadlineRelative($m[1] . ' hours');
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            '/^[-[:alpha:]]+\s*,\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})$/u', // mar, may 11, 2021
            '/^\w+\s+(\d+,\s+\d{4}|\d+\s+[^\d\s]+\s+\d{4})$/u', // Mar 04, 2021    |    Thu 04 Mar 2021
            '/^\w+,\s*(\w+)\s*(\d+),\s*(\d+)[\s\-]+([\d:]+\s*A?P?M)$/u', // Thu, Mar 04, 2021 - 10:00 AM
        ];
        $out = [
            '$2 $1 $3',
            '$1',
            '$2 $1 $3, $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang(): bool
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false) {
                    $re = (array) $reBody[1];

                    foreach ($re as $r) {
                        if (stripos($body, $r) !== false) {
                            $this->lang = substr($lang, 0, 2);

                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[^\d\s]*)\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[^\d\s]*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $this->currency($m['c']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'C$'=> 'CAD',
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function paxCollector($pax): array
    {
        $paxes = [];
        $pax_chunk = array_chunk($pax, 7);

        foreach ($pax_chunk as $p) {
            $paxes[] = str_replace("  ", " ", $p[2] . " " . $p[3] . " " . $p[4]);
        }

        return $paxes;
    }
}
