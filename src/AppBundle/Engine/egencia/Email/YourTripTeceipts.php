<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourTripTeceipts extends \TAccountChecker
{
    public $mailFiles = "egencia/it-55129700.eml, egencia/it-55135262.eml, egencia/it-55284461.eml, egencia/it-55303468.eml, egencia/it-55309302.eml";
    //Format detectors
    //detectors[0]&&[1]
    private static $detectors = [
        'en' => [
            "1" => ["Hotel Receipt", "Itinerary"],
            "2" => ["Flight Receipt", "Itinerary"],
        ],
    ];

    //Language detectors and dictionary
    private static $dictionary = [
        'en' => [
            "detectFirst" => ["Today's Date"],
            "detectLast"  => ["Purchased"],
            "Purchase"    => ["Purchase", "Exchange", "Void"],
        ],
    ];
    private $from = "@mail.egencia.com";
    private $subject = ["Your trip receipts are now available"];
    private $body = ['This receipt only includes transactions which were charged through Egencia.'];
    private $lang;
    private $pdfNamePattern = ".*pdf";

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $sub) {
            if (stripos($headers["subject"], $sub) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!empty($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($this->detectBody($text) === true && $this->assignLang($text) === true) {
                    foreach ($this->body as $word) {
                        if (stripos($text, $word) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!empty($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text) === false) {
                        $this->logger->debug("Can't determine a language");
                    } else {
                        if ($this->detectBody($text) === true) {
                            if (strpos($text, "Hotel Receipt") !== false) {
                                $this->parseHotelPDF($email, $text);
                            } elseif (strpos($text, "Flight Receipt") !== false) {
                                $this->parseFlightPDF($email, $text);
                            }
                        }
                    }
                }
            }
        }
        $email->setType('YourTripTeceipts');

        return $email;
    }

    private function detectBody($body)
    {
        if (!empty($this->body)) {
            foreach (self::$detectors as $lang => $formats) {
                foreach ($formats as $phrases) {
                    if (strpos($body, $phrases[0]) !== false && strpos($body, $phrases[1]) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        if (!empty($this->body)) {
            foreach (self::$dictionary as $lang => $words) {
                foreach ($words["detectFirst"] as $word1) {
                    if (strpos($body, $word1) !== false) {
                        foreach ($words["detectLast"] as $word2) {
                            if (strpos($body, $word2) !== false) {
                                $this->lang = $lang;

                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    private function parseFlightPDF(Email $email, $text)
    {
        $r = $email->add()->flight();

        $this->parseTitleBlock($r, $text);

        $r->general()->noConfirmation();

        $cost = $tax = $booking_fee = $change_fee = $credit = $check_price = null;

        if (preg_match("/[A-Z]{3}-[A-Z]{3}.+\s*.+(?:|[\n\s]+)\(" . $this->opt($this->t("Purchase")) . "\)((?:\n.*?)+)" . $this->opt($this->t("Ticket")) . "\s\d+/m",
            $text, $segBlock)) {
            if (preg_match_all("/\s*.+?\s\d+\s?,\s?" . $this->opt($this->t('Departure date')) . " - [A-z]{3}\s\d{1,2},\s\d{4}\s*[A-Z]{3}-[A-Z]{3},.+" . $this->opt($this->t("Class")) . " \(.+\)/m",
                $segBlock[1], $segments)) {
                foreach ($segments[0] as $key => $segment) {
                    if (preg_match("/\s*(?<airName>.+?)\s(?<airNumber>\d+)\s?,\s?" . $this->opt($this->t('Departure date')) . " - (?<depDate>[A-z]{3}\s\d{1,2},\s\d{4})\s*(?<depCode>[A-Z]{3})-(?<arrCode>[A-Z]{3}), (?<cabin>.+) " . $this->opt($this->t("Class")) . " \((?<class>.+)\)/m",
                        $segment, $seg)) {
                        $s = $r->addSegment();
                        $s->airline()->name($seg['airName'])->number($seg['airNumber']);
                        $s->departure()->day(strtotime($seg['depDate']))->code($seg['depCode'])->noDate();
                        $s->arrival()->code($seg['arrCode'])->noDate();
                        $s->extra()
                            ->cabin(preg_replace("/\s*\/\s*Coach\s*$/", '', $seg['cabin']))
                            ->bookingCode($seg['class']);
                    }
                }
            }

            if (preg_match_all("/Ticket\s(?<ticket>.+)/", $text, $m)) {
                $r->issued()->tickets($m['ticket'], false);
            }

            if (preg_match_all("/" . $this->opt($this->t("Base fare")) . "\s*(?:[A-Z]{3}\s?|.)(?<cost>[\d,.]+)$\s*" . $this->opt($this->t("Taxes & airline fees")) . "\s*(?:[A-Z]{3}\s?|.)(?<tax>[\d,.]+)$/m",
                $text, $m)) {
                $cost = array_sum($m['cost']);
                $tax = array_sum($m['tax']);
            }

            if (preg_match_all("/\s*" . $this->opt($this->t("Air booking fee")) . "\s*(?:[A-Z]{3}\s?|.)(?<booking_fee>\d[\d,.]*)$/m",
                $text, $m)) {
                $booking_fee = array_sum($m['booking_fee']);
            }

            if (preg_match_all("/\s*" . $this->opt($this->t("Airline change fee")) . "\s*(?:[A-Z]{3}\s?|.)(?<change_fee>[\d,.]+)$/m",
                $text, $m)) {
                $change_fee = array_sum($m['change_fee']);
            }

            if (preg_match_all("/\s*" . $this->opt($this->t("Credit")) . "\s*\((?:[A-Z]{3}\s?|.)(?<credit>[\d,.]+)\)$/m",
                $text, $m)) {
                $credit = array_sum($m['credit']);
            }

            if (preg_match("/\s*" . $this->opt($this->t("TOTAL FLIGHT CHARGES")) . "\s*(?<cur>(?:[A-Z]{3}\s?|.))(?<tot>[\d,.]+)$/m",
                $text, $m)) {
                $r->price()->currency($m['cur'])->total(str_replace(",", "", $m['tot']));
                $check_price = $cost + $tax + $booking_fee + $change_fee - $credit;
                $total = (float) str_replace(",", "", $m['tot']);

                if (!empty($check_price) && round($check_price, 3) === round($total, 3)) {
                    if (!empty($tax)) {
                        $r->price()->tax($tax);
                    }

                    if (!empty($cost)) {
                        $r->price()->cost($cost);
                    }

                    if (!empty($booking_fee)) {
                        $r->price()->fee($this->t("Air booking fee"), $booking_fee);
                    }

                    if (!empty($change_fee)) {
                        $r->price()->fee($this->t("Airline change fee"), $change_fee);
                    }
                }
            }
        }
    }

    private function parseHotelPDF(Email $email, $text)
    {
        $r = $email->add()->hotel();

        $this->parseTitleBlock($r, $text);

        $r->general()->noConfirmation();

        if (preg_match("/" . $this->opt($this->t("Hotel Receipt")) . "\s*(?<name>.+)\s*\(" . $this->opt($this->t("Purchase")) . "\)\s*(?<address>.+)$[\s]+" . $this->opt($this->t("Check in:")) . "\s?(?<in>[A-z]{3}\s\d{1,2},\s\d{4})\s" . $this->opt($this->t("Check out:")) . "\s?(?<out>[A-z]{3}\s*\d{1,2},\s*\d{4})/ms",
            $text, $m)) {
            $r->hotel()->name(str_replace(["\n", "", "  "], '', $m['name']))
                ->address($m['address']);
            $r->booked()->checkIn(strtotime(preg_replace("/\s{2,}/", "",
                $m['in'])))->checkOut(strtotime(preg_replace("/\s{2,}/", "", $m['out'])));
        }

        if (preg_match("/\d{1,2}\/\d{1,2}\/\d{4}\s*(?:[A-Z]{3}\s?|.)(?<cost>[\d,.]+)$[\s\n]+\d{1,2}\/\d{1,2}\/\d{4}\s*Taxes and service fees\s*(?:[A-Z]{3}\s?|.)(?<tax>[\d,.]+)$/m",
            $text, $m)) {
            $r->price()->cost($m['cost'])->tax(str_replace(",", "", $m['tax']));
        }

        if (preg_match("/^\s*TOTAL HOTEL CHARGES\s*(?<cur>(?:[A-Z]{3}\s?|.))(?<tot>[\d,.]+)$/m", $text, $m)) {
            $r->price()->currency($m['cur'])->total(str_replace(",", "", $m['tot']));
        }

        return $email;
    }

    private function parseTitleBlock($r, $text)
    {
        if (preg_match("/" . $this->opt($this->t("Today's Date")) . "\n\s*(?<date>[A-z]{3}\s\d{1,2},\s\d{4})\n\s*" . $this->opt($this->t("Purchased")) . "\n\s*" . $this->opt($this->t("Itinerary")) . "\s(?<confNo>\d+)\s*(?<traveller>[A-z\s,]+?)$/m",
            $text, $m)) {
            $r->general()->date(strtotime($m['date']));
            $r->ota()->confirmation($m['confNo']);
            $r->general()->traveller($m['traveller'], true);
        }
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
