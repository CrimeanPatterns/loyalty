<?php

namespace AwardWallet\Engine\amtrav\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "amtrav/it-206591113.eml, amtrav/it-208235601.eml, amtrav/it-216848817.eml, amtrav/it-218630148.eml";

    public $date;
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Trip Summary' => 'Trip Summary',
        ],
    ];

    private $detectFrom = 'Support@AmTrav.com';

    private $detectSubject = [
        // en
        'Your Updated Amtrav Itinerary',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.amtrav.com/'], '@href')}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Trip Summary']) && $this->http->XPath->query("//*[{$this->eq($dict['Trip Summary'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
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

    private function parseEmailHtml(Email $email)
    {
        // Travel Agent
        $email->obtainTravelAgency();

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t("BOOKING NUMBER"))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/"));

        $accounts = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("KNOWN TRAVELER #"))}]/following::text()[normalize-space()][1]",
            null, "/^\s*([\dA-Z]{5,})\s*$/"));

        if (!empty($accounts)) {
            $email->ota()
                ->accounts($accounts, false);
        }

        $this->date = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t("BOOKING DATE"))}]/following::text()[normalize-space()][1]"));

        // FLIGHT
        $fXpath = "//text()[{$this->eq($this->t('DEPARTS'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[count(.//text()[{$this->eq($this->t('DEPARTS'))}]) = 1 and count(.//text()[{$this->eq($this->t('ARRIVES'))}]) = 1 ][1]";
//        $this->logger->debug('$fXpath = '.print_r( $fXpath,true));
        $fNodes = $this->http->XPath->query($fXpath);

        if ($fNodes->length > 0) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->noConfirmation();

            // Issued
            $tickets = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("TICKET #"))}]/following::text()[normalize-space()][1]",
                null, "/^\s*(\d{5,})\s*$/"));

            if (!empty($tickets)) {
                $f->issued()
                    ->tickets($tickets, false);
            }

            // Price
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t("SUBTOTAL"))}]/following::text()[normalize-space()][1]");

            if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/u", $total, $m)
                || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*\s*$/u", $total, $m)
            ) {
                $currency = $this->normalizeCurrency($m['currency']);
                $f->price()
                    ->currency($currency)
                    ->total(PriceHelper::parse($m['amount'], $currency))
                ;
            }

            foreach ($fNodes as $root) {
                $s = $f->addSegment();

                $extraXpath = "ancestor::*[following-sibling::*[normalize-space()][1][starts-with(normalize-space(), 'CABIN')]][1]/following-sibling::*[position() < 5]";

                // Airline
                $node = $this->http->FindSingleNode("descendant::td[not(.//td)][1]", $root);

                if (preg_match("/^\s*(?<al>.+?)\s*\#\s*(?<fn>\d{1,5})\s*$/s", $node, $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn'])
                        ->confirmation($this->http->FindSingleNode("ancestor::*[{$this->contains($this->t("CONF #"))}][1]//text()[{$this->eq($this->t("CONF #"))}]/following::text()[normalize-space()][1]",
                            $root, null, "/^\s*([A-Z\d]{5,7})\s*$/"));
                }

                // Departure
                $node = implode("\n", $this->http->FindNodes(".//td[{$this->starts($this->t('DEPARTS'))} and not(.//text()[{$this->eq($this->t('ARRIVES'))}])]//text()[normalize-space()]", $root));

                if (preg_match("/^\s*\w+\s*\n\s*(?<time>\d{1,2}:\d{2}(?:\s*[apAP][mM])?)\s*\n\s*(?<date>.+)\s*\n\s*(?<code>[A-Z]{3})\s*$/s", $node, $m)) {
                    $s->departure()
                        ->code($m['code'])
                        ->date($this->normalizeDate($m['date'] . ', ' . $m['time']));
                }

                // Arrival
                $node = implode("\n", $this->http->FindNodes(".//td[{$this->starts($this->t('ARRIVES'))} and not(.//text()[{$this->eq($this->t('DEPARTS'))}])]//text()[normalize-space()]", $root));

                if (preg_match("/^\s*\w+\s*\n\s*(?<time>\d{1,2}:\d{2}(?:\s*[apAP][mM])?)\s*\n\s*(?<date>.+)\s*\n\s*(?<code>[A-Z]{3})\s*$/s", $node, $m)) {
                    $s->arrival()
                        ->code($m['code'])
                        ->date($this->normalizeDate($m['date'] . ', ' . $m['time']));
                }

                // Extra
                $s->extra()
                    ->cabin(preg_replace("/.*\(\s*(.+?)\s*\)\s*$/", '$1', $this->http->FindSingleNode($extraXpath . "//text()[{$this->eq($this->t("CABIN"))}]/ancestor::td[1]",
                        $root, null, "/^\s*{$this->opt($this->t("CABIN"))}\s*(.+)\s*$/")))
                    ->miles($this->http->FindSingleNode($extraXpath . "//text()[{$this->eq($this->t("MILES"))}]/ancestor::td[1]",
                        $root, null, "/^\s*{$this->opt($this->t("MILES"))}\s*(.+)\s*$/"))
                    ->duration($this->http->FindSingleNode($extraXpath . "//text()[{$this->eq($this->t("DURATION"))}]/ancestor::td[1]",
                        $root, null, "/^\s*{$this->opt($this->t("DURATION"))}\s*(.+)\s*$/"))
                    ->aircraft($this->http->FindSingleNode($extraXpath . "//text()[{$this->eq($this->t("AIRCRAFT"))}]/ancestor::td[1]",
                        $root, null, "/^\s*{$this->opt($this->t("AIRCRAFT"))}\s*(.+)\s*$/"));

                if (!empty($this->http->FindSingleNode($extraXpath . "//text()[{$this->eq($this->t("STOPS"))}]/ancestor::td[1]",
                        $root, null, "/^\s*{$this->opt($this->t("STOPS"))}\s*Non[\- ]?stop\s*$/i"))) {
                    $s->extra()
                        ->stops(0);
                }

                $seatsText = $this->http->FindSingleNode($extraXpath . "//text()[{$this->eq($this->t("SEAT(S)"))}]/ancestor::td[1]",
                        $root, null, "/^\s*{$this->opt($this->t("SEAT(S)"))}\s*(.*)\s*$/");
                $seatsText = preg_replace("/\s*\([^)]+\)\s*/", '', $seatsText);

                if (preg_match("/^[\sA-Z\d]+$/", $seatsText)) {
                    $seats = array_filter(explode(', ', $seatsText));

                    if (!empty($seats)) {
                        $s->extra()
                            ->seats($seats);
                    }
                }
            }
        }

        // RENTAL
        $rXpath = "//text()[{$this->eq($this->t('PICK UP'))}]/ancestor::tr[count(.//text()[{$this->eq($this->t('PICK UP'))}]) = 1 and count(.//text()[{$this->eq($this->t('DROP OFF'))}]) = 1 ][1]/ancestor::*[.//text()[{$this->eq($this->t('RATE'))}]][1]";
