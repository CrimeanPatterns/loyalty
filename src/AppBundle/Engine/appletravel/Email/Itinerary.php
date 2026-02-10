<?php

namespace AwardWallet\Engine\appletravel\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "appletravel/it-540935683.eml, appletravel/it-541853192.eml, appletravel/it-543888300.eml, appletravel/it-547084767.eml, appletravel/it-547096257.eml, appletravel/it-551018895.eml, appletravel/it-551349053.eml, appletravel/it-551366220.eml, appletravel/it-556881876.eml, appletravel/it-557384792.eml";

    public $date;
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Trip Overview'        => 'Trip Overview',
            'Travel Itinerary For' => 'Travel Itinerary For',
            'Travel Begins'        => 'Travel Begins',
            'DEPARTING FROM'       => ['DEPARTING FROM', 'Departing From'],
            'ARRIVING AT'          => ['ARRIVING AT', 'Arriving At'],
            'PICK-UP AT'           => ['PICK-UP AT', 'Pick-up at', 'Pick-up At'],
            'DROP-OFF AT'          => ['DROP-OFF AT', 'Drop-off at', 'Drop-off At'],
            'Rate per Night'       => ['Rate per Night', 'Average rate per night'],
        ],
    ];

    private $detectFrom = ["travel.meetings@apple.com", "travel@apple.com"];
    private $detectSubject = [
        // en
        // ITINERARY: JONATHON A WARTHMAN, 22OCT (Los Angeles) CJRAVX
        'ITINERARY: ',
    ];

    private $detectRentalProviders = [
        // TODO: set key => values
        //        'alamo' => [
        //            'ALAMO',
        //        ],
        //        'autoeuro' => [
        //            'AUTO EUROPE',
        //        ],
        'avis' => [
            'Avis',
        ],
        //        'europcar' => [
        //            'EUROPCAR',
        //        ],
        //        'hertz' => [
        //            'HERTZ',
        //        ],
        //        'perfectdrive' => [
        //            'BUDGET',
        //        ],
        //        'thrifty' => [
        //            'THRIFTY',
        //        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/.*travel.*[@.]apple\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['travelweb.apple.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Contact Apple Travel'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->eq(['Apple Travel Reference'])}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Trip Overview']) && $this->http->XPath->query("//*[{$this->contains($dict['Trip Overview'])}]")->length > 0) {
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
            if (isset($dict["Travel Itinerary For"], $dict["Travel Begins"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Travel Itinerary For'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Travel Begins'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->nextTd($this->t('Apple Travel Reference')));

        $this->parseFlight($email);
        $this->parseHotel($email);
        $this->parseRental($email);

        $traveller = $this->nextTd($this->t('Travel Itinerary For'));

        foreach ($email->getItineraries() as $it) {
            $it->general()
                ->traveller($traveller, true);
        }

        return true;
    }

    private function parseFlight(Email $email)
    {
        $xpath = "//*[*[1][{$this->starts($this->t('DEPARTING FROM'))}] and *[2][{$this->starts($this->t('ARRIVING AT'))}]]/ancestor::*[{$this->starts($this->t('Flight'))}][1]";
        // $this->logger->debug('Flight XPath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $f = $email->add()->flight();

            $f->general()
                ->noConfirmation();

            $tickets = $this->nextTd($this->t('Ticket information'));

            if (preg_match_all("/-(\d{13})/", $tickets, $m)) {
                $f->issued()
                    ->tickets($m[1], false);
            }

            $total = $this->getTotal($this->nextTd($this->t('Ticket Cost')));
            $f->price()
                ->total($total['amount'])
                ->currency($total['currency'])
            ;
        } elseif ($nodes->length == 0 && $this->http->XPath->query("//*[{$this->starts($this->t('DEPARTING FROM'))}]/following::*[{$this->starts($this->t('ARRIVING AT'))}]")->length > 0) {
            $this->logger->debug('maybe include Flight');
            $email->add()->flight();
        }

        // Segments
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*{$this->opt($this->t('Flight'))}\s+(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?:\\/)?(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }
            $operator = $this->nextTd($this->t('Carrier'), null, $root);

            if (preg_match("/^\s*{$this->opt($this->t('OPERATED BY'))}\s*(?<wl>\\/)?(?<operator>.+?)( AS | DBA |\s*$)/", $operator, $m)) {
                $s->airline()
                    ->operator($m['operator']);

                if (!empty($m['wl'])) {
                    $s->airline()
                        ->wetlease();
                }
            }
            $conf = $this->nextTd($this->t('Reference #'), "/^\s*([A-Z\d]{5,7})\s*$/", $root);

            if (!empty($conf)) {
                $s->airline()
                    ->confirmation($conf);
            }

            $re = "/^\s*.+\n(?<name>.+?)\s*\((?<code>[A-Z]{3})\)\s+(?<date>\d{1,2}:\d{2}\D{0,5}?\s*-\s*.+?)(?i)(?<terminal>\n.*terminal.*)?\s*$/";
            // Departure
            $node = implode("\n", $this->http->FindNodes(".//*[*[1][{$this->starts($this->t('DEPARTING FROM'))}] and *[2][{$this->starts($this->t('ARRIVING AT'))}]]/*[1]//text()[normalize-space()]", $root));
            // if (preg_match("/^\s*.+\n(?<name>.+?)\s*\((?<code>[A-Z]{3})\)\s+(?<time>\d{1,2}:\d{2}\D{0,5}?)\s*-\s*(?<date>.+?)(?i)(?<terminal>\n.*terminal.+)?\s*$/", $node, $m)) {
            if (preg_match($re, $node, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $m['terminal'] ?? '')), true, true)
                ;
            } else {
                $this->logger->debug('$node = ' . print_r($node, true));
                $this->logger->debug('$re = ' . print_r($re, true));
            }

            // Arrival
            $node = implode("\n", $this->http->FindNodes(".//*[*[1][{$this->starts($this->t('DEPARTING FROM'))}] and *[2][{$this->starts($this->t('ARRIVING AT'))}]]/*[2]//text()[normalize-space()]", $root));

            if (preg_match($re, $node, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $m['terminal'] ?? '')), true, true)
                ;
            } else {
                $this->logger->debug('$node = ' . print_r($node, true));
                $this->logger->debug('$re = ' . print_r($re, true));
            }

            // Extra
            $s->extra()
                ->aircraft($this->nextTd($this->t('Equipment'), "/^\s*(.*?)(?:\s*{$this->opt($this->t('Cabin'))}|\s*$)/", $root), true, true)
                ->duration($this->nextTd($this->t('Flight time'), null, $root))
                ->status($this->nextTd($this->t('Status'), null, $root))
                ->stops($this->nextTd($this->t('Stops'), null, $root))
                ->cabin(trim($this->nextTd($this->t('Cabin'), null, $root), ' .'))
            ;

            $seat = $this->nextTd($this->t('Seat #'), "/^\s*(\d{1,3}[A-Z])\s*$/", $root);

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }
        }

        return true;
    }

    private function parseHotel(Email $email)
    {
        $xpath = "//*[*[1][{$this->starts($this->t('Check In'))}] and *[2][{$this->starts($this->t('Check-Out'))}]]/ancestor::*[descendant::text()[normalize-space()][{$this->starts($this->t('Hotel'))}]][1]";
        // $this->logger->debug('Hotel XPath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && $this->http->XPath->query("//text()[{$this->starts($this->t('Check In'))}]/following::text()[position() < 10][{$this->starts($this->t('Check-Out'))}]")->length > 0) {
            $this->logger->debug('maybe include Hotel');
            $email->add()->hotel();
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $conf = $this->nextTd($this->t('Confirmation #'), null, $root);

            if (preg_match("/^\s*SELF BOOKED\s*$/i", $conf)) {
                $h->general()
                    ->noConfirmation();
            } else {
                $h->general()
                    ->confirmation($conf);
            }

            $h->general()
                ->cancellation($this->nextTd($this->t('Cancellation Policy'), null, $root), true, true)
            ;

            // Hotel
            $hotelText = implode("\n", $this->http->FindNodes("descendant::text()[normalize-space()][position() < 8]", $root));
            $re = "/^\s*{$this->opt($this->t('Hotel'))}(?: - Out Of Policy)?(?:\n[A-z\-]+(?: [A-z\-]+)?)?\n(?<name>.+)\n(?<address>.{5,}[\d,].*|.*[\d,].{5,})\n(?<phone>[\d\W]{5,})\n{$this->opt($this->t('Check In'))}/";

            if (preg_match($re, $hotelText, $m)) {
                $h->hotel()
                    ->name($m['name'])
                    ->address($m['address'])
                    ->phone($m['phone'])
                ;
            } else {
                $this->logger->debug('$re = ' . print_r($re, true));
                $this->logger->debug('$hotelText = ' . print_r($hotelText, true));
            }

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->nextTd($this->t('Check In'), null, $root)))
                ->checkOut($this->normalizeDate($this->nextTd($this->t('Check-Out'), null, $root)))
            ;

            $h->addRoom()
                ->setDescription($this->nextTd($this->t('Special Information'), null, $root), true, true)
                ->setRate($this->nextTd($this->t('Rate per Night'), null, $root))
            ;
        }

        return true;
    }

    private function parseRental(Email $email)
    {
        $xpath = "//*[*[1][{$this->starts($this->t('PICK-UP AT'))}] and *[2][{$this->starts($this->t('DROP-OFF AT'))}]]/ancestor::*[{$this->starts($this->t('Car Rental'))}][1]";
        // $this->logger->debug('Rental XPath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && $this->http->XPath->query("//*[{$this->starts($this->t('PICK-UP AT'))}]/following::*[{$this->starts($this->t('DROP-OFF AT'))}]")->length > 0) {
            $this->logger->debug('maybe include Rental');
            $email->add()->rental();
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $conf = $this->nextTd($this->t('Confirmation #'), null, $root);

            if (preg_match("/^\s*SELF BOOKED\s*$/i", $conf)) {
                $r->general()
                    ->noConfirmation();
            } else {
                $r->general()
                    ->confirmation($conf);
            }

            $r->general()
                ->cancellation($this->nextTd($this->t('Cancellation'), null, $root))
            ;

            $r->car()
                ->type($this->nextTd($this->t('Vehicle Type'), null, $root));

            $rentalProvider = $this->getRentalProviderByKeyword($this->nextTd($this->t('Company'), null, $root));

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            }

            $re = "/^\s*.+\n(?<name>.+?)\s*\n\s*(?<date>\d{1,2}:\d{2}\D{0,5}?\s*-\s*.+?)\s*$/";
            // Pick Up
            $node = implode("\n", $this->http->FindNodes(".//*[*[1][{$this->starts($this->t('PICK-UP AT'))}] and *[2][{$this->starts($this->t('DROP-OFF AT'))}]]/*[1]//text()[normalize-space()]", $root));

            if (preg_match($re, $node, $m)) {
                $r->pickup()
                    ->location($m['name'])
                    ->date($this->normalizeDate($m['date']))
                ;
            }
            // Drop Off
            $node = implode("\n", $this->http->FindNodes(".//*[*[1][{$this->starts($this->t('PICK-UP AT'))}] and *[2][{$this->starts($this->t('DROP-OFF AT'))}]]/*[2]//text()[normalize-space()]", $root));

            if (preg_match($re, $node, $m)) {
                $r->dropoff()
                    ->location($m['name'])
                    ->date($this->normalizeDate($m['date']))
                ;
            }
        }

        return true;
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->detectRentalProviders as $code => $kws) {
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

    private function nextTd($field, $regexp = null, $root = null): ?string
    {
        return $this->http->FindSingleNode(".//text()[{$this->eq($field)}][1]/ancestor::*[{$this->eq($field)}][count(following-sibling::*[normalize-space()]) = 1][1]/following-sibling::*[normalize-space()][1][not(.//*[contains(@style, 'font-weight: bold;') or contains(@style, 'font-weight:bold;')])]", $root, true, $regexp);
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

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$date = '.print_r( $str,true));
        $year = date("Y", $this->date);
        $in = [
            // 7:55 PM - Tuesday, October 24
            "/^\s*(\d+:\d+(?:\D{0,5}?))\s*-\s*([[:alpha:]]+)\s*,\s*([[:alpha:]]+)\s+(\d{1,2})\s*$/",
            // Tuesday, October 24
            "/^\s*([[:alpha:]]+)\s*,\s*([[:alpha:]]+)\s+(\d{1,2})\s*$/",
        ];
        $out = [
            "$2, $4 $3 $year, $1",
            "$1, $3 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        // if (strtotime($str) === false && preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
        //     if ($en = MonthTranslate::translate($m[1], $this->lang)) {
        //         $str = str_replace($m[1], $en, $str);
        //     }
        // }

        // $this->logger->debug('$date = '.print_r( $str,true));

        if (preg_match("#^(?<week>[\w\-]+), (?<date>\d+ \w+ .+)#u", $str, $m)) {
            $weekT = WeekTranslate::translate($m['week'], $this->lang);
            $weeknum = WeekTranslate::number1($weekT);
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d,.\s][^\d]{0,5})\s*(?<amount>\d[\d\., ]*)\s*$#u", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d,.\s][^\d]{0,5})\s*$#u", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#u", $text, $m)
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
        if (preg_match("#^\s*(?:\D{1,3}\s)?\b(?<c>[A-Z]{3})\b(?:\s\D{1,3})?\s*$#u", $s, $m)) {
            return $m['c'];
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
}
