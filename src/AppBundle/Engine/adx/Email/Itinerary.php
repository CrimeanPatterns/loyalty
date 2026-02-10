<?php

namespace AwardWallet\Engine\adx\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "";

    public $detectBody = [
        'en' => ['Please find attached details for your itinerary.', 'Please find attached the quote for your upcoming trip.'],
    ];

    public $date;
    public $isJunk = false;

    public $lang = 'en';
    public static $dictionary = [
        "en" => [
            'Date'        => 'Date',
            'Service'     => 'Service',
            'Description' => 'Description',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src, '/adxtravel.com')]/@src | //text()[contains(normalize-space(), 'no-reply@adxtravel.com')] | //a[contains(@href, '.traveledge.com')]")->length === 0
            && stripos($parser->getHeader('from'), 'no-reply@adxtravel.com') === false
        ) {
            return false;
        }

        $pdf = $parser->searchAttachmentByName('.*\.pdf');

        if (count($pdf) > 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($dBody)}]")->length > 0
                && !empty(self::$dictionary[$lang]) && !empty(self::$dictionary[$lang]['Date']) && !empty(self::$dictionary[$lang]['Service']) && !empty(self::$dictionary[$lang]['Description'])
                && $this->http->XPath->query("//tr[count(*[normalize-space()]) = 3"
                    . " and *[normalize-space()][1][{$this->eq(self::$dictionary[$lang]['Date'])}]"
                    . " and *[normalize-space()][2][{$this->eq(self::$dictionary[$lang]['Service'])}]"
                    . " and *[normalize-space()][3][{$this->eq(self::$dictionary[$lang]['Description'])}]]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'no-reply@adxtravel.com') !== false;
    }

    public function ParseItinerary(Email $email)
    {
        $email->obtainTravelAgency();

        $xpath = "//tr[count(*[normalize-space()]) = 3"
            . " and *[normalize-space()][1][{$this->eq($this->t('Date'))}]"
            . " and *[normalize-space()][2][{$this->eq($this->t('Service'))}]"
            . " and *[normalize-space()][3][{$this->eq($this->t('Description'))}]]/ancestor::thead/following-sibling::tbody/tr[normalize-space()][1]";
        $nodes = $this->http->XPath->query($xpath);

        $this->date = $this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Client:')) . " or " . $this->starts($this->t('Date Sent:')) . " ]/ancestor::tr[1]/preceding::tr[normalize-space()][1]/*[normalize-space()][1]",
            null, true, "/^(.+?)\s*–/"));

        foreach ($nodes as $root) {
            $root->nodeValue;

            if ($this->http->XPath->query(".//text()[{$this->contains("→")}]", $root)->length > 0) {
                if (!isset($f)) {
                    $f = $email->add()->flight();

                    $f->general()
                        ->noConfirmation();
                }
                $this->parseFlight($f, $root);
            } elseif ($this->http->XPath->query(".//text()[{$this->contains($this->t("Check out:"))}]", $root)->length > 0) {
                $this->parseHotel($email, $root);
            } elseif ($this->http->XPath->query(".//text()[normalize-space()][1][{$this->eq($this->t("Covered"))}]", $root)->length > 0) {
                continue;
            } else {
                $this->logger->debug('unknown segment type');
                $email->add()->flight();
            }
        }

        $travellers = preg_replace(["/^\s*(Mr\.|Mrs\.|Dr\.|Miss|Ms\.|Mstr) +/", '/\s*,$/'], "",
            $this->http->FindNodes("//text()[" . $this->eq($this->t('Travelers')) . "]/ancestor::td[1]//text()[normalize-space()][not(" . $this->eq($this->t('Travelers')) . ")]"));

        if (count($travellers) > 0) {
            foreach ($email->getItineraries() as $i => $it) {
                $email->getItineraries()[$i]->general()
                    ->travellers($travellers, true);
            }
        }

        if ($this->isJunk == true) {
            foreach ($email->getItineraries() as $it) {
                $email->removeItinerary($it);
            }
            $email->setIsJunk(true);
        }
    }

    public function parseFlight(Flight $f, \DOMElement $root)
    {
        $info = implode(" ", $this->http->FindNodes("*[normalize-space()][3]//text()[normalize-space()]", $root));

        if (preg_match_all("/\b(?<dCode>[A-Z]{3}) +at +(?<dTime>\d{1,2}:\d{2}( *[apAP][mM])?)\s*→\s*\b(?<aCode>[A-Z]{3}) +at +(?<aTime>\d{1,2}:\d{2}( *[apAP][mM])?)\b/u", $info, $m)) {
            // PMI at 08:10 AM → FLR at 06:20 PM

            $date = $this->normalizeDate($this->http->FindSingleNode("*[normalize-space()][1]", $root));

            if (count($m[0]) > 1) {
                $this->isJunk = true;
            }

            foreach ($m[0] as $i => $values) {
                $s = $f->addSegment();

                // Airline
                if ($i == 0) {
                    $node = $this->http->FindSingleNode("*[normalize-space()][2]", $root);

                    if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\s*,\s*(?<cabin>[^,]+)\s*,\s*Class\s+(?<code>[A-Z]{1,2})\s*$/", $node, $mat)) {
                        // LH 5075, Economy, Class S
                        $s->airline()
                            ->name($mat['al'])
                            ->number($mat['fn']);

                        $s->extra()
                            ->cabin($mat['cabin'])
                            ->bookingCode($mat['code']);
                    }
                } else {
                    $s->airline()
                        ->noName()
                        ->noNumber();
                }

                // Departure
                $s->departure()
                    ->code($m['dCode'][$i]);

                if (!empty($date)) {
                    $s->departure()
                        ->date(strtotime($m['dTime'][$i], $date));
                }

                // Arrival
                $s->arrival()
                    ->code($m['aCode'][$i]);

                if (!empty($date)) {
                    $s->arrival()
                        ->date(strtotime($m['aTime'][$i], $date));
                }
            }
        } else {
            $s = $f->addSegment();
        }

        return true;
    }

    public function parseHotel(Email $email, \DOMElement $root)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->noConfirmation();

        $h->hotel()
            ->name($this->http->FindSingleNode("*[normalize-space()][2]", $root))
            ->noAddress();

        $dates = implode(" ", $this->http->FindNodes("*[normalize-space()][3]//text()[normalize-space()]", $root));

        if (!preg_match("/^\s*Check in:\s*\d{1,2}:\d{2}(?:\s*[ap]m)?\s*Check out:\s*\d{1,2}:\d{2}(?:\s*[ap]m)?\s*/i", $dates)) {
            $checkIn = $this->normalizeDate($this->http->FindSingleNode("*[normalize-space()][1]", $root));
            $h->booked()
                ->checkIn($checkIn);

            if (!empty($checkIn) && preg_match("/Check out:\s*([[:alpha:]]+) (\d{1,2})\s*$/", $dates, $m)) {
                $checkOut = $this->normalizeDate($m[2] . ' ' . $m[1] . ' ' . date("Y", $checkIn));

                if (!empty($checkOut)) {
                    if ($checkOut < $checkIn) {
                        $checkOut = strtotime("+1 year", $checkOut);
                    }
                    $h->booked()
                        ->checkOut($checkOut);
                }
            }
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdf = $parser->searchAttachmentByName('.*\.pdf');

        if (count($pdf) > 0) {
            $this->logger->debug('go to parse pdf');

            return $email;
        }

        $this->ParseItinerary($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $year = '';

        if (!empty($this->date)) {
            $year = date("Y", $this->date);
        }

        $in = [
            // Tue Sep 27
            "/^\s*([[:alpha:]]+),?\s*([[:alpha:]]+)\s+(\d{1,2})\s*$/",
            // Tue, Sep 27 2022
            "/^\s*([[:alpha:]]+),?\s*([[:alpha:]]+)\s+(\d{1,2})\s+(\d{4})\s*$/",
        ];
        $out = [
            "$1, $3 $2 $year",
            "$1, $3 $2 $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>[[:alpha:]]{2,}), (?<date>\d+ [[:alpha:]]{3,} \d{4})\s*$#u", $date, $m)) {
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
