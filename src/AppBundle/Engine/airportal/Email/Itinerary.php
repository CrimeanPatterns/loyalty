<?php

namespace AwardWallet\Engine\airportal\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "airportal/it-11582298.eml, airportal/it-19390436.eml, airportal/it-204024711.eml, airportal/it-327841516.eml";

    public $reBody = [
        'en2' => ['Confirmation', 'Departure:'],
        'en3' => ['Confirmation', 'Check-in:'],
        'en4' => ['Confirmation', 'Pick-up @'],
    ];
    public $reSubject = [
        'en' => ['AirPortal - Airtinerary'],
    ];

    public $lang = '';

    public static $dict = [
        'en' => [
            'Passengers' => ['Passengers', 'Passenger'],
        ],
    ];
    private $providerCode = '';

    private $xpathFragments = [
        'strongText' => './ancestor::*[self::strong or self::b]',
    ];

    private $travellers = [];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getHeaders());

        $this->assignLang();

        // Travel Agency
        $conf = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Agency Locator:')) . ']/following::text()[normalize-space(.)][1]', null, true, '/^[A-Z\d]{5,}$/');

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Agency Locator')) . ']/preceding::text()[normalize-space(.)][1]', null, true, '/^[A-Z\d]{5,}$/');
        }

        if (!empty($conf)) {
            $email->ota()
                ->confirmation($conf);
        }

        // Passengers
        $this->travellers = $this->http->FindNodes('(//text()[' . $this->eq($this->t('Passengers')) . '])[1]/ancestor::*[ ./following-sibling::*[normalize-space(.)] ][1][not(./descendant::td)]/following-sibling::*[normalize-space(.)]', null, '/^([A-z][-.\'A-z ]*[A-z])(?:$|\s*\()/');

        $this->parseEmailFlight($email);
        $this->parseEmailHotel($email);
        $this->parseEmailCar($email);

        $email->setProviderCode($this->providerCode);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
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
        $types = 3;
        $cnt = $types * count(self::$dict);

        return $cnt;
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

    public static function getEmailProviders()
    {
        return ['andavo', 'airportal', 'christopherson'];
    }

    /*
    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if ($i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                    foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                        if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])
                            && (isset($tsJ['Seats']) || isset($tsI['Seats']))
                        ) {
                            $new = [];

                            if (isset($tsJ['Seats'])) {
                                $new = array_merge($new, (array) $tsJ['Seats']);
                            }

                            if (isset($tsI['Seats'])) {
                                $new = array_merge($new, (array) $tsI['Seats']);
                            }

                            if (!empty($new)) {
                                $its[$j]['TripSegments'][$flJ]['Seats'] = array_values(array_filter(array_unique($new)));
                                $its[$i]['TripSegments'][$flI]['Seats'] = array_values(array_filter(array_unique($new)));
                            }
                        }
                    }
                }

                $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));

                if (isset($its[$j]['Passengers']) || isset($its[$i]['Passengers'])) {
                    $new = [];

                    if (isset($its[$j]['Passengers'])) {
                        $new = array_merge($new, $its[$j]['Passengers']);
                    }

                    if (isset($its[$i]['Passengers'])) {
                        $new = array_merge($new, $its[$i]['Passengers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", $new))));
                    $its[$j]['Passengers'] = $new;
                }

                if (isset($its[$j]['AccountNumbers']) || isset($its[$i]['AccountNumbers'])) {
                    $new = [];

                    if (isset($its[$j]['AccountNumbers'])) {
                        $new = array_merge($new, $its[$j]['AccountNumbers']);
                    }

                    if (isset($its[$i]['AccountNumbers'])) {
                        $new = array_merge($new, $its[$i]['AccountNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", $new))));
                    $its[$j]['AccountNumbers'] = $new;
                }

                if (isset($its[$j]['TicketNumbers']) || isset($its[$i]['TicketNumbers'])) {
                    $new = [];

                    if (isset($its[$j]['TicketNumbers'])) {
                        $new = array_merge($new, $its[$j]['TicketNumbers']);
                    }

                    if (isset($its[$i]['TicketNumbers'])) {
                        $new = array_merge($new, $its[$i]['TicketNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", $new))));
                    $its[$j]['TicketNumbers'] = $new;
                }

                unset($its[$i]);
            }
        }

        return $its;
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if ($g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }
    */

    private function parseEmailFlight(Email $email): void
    {
        $xpath = "//text()[{$this->eq($this->t('Departure:'))}]/ancestor::table[1]";
//        $this->logger->debug('Flight $xpath = ' . print_r($xpath, true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $f = $email->add()->flight();

            $confs = array_unique($this->http->FindNodes($xpath . "/following::table[1][{$this->contains($this->t('Flight'))}]/descendant::text()[{$this->starts($this->t('Confirmation'))}]/following::text()[string-length(normalize-space())>1][1]", null, "#([A-Z\d]{5,})#"));

            foreach ($confs as $conf) {
                $f->general()
                    ->confirmation($conf)
                    ->travellers($this->travellers)
                ;
            }

            $ticketXpath = "//tr[ *[1][{$this->eq($this->t('Ticket #'))}] and *[4][{$this->eq($this->t('Type'))}] ]/ancestor::tr[1]/descendant::tr[ *[4][{$this->eq($this->t('Air'))}] ]";

            foreach ($this->http->XPath->query($ticketXpath) as $tRoot) {
                $ticket = $this->http->FindSingleNode("*[1]", $tRoot, "/^\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}$/");
                $name = $this->http->FindSingleNode("*[2]", $tRoot);

                if (!in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
                    preg_match("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", $name, $np);

                    if (!empty($name) && !in_array($ticket, array_column($f->getTicketNumbers(), 0))
                        && preg_match("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", $name, $np)
                        && preg_match_all("/^\s*" . preg_replace('/(\S)/', '$1 ?', $np[2]) . ".*\s+{$np[1]}\s*$/mi",
                            implode("\n", $this->travellers), $m)
                        && count($m[0]) === 1
                    ) {
                        // HILTON/RALPHW HILTON -> RALPH WILLIAM HILTON
                        $name = $m[0][0];
                    } else {
                        $name = null;
                    }
                    $f->issued()->ticket($ticket, false, $name);
                }
            }
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $node = $this->http->FindSingleNode("./following::table[1][{$this->contains($this->t('Flight'))}]/descendant::text()[{$this->contains($this->t('Flight'))}][1]", $root);

            if (preg_match("#(.+?)\s*Flight\s+(\d{1,5})\s*$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            // Departure, Arrival
            $dname = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departure'))}]/following::text()[normalize-space(.)!=''][1]", $root);
            $aname = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrival'))}]/following::text()[normalize-space(.)!=''][1]", $root);

            $node = $this->http->FindSingleNode("./descendant::td[1]/*[normalize-space(.)!=''][1]/following-sibling::*[1][contains(normalize-space(.),' to ')]", $root);

            if (preg_match("#(.+)\s+to\s+(.+)#", $node, $m)) {
                $dname = trim($m[1] . ', ' . $dname, ', ');
                $aname = trim($m[1] . ', ' . $aname, ', ');
            }

            $s->departure()
                ->name($dname);
            $s->arrival()
                ->name($aname);

            $node = implode(" ", $this->http->FindNodes("./descendant::td[1]/*[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']", $root));

            if (preg_match("/^\s*([A-Z]{3})\s*[^\s\w]\s*([A-Z]{3})\s*$/u", $node, $m)) {
                $s->departure()
                    ->code($m[1]);
                $s->arrival()
                    ->code($m[2]);
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure:'))}]/following::text()[normalize-space(.)!=''][2]", $root));
            $time = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure:'))}]/following::text()[normalize-space(.)!=''][3]", $root, false, "/\d+:\d+\s*(?:[AP]m)?/i");

            if (empty($time)) {
                $time = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure:'))}]/following::text()[normalize-space(.)!=''][3]/ancestor::*[1][self::span]", $root, false, "/\d+:\d+\s*(?:[AP]m)?/i");
            }

            if (!empty($date) && preg_match("/^\s*\d{1,2}:\d{2}(\s*[ap]m)?\s*$/i", $time)) {
                $s->departure()
                    ->date(strtotime($time, $date));
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival:'))}]/following::text()[normalize-space(.)!=''][2]", $root));
            $time = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival:'))}]/following::text()[normalize-space(.)!=''][3]", $root, false, "/\d+:\d+\s*(?:[AP]m)?/i");

            if (empty($time)) {
                $time = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival:'))}]/following::text()[normalize-space(.)!=''][3]/ancestor::*[1][self::span]", $root, false, "/\d+:\d+\s*(?:[AP]m)?/i");
            }

            if (!empty($date) && preg_match("/^\s*\d{1,2}:\d{2}(\s*[ap]m)?\s*$/i", $time)) {
                $s->arrival()
                    ->date(strtotime($time, $date));
            }

            // Extra
            $s->extra()
                ->status($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Status:'))}]/following::text()[normalize-space()][1]", $root))
                ->duration($this->http->FindSingleNode("following::table[1][{$this->contains($this->t('Flight'))}]/descendant::text()[{$this->eq($this->t('Duration:'))}]/following::text()[normalize-space()][1]", $root, true, '/^\d.+/'), false, true)
                ->aircraft($this->http->FindSingleNode("following::table[1][{$this->contains($this->t('Flight'))}]/descendant::text()[{$this->eq($this->t('Aircraft:'))}]/following::text()[normalize-space()][1]", $root), false, true)
            ;

            $seatsXpath = "ancestor::table[1]/following-sibling::table[position()<4][{$this->contains($this->t('Passenger Name'))}]/descendant::text()[{$this->eq($this->t('Passenger Name'))}]/ancestor::table[{$this->contains($this->t('Seat'))}][1]/following-sibling::table/descendant::tr[1]";

            foreach ($this->http->XPath->query($seatsXpath, $root) as $sRoot) {
                $name = $this->http->FindSingleNode("td[1]", $sRoot);
                $seat = $this->http->FindSingleNode("td[2]", $sRoot);
                $account = $this->http->FindSingleNode("td[4]", $sRoot, true, "/^\s*[A-Z\d][A-Z\d -]{5,}\s*$/");
                $s->extra()
                    ->seat($seat, true, true, $name);

                if (!empty($account) && !in_array($account, array_column($f->getAccountNumbers(), 0))) {
                    $f->program()
                        ->account($account, false, $name);
                }
            }

            $cabins = array_unique(array_filter($this->http->FindNodes("./ancestor::table[1]/following-sibling::table[position()<4][{$this->contains($this->t('Passenger Name'))}]/descendant::text()[{$this->eq($this->t('Passenger Name'))}]/ancestor::table[{$this->contains($this->t('Seat'))}][1]/following-sibling::table/descendant::tr[1]/td[3]", $root)));

            if (!empty($cabins[0])) {
                if (preg_match("#^\s*(\S.+?)\s*\(([A-Z]{1,2})\)\s*$#", $cabins[0], $m)) {
                    $s->extra()
                        ->cabin($m[1])
                        ->bookingCode($m[2]);
                } elseif (preg_match("#^\s*([A-Z]{1,2})\s*$#", $cabins[0], $m)) {
                    $s->extra()
                        ->bookingCode($m[1]);
                }
            }
        }

        // Price
        $totalTax = $this->getTotalCurrency(($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Total Tax'))}]/following::text()[normalize-space(.)!=''][1])[1]")));

        if (isset($f) && $totalTax['Total'] !== '') {
            $f->price()
                ->tax($totalTax['Total']);
        }

        $totalCharge = $this->getTotalCurrency(($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Total Charged'))}])[1]")));

        if (isset($f) && $totalCharge['Total'] !== '') {
            $f->price()
                ->total($totalCharge['Total'])
                ->currency($totalCharge['Currency'])
            ;
        }
    }

    private function parseEmailHotel(Email $email): void
    {
        $xpath = "//text()[normalize-space(.)='Check-in:']/ancestor::table[1]";
//        $this->logger->debug('Hotel XPath = ' . print_r($xpath, true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $conf = $this->http->FindSingleNode("./following::table[1][contains(.,'Confirmation')]/descendant::text()[{$this->starts($this->t('Confirmation'))}]", $root, true, "#{$this->opt($this->t('Confirmation'))}\s+([A-Z\d]{4,})$#");

            if (empty($conf) && $this->http->FindSingleNode("./following::table[1][contains(.,'Confirmation')]/descendant::text()[{$this->eq($this->t('Confirmation'))}]/following::text()[normalize-space()][1][{$this->starts($this->t('Duration'))}]", $root)) {
                $h->general()
                    ->noConfirmation();
            } else {
                $h->general()
                    ->confirmation($conf);
            }

            if (empty($this->travellers)) {
                $this->travellers = array_unique($this->http->FindNodes("./ancestor::table[1]/following::table[position()<4][{$this->contains($this->t('Guest Name'))}]/descendant::text()[{$this->contains($this->t('Guest Name'))}]/ancestor::table[{$this->contains($this->t('Nightly Rate'))}][1]/following-sibling::table/descendant::tr[1]/td[1]",
                    $root));
            }
            $h->general()
                ->travellers($this->travellers);

            $h->general()
                ->status($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Status:'))}]/following::text()[normalize-space(.)!=''][1][not({$this->starts($this->t('Check-in'))})]", $root));

            $cancellationPolicy = $this->http->FindSingleNode("./following::table[1][{$this->contains($this->t('Confirmation'))}]/descendant::text()[{$this->starts($this->t('Cancellation Policy'))}]/following::text()[normalize-space(.)!=''][1][not({$this->starts($this->t('Room'))})]", $root);

            if (!empty($cancellationPolicy)) {
                $h->general()
                    ->cancellation($cancellationPolicy);
            }

            // Program
            $account = $this->http->FindSingleNode("./following::table[1][{$this->contains($this->t('Confirmation'))}]/descendant::text()[{$this->eq($this->t('Membership ID:'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#^\s*[A-Z\d][A-Z\d\-]{5,}\s*$#");

            if (!empty($account)) {
                $h->program()
                    ->account($account, false);
            }

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode("./descendant::td[1]/*[1]", $root));

            $address = $this->http->FindSingleNode("./descendant::td[1]/*[1]/following-sibling::*[1][not({$this->starts($this->t('Status'))})]", $root);

            if (!empty($address)) {
                if (preg_match("/^\s*No Address Provided/", $address)) {
                    $h->hotel()
                        ->noAddress();
                } else {
                    $h->hotel()
                        ->address($address);
                }
            } else {
                $nextText = $this->http->FindSingleNode("./descendant::td[1]/*[1]/following::text()[normalize-space()][1]", $root);

                if (preg_match("/(?:^\s*Status|^[^[:alpha:]]$)/", $nextText)) {
                    $h->hotel()
                        ->noAddress();
                }
            }

            $phone = $this->http->FindSingleNode("./descendant::td[1]/*[1]/following-sibling::*[2][not( {$this->starts($this->t('Status'))} or {$this->starts($this->t('Check-in:'))} or contains(., ':'))]", $root, true, "#^\s*([\d\-\+\(\) ]{5,})\s*$#");

            if (!empty($phone)) {
                $h->hotel()
                    ->phone($phone);
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Check-in:'))}]/following::text()[normalize-space(.)!=''][1]", $root));
            $time = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Check-in'))}]/following::text()[normalize-space(.)!=''][2]", $root, true, "#^\s*(\d+:\d+(?:\s*[ap]m)?)\s*$#i");

            if (!empty($date)) {
                if (!empty($time)) {
                    $date = strtotime($time, $date);
                }
            }

            $h->booked()
                ->checkIn($date);

            $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Check-out'))}]/following::text()[normalize-space(.)!=''][1]", $root));
            $time = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Check-out'))}]/following::text()[normalize-space(.)!=''][2]", $root, true, "#^\s*(\d+:\d+(?:\s*[ap]m)?)\s*$#i");

            if (!empty($date)) {
                if (!empty($time)) {
                    $date = strtotime($time, $date);
                }
            }

            $h->booked()
                ->checkOut($date);

            $roomDescription = $this->http->FindSingleNode("./following::table[1][{$this->contains($this->t('Confirmation'))}]/descendant::text()[{$this->eq($this->t('Room:'))}]/following::text()[normalize-space(.)!=''][1]", $root);

            $rowsXpath = "./ancestor::table[1]/following::table[position()<4]//tr[*[1][{$this->eq($this->t('Guest Name'))}] and *[3][{$this->eq($this->t('Effective Date'))}]]/ancestor::table[1]/following-sibling::table//tr[count(*)>3][normalize-space()]";
            $rows = $this->http->XPath->query($rowsXpath, $root);
            $nignt = $this->http->FindSingleNode("./following::table[1][{$this->contains($this->t('Confirmation'))}]/descendant::text()[{$this->eq($this->t('Duration:'))}]/following::text()[normalize-space(.)!=''][1]",
                $root, true, "/^\s*(\d+) [[:alpha:]]+\s*$/");

            if ($rows->length == 1) {
                $h->booked()
                    ->rooms($this->http->FindSingleNode("*[4]", $rows->item(0)))
                    ->guests($this->http->FindSingleNode("*[5]", $rows->item(0)))
                    ->kids($this->http->FindSingleNode("*[6]", $rows->item(0)), true, true)
                ;
                $r = $h->addRoom();

                $r->setRate($this->http->FindSingleNode("*[2]", $rows->item(0)));

                if (!empty($roomDescription)) {
                    $r->setDescription($roomDescription);
                }
            } elseif ($rows->length > 1) {
                $emptyDate = false;
                $rates = [];

                foreach ($rows as $row) {
                    $date = $this->http->FindSingleNode("*[3]", $row);

                    if (empty($date)) {
                        $emptyDate = true;
                    }
                    $rates[] = $this->http->FindSingleNode("*[2]", $row);
                }

                if ($emptyDate === false && !empty($nignt) && $rows->length == $nignt) {
                    $h->booked()
                        ->rooms($this->http->FindSingleNode("*[4]", $rows->item(0)))
                        ->guests($this->http->FindSingleNode("*[5]", $rows->item(0)))
                        ->kids($this->http->FindSingleNode("*[6]", $rows->item(0)), true, true)
                    ;
                    $r = $h->addRoom();

                    if (!empty($roomDescription)) {
                        $r->setDescription($roomDescription);
                    }
                    $r->setRates($rates);
                } elseif ($emptyDate === false) {
                    $h->booked()
                        ->rooms($this->http->FindSingleNode("*[4]", $rows->item(0)))
                        ->guests($this->http->FindSingleNode("*[5]", $rows->item(0)))
                        ->kids($this->http->FindSingleNode("*[6]", $rows->item(0)), true, true)
                    ;

                    if (!empty($roomDescription)) {
                        $r = $h->addRoom();
                        $r->setDescription($roomDescription);
                    }
                } else {
                    $r = $h->addRoom();
                    $this->logger->debug('TODO. no example for this case');
                }
            }

            $total = $this->getTotalCurrency($this->http->FindSingleNode("./following::table[1][{$this->contains($this->t('Confirmation'))}]/descendant::text()[{$this->eq($this->t('Rate Info:'))}]/following::text()[normalize-space(.)!=''][1][not({$this->starts($this->t('Cancellation Policy'))} or {$this->starts($this->t('Room'))})]",
                $root, true, "/Approx total is (.+)/"));

            if ($total['Total'] !== '') {
                $h->price()
                    ->total($total['Total'])
                    ->currency($total['Currency'])
                ;
            }
        }
    }

    private function parseEmailCar(Email $email): void
    {
        $xpath = "//text()[normalize-space(.)='Pick-up @']/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            // General
            $r->general()
                ->confirmation(str_replace(' ', '-', $this->http->FindSingleNode("./following::table[1][contains(.,'Confirmation')]/descendant::text()[{$this->contains($this->t('Confirmation'))}]", $root, true, "#{$this->opt($this->t('Confirmation'))}\s+([A-Z\d]{5,}(?: PEXP)?)\s*$#")))
                ->travellers($this->travellers)
                ->status($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Status:'))}]/following::text()[normalize-space(.)][1][not({$this->starts($this->t('Pick-up @'))})]", $root))
            ;

            // Pick Up
            $info = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Pick-up @'))}]/ancestor::*[contains(., ':')][1]//text()[normalize-space()]"));

            if (preg_match("/Pick-up @\s*(?<location>[\s\S]+)\n(?<date>.*\d{4})\n\s*(?:(?<time>\d+:\d+.+)|\D*)\s*$/", $info, $m)) {
                $r->pickup()
                    ->location(preg_replace('/\s+/', ' ', trim($m['location'])));
                $date = $this->normalizeDate($m['date']);

                if (!empty($date) && !empty($m['time'])) {
                    $date = strtotime($m['time'], $date);
                }
                $r->pickup()
                    ->date($date);
            }

            $r->pickup()
                ->phone($this->http->FindSingleNode("./following::table[1][{$this->contains($this->t('Confirmation'))}]/descendant::text()[{$this->starts($this->t('Pick-up Phone'))}]/following::text()[normalize-space(.)][1][not({$this->xpathFragments['strongText']})]", $root), true, true)
                ->openingHours($this->http->FindSingleNode("./following::table[1][{$this->contains($this->t('Confirmation'))}]/descendant::text()[{$this->starts($this->t('Pick-Up Location Hours'))}]/following::text()[normalize-space(.)][1][not({$this->xpathFragments['strongText']})]", $root), true, true)
            ;

            // Drop Off
            $info = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Drop-off @'))}]/ancestor::*[contains(., ':')][1]//text()[normalize-space()]"));

            if (preg_match("/Drop-off @\s*(?<location>[\s\S]+)\n(?<date>.*\d{4})\n\s*(?:(?<time>\d+:\d+.+)|\D*)\s*$/", $info, $m)) {
                $r->dropoff()
                    ->location(preg_replace('/\s+/', ' ', trim($m['location'])));
                $date = $this->normalizeDate($m['date']);

                if (!empty($date) && !empty($m['time'])) {
                    $date = strtotime($m['time'], $date);
                }
                $r->dropoff()
                    ->date($date);
            }

            // Car
            $r->car()
                ->type($this->http->FindSingleNode("./following::table[1][{$this->contains($this->t('Confirmation'))}]/descendant::text()[{$this->starts($this->t('Car Type'))}]/following::text()[normalize-space(.)][1][not({$this->xpathFragments['strongText']})]", $root));

            // Extra
            $r->extra()
                ->company($this->http->FindSingleNode("./descendant::td[1]/*[1]", $root));

            // Price
            $total = $this->getTotalCurrency($this->http->FindSingleNode("./following::table[1][{$this->contains($this->t('Confirmation'))}]/descendant::text()[{$this->starts($this->t('Rate Info'))}]/following::text()[normalize-space(.)][1][not({$this->xpathFragments['strongText']})]",
                $root, true, '/(?:Approx rental cost is|Approx total|ESTIMATED TOTAL PRICE IS)\s+(.+?)(?:\s*including tax)?\s*$/i'));

            if ($total['Total'] !== '') {
                $r->price()
                    ->total($total['Total'])
                    ->currency($total['Currency'])
                ;
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            '/^\s*[-[:alpha:]]+\s+([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})\s*$/u',
        ];
        $out = [
            '$2 $1 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignProvider($headers): bool
    {
        if ($this->http->XPath->query('//node()[contains(.,"www.andavotravel.com")]')->length > 0
            || $this->http->XPath->query('//a[contains(@href,"//www.andavotravel.com")]')->length > 0) {
            $this->providerCode = 'andavo';

            return true;
        }

        if ($this->http->XPath->query('//a[contains(@href,"@cbtravel.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(.,"@cbtravel.com")]')->length > 0
        ) {
            $this->providerCode = 'christopherson';

            return true;
        }

        // last
        $condition1 = stripos($headers['subject'], 'AirPortal - Airtinerary') !== false;
        $condition2 = $this->http->XPath->query('//node()[contains(normalize-space(.),"AirPortal - Airtinerary")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'airportal';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (!empty($reBody[0]) && !empty($reBody[1])
                && $this->http->XPath->query("//text()[{$this->contains($reBody[0])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($reBody[0])}]")->length > 0
            ) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("/(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)/", $node, $m)
            || preg_match("/(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})/", $node, $m)
            || preg_match("/(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)/", $node, $m)
        ) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
