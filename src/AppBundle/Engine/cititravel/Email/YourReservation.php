<?php

namespace AwardWallet\Engine\cititravel\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "cititravel/it-338601115.eml, cititravel/it-348354298.eml, cititravel/it-354773143.eml, cititravel/it-359208638.eml, cititravel/it-362139330.eml, cititravel/it-365893822.eml, cititravel/it-373750693.eml, cititravel/it-377657571.eml";

    public $date;
    public $lang;
    public static $dictionary = [
        'en' => [
            'Order summary' => 'Order summary',
            'Passenger:'    => ['Passenger:', 'Passengers:'],
        ],
    ];

    private $detectFrom = "no-reply@cititravel.com";
    private $detectSubject = [
        // en
        "You're all set! Your reservation with the Citi Travel℠ center is confirmed",
    ];
    private $detectBody = [
        'en' => [
            'You’re on your way,',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]cititravel\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && stripos($headers["subject"], 'Citi Travel') === false
        ) {
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
            $this->http->XPath->query("//a[{$this->contains(['.travel.citi.com'], '@href')}]")->length === 0
            || $this->http->XPath->query("//*[{$this->contains(['Citibank, N.A. Citi'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->date = strtotime($parser->getDate());

        $this->parseEmailHtml($email);

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
        return count(self::$dictionary);
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Order summary"])
                && $this->http->XPath->query("//*[{$this->contains($dict['Order summary'])}]")->length > 0
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
        $email->obtainTravelAgency();

        $confs = $this->http->FindNodes("//td[{$this->eq($this->t('Citi Travel Flight Booking ID'))}]/following-sibling::td[normalize-space()][1]",
                null, "/^\s*(\d{5,})\s*$/");

        foreach ($confs as $conf) {
            $email->ota()
                ->confirmation($conf);
        }

        $points = trim($this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t('Points redeemed ('))}]",
            null, true, "/\((.+)\)\s*$/"));

        if (!empty($points)) {
            $email->price()
                ->spentAwards($points);
        }

        $pointsValue = $this->getTotal($this->http->FindSingleNode("//tr[*[1][{$this->starts($this->t('Points redeemed ('))}]]/*[normalize-space()][2]",
            null, true, "/^\s*\-\s*(.+)/"));
        $totalStr = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Total'))}]]/*[normalize-space()][2]");
        $total = $this->getTotal($totalStr);

        if ($pointsValue['amount'] !== null && $total['amount'] !== null) {
            $email->price()
                ->currency($total['currency'])
                ->total($total['amount'] === 0.00 ? $total['amount'] : round(($total['amount'] - $pointsValue['amount']), 2));
        } else {
            $email->price()
                ->total(null);
        }

        $taxesStr = $this->http->FindNodes("//tr[*[1][{$this->eq($this->t('Taxes and fees'))}]]/*[normalize-space()][2]",
            null, "/^\s*\+\s*(.+)/");
        $taxAmount = 0.0;

        foreach ($taxesStr as $str) {
            $taxAmount += $this->getTotal($str)['amount'];
        }

        if (!empty($taxAmount)) {
            $email->price()
                ->tax($taxAmount);
        }

