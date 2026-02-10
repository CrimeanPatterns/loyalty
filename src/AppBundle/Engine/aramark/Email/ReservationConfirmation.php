<?php

namespace AwardWallet\Engine\aramark\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "aramark/it-87527086.eml, aramark/it-87561047.eml, aramark/it-90733408.eml, aramark/it-90803411.eml, aramark/it-90915191.eml, aramark/it-91231201.eml, aramark/it-91761727.eml, aramark/it-96123341.eml";
    public $lang = "en";

    public static $dictionary = [
        "en" => [
            //            'Itinerary Number' => '',
            'Confirmation Number:' => ['Confirmation Number:', 'Confirmation Number'],
            //            'Guest Name' => '',
            'Property Name'  => ['Property Name', 'Property Description'],
            'Arrival Date'   => ['Arrival Date', 'Pickup'],
            'Check-in Time'  => ['Check-in Time', 'Check-In Time'],
            'Departure Date' => ['Departure Date', 'Return'],
            'Check-out Time' => ['Check-out Time', 'Check-Out Time'],
            //            'Adults / Children' => '',
            //            'Accommodations' => '',
            //            'Taxes' => '',
            //            'Total Cost' => '',
            'Total'                => ['Price', 'Total'],
            'LODGING INFORMATION'  => ['LODGING INFORMATION', 'Itemized Costs'],
            'ACTIVITY INFORMATION' => ['ACTIVITY INFORMATION', 'Planned Activities'],
        ],
    ];

    private $detectFrom = "@aramark.com";

    private $detectSubject = [
        // en
        "Reservation Confirmation",
    ];

    private $detectCompany = [
        'This email was sent by: Aramark',
        '@aramark.com',
        '@Aramark.com',
        'at the Yosemite Valley Lodge',
        '.travelyosemite.com',
        '.olympicnationalparks.com',
        //        '',
    ];

    private $detectBody = [
        "en" => ["To make your arrival experience and stay with us"],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        foreach ($this->detectBody as $lang => $detectBody){
//            if ($this->http->XPath->query("//text()[".$this->contains($detectBody)."]")->length > 0) {
//                $this->lang = $lang;
//                break;
//            }
//        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains($this->detectCompany, '@href') . "] | //*[" . $this->contains($this->detectCompany) . "]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 5; // 2 type 1 hotel, 2 type any hotels, 1 activity
    }

    private function parseHtml(Email $email)
    {
        $name = $this->getField($this->t('Property Name'));
        $address = '';

        if (!empty($name)) {
            $address = trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Arrival Date")) . "]/following::td[not(.//td) and .//text()[" . $this->eq($name) . "]]", null, true,
                "/^\s*" . preg_quote($name) . "\s*(.+)/"));
        }

        if (empty($address)) {
            $address = trim(implode(', ', $this->http->FindNodes("//td[" . $this->eq($this->t("IMPORTANT INFORMATION")) . "]/preceding::text()[normalize-space()][1]/ancestor::table[1][count(descendant::text()[normalize-space()]) < 5 and descendant::text()[normalize-space()][1]/ancestor::a[contains(@href, 'https://g.page/') or contains(@href, 'https://goo.gl/maps')]]/descendant::text()[normalize-space()][not(ancestor::a)]")), ' ,');
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("We look forward to your arrival at")) . "]",
                null, true, "/" . $this->preg_implode($this->t("We look forward to your arrival at")) . "\s+(.+?)\. /");
        }

        // Hotel
        if (!empty($this->getField($this->t("Arrival Date"))) || !empty($this->getField($this->t('Adults / Children')))) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->confirmation($this->getField($this->t('Itinerary Number')), 'Itinerary Number')
                ->traveller($this->getField($this->t('Guest Name')), true);

            $cancellation = $this->getField($this->t('Cancellation Policy'));

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);
            }

            // Hotel

            $h->hotel()
                ->name($name);

            if (empty($address) && !empty($cancellation)) {
                $h->hotel()
                    ->noAddress();
            } else {
                $h->hotel()
                    ->address($address);
            }

            if (empty($this->http->FindSingleNode("//text()[" . $this->eq($this->t('LODGING INFORMATION')) . "]"))) {
                $h->general()
                    ->confirmation($this->getField($this->t('Confirmation Number:')), 'Confirmation Number')
                ;

                // Booked
                $h->booked()
                    ->checkIn($this->normalizeDate(
                        $this->getField($this->t("Arrival Date")) . ', ' . $this->getField($this->t("Check-in Time"))
                    ))
                    ->checkOut($this->normalizeDate(
                        $this->getField($this->t('Departure Date')) . ', ' . $this->getField($this->t("Check-out Time"))
                    ))
                    ->guests($this->getField($this->t('Adults / Children'), "/^\s*(\d+)\s*\/.+/"))
                    ->kids($this->getField($this->t('Adults / Children'), "/^\s*\d+\s*\/\s*(\d+)\s*$/"));

                // Rooms
                $h->addRoom()
                    ->setType(implode("|",
                        $this->http->FindNodes("//text()[" . $this->eq($this->t('Accommodations')) . "]/following::text()[normalize-space(.)][1]")));

                // Price
                $taxes = $this->getField($this->t('Taxes'));

                if (preg_match("#^\s*([^\s\d]{1,5})\s*(\d[\d.,]*)\s*$#", $taxes, $m)) {
                    $h->price()
                        ->tax(PriceHelper::cost($m[2]))
                        ->currency($m[1]);
                }
                $total = $this->getField($this->t('Total Cost'));

                if (preg_match("#^\s*([^\s\d]{1,5})\s*(\d[\d.,]*)\s*$#", $total, $m)) {
                    $h->price()
                        ->total(PriceHelper::cost($m[2]))
                        ->currency($m[1]);
                }
            } else {
                $xpath = "//tr[" . $this->eq($this->t('LODGING INFORMATION')) . "]/ancestor::table[1]//tr[not(.//tr) and normalize-space() and count(td[normalize-space()]) > 2]";
                $headers = $this->http->FindNodes($xpath . "[td[1][" . $this->eq($this->t('Lodging')) . "]]/td");
//                $this->logger->debug('$xpath = '.print_r( $xpath,true));
                $lNodes = $this->http->XPath->query($xpath . "[td[1][not(" . $this->eq($this->t('Lodging')) . ")]]");

                foreach ($lNodes as $lRoot) {
                    $l = $email->add()->hotel();
                    $l->fromArray($h->toArray());
                    unset($r);

                    foreach ($headers as $i => $name) {
                        $i++;

                        // Rooms
                        if ($i == 1) {
                            $r = $l->addRoom();
                            $r->setType($this->http->FindSingleNode("td[1]", $lRoot));
                        }
                        // General
                        if ($i == 2) {
                            $l->general()
                                ->confirmation($this->http->FindSingleNode("td[2]", $lRoot));
                        }

                        if (in_array($name, (array) $this->t("Total"))) {
                            $total = $this->http->FindSingleNode("td[$i]", $lRoot);

                            if (preg_match("#^\s*([^\s\d]{1,5})\s*(\d[\d.,]*)\s*$#", $total, $m)) {
                                $l->price()
                                    ->total(PriceHelper::cost($m[2]))
                                    ->currency($m[1]);
                            }
                        }

                        if (in_array($name, (array) $this->t("Cost")) && isset($r)) {
                            $r->setRate($this->http->FindSingleNode("td[$i]", $lRoot));
                        }

                        // Booked
                        if (in_array($name, (array) $this->t("Arrival Date"))) {
                            $l->booked()
                                ->checkIn(strtotime($this->http->FindSingleNode("td[$i]", $lRoot)))
                            ;
                        }

                        if (in_array($name, (array) $this->t("Nights")) && !empty($l->getCheckInDate())) {
                            $l->booked()
                                ->checkOut(strtotime(($this->http->FindSingleNode("td[$i]", $lRoot) ?? 0) . " days", $l->getCheckInDate()))
                            ;
                        }
                    }
                }
                $email->removeItinerary($h);
            }

            $this->detectDeadLine($h);
        }

        // Tours
        $hXpath = "//text()[" . $this->eq($this->t('ACTIVITY INFORMATION')) . "][1]/following::tr[td[1][" . $this->eq($this->t('Date')) . "] and td[2][" . $this->eq($this->t('Time')) . "]]";
        $xpathTours = $hXpath . "/following::tr[normalize-space()][1]/ancestor::*[1]/tr[not(td[1][" . $this->eq($this->t('Date')) . "])]";
        $headers = $this->http->FindNodes($hXpath . "/td");
