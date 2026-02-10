<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingCancellation extends \TAccountChecker
{
    public $mailFiles = "british/it-50908602.eml";

    private static $detectors = [
        'en' => [
            "Your British Airways flight booking has been cancelled.",
            "Your customer's British Airways flight booking has been cancelled."
        ],
    ];
    private static $dictionary = [
        'en' => [
            "Booking reference:" => "Booking reference:",
            "Ticket Number(s)"   => ["Ticket Number(s)"],
            "Your British Airways flight booking has been cancelled."   => [
                "Your British Airways flight booking has been cancelled.",
                "Your customer's British Airways flight booking has been cancelled."
            ],
        ],
    ];
    private $from = ["@contact.britishairways.com", "ba.custsvcs@email.ba.com"];

    private $body = "British Airways";

    private $subject = ["BA confirmation of cancellation and refund: Ref."];

    private $lang;

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

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
        foreach ($this->from as $re) {
            if (stripos($from, $re) !== false) {
                return true;
            }
        }

        return false;
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

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $email->setType("BookingCancellation");
        $this->parseEmail($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Booking reference:"], $words["Ticket Number(s)"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking reference:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Ticket Number(s)'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        if (!$this->detectBody()) {
            return false;
        }

        $r = $email->add()->flight();

        $confNo = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Booking reference:')) . "]/following::text()[1]",
            null, true, "/^([A-Z\d]+)$/");

        if (!empty($confNo)) {
            $r->general()->confirmation($confNo, $this->t('Booking reference:'));
        }

        $pax = array_unique($this->http->FindNodes("(//text()[" . $this->starts($this->t('Passengers')) . "])[1]/ancestor::table[1]/descendant::td[not(" . $this->starts($this->t('Passengers')) . ")]"));

        if (!empty($pax)) {
            $r->general()->travellers($pax, true);
        }

        $ticketNo = array_unique($this->http->FindNodes("//text()[" . $this->starts($this->t('Ticket Number(s)')) . "]/ancestor::table[1]/descendant::td[not(" . $this->starts($this->t('Ticket Number(s)')) . ")]"));

        if (!empty($ticketNo)) {
            $r->issued()->tickets($ticketNo, false);
        }

        if ($this->http->XPath->query("//text()[" . $this->starts($this->t('Your British Airways flight booking has been cancelled.')) . "]")->length > 0) {
            $r->general()->status("cancelled");
            $r->general()->cancelled();
        }

        $tPrice = $this->http->FindNodes("//text()[" . $this->starts($this->t('Total refund due')) . "]/ancestor::tr[1]/descendant::td");
        $price = $this->http->FindNodes("//text()[" . $this->starts($this->t('Total refund due')) . "]/following::tr[1]/descendant::td");

//        if (!empty($price) && !empty($tPrice)) {
//            $total = $price[array_search("Total refund due", $tPrice)];
//
//            if (!empty($total)) {
//                $r->price()
//                    ->total($this->priceNormalization($total)['sum'])
//                    ->currency($this->priceNormalization($total)['cur']);
//            }
//            $tax = $price[array_search("Tax refund", $tPrice)];
//
//            if (!empty($tax)) {
//                $r->price()
//                    ->tax($this->priceNormalization($tax)['sum']);
//            }
//        }

        return $email;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function priceNormalization($sum)
    {
        if (preg_match("/^([A-Z]{3})\s(\d+[\d,.]+)$/", $sum, $m)) {
            return ["sum" => $m[2], "cur" => $m[1]];
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
