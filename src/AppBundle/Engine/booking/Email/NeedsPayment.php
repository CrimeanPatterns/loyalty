<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NeedsPayment extends \TAccountChecker
{
    public $mailFiles = "booking/it-107211386.eml";

    private $detectFrom = 'noreply-payments@booking.com';
    private $detectSubject = [
        "en" => [
            "hours to pay for your booking at",
        ],
        "it" => [
            "ore di tempo per pagare la prenotazione per",
        ],
        "es" => [
            "horas para pagar tu reserva en",
        ],
        "ru" => [
            "часа, чтобы оплатить бронирование в",
        ],
    ];

    private $detectBody = [
        'en' => ['Your booking is on hold and needs payment'],
        'it' => ['La prenotazione che hai in sospeso è ancora da pagare'],
        'es' => ['Debes pagar la reserva que tienes guardada'],
        'ru' => ['Это бронирование нужно оплатить, если вы не хотите его потерять'],
    ];

    private $lang = 'en';

    private static $dictionary = [
        "en" => [
            //            'Dear ' => '',
            //            'Confirmation:' => '',
            //            'Check-in:' => '',
            //            'Check-out:' => '',
            //            'Room' => '',
        ],
        "it" => [
            'Dear '         => 'Ciao ',
            'Confirmation:' => 'Conferma:',
            'Check-in:'     => 'Arrivo:',
            'Check-out:'    => 'Partenza:',
            'Room'          => 'Camera',
        ],
        "es" => [
            'Dear '         => 'Hola,',
            'Confirmation:' => 'Confirmación:',
            'Check-in:'     => 'Entrada:',
            'Check-out:'    => 'Salida:',
            'Room'          => 'Habitación',
        ],
        "ru" => [
            'Dear '         => 'Здравствуйте,',
            'Confirmation:' => 'Номер подтверждения:',
            'Check-in:'     => 'Заезд',
            'Check-out:'    => 'Отъезд',
            'Room'          => 'номер',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectBody();

        // Travel Agency
        $email->obtainTravelAgency();

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
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if ($this->http->XPath->query("//a[contains(@href, 'secure.booking.com/mybooking') or contains(@href, 'secure.booking.com/myreservations')]")->length === 0) {
            return false;
        }

        return $this->detectBody();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation:")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\d{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
                "/^\s*" . $this->opt($this->t("Dear ")) . " ?([[:alpha:] \-]+)[,\.!:]\s*$/u"), false)
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-in:")) . "]/preceding::text()[normalize-space()][1]/ancestor::td[1]"))
            ->noAddress()
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-in:")) . "]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-out:")) . "]/following::text()[normalize-space()][1]")))
        ;

        $rooms = $this->http->XPath->query("//text()[" . $this->eq($this->t("Check-out:")) . "]/following::tr[normalize-space()][position()<7][count(td[normalize-space()]) = 2]"
            . "[descendant::text()[normalize-space()][1][" . $this->contains($this->t("Room")) . "]]");

        foreach ($rooms as $root) {
            if (preg_match("/^\s*([^\s\d]{1,5}\s*\d[\d,. ]*|\d[\d,. ]*\s?[^\s\d]{1,5})\s*$/u", $this->http->FindSingleNode("td[normalize-space()][2]", $root))) {
                $h->addRoom()->setType($this->http->FindSingleNode("td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root));
            }
        }

        return $email;
    }

    private function detectBody()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function nextTd($field, $regexp = null, $root = null)
    {
        return $this->http->FindSingleNode("(.//text()[{$this->eq($field)}])[1]/ancestor::td[1]/following-sibling::td[1]", $root, true, $regexp);
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug($str);
        $in = [
            //Tuesday, 1 September 2020; segunda-feira, 24 de agosto de 2020; mercoledì 26 agosto 2020
            "/^\s*[^\d\s]+,?\s*(\d+)\s+(?:de\s+)?([^\d\s]+)\s+(?:de\s+)?(\d{4})\s*(?:г\.\s*)?$/",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function getField($text, $regexp = null)
    {
        $result = $this->http->FindSingleNode("//text()[" . $this->eq($text) . "]/ancestor::*[self::td or self::th][1]/following-sibling::*[1]", null, true, $regexp);

        if (empty($result)) {
            $result = $this->http->FindSingleNode("//text()[" . $this->eq($text) . "]/following::text()[normalize-space()][1]", null, true, $regexp);
        }

        return $result;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        if (in_array($this->lang, ['pt'])) {
            $price = str_replace(',', '.', $price);
        }
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'R$' => 'BRL',
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