//        $tax = $this->getTotal($taxStr);
//        if (!empty($tax['amount'])) {
//            $email->price()
//                ->tax($tax['amount']);
//        }

        $this->parseFlight($email);
        $this->parseHotel($email);
        $this->parseRental($email);

        return true;
    }

    private function parseFlight(Email $email)
    {
        $xpath = "//tr[count(*) = 3 and *[2][not(normalize-space())][.//img[contains(@src, 'Arrow') or contains(@alt, 'arrow to destination')]]]/ancestor::*[position() < 5][descendant::td[not(.//td)][normalize-space()][1][contains(., '#')]][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            return false;
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers(array_unique(preg_replace('/^\s*(.+?)\s*\([A-Z ]+\)$/', '$1', preg_split('/\s*,\s*/',
                implode(', ', $this->http->FindNodes("//tr[not(.//tr)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Passenger:'))}]]", null, "/^\s*{$this->opt($this->t('Passenger:'))}\s*(.+)/"))))))
        ;

        // Segments
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $node = $this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<aln>.+?)\s*#\s*(?<alc>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\s*$/", $node, $m)) {
                $s->airline()
                    ->name($m['alc'])
                    ->number($m['fn'])
                ;
                $conf = null;
                $confNames = $this->http->FindNodes("preceding::td[not(.//td)][.//text()[{$this->eq(preg_replace("/(.+)/", $m['aln'] . ' $1', $this->t('Confirmation Number')))}]][1]//text()[normalize-space()]",
                    $root, "/.*{$this->opt($this->t('Confirmation Number'))}.*/");
                $confNumbers = $this->http->FindNodes("preceding::td[not(.//td)][.//text()[{$this->eq(preg_replace("/(.+)/", $m['aln'] . ' $1', $this->t('Confirmation Number')))}]][1]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]",
                    $root, "/^\s*([A-Z\d]{5,7})\s*$/");

                if (count($confNames) === count($confNumbers)) {
                    foreach ($confNames as $i => $cname) {
                        if (preg_match("/^\s*{$m['aln']}\s*{$this->opt($this->t('Confirmation Number'))}\s*$/", $cname)) {
                            $conf = $confNumbers[$i];
                        }
                    }
                }
                $s->airline()
                    ->confirmation($conf);
            }

            // LHR 11:20 AM
            // Sun, Jun 4, 2023
            // Heathrow Airport
            $re = "/^\s*(?<code>[A-Z]{3})\s*(?<time>\d+:\d+.*)\s*\n(?<date>.*\d{4}.*)\n(?<name>.+)\s*$/";
            $xp = ".//tr[count(*) = 3 and *[2][not(normalize-space())][.//img[contains(@src, 'Arrow') or contains(@alt, 'arrow to destination')]]]/ancestor::*[1]";
            // Departure
            $node = implode("\n", $this->http->FindNodes($xp . "/tr/*[1]", $root));

            if (preg_match($re, $node, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']));
            }

            // Arrival
            $node = implode("\n", $this->http->FindNodes($xp . "/tr/*[last()]", $root));

            if (preg_match($re, $node, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']));
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode('(' . $xp . "/tr/*[2][normalize-space()])[1]/descendant::text()[normalize-space()][1]",
                    $root, null, "/^\s*((?: *\d+ ?[hm])+)\s*$/"))
            ;
        }

        return true;
    }

    private function parseHotel(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Cancellation and change policy'))}]/ancestor::*[preceding::td[normalize-space()][1][{$this->starts($this->t('Hotel Booking ID:'))}]][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            return false;
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // Travel Agency
            $h->ota()
                ->confirmation($this->http->FindSingleNode("preceding::td[normalize-space()][1][{$this->starts($this->t('Hotel Booking ID:'))}]",
                    $root, true, "/{$this->opt($this->t('Hotel Booking ID:'))}\s*(\w{5,})\s*$/"));
            // General
            $h->general()
                ->noConfirmation()
                ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('You’re on your way,'))}]",
                    null, true, "/^\s*{$this->opt($this->t('You’re on your way,'))}\s*(.+?)\s*!\s*$/"))
                ->cancellation($this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('Cancellation and change policy'))}]/following::tr[normalize-space()][1]", $root))
            ;

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode("descendant::text()[normalize-space()][1]/ancestor::h2", $root))
                ->noAddress()
            ;

            // Booked
            $dates = $this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][3]", $root);

            if (preg_match("/^(.+) – (.+)\(\s*\d+\s*{$this->opt($this->t('night'))}/", $dates, $m)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($m[1]))
                    ->checkOut($this->normalizeDate($m[2]))
                ;
            }
            $h->booked()
                ->guests($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][4]",
                    $root, true, "/(\d+) ?{$this->opt($this->t('adult'))}/"))
                ->kids($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][4]",
                    $root, true, "/(\d+) ?{$this->opt($this->t('child'))}/"), true, true)
            ;

            // Rooms
            $type = $this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][2]", $root);

            if (preg_match("/^\s*(\d+)x\s*(.+)/", $type, $m)) {
                for ($i = 1; $i <= $m[1]; $i++) {
                    $h->addRoom()
                        ->setType($m[2]);
                }
            } else {
                $h->addRoom()
                    ->setType(null);
            }
        }

        return true;
    }

    private function parseRental(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Cancellation and change policy'))}]/ancestor::*[preceding::td[normalize-space()][1][{$this->starts($this->t('Car Booking ID:'))}]][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            return false;
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            // Travel Agency
            $r->ota()
                ->confirmation($this->http->FindSingleNode("preceding::td[normalize-space()][1][{$this->starts($this->t('Car Booking ID:'))}]",
                    $root, true, "/{$this->opt($this->t('Car Booking ID:'))}\s*(\w{5,})\s*$/"));

            // General
            $r->general()
                ->noConfirmation()
                ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('You’re on your way,'))}]",
                    null, true, "/^\s*{$this->opt($this->t('You’re on your way,'))}\s*(.+?)\s*!\s*$/"))
                ->cancellation($this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('Cancellation and change policy'))}]/following::tr[normalize-space()][1]"))
            ;

            // Pick Up
            $pickUpXpath = ".//text()[{$this->starts($this->t('Pick-up at'))}]/ancestor::table[1]/descendant::tr[not(.//tr)][normalize-space()]";
            $r->pickup()
                ->location($this->http->FindSingleNode($pickUpXpath . '[2]', $root) . ', ' . $this->http->FindSingleNode($pickUpXpath . '[3]', $root))
                ->date($this->normalizeDate($this->http->FindSingleNode($pickUpXpath . '[1]/*[normalize-space()][2]', $root)
                    . ', ' . $this->http->FindSingleNode($pickUpXpath . '[1]/*[normalize-space()][1]', $root, true, "/^\s*{$this->opt($this->t('Pick-up at'))}\s*(.+)/")))
            ;
            // Drop Off
            $dropOffXpath = ".//text()[{$this->starts($this->t('Drop-off at'))}]/ancestor::table[1]/descendant::tr[not(.//tr)][normalize-space()]";
            $r->dropoff()
                ->location($this->http->FindSingleNode($dropOffXpath . '[2]', $root) . ', ' . $this->http->FindSingleNode($dropOffXpath . '[3]', $root))
                ->date($this->normalizeDate($this->http->FindSingleNode($dropOffXpath . '[1]/*[normalize-space()][2]', $root)
                    . ', ' . $this->http->FindSingleNode($dropOffXpath . '[1]/*[normalize-space()][1]', $root, true, "/^\s*{$this->opt($this->t('Drop-off at'))}\s*(.+)/")))
            ;

            // Car
            $r->car()
                ->model($this->http->FindSingleNode(".//text()[{$this->contains($this->t('or similar'))}]",
                    $root, true, "/^\s*(.+?)\s*{$this->opt($this->t('or similar'))}/"));

            // Extra
            $r->extra()
                ->company($this->http->FindSingleNode(".//text()[{$this->contains($this->t('or similar'))}]/preceding::text()[normalize-space()][1]/ancestor::td[1]",
                    $root));
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

    private function normalizeDate(?string $date): ?int
    {
        $year = date("Y", $this->date);
//        $this->logger->debug('date begin = ' . print_r($date, true));
        $in = [
            //            // Sat, Jun 10, 2023, 06:10 PM
            '/^\s*[[:alpha:]]+\s*,\s*([[:alpha:]]+)\s+(\d{1,2}),\s*(\d{4})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
            // Tue, Apr 25, 02:00 PM
            '/^\s*([[:alpha:]]+)\s*,\s*([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$1, $3 $2 ' . $year . ', $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match("#^(?<week>[[:alpha:]]{2,}), (?<date>\d+ [[:alpha:]]{3,} .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }
//        $this->logger->debug('date end = ' . print_r($date, true));

        return $date;
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
