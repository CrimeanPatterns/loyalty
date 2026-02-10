<?php

namespace AwardWallet\Engine\aerocrs\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "aerocrs/it-196159364.eml, aerocrs/it-196275924.eml, aerocrs/it-666777608.eml, aerocrs/it-671298964.eml, aerocrs/it-673552138.eml";

    public $detectFrom = '@aerocrs.com';

    public $detectSubject = [
        'Booking confirmation PNR Ref:',
        'E-Ticket Blue Bird Airways PNR Ref:',
    ];

    public $detectAirline = [
        'Airkenya Express' => [
            'body' => ['Airkenya Express LTD', 'www.airkenya.com', 'AirKenya Contacts:'],
            'href' => ['www.airkenya.com'],
        ],
        'Safarilink' => [
            'body' => ['Safarilink', '@flysafarilink.com', 'Safarilink Reservations Phones:', 'www.flysafarilink.com'],
            'href' => ['www.flysafarilink.com', '@flysafarilink.com'],
        ],
        'Auric Air' => [
            'body' => ['Auric Air Services LTD', '@auricair.com', 'www.auricair.com'],
            'href' => ['@auricair.com', 'www.auricair.com'],
        ],
        // '' => [
        //     'body' => [''],
        //     'href' => ['']
        // ],
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Booking confirmation Pnr Ref.' => ['Booking confirmation Pnr Ref.', 'E-TICKET Pnr Ref.', 'Booking Confirmation Pnr Ref.', 'PROFORMA INVOICE: NO-REPLY Pnr Ref.', 'Booking confirmation PNR Ref:'],
            'STD'                           => 'STD',
            'STA'                           => 'STA',
            'Traveller Name'                => ['Traveller Name', 'Passenger Name'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Booking confirmation Pnr Ref.'])
                && $this->http->XPath->query("(//*[" . $this->starts($dict['Booking confirmation Pnr Ref.']) . "])[1]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'www.aerocrs.com')] | //img[contains(@src, 'poweredbyaerocrs.png')] | //text()[contains(., 'This E-mail is sent to you by AeroCRS system')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Booking confirmation Pnr Ref.'])
                && $this->http->XPath->query("(//*[" . $this->starts($dict['Booking confirmation Pnr Ref.']) . " or contains(., 'Pnr Ref.')])[1]")->length > 0
                && !empty($dict['STD']) && !empty($dict['STA'])
                && $this->http->XPath->query("(//td[" . $this->eq($dict['STD']) . "]/following-sibling::td[" . $this->eq($dict['STA']) . "])[1]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) == false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
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
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Booking confirmation Pnr Ref.')) . "]", null, true,
            "/" . $this->preg_implode($this->t('Booking confirmation Pnr Ref.')) . "\s*([A-Z\d]{5,})\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('STD')) . "]//preceding::text()[" . $this->contains('Pnr Ref.') . "][1]", null, true,
                "/Pnr Ref\.\s*([A-Z\d]{5,})\s*$/");
        }
        $f->general()
            ->confirmation($conf);

        $travellers = $this->http->FindNodes("//*[self::td or self::th][" . $this->eq($this->t('Traveller Name')) . "]/ancestor::tr[1]/following-sibling::tr/*[1]");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//text()[" . $this->starts($this->t('Traveller ')) . " and contains(., ':') and string-length(normalize-space()) < 15]/following::text()[normalize-space()][1]");
        }
        $f->general()
            ->travellers(preg_replace('/^\s*((MR|MS|MRS|MISS|MSTR|DR)\.|CHILD)\s+/i', '', $travellers));

        $airlineName = '';

        foreach ($this->detectAirline as $name => $detects) {
            $count = 0;

            if (!empty($detects['body'])) {
                $count += $this->http->XPath->query("//text()[{$this->contains($detects['body'])}]")->length;
            }

            if (!empty($detects['href'])) {
                $count += $this->http->XPath->query("//a/@href[{$this->contains($detects['href'])}]")->length;
            }

            if ($count > 1) {
                $airlineName = $name;
            }
        }
        $xpath = "//td[" . $this->eq($this->t('STD')) . "]/following-sibling::td[" . $this->eq($this->t('STA')) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()][not({$this->contains($this->t("Sub-Total"))})]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            unset($s2);

            $date = $this->http->FindSingleNode("./td[normalize-space()][2]", $root);

            // Airline
            $airline = $this->http->FindSingleNode("./td[normalize-space()][3]", $root);

            if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\s*$/i", $airline, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            } elseif (preg_match("/^\s*(?<fn>\d{1,5})\s*$/", $airline, $m)) {
                $s->airline()
                    ->number($m['fn'])
                ;

                if (!empty($airlineName)) {
                    $s->airline()
                        ->name($airlineName)
                    ;
                } else {
                    $s->airline()
                        ->noName();
                }
            } elseif (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn1>\d{1,5})\s*-\s*(?<fn2>\d{1,5})\s*$/", $airline, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn1'])
                ;
                $s2 = $f->addSegment();
                $s2->airline()
                    ->name($m['al'])
                    ->number($m['fn2'])
                ;
            }

            // Departure
            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("./td[normalize-space()][4]", $root))
            ;

            $time = $this->http->FindSingleNode("./td[normalize-space()][5]", $root);

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $time))
                ;
            }

            // Arrival
            $name = $this->http->FindSingleNode("./td[normalize-space()][6]", $root);

            if (isset($s2) && preg_match("/^(.+) Via (.+)/", $name, $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($m[2])
                ;
                $s2->departure()
                    ->noCode()
                    ->name($m[2])
                ;
                $s2->arrival()
                    ->noCode()
                    ->name($m[1])
                ;
            } elseif (isset($s2)) {
                $s->arrival()
                    ->noCode()
                ;
                $s2->departure()
                    ->noCode()
                ;
                $s2->arrival()
                    ->noCode()
                    ->name($name)
                ;
            } else {
                $s->arrival()
                    ->noCode()
                    ->name($name)
                ;
            }

            $time = $this->http->FindSingleNode("./td[normalize-space()][7]", $root);

            if (!empty($date) && !empty($time)) {
                if (isset($s2)) {
                    $s->arrival()
                        ->noDate();
                    $s2->departure()
                        ->noDate();
                    $s2->arrival()
                        ->date($this->normalizeDate($date . ', ' . $time));
                } else {
                    $s->arrival()
                        ->date($this->normalizeDate($date . ', ' . $time));
                }
            }

            // Extra
            $class = $this->http->FindSingleNode("./td[normalize-space()][8]", $root);

            if (preg_match("/^\s*(?:Class\s*)?([A-Z]{1,2})(?:\s*\\/.+|)\s*$/", $class, $m)) {
                $s->extra()
                    ->bookingCode($m[1], true, true);

                if (isset($s2)) {
                    $s2->extra()
                        ->bookingCode($m[1], true, true);
                }
            } elseif (!empty($class)) {
                $s->extra()
                    ->cabin($class, true, true);

                if (isset($s2)) {
                    $s2->extra()
                        ->cabin($class, true, true);
                }
            }

            if (!empty($airline)) {
                $seats = array_filter($this->http->FindNodes("//*[self::td or self::th][" . $this->eq($this->t('Chosen Seats')) . "]/ancestor::tr[1]/following-sibling::tr//td[not(./td)][starts-with(normalize-space(), '{$airline}')]",
                    null, "/^{$airline}\s*:\s*(\d{1,3}[A-Z])\s*$/"));

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Total:')) . "][following::*[" . $this->eq($this->t('Traveller Name')) . " or (" . $this->starts($this->t('Traveller ')) . " and contains(.,':'))]]",
            null, true, "/" . $this->preg_implode($this->t('Total:')) . "\s*(.+)\s*$/");

        if (preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $m)
            || preg_match("/^\s*[^\d\s]{0,5}\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $total, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $m['curr']))
                ->currency($m['curr'])
            ;
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //02 Jul (Mon), 15:50
            //            '#^\s*(\d+)\s+([^\d\s]+)\s*\(([^\d\s]+)\)\s*,\s*(\d+:\d+)$#u',
        ];
        $out = [
            //            '$3, $1 $2 year, $4',
        ];
        $str = preg_replace($in, $out, $date);

//        if (preg_match("#^(?<week>[^\d\s]+),\s+(?<date>\d+\s+\w+.+)#", $str, $m)) {
//            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
//            $m['date'] = str_replace('year', date('Y', $this->dateEmail), $m['date']);
//            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
//        } else {
//            $str = strtotime($str);
//        }

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
