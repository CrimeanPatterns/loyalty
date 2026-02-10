<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary1 extends \TAccountChecker
{
    /*
     * Format Flight table:
     *      [Flight, Departs, Arrives] -> go to parser ReceiptSentFromAlaskaairCom
     *      [Flight, Departs, Arrives, Details] -> this parser
     * */
    public $mailFiles = "alaskaair/it-10.eml, alaskaair/it-11.eml, alaskaair/it-12.eml, alaskaair/it-13.eml, alaskaair/it-14.eml, alaskaair/it-15.eml";

    private $detectProvider = "alaskaair.com";
    private $detectSubject = [
        'alaskaair.com Itinerary from',
    ];

    private $emailDate = null;
    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectProvider) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['subject'])) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'alaskaair.com')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//*[" . $this->contains(['Traveler Documentation', 'Traveler Information']) . "]")->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->emailDate = strtotime($parser->getDate());

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        $travellerXpath = "//tr[*[1][" . $this->eq(["Traveler Information"]) . "] and *[2][" . $this->starts(["Reserved Seats"]) . "] ]/following::tr[normalize-space()][1]/ancestor::*[1]/*";

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($this->http->FindNodes($travellerXpath . "//text()[" . $this->eq('Name:') . "]/following::text()[normalize-space()][1]", null, "#^\s*(.+)\s*$#"), true)
        ;

        // Segments
        $xpath = "//th[contains(text(), 'Departs')][ ./following-sibling::th[contains(.,'Details')]]/ancestor::tr[1]/following-sibling::tr[normalize-space() and not(" . $this->starts('Flight operated by') . ")]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        $seats = [];
        $seatsTexts = $this->http->FindNodes($travellerXpath . "/td[2]");

        foreach ($seatsTexts as $text) {
            $s = array_map('trim', preg_split('#[, ]#', $text));

            if (count($s) === $nodes->length) {
                foreach ($s as $i => $v) {
                    $seats[$i][] = (preg_match("#^\s*\d{1,3}[A-Z]\s*$#", $v)) ? $v : null;
                }
            } else {
                $seats = [];

                break;
            }
        }

        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();

            $col1 = implode("\n", $this->http->FindNodes("./td[1]", $root));
            // Airline
            if (preg_match("#^\s*(?<al>.+)\s(?<fn>\d{1,5})\s*#", $col1, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                $operated = $this->http->FindSingleNode("./following-sibling::tr[1][" . $this->starts('Flight operated by') . "]", $root);

                if (preg_match("#Flight operated by (.+?) - #", $operated, $m)) {
                    $s->airline()
                        ->operator($m[1])
                    ;
                }

                if (preg_match("# confirmation code:\s*([A-Z\d]{5,7})\s*$#", $operated, $m)) {
                    $s->airline()
                        ->confirmation($m[1])
                    ;
                }
            }

            // Departure
            $col2 = implode(" ", $this->http->FindNodes("./td[2]//text()[normalize-space()]", $root));

            if (preg_match("#^\s*(?<name>.*?)\((?<code>[A-Z]{3})\)\s*(?<date>.*\d+:\d+.*)$#", $col2, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']))
                ;
            }

            // Arrival
            $col3 = implode(" ", $this->http->FindNodes("./td[3]//text()[normalize-space()]", $root));

            if (preg_match("#^\s*(?<name>.*?)\((?<code>[A-Z]{3})\)\s*(?<date>.*\d+:\d+.*)$#", $col3, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']))
                ;
            }

            // Extra
            if (isset($seats[$i]) && !empty(array_filter($seats[$i]))) {
                $s->extra()->seats(array_filter($seats[$i]));
            }

            $detailsCol = str_replace('·', " | ", implode("\n", $this->http->FindNodes("./td[4]//text()[normalize-space()]", $root)));

            if (preg_match("#^(.+?)\s+\|\s+(.+?)\n#", $detailsCol, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->aircraft($m[2]);
            }

            if (preg_match("#(?:\||\n)\s*(.*?stop.*?)[ ]*(?:\||\n)#", $detailsCol, $m)) {
                if (preg_match("#(non[ \-]?stop)#i", $m[1])) {
                    $s->extra()->stops(0);
                } elseif (preg_match("#(\d+)#", $m[1], $mt)) {
                    $s->extra()->stops($mt[1]);
                }
            }

            if (preg_match("#Total: ?(.+?)\s+\|\s+(.+?)(?:\||\n|$)#", $detailsCol, $m)) {
                $s->extra()
                    ->miles($m[1])
                    ->duration($m[2])
                ;
            }

            if (preg_match("#Meal: (.+?)\s*(?:\||\n|$)#", $detailsCol, $m)) {
                $s->extra()
                    ->meal($m[1]);
            }
        }

        return $email;
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

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function normalizeDate($date)
    {
        $year = date("Y", $this->emailDate);
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // 7:25 pm Thu , Oct 15
            "#^\s*(\d{1,2}:\d{2}\s*[ap]m)\s+(\w+)[,\s]+(\w+)\s+(\d+)\s*$#",
            // Fri , Sep 11 8:45 am
            "#^\s*(\w+)[,\s]+(\w+)\s+(\d+)\s*(\d{1,2}:\d{2}\s*[ap]m)\s*$#",
        ];
        $out = [
            "$2, $4 $3 $year, $1",
            "$1, $3 $2 $year, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }
}