//        $this->logger->debug('$rXpath = '.print_r( $rXpath,true));
        $rNodes = $this->http->XPath->query($rXpath);

        foreach ($rNodes as $root) {
            $r = $email->add()->rental();

            // General
            $r->general()
                ->confirmation($this->http->FindSingleNode(".//text()[{$this->eq($this->t("CONF #"))}]/following::text()[normalize-space()][1]",
                    $root, null, "/^\s*([A-Z\d]{5,})\s*$/"));

            // Pick Up
            $node = implode("\n", $this->http->FindNodes(".//tr/td[1][{$this->starts($this->t('PICK UP'))} and not(.//text()[{$this->eq($this->t('DROP OFF'))}])]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*.+\s*\n\s*(?<time>\d{1,2}:\d{2}(?:\s*[apAP][mM])?)\s*\n\s*(?<date>.+)\s*\n\s*(?<name>[\s\S]+)\s*$/", $node, $m)) {
                $r->pickup()
                    ->location(preg_replace('/\s+/', ' ', $m['name']))
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']));
            }

            // Drop Off
            $node = implode("\n", $this->http->FindNodes(".//tr/td[2][{$this->starts($this->t('DROP OFF'))} and not(.//text()[{$this->eq($this->t('PICK UP'))}])]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*.+\s*\n\s*(?<time>\d{1,2}:\d{2}(?:\s*[apAP][mM])?)\s*\n\s*(?<date>.+)\s*\n\s*(?<name>[\s\S]+)\s*$/", $node, $m)) {
                $r->dropoff()
                    ->location(preg_replace('/\s+/', ' ', $m['name']))
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']));
            }

            // Car
            $node = implode("\n", $this->http->FindNodes(".//text()[{$this->eq($this->t('CAR TYPE'))}]/ancestor::td[1]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*.+\s*\n\s*(?<type>.+)\s*\n\s*(?<model>.+?) *\(.+\)\s*$/", $node, $m)) {
                $r->car()
                    ->model($m['model'])
                    ->type($m['type']);
            } elseif (preg_match("/^\s*.+\s*\n\s*(?<type>.+)\s*$/", $node, $m)) {
                $r->car()
                    ->type($m['type']);
            }

            // Price
            $total = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("RATE"))}]/following::text()[normalize-space()][position() < 6][{$this->contains($this->t("TOTAL"))}]",
                $root, true, "/(.+?)\s*{$this->opt($this->t("TOTAL"))}/");

            if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/u", $total, $m)
                || preg_match("/^\s*[^\d\s]{1,3}\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$/u", $total, $m)
                || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*\s*$/u", $total, $m)
            ) {
                $currency = $this->normalizeCurrency($m['currency']);
                $r->price()
                    ->currency($currency)
                    ->total(PriceHelper::parse($m['amount'], $currency))
                ;
            }
        }

        // HOTEL
        $hXpath = "//text()[{$this->eq($this->t('ADDRESS'))}]/ancestor::tr[count(.//text()[{$this->eq($this->t('ADDRESS'))}]) = 1 and count(.//text()[{$this->eq($this->t('PHONE'))}]) = 1 ][1]/ancestor::*[.//text()[{$this->eq($this->t('RATE'))}]][1]";
