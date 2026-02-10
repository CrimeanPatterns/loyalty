<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Schema\Parser\Email\Email;

class PurchaseFlightSucceed extends \TAccountChecker
{
    public $mailFiles = "asia/it-50815225.eml, asia/it-50872040.eml, asia/it-50951587.eml, asia/it-50872041.eml";

    private static $detectors = [
        'en' => ["Thank you for purchasing"],
    ];

    private static $dictionary = [
        'en' => [
            "Booking reference:" => "Booking reference:",
            "Payment details"    => "Payment details",
            "Lounge Pass"        => ["Lounge Pass", "Paid Seat"],
            "Price"              => ["Price", "Total"],
        ],
    ];

    private $from = "@cathaypacific.com";

    private $body = "cathaypacific.com";

    private $subject = ["Purchase Succeed - Booking reference"];

    private $lang;

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
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
        $text = $this->http->Response['body'];

        if (stripos($text, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $email->setType("PurchaseFlightSucceed");
        $this->parseEmail($email);

        return $email;
    }

    private function parseEmail(Email $email)
    {
        if (!$this->detectBody()) {
            return false;
        }
        $it = [];
        $r = $email->add()->flight();

        $confNo = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Booking reference:')) . "]/following::text()[1]",
            null, true, "/^([A-Z\d]+)$/");

        if (!empty($confNo)) {
            $r->general()->confirmation($confNo, trim($this->t('Booking reference:'), ':'));
        }

        $status = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Your purchase has been confirmed')) . "]");

        if (!empty($status)) {
            $r->general()->status("confirmed");
        }

        // $total = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Total')) . "]/following-sibling::b[1]");
        // total for seats, not for reservation
        // if (!empty($total)) {
        //     if (preg_match("/^([A-Z]{3})\s(\d+[,.\d]+)$/", $total, $m)) {
        //         $r->price()
        //             ->currency($m[1])
        //             ->total($m[2]);
        //     }
        // }

        $xpath = "//table[" . $this->starts($this->t("Lounge Pass")) . "]/following-sibling::table[not(" . $this->contains($this->t("Price")) . ")]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $seg) {
            $s = $r->addSegment();

            $airline = $this->http->FindSingleNode("./descendant::div[1]", $seg);

            if (!empty($airline)) {
                if (preg_match("/[A-z]{3}\s(\d{1,2}\s[A-z]{3}\s\d{4})\s\|\s([A-Z]{2})(\d{3,5})/",
                    $airline, $m)) {
                    $s->departure()
                        ->noCode()
                        ->noDate()
                        ->day(strtotime($m[1]));
                    $s->arrival()
                        ->noCode()
                        ->noDate();
                    $s->airline()
                        ->name($m[2])
                        ->number($m[3]);
                }
            }

            $arrdepName = $this->http->FindSingleNode("./descendant::div[2]", $seg);

            if (!empty($arrdepName)) {
                if (preg_match("/^(.+)\sto\s(.+)$/",
                    $arrdepName, $m)) {
                    $s->departure()
                        ->name($m[1]);
                    $s->arrival()
                        ->name($m[2]);
                }
            }

            $aic = $this->http->FindNodes("./following-sibling::table", $seg);

            foreach ($aic as $k => $item) {
                if (preg_match('/Passenger\s(.+)\s[A-z]+?\sseat\s([\dA-Z]+)\s/', $item,
                        $m) || preg_match("/^Passenger\s(.+?)\sLounge Pass/", $item, $m)) {
                    if (!empty($m[1])) {
                        $it['pax'][$k] = $m[1];
                    }

                    if (!empty($m[2])) {
                        $s->extra()->seat($m[2]);
                    }
                } else {
                    break;
                }
            }
        }

        if (!empty($it['pax'])) {
            $r->general()->travellers(array_unique($it['pax']), true);
        }

        return $email;
    }

    private function detectBody()
    {
        foreach (self::$detectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Booking reference:"], $words["Payment details"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking reference:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Payment details'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
