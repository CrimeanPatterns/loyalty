<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightTo extends \TAccountChecker
{
    public $mailFiles = "expedia/it-630861462.eml, expedia/it-631118291.eml, expedia/it-631119044.eml, expedia/it-631213581.eml";
    public $subjects = [
        'Confirmed: Flight to',
    ];

    public $lang = 'en';
    public $year;
    public $seg = [];

    public static $dictionary = [
        "en" => [
            'Expedia itinerary:' => ['Expedia itinerary:', 'Itinerary #'],
            'View booking'       => ['View booking', 'View your trip'],
            'Your flight is'     => ['Your flight is', 'Your flights are', 'we are processing your flight purchase'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@eg.expedia.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Expedia app')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your flight is'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('View booking'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]eg\.expedia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $this->year = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This plan is available until')]", null, true, "/\s(\d{4})\s*$/");

        $otaConf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Expedia itinerary:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Expedia itinerary:'))}\s*([\dA-Z]+)/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $f->general()
            ->noConfirmation();

        $travellers = $this->http->FindNodes("//text()[normalize-space()='Traveler details']/ancestor::table[1]/following::text()[normalize-space()][string-length()>2][1]/ancestor::div[1]/ancestor::div[normalize-space()][1]/descendant::text()[normalize-space()]");

        $travellers = preg_split("/\s*,\s*/", trim(implode(', ', $travellers), ', '));

        if (count($travellers) > 0) {
            $f->general()
                ->travellers(array_filter($travellers), true);
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total paid']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (preg_match("/^(?<currency>\D{1,3})(?<total>[\d\.\,]+)$/", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes and fees']/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^\D*([\d\.\,]+)/");

            if (!empty($tax)) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $fee = $this->http->FindSingleNode("//text()[normalize-space()='Expedia booking fee']/ancestor::tr[1]/descendant::td[normalize-space()][2]", null, true, "/^\D*([\d\.\,]+)/");

            if (!empty($fee)) {
                $f->price()
                    ->fee('Expedia booking fee', $fee);
            }
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Layover:')]")->length === 0
        || ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Layover:')]/following::img[contains(@src, 'flights')][1]/ancestor::div[1][contains(normalize-space(), 'flight')]/following::div[normalize-space()][3][contains(normalize-space(), 'Departure')]")->length > 0
            && $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Layover:')]/preceding::img[contains(@src, 'flights')][1]/ancestor::div[1][contains(normalize-space(), 'flight')]/following::div[normalize-space()][3][contains(normalize-space(), 'Departure')]")->length > 0)) {
            $this->parseSegmentFull($f);
        } else {
            $this->parseSegmentWithLayover($f);
        }
    }

    public function parseSegmentFull(\AwardWallet\Schema\Parser\Common\Flight $f)
    {
        $nodes = $this->http->XPath->query("//text()[normalize-space()='Departure']");
        $this->ParseSegments($f, $nodes);
    }

    public function parseSegmentWithLayover(\AwardWallet\Schema\Parser\Common\Flight $f)
    {
        $this->logger->debug(__METHOD__);
        $layoverTime = 0;
        $layover = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Layover:')]", null, true, "/{$this->opt($this->t('Layover:'))}\s*(.+)\s+in/");

        $hours = $this->re("/(?<hours>\d+)h/", $layover);

        if (isset($hours) && !empty($hours)) {
            $layoverTime += $hours * 3600;
        }

        $min = $this->re("/(?<hours>\d+)h/", $layover);

        if (isset($min) && !empty($min)) {
            $layoverTime += $min * 600;
        }

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Layover:')]/preceding::text()[normalize-space()='Departure']");
        //it-631119044
        if ($nodes->length > 0) {
            $this->ParseSegments($f, $nodes);

            if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Layover:')]/following::img[contains(@src, 'flights')]/ancestor::div[1][contains(normalize-space(), 'flight')]")->length > 1) {
                $this->logger->debug('After stopping for more than 1 segment');

                return;
            }

            $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Layover:')]/following::text()[normalize-space()='Departure']");

            if ($nodes->length > 0) {
                $this->ParseSegments($f, $nodes);
            } else {
                $nextSegText = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Layover:')]/following::img[contains(@src, 'flights')]/ancestor::div[1][contains(normalize-space(), 'flight')]/descendant::text()[normalize-space()]"));
                $s = $f->addSegment();

                if (preg_match("/^(?<aName>[\S\s]*)\s+(?<fNumber>\d{1,4})(?:\s|\n)/", $nextSegText, $m)) {
                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['fNumber']);
                }

                $s->departure()
                    ->code($this->seg['arrCode'])
                    ->date($this->seg['arrDate'] + $layoverTime)
                    ->name($this->seg['arrName']);

                $arrCode = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Seats and bags')]/following::text()[{$this->starts($this->seg['depCode'] . ' to ')}][1]", null, true, "/to\s+([A-Z]{3})/");

                $s->arrival()
                    ->noDate()
                    ->code($arrCode);

                $seats = $this->searchSeats($this->seg['arrCode'], $arrCode);

                if (count($seats) > 0) {
                    $s->setSeats($seats);
                }

                $cabin = $this->re("/Fare type:\s*(.+)/", $nextSegText);

                if (!empty($cabin)) {
                    $s->extra()
                        ->cabin($cabin);
                }

                $duration = $this->re("/^(\d(?:h|m).*)\s+flight/mu", $nextSegText);

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }

                $conf = $this->re("/confirmation: ([A-Z\d]{6})\s*\n/", $nextSegText);

                if (!empty($conf)) {
                    $s->setConfirmation($conf);
                }
            }
        } else {
            $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Layover:')]/following::text()[normalize-space()='Departure']");
            //it-631118291.eml
            if ($nodes->length > 0) {
                $this->ParseSegments($f, $nodes);

                $previousSegText = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Layover:')]/preceding::img[contains(@src, 'flights')]/ancestor::div[1][contains(normalize-space(), 'flight') or contains(normalize-space(), 'Fare type:')]/descendant::text()[normalize-space()]"));
                $s = $f->addSegment();

                if (preg_match("/^(?<aName>[\S\s]*)\s+(?<fNumber>\d{1,4})(?:\s|\n)/", $previousSegText, $m)) {
                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['fNumber']);
                }

                $depCode = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Seats and bags')]/following::text()[{$this->contains(' to ' . $this->seg['arrCode'])}][1]", null, true, "/([A-Z]{3})\s+to\s+/");

                if (!empty($depCode)) {
                    $s->departure()
                        ->code($depCode);
                } else {
                    $s->departure()
                        ->noCode();
                }
                $s->departure()
                    ->noDate();

                $s->arrival()
                    ->code($this->seg['depCode'])
                    ->date($this->seg['depDate'] - $layoverTime)
                    ->name($this->seg['depName']);

                $cabin = $this->re("/Fare type:\s*(.+)/", $previousSegText);

                if (!empty($cabin)) {
                    $s->extra()
                        ->cabin($cabin);
                }

                $duration = $this->re("/^(\d(?:h|m).*)\s+flight/mu", $previousSegText);

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }

                $conf = $this->re("/confirmation: ([A-Z\d]{6})\s*\n/", $previousSegText);

                if (!empty($conf)) {
                    $s->setConfirmation($conf);
                }
            }
        }
    }

    public function ParseSegments(\AwardWallet\Schema\Parser\Common\Flight $f, \DOMNodeList $nodes)
    {
        $this->logger->debug(__METHOD__);
        //it-631213581.eml
        foreach ($nodes as $root) {
            $segsFlight = $f->getSegments();

            $s = $f->addSegment();

            $airInfo = implode("\n", $this->http->FindNodes("./preceding::img[contains(@src, 'flight')][1]/ancestor::div[1]/descendant::text()[normalize-space()]", $root));

            if (empty($airInfo)) {
                $airInfo = implode("\n", $this->http->FindNodes("./preceding::text()[contains(normalize-space(), 'confirmation:')][1]/ancestor::div[1]/descendant::text()[normalize-space()]", $root));
            }

            if (preg_match("/^(?<aName>[\S\s]*)\s+(?<fNumber>\d{1,4})(?:\s|\n)/u", $airInfo, $m)) {
                if (stripos($m['aName'], '(') !== false) {
                    $m['aName'] = preg_replace("/\(.+/", "", $m['aName']);
                }

                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $operator = $this->re("/\((.+)\s+operated\)/", $airInfo);

                if (!empty($operator)) {
                    $s->airline()
                        ->operator($operator);
                }
            }

            $depInfo = implode("\n", $this->http->FindNodes("./ancestor::div[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/Departure\n*(?<depTime>[\d\:]+\s*a?p?m?)\n(?<depDay>.+\s\d{1,2})\n(?<depName>\D*)\s+\((?<depCode>[A-Z]{3})/", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDay'] . ', ' . $m['depTime']));

                $this->seg['depCode'] = $m['depCode'];
                $this->seg['depDate'] = $s->getDepDate();
                $this->seg['depName'] = $m['depName'];
            }

            $arrInfo = implode("\n", $this->http->FindNodes("./following::text()[normalize-space()='Arrival']/ancestor::div[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/Arrival\n*(?<arrTime>[\d\:]+\s*(?:[ap]m)?)\n*(?<nextDay>[+]\d)?\n(?<arrDay>.+\s\d{1,2})\n(?<arrName>\D*)\s+\((?<arrCode>[A-Z]{3})/", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDay'] . ', ' . $m['arrTime']));

                $this->seg['arrCode'] = $m['arrCode'];
                $this->seg['arrDate'] = $s->getArrDate();
                $this->seg['arrName'] = $m['arrName'];
            }

            foreach ($segsFlight as $segFlight) {
                if ($segFlight->getDepCode() . '-' . $segFlight->getFlightNumber() . '-' . $segFlight->getDepDate()
                    === $s->getDepCode() . '-' . $s->getFlightNumber() . '-' . $s->getDepDate()) {
                    $f->removeSegment($s);
                    $s = $segFlight;
                }
            }

            if (isset($this->seg['depCode']) && isset($this->seg['arrCode'])) {
                $seats = array_filter($this->searchSeats($this->seg['depCode'], $this->seg['arrCode']));

                if (count($seats) > 0) {
                    $s->extra()
                        ->seats($seats);
                }
            }

            $cabin = $this->re("/Fare type:\s*(.+)/", $airInfo);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $duration = $this->re("/^(\d(?:h|m).*)\s+flight/mu", $airInfo);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $conf = $this->re("/confirmation: ([A-Z\d]{6})\s*\n/", $airInfo);

            if (!empty($conf)) {
                $s->setConfirmation($conf);
            }
        }
    }

    public function searchSeats(string $depCode, string $arrCode)
    {
        $seatText = $this->http->FindSingleNode("//td[{$this->starts($depCode)} and {$this->contains('to')} and {$this->contains($arrCode)}]/ancestor::tr[1]/descendant::td[last()]", null, true, "/^(\d+[A-Z][\,\dA-Z]+)$/u");

        return explode(',', $seatText);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            //Sun, Mar 24, 9:00am
            "#^(\w+)\,\s*(\w+)\s*(\d+)\,\s*([\d\:]+a?p?m?)$#i",
        ];
        $out = [
            "$1, $3 $2 $this->year, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