//        $this->logger->debug('$hXpath = '.print_r( $hXpath,true));
        $hNodes = $this->http->XPath->query($hXpath);

        foreach ($hNodes as $root) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->confirmation($this->http->FindSingleNode(".//text()[{$this->eq($this->t("CONF #"))}]/following::text()[normalize-space()][1]",
                    $root, null, "/^\s*([A-Z\d]{5,})\s*$/"))
                ->cancellation($this->http->FindSingleNode(".//text()[{$this->eq($this->t('CANCEL POLICY'))}]/ancestor::td[1]", $root, true,
                    "/\s*{$this->opt($this->t("CANCEL POLICY"))}\s*(.+)/"), true, true)
            ;

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode("descendant::text()[normalize-space()][1][following::text()[normalize-space()][1][{$this->eq($this->t('CHECK-IN'))}]]", $root))
                ->address(implode(", ", $this->http->FindNodes(".//text()[{$this->eq($this->t('ADDRESS'))}]/ancestor::td[1]//text()[normalize-space()][not({$this->eq($this->t('ADDRESS'))})]", $root)))
                ->phone($this->http->FindSingleNode(".//text()[{$this->eq($this->t('PHONE'))}]/ancestor::td[1]", $root, true,
                    "/\s*{$this->opt($this->t("PHONE"))}\s*(.+)/"))
            ;

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('CHECK-IN'))}]/following::text()[normalize-space()][1]", $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('CHECK-OUT'))}]/following::text()[normalize-space()][1]", $root)))
                ->guests($this->http->FindSingleNode(".//text()[{$this->eq($this->t('OCCUPANTS'))}]/ancestor::td[1]//text()[{$this->contains($this->t('Adult'))}]", $root,
                    true, "/^\s*(\d+)\s*{$this->opt($this->t('Adult'))}/"))
            ;

            // Rooms
            $h->addRoom()
                ->setType($this->http->FindSingleNode(".//text()[{$this->eq($this->t('ROOM TYPE'))}]/ancestor::td[1]", $root, true,
                    "/\s*{$this->opt($this->t("ROOM TYPE"))}\s*(.+)/"))
                ->setRate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('RATE'))}]/ancestor::td[1]", $root, true,
                    "/\s*{$this->opt($this->t("RATE"))}\s*(.+?)\s*\\/\s*NIGHT/"))
            ;

            // Price
            $total = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("RATE"))}]/following::text()[normalize-space()][position() < 6][{$this->contains($this->t("ESTIMATED TOTAL FOR STAY"))}]",
                $root, true, "/(.+?)\s*{$this->opt($this->t("ESTIMATED TOTAL FOR STAY"))}/");

            if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/u", $total, $m)
                || preg_match("/^\s*[^\d\s]{1,3}\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$/u", $total, $m)
                || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*\s*$/u", $total, $m)
            ) {
                $currency = $this->normalizeCurrency($m['currency']);
                $h->price()
                    ->currency($currency)
                    ->total(PriceHelper::parse($m['amount'], $currency))
                ;
            }
        }

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t("Traveler Names"))}]/following::text()[normalize-space()][1]/ancestor::table[1]//tr[not(.//tr)]/td[2]/descendant::text()[normalize-space()][1]");

        foreach ($email->getItineraries() as $it) {
            $it->general()
                ->travellers($travellers, true)
                ->date($this->date)
            ;
        }

        return true;
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
//        $this->logger->debug('date begin = ' . print_r( $date, true));

        if (empty($date) || empty($this->date)) {
            return null;
        }
        $date = preg_replace("/\s+/", ' ', $date);

        $year = date('Y', $this->date);

        $in = [
            // Thu, Oct 20, 9:00 pm
            '/^\s*([[:alpha:]]{2,})\s*,\s*([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1, $3 $2 ' . $year . ', $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([[:alpha:]]{3,})\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>[[:alpha:]]{2,}), (?<date>\d+ [[:alpha:]]{3,} .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

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

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'USD' => ['$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
