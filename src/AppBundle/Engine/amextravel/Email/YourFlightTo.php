<?php

namespace AwardWallet\Engine\amextravel\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourFlightTo extends \TAccountChecker
{
    public $mailFiles = "amextravel/it-31122666.eml, amextravel/it-31783251.eml, amextravel/it-32139413.eml, amextravel/it-48740183.eml, amextravel/it-49335176.eml, amextravel/it-49363347.eml, amextravel/it-647332665.eml";

    public static $dictionary = [
        'en' => [
            'Stop'                                              => ["Stop", "STOP", "Stops", "STOPS"],
            'I just booked a trip with American Express Travel' => ["I just booked a trip with American Express Travel", "'s American Express Travel Itinerary."],
        ],
    ];

    private $detectsFrom = "@amextravel.com";

    private $detectSubject = [
        "en" => "/Your Flight to .+? Trip ID: \d{4}\-\d{4}$/", //Your Flight to Cleveland. Trip ID: 3058-2578
    ];

    private $detectCompany = [
        "AMEX TRAVEL",
        "American Express Travel Consultant",
        "section of AmexTravel.com",
    ];

    private $detectBody = [
        "en" => ["Enjoy your trip to", "AMEX TRAVEL TRIP ID"],
    ];

    private $date;
    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response['body']);

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $date = $this->http->FindSingleNode("(//a[" . $this->eq($this->t('Add to Calendar')) . "]/@href)[1]", null, true, "#&start=(\d{4}-\d{2}-\d{2})T\d{2}#");

        if (preg_match("#^\s*(\d{4})-(\d{2})-(\d{2})\s*$#", $date, $m)) {
            $this->date = strtotime($m[3] . '.' . $m[2] . '.' . $m[1]);
        }

        if (empty($this->date)) {
            $this->date = strtotime($parser->getDate());
        }

        $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('AMEX TRAVEL TRIP ID:')) . "]/following::text()[normalize-space()][1]", null,
            '#^\s*([A-Z\d\-]{5,})\s*$/');

        if (empty($conf) && preg_match("/Trip ID: (\d{4}\-\d{4})$/", $parser->getSubject(), $m)) {
            $conf = $m[1];
        }

        if (!empty($conf)) {
            $email->ota()
                ->confirmation($conf, 'Trip ID');
        } else {
            $email->obtainTravelAgency();
        }

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($this->http->Response['body']);
        $foundCompany = false;

        foreach ($this->detectCompany as $dCompany) {
            if (stripos($body, $dCompany) !== false) {
                $foundCompany = true;

                break;
            }
        }

        if ($foundCompany == false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (stripos($headers["from"], $this->detectsFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (preg_match($dSubject, $headers["subject"])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectsFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $confs = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t('RECORD LOCATOR')) . "]/following::text()[normalize-space()][1]", null, "#^\s*([A-Z\d]{5,})\s*$#")));

        if (empty($confs)) {
            $confs = array_values(array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t('RECORD LOCATOR')) . "]/following::text()[normalize-space()][1]"))));

            if (count($confs) === 1 && in_array($confs[0], (array) $this->t('Unassigned'))) {
                $f->general()->noConfirmation();
            }
        } else {
            foreach ($confs as $conf) {
                $f->general()
                    ->confirmation($conf);
            }
        }
        $node = $this->http->FindSingleNode("//text()[({$this->contains($this->t('Your reservation is'))})]/ancestor::*[({$this->starts('Thanks')})][1]");

        if (empty($node)) {
            $node = $this->http->FindSingleNode("//text()[({$this->starts('Thanks')})]/ancestor::*[({$this->contains($this->t('Your reservation is'))})][1]");
        }

        if (empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('I just booked a trip with American Express Travel'))}]"))
            && !empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reservation is'))}]"))) {
            $f->general()->status($this->http->FindPreg("/Your reservation is\s+(.+?)(?:\.|$)/", false, $node));
        }

        if (preg_match("/cancell?ed/i", $f->getStatus())) {
            $f->general()->cancelled();
        }

        if ($this->http->XPath->query("//text()[{$this->contains('Ticket Number')}][not(ancestor::td[1]/preceding-sibling::*[{$this->starts(['Loyalty Program', 'LOYALTY PROGRAM'])}])]")->length > 0) {
            $pax = array_filter($this->http->FindNodes("//text()[{$this->eq('Ticket Number')}]/preceding::text()[normalize-space()][1]", null, "#^\s*([[:alpha:] \-]{5,})\s*$#"));
        } else {
            $pax = array_filter($this->http->FindNodes("//text()[" . $this->eq(['Loyalty Program', 'LOYALTY PROGRAM']) . "]/preceding::text()[normalize-space()][not(contains(normalize-space(), 'Unassigned') or contains(normalize-space(), 'Ticket Number') or contains(normalize-space(), 'TICKET NUMBER'))][1]", null, "#^\s*([[:alpha:] \-]{5,})\s*$#"));
        }
        $tickets = $this->http->FindNodes("//text()[{$this->contains(['Ticket Number', 'Ticket Number'])}]/following::text()[normalize-space()][1]", null, "/^(\d+)$/");

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_unique(array_filter($tickets)), false);
        }

        if (!empty($pax)) {
            $f->general()->travellers($pax, true);
        } else {
            if (preg_match("/{$this->t('Thanks')}\s+(.+?)\!/", $node, $m)) {
                $f->general()->traveller($m[1], false);
            }
        }

        // Account
        $accounts = array_filter($this->http->FindNodes("//text()[" . $this->eq(['Loyalty Program', 'LOYALTY PROGRAM']) . "]/following::text()[normalize-space()][1]", null, "#^\s*.+ ([A-Z\d]{5,})\s*$#"));

        if (!empty($accounts)) {
            $f->program()
                ->accounts($accounts, false);
        }

        // Price

        $points = $this->http->FindSingleNode("//text()[" . $this->eq('Points Used') . "]/ancestor::*[self::td or self::th][1]/following::*[self::td or self::th][normalize-space()][1]");

        if (!empty($points)) {
            $f->price()
                ->spentAwards($points . ' Points');
            $totalCharge = $this->http->FindSingleNode("//text()[" . $this->eq(['Cost Information', 'COST INFORMATION']) . "]/following::text()[normalize-space()='Dollars Used']/ancestor::*[self::td or self::th][1]/following::*[self::td or self::th][normalize-space()][1]");

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $totalCharge, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $totalCharge, $m)) {
                $f->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']));
            }
        } else {
            $totalCharge = $this->http->FindSingleNode("//text()[" . $this->eq(['Cost Information', 'COST INFORMATION']) . "]/following::text()[normalize-space()='Total']/ancestor::*[self::td or self::th][1]/following::*[self::td or self::th][normalize-space()][1]");

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $totalCharge, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $totalCharge, $m)) {
                $f->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']));
            }
        }
        $totalCharge = $this->http->FindSingleNode("//text()[" . $this->eq(['Cost Information', 'COST INFORMATION']) . "]/following::text()[starts-with(normalize-space(),'Taxes & Fees')]/ancestor::*[self::td or self::th][1]/following::*[self::td or self::th][normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $totalCharge, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $totalCharge, $m)) {
            $f->price()->tax($this->amount($m['amount']));

            if (!$f->getPrice()->getCurrencyCode()) {
                $f->price()->currency($this->currency($m['curr']));
            }
        }

        // Segments
        $xpath = "//text()[starts-with(translate(normalize-space(),'0123456789', 'dddddddddd'), 'd:dd') or starts-with(translate(normalize-space(),'0123456789', 'dddddddddd'), 'dd:dd')]/ancestor::tr[2][count(*)>=3]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