//        $this->logger->debug('$xpathTours = '.print_r( $xpathTours,true));
        $tNodes = $this->http->XPath->query($xpathTours);

        foreach ($tNodes as $tRoot) {
            $t = $email->add()->event();

            // General
            $t->general()
                ->confirmation($this->getField($this->t('Itinerary Number')), 'Itinerary Number')
                ->traveller($this->getField($this->t('Guest Name')), true)
            ;

            if (!isset($h)) {
                $conf = $this->getField($this->t('Confirmation Number:'));

                if (!empty($conf)) {
                    $t->general()
                        ->confirmation($conf, 'Confirmation Number');
                }
            }

            // Place
            $t->place()
                ->name($this->http->FindSingleNode("td[3]", $tRoot))
                ->address($address)
                ->type(EVENT_EVENT);

            // Booked
            $t->booked()
                ->start(strtotime($this->http->FindSingleNode("td[1]", $tRoot) . ', ' . $this->http->FindSingleNode("td[2]", $tRoot, null, "/^(.*?)(?:TWT [AP]M|$)/i")))
                ->noEnd()
            ;

            if (!empty($headers[5]) && in_array($headers[5], (array) $this->t('Child'))) {
                $t->booked()
                    ->guests($this->http->FindSingleNode("td[5]", $tRoot) + $this->http->FindSingleNode("td[6]", $tRoot));
            } elseif (!empty($headers[4]) && in_array($headers[4], (array) $this->t('Qty'))) {
                $t->booked()
                    ->guests($this->http->FindSingleNode("td[5]", $tRoot));
            } elseif (!empty($headers[6]) && in_array($headers[6], (array) $this->t('Child'))) {
                $t->booked()
                    ->guests($this->http->FindSingleNode("td[6]", $tRoot) + $this->http->FindSingleNode("td[7]", $tRoot));
            }

            $total = "";

            if (!empty($headers[3]) && in_array($headers[3], (array) $this->t('Total'))) {
                $total = $this->http->FindSingleNode("td[4]", $tRoot);
            }

            if (!empty($headers[5]) && in_array($headers[5], (array) $this->t('Total'))) {
                $total = $this->http->FindSingleNode("td[6]", $tRoot);
            }

            if (preg_match("#^\s*([^\s\d]{1,5})\s*(\d[\d.,]*)\s*$#", $total, $m)) {
                $t->price()
                    ->total(PriceHelper::cost($m[2]))
                    ->currency($m[1]);
            }
        }

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancellations made (?<priorD>\d+ hours?) or more prior to check-in will receive a full refund\./i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['priorD']);
        }
    }

    private function getField($field, $regexp = null, $n = 1)
    {
        return $this->http->FindSingleNode("(//text()[{$this->eq($field)}]/following::text()[normalize-space(.)][1])[{$n}]", null, true, $regexp);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            "#^\s*\w+\s+(\d+)\s+(\w+)\s+(\d{4})[\s,]*$#",
            // Tuesday, August 3, 2021 After 8:00 AM,
            "#^\s*\w+[\s,]+(\w+)\s+(\d+)[\s,]+(\d{4})[\s,\D]*(\d{1,2}:\d{2}(?:\s*[ap]m)?)[\s,]*$#i",
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
