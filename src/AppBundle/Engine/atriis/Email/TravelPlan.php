<?php

namespace AwardWallet\Engine\atriis\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TravelPlan extends \TAccountChecker
{
    public $mailFiles = "atriis/it-417023143.eml, atriis/it-417430395.eml, atriis/it-421911876.eml, atriis/it-422710891.eml, atriis/it-424896632.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Consultant name:'   => 'Consultant name:',
            'Traveller(s) name:' => 'Traveller(s) name:',
            'Trip Number:'       => 'Trip Number:',
            //            'Confirmation' => 'Confirmation',
            //            'Confirmation' => 'Confirmation',
            //            'Confirmation' => 'Confirmation',
        ],
    ];

    private $detectFrom = "notification@gtp-marketplace.com";
    private $detectSubject = [
        // en
        'Travel plan For',
    ];

    private static $detectProvider = [
        'travexp' => [
            '@travel-experts.be',
            'Travel Experts-',
        ],
    ];

    // Main Detects Methods

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]gtp-marketplace\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.gtp-marketplace.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['@gtp-marketplace.com'])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        foreach (self::$detectProvider as $code => $prDetect) {
            if ($this->http->XPath->query("//node()[{$this->contains($prDetect)}]")->length > 0) {
                $email->setProviderCode($code);

                break;
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
        return count(self::$dictionary);
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Consultant name:"]) && !empty($dict["Traveller(s) name:"]) && !empty($dict["Trip Number:"])
                && $this->http->XPath->query("//*[{$this->contains($dict['Consultant name:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Traveller(s) name:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Trip Number:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip Number:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/"));

        $this->parseFlight($email);
        $this->parseHotel($email);

        return true;
    }

    private function parseFlight(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Duration:'))}]/ancestor::*[{$this->contains($this->t('Terminal'))}][{$this->contains($this->t('Passenger Name'))}][not(.//text()[{$this->eq($this->t('Flight'))}])][last()]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            if ($this->http->XPath->query("//text()[{$this->eq($this->t('Flight'))}]")->length === 0) {
                return true;
            } else {
                $email->add()->flight();

                return false;
            }
        }

        $f = $email->add()->flight();

        foreach ($nodes as $root) {
            // General
            $conf = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Agency Booking Reference:'))}]",
                $root, true, "/: *([A-Z\d]+)\s*$/");

            if (!in_array($conf, array_column($f->getConfirmationNumbers(), 0))) {
                $f->general()
                    ->confirmation($conf);
            }

            $passengerXpath = ".//tr[*[1][{$this->eq($this->t('Passenger Name'))}]][*[3][{$this->eq($this->t('E-Ticket Number'))}]]/following-sibling::tr[normalize-space()]";
            $travellers = $this->http->FindNodes($passengerXpath . '/*[1]', $root, "/^\s*(.+?)\s*\([^()]+\)$/");

            foreach ($travellers as $traveller) {
                if (!in_array($traveller, array_column($f->getTravellers(), 0))) {
                    $f->general()
                        ->traveller($traveller, true);
                }
            }

            // Program
            $accounts = array_filter($this->http->FindNodes($passengerXpath . '/*[2]'));

            foreach ($accounts as $account) {
                if (!in_array($account, array_column($f->getAccountNumbers(), 0))) {
                    $f->program()
                        ->account($account, false);
                }
            }

            // Issued
            $tickets = array_filter($this->http->FindNodes($passengerXpath . '/*[3]'));

            foreach ($tickets as $ticket) {
                if (preg_match("/^[\d\W]+$/", $ticket)
                    && !in_array($ticket, array_column($f->getTicketNumbers(), 0))) {
                    $f->issued()
                        ->ticket($ticket, false);
                }
            }

            // Segments
            $s = $f->addSegment();
            $routeXpath = ".//tr[count(*[string-length(normalize-space()) > 3]) = 3][*[normalize-space()][2][{$this->contains($this->t('Terminal'))}]][*[string-length(normalize-space()) > 3][3][{$this->contains($this->t('Terminal'))}]]";

            // Airline
            $node = implode("\n", $this->http->FindNodes($routeXpath . '/*[string-length(normalize-space()) > 3][1]/descendant::text()[normalize-space()]', $root));

            if (preg_match("/\b(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\W?(?<fn>\d{1,5})(?:\n|$)/u", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            if (preg_match("/{$this->opt($this->t('Operated by'))} .+ (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\s*$/s", $node, $m)
                && $m['al'] !== $s->getAirlineName()) {
                $s->airline()
                    ->carrierName($m['al'])
                    ->carrierNumber($m['fn']);
            } elseif (preg_match("/{$this->opt($this->t('Operated by'))} (.+?)\s*$/s", $node, $m)) {
                $s->airline()
                    ->operator($m[1]);
            }

            // Departure
            $node = implode("\n", $this->http->FindNodes($routeXpath . '/*[string-length(normalize-space()) > 3][2]/descendant::text()[normalize-space()]', $root));

            if (preg_match("/^\s*(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\n(?<date>.+)\n\s*Terminal\s*(?:N\\/A|(?<terminal>.+))\s*$/s", $node ?? '', $m)) {
                $s->departure()
                    ->name(trim($m['name'], ','))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']))
                    ->terminal($m['terminal'] ?? null, true, true)
                ;
            }

            // Arrival
            $node = implode("\n", $this->http->FindNodes($routeXpath . '/*[string-length(normalize-space()) > 3][3]/descendant::text()[normalize-space()]', $root));

            if (preg_match("/^\s*(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\n(?<date>.+)\n\s*Terminal\s*(?:N\\/A|(?<terminal>.+))\s*$/s", $node ?? '', $m)) {
                $s->arrival()
                    ->name(trim($m['name'], ','))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']))
                    ->terminal($m['terminal'] ?? null, true, true)
                ;
            }

            $infoXpath = ".//tr[*[normalize-space()][1][{$this->starts($this->t('Duration:'))}]][*[normalize-space()][3][{$this->starts($this->t('CO2:'))}]]";

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode($infoXpath . "/*[normalize-space()][1]", $root, true, "/:\s*(.+?)\s*$/"))
                ->miles($this->http->FindSingleNode($infoXpath . "/*[normalize-space()][2]", $root, true, "/:\s*(.+?)\s*$/"))
                ->aircraft($this->http->FindSingleNode($infoXpath . "/*[normalize-space()][4]", $root, true, "/:\s*(.+?)\s*$/"), true, true)
                ->cabin($this->http->FindSingleNode($infoXpath . "/*[normalize-space()][5]", $root, true, "/:\s*(.+?)\s*\(\s*[A-Z]{1,2}\s*\)\s*$/"))
                ->bookingCode($this->http->FindSingleNode($infoXpath . "/*[normalize-space()][5]", $root, true, "/:\s*.+?\s*\(\s*([A-Z]{1,2})\s*\)\s*$/"))
                ->status($this->http->FindSingleNode($infoXpath . "/*[normalize-space()][7]", $root, true, "/:\s*(.+?)\s*$/"))
            ;

            $s->airline()
                ->confirmation($this->http->FindSingleNode($infoXpath . "/*[normalize-space()][6]", $root, true, "/:\s*(.+?)\s*$/"));

            $meals = array_filter($this->http->FindNodes($passengerXpath . '/*[4]', $root));

            if (!empty($meals)) {
                $s->extra()
                    ->meals($meals);
            }

            $seats = array_filter($this->http->FindNodes($passengerXpath . '/*[5]', $root));
            $seats = array_filter(array_map(function ($v) {
                if (preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $v, $m)) {
                    return $m[1];
                }

                return null;
            }, $seats));

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }
        }

        return true;
    }

    private function parseHotel(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('Total nights'))}]/ancestor::*[{$this->contains($this->t('Guests'))}][not(.//text()[{$this->eq($this->t('Hotel'))}])][last()]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            if ($this->http->XPath->query("//text()[{$this->eq($this->t('Hotel'))}]")->length === 0) {
                return true;
            } else {
                $email->add()->hotel();

                return false;
            }
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $confs[] = [$this->http->FindSingleNode(".//text()[{$this->starts($this->t('Confirmation Number:'))}]", $root, true, "/: *([A-Z\d]+)\s*$/"),
                $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Confirmation Number:'))}]", $root, true, "/(.+): *[A-Z\d]+\s*$/"), ];
            $confs[] = [$this->http->FindSingleNode(".//text()[{$this->starts($this->t('Supplier reference:'))}]", $root, true, "/: *([A-Z\d]+)\s*$/"),
                $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Supplier reference:'))}]", $root, true, "/(.+): *[A-Z\d]+\s*$/"), ];

            foreach ($confs as $conf) {
                $h->general()
                    ->confirmation($conf[0], $conf[1]);
            }

            $h->general()
                ->travellers($this->http->FindNodes(".//tr[*[1][{$this->eq($this->t('Guests'))}]]/following-sibling::tr[normalize-space()]/*[1]", $root), true)
                ->cancellation($this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancellation Policy'))}]/following-sibling::tr[1]"), true, true)
            ;

            // Program
            $accounts = array_filter($this->http->FindNodes(".//tr[*[1][{$this->eq($this->t('Guests'))}]]/following-sibling::tr[normalize-space()]/*[2]", $root));

            foreach ($accounts as $account) {
                if (!in_array($account, array_column($h->getAccountNumbers(), 0))) {
                    $h->program()
                        ->account($account, false);
                }
            }

            unset($room);

            // Hotel
            $hotelInfo = implode("\n", $this->http->FindNodes('.//tr[not(.//tr)]/*[1][normalize-space()]', $root));
            $re = "/^(?<name>.+?)\s*\(.*\d{4}.*-.*\d{4}.*\)\n\s*\\1\n(?<address>.+)\n{$this->opt($this->t('Tel:'))} (?<phone>.+)\n(?<type>.+)/i";

            if (preg_match($re, $hotelInfo, $m)) {
                $h->hotel()
                    ->name($m['name'])
                    ->address($m['address'])
                    ->phone($m['phone']);

                $room = $h->addRoom()
                    ->setType($m['type']);
            }

            $infoXpath = ".//tr[*[normalize-space()][1][{$this->eq($this->t('Check-in'))}]][*[normalize-space()][3][{$this->eq($this->t('Total nights'))}]]/following-sibling::*[1]";

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode($infoXpath . "/*[normalize-space()][1]", $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode($infoXpath . "/*[normalize-space()][2]", $root)))
                ->rooms($this->http->FindSingleNode($infoXpath . "/*[normalize-space()][4]", $root))
            ;

            if (isset($room)) {
                $room->setRate($this->http->FindSingleNode($infoXpath . "/*[normalize-space()][5]", $root));
            }

            $priceInfo = $this->http->FindSingleNode($infoXpath . "/*[normalize-space()][last()]", $root);
            $h->price()
                ->total($this->getTotal($priceInfo)['amount'])
                ->currency($this->getTotal($priceInfo)['currency'])
            ;
        }

        return true;
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'  => 'EUR',
            '¢æ' => 'EUR',
            '$'  => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date)
    {
        // $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            //            // Thu,23­Nov­2023 10:55
            '/^\s*[[:alpha:]\-]+,\s*(\d{1,2})\W?([[:alpha:]]+)\W?(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