//            if ($this->http->FindSingleNode("following::text()[normalize-space()][1][".$this->eq($this->t("Stops"))." or ".$this->eq($this->t("STOPS"))."]", $root)) {

            if ($this->http->XPath->query("./descendant::*[{$this->contains('Operated by')}]", $root)->length === 0
                || $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Stop'))}]", $root)) {
                $dateSegment = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root));

                if ($this->http->XPath->query("./descendant::*[{$this->contains('Non-Stop')}]", $root)->length === 0) {
                    continue;
                }
            }

            $s = $f->addSegment();

            $td1 = implode("\n", $this->http->FindNodes("*[normalize-space()][1]//text()[normalize-space()]", $root));
            unset($date);

            if (preg_match("#^\s*(?:(?:.+\n)?(?<date>.+\n))?(?<al>.+)\s+(?<fn>\d{1,5})(?:\n\s*Operated by\s+(?<oper>.+))?\s*$#", $td1, $m)) {
                // Airline
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                if (!empty($m['oper']) && $m['oper'] !== $m['al']) {
                    $s->airline()->operator($m['oper']);
                }

                if (!empty(trim($m['date']))) {
                    $date = $this->normalizeDate(trim($m['date']));
                    unset($dateSegment);
                }
            }

            $td2 = implode("\n", $this->http->FindNodes("*[normalize-space()][2]//text()[normalize-space()]", $root));

            if (preg_match("#^\s*(?<dTime>\d+:\d+([ ]?[ap]m)?)\s+-\s+(?<aTime>\d+:\d+([ ]?[ap]m)?)(?:\s+(?<nextday>[\-+]\d) \w+)?\s*"
                    . "\n\s*(?<dName>.+?), (?<dCode>[A-Z]{3})\s+-\s+(?<aName>.+?), (?<aCode>[A-Z]{3})\s*$#", $td2, $m)) {
                // Departure
                $s->departure()
                    ->name($m['dName'])
                    ->code($m['dCode'])
                ;

                if (!empty($date)) {
                    $s->departure()
                        ->date(strtotime($m['dTime'], $date));
                } elseif (!empty($dateSegment)) {
                    $s->departure()
                        ->date(strtotime($m['dTime'], $dateSegment));
                }

                // Arrival
                $s->arrival()
                    ->name($m['aName'])
                    ->code($m['aCode'])
                ;

                if (!empty($date)) {
                    $s->arrival()
                        ->date(strtotime($m['aTime'], $date));
                } elseif (!empty($dateSegment)) {
                    $s->arrival()
                        ->date(strtotime($m['aTime'], $dateSegment));
                }

                if (!empty($m['nextday']) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($m['nextday'] . ' day', $s->getArrDate()));
                }
            }

            $td3 = implode("\n", $this->http->FindNodes("*[normalize-space()][3]//text()[normalize-space()]", $root));

            if (preg_match("#^\s*(?<duration>\d+[^\n\|]+)\s+\|\s+(?<cabin>.+)(?:\s+Seats?:\s*(?<seats>[A-Z\d, ]+))?#", $td3, $m)) {
                // Extra
                $s->extra()
                    ->duration($m['duration'])
                    ->cabin($m['cabin'])
                ;

                if (!empty($m['seats'])) {
                    $seats = array_filter(array_map(function ($v) {
                        if (preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $v)) {
                            return trim($v);
                        }

                        return null;
                    }, explode(",", $m['seats'])));

                    if (!empty($seats)) {
                        $s->extra()
                            ->seats($seats);
                    }
                }
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->http->log('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            //Mon, May 20
            '#^(\w+),\s*(\w+)\s+(\d+)\s*$#u',
        ];
        $out = [
            '$1, $3 $2 ' . $year,
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
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
        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $s;
        }
        $sym = [
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
