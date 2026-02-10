<?php

namespace AwardWallet\Engine\edreams\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NotFinishCar extends \TAccountChecker
{
    public $mailFiles = "edreams/it-14670759.eml, edreams/it-16950507.eml, edreams/it-17302001.eml, edreams/it-58631526.eml";

    private static $detectHeaders = [
        'edreams' => [
            'from'    => 'email@res.email-edreams.com',
            'subject' => [
                'en' => 'Your eDreams Quotation - Ref:',
                'pt' => 'O seu orçamento da eDreams - Ref:',
                'it' => 'Il tuo preventivo con eDreams - Ref:',
            ],
        ],
        'rentalcars' => [
            'from'    => '.rentalcars.com',
            'subject' => [
                'en' => 'Your Rentalcars.com Quotation - Ref:',
                'pt' => 'Orçamento Rentalcars.com - Ref:',
                'ko' => 'Rentalcars.com 고객님 견적요금 - 조회번호:',
            ],
        ],
        'booking' => [
            'from'    => 'cars.booking.com',
            'subject' => [
                'en' => 'Your Booking.com Quotation - Ref:',
                'es' => 'Tenemos un coche para ti... ¿Lo quieres?',
                'pt' => 'Orçamento Booking.com - Ref:',
            ],
        ],
    ];

    private $detectText = [
        'en' => ['didn\'t finish your booking', 'your quote for your trip to'],
        'pt' => ['não finalizou a sua reserva', 'não conseguiu concluir a reserva'],
        'it' => ['non hai completato la procedura di prenotazione'],
        'ko' => ['예약을 아직안하셨나요'],
        'es' => [', no ha finalizado su reserva?'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            //			"Pick up:" => "",
            //			"Drop off:" => "",
            //			"Date & Time :" => "",
            //			"Book Now" => "",
            //			"Today's Price" => "",
            //			"didn't finish your booking" => "",
            "Amount Due:" => ["Amount Due:", "Car Hire Charge:"], // order is important
            //			"saved quote" => "",
        ],
        'pt' => [
            "Pick up:"                   => ["Levantamento:", "Retirada:"],
            "Drop off:"                  => "Devolução:",
            "Date & Time :"              => ["Data & Hora :", "Data e horário: :"],
            "Book Now"                   => ["Reserve já", "Reservar"],
            "Today's Price"              => "Preço de Hoje",
            "didn't finish your booking" => ["não finalizou a sua reserva", "não conseguiu concluir a reserva"],
            "Amount Due:"                => ["Preço do seu aluguer:"], // order is important
            "saved quote"                => "nós guardamos o seu orçamento",
        ],
        'it' => [
            "Pick up:"                   => "Ritiro:",
            "Drop off:"                  => "Riconsegna:",
            "Date & Time :"              => "Data e Orari :",
            "Book Now"                   => "Prenota ora",
            "Today's Price"              => "Il Prezzo di Oggi",
            "didn't finish your booking" => "non hai completato la procedura di prenotazione",
            "Amount Due:"                => ["Totale:", "Spesa per il noleggio auto:"], // order is important
            "saved quote"                => "abbiamo salvato un preventivo",
        ],
        'ko' => [
            "Pick up:"                   => "인수:",
            "Drop off:"                  => "반납",
            "Date & Time :"              => "날짜 및 시간 :",
            "Book Now"                   => "지금 예약",
            "Today's Price"              => "특별가",
            "didn't finish your booking" => "예약을 아직안하셨나요",
            "Amount Due:"                => ["차량 대여요금:"], // order is important
            "saved quote"                => "저장되었습니다",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (self::detectEmailByBody($parser) === true) {
            $email->setIsJunk(true);
        }

        foreach ($this->detectText as $lang => $detectText) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectText) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (preg_match("# - Ref:\s*([\dA-Z]{5,})#", $parser->getSubject(), $m)) {
            $email->ota()->confirmation($m[1]);
        }
        //		$this->car($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (self::detectEmailByHeaders($parser->getHeaders()) === false) {
            return false;
        }

        foreach ($this->detectText as $lang => $detectText) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectText) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectHeaders as $dh) {
            if (isset($dh['subject']) && $this->striposAll($headers['subject'], $dh['subject']) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectHeaders as $dh) {
            if (isset($dh['from']) && $this->striposAll($from, $dh['from']) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 0;
//        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectHeaders);
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function car(Email $email)
    {
        $r = $email->add()->rental();

        // General
        $r->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[" . $this->contains($this->t("didn't finish your booking")) . "]", null, true, "#(.+),\s*" . $this->preg_implode($this->t("didn't finish your booking")) . "#"))
            ->status($this->t("saved quote"));

        // Price
        if (is_array($this->t("Amount Due:"))) {
            foreach ($this->t("Amount Due:") as $value) {
                $price = $this->http->FindSingleNode("//text()[" . $this->eq($value) . "]/ancestor::td[1]", null, true, "#:\s*(.+)#");

                if (!empty($price)) {
                    $r->price()
                        ->total($this->amount($price))
                        ->currency($this->currency($price));

                    break;
                }
            }
        }
        //Pick Up
        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Pick up:")) . "]/ancestor::td[1]", null, true, "#" . $this->preg_implode($this->t("Pick up:")) . "\s*(.+)#u"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Drop off:")) . "]/preceding::text()[" . $this->eq($this->t("Date & Time :")) . "]/ancestor::td[1]", null, true, "#" . $this->preg_implode($this->t("Date & Time :")) . "\s*(.+)#")));

        // Drop Off
        $r->dropoff()
            ->location($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Drop off:")) . "]/ancestor::td[1]", null, true, "#" . $this->preg_implode($this->t("Drop off:")) . "\s*(.+)#u"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Drop off:")) . "]/following::text()[" . $this->eq($this->t("Date & Time :")) . "]/ancestor::td[1]", null, true, "#" . $this->preg_implode($this->t("Date & Time :")) . "\s*(.+)#")));

        $r->car()
            ->image($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Book Now")) . "]/ancestor::th[" . $this->contains($this->t("Today's Price")) . "][1]/preceding-sibling::th[last()]//img[1]/@src[contains(.,'//www.')])[1]"))
            ->model($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Book Now")) . "]/ancestor::th[" . $this->contains($this->t("Today's Price")) . "][1]/preceding-sibling::th[1]//descendant::td[normalize-space()])[1]"))
            ->type(trim(
                $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Book Now")) . "]/ancestor::th[" . $this->contains($this->t("Today's Price")) . "][1]/preceding-sibling::th[1]//descendant::tr[normalize-space()])[1]/following-sibling::tr[normalize-space()][1]") . ', ' .
                implode(", ", $this->http->FindNodes("(//text()[" . $this->eq($this->t("Book Now")) . "]/ancestor::th[" . $this->contains($this->t("Today's Price")) . "][1]/preceding-sibling::th[1]//descendant::tr[normalize-space()])[1]/following-sibling::tr[normalize-space()][2]//text()[normalize-space()]")), ', '));

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || empty(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($str)
    {
        //	    $this->logger->debug($str);
        $in = [
            '#^\s*([^\d\s]+)\s+(\d{1,2})\s+(\d{4}) - (\d+:\d+)\s*$#u', //Aug 5 2017 - 20:30
            '#^\s*(\d{1,2})월\s+(\d{1,2})\s+(\d{4}) - (\d+:\d+)\s*$#u', //6월 22 2018 - 10:00
        ];
        $out = [
            '$2 $1 $3, $4',
            '$2.$1.$3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function striposAll($text, $needle): bool
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

    private function eq($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'normalize-space(' . $text . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'starts-with(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₩'=> 'KRW',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
