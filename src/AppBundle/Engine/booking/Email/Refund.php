<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Refund extends \TAccountChecker
{
    public $mailFiles = "booking/it-94833097.eml";

    private $detectFrom = 'noreply-payments@booking.com';

    private $detectSubject = [
        "en" => [
            "We have a refund for you",
        ],
        "ru" => [
            "Возмещение вашей оплаты",
        ],
        "pt" => [
            "Temos um reembolso para você",
        ],
        "fr" => [
            "Informations concernant votre remboursement",
        ],
        "es" => [
            "Información sobre tu reembolso",
        ],
        "de" => [
            "Sie erhalten eine Rückerstattung",
        ],
        "ja" => [
            "返金のお知らせ",
        ],
        "da" => [
            "Refundering til dig",
        ],
        "it" => [
            "C'è un rimborso per te!",
        ],
    ];

    private $detectBody = [
        'en' => ['Refund receipt for your records'],
        'ru' => ['Чек о возврате средств'],
        'pt' => ['Recibo de reembolso para seu controle'],
        'fr' => ['Reçu de remboursement pour vos dossiers'],
        'es' => ['Recibo del reembolso'],
        'de' => ['Rückerstattungsbeleg für Ihre Unterlagen'],
        'ja' => ['返金通知書'],
        'da' => ['Refundering til dig'],
        'it' => ['Ricevuta di rimborso'],
    ];


    private $lang = 'en';

    private static $dictionary = [
        "en" => [
            //'Booking Number:' => '',
            //'Name:' => '',
            'Property Name:' => ['Property Name:', 'Property name:'],
            //            'Address:' => '',
            'Stay Period:' => ['Stay Period:', 'Stay period:'],
        ],
        "ru" => [
            'Booking Number:' => 'Номер бронирования:',
            'Name:'           => 'Имя:',
            'Property Name:'  => 'Название объекта размещения:',
            'Address:'        => 'Адрес:',
            'Stay Period:'    => 'Период пребывания:',
        ],
        "pt" => [
            'Booking Number:' => 'Número da Reserva:',
            'Name:'           => 'Nome:',
            'Property Name:'  => 'Nome da propriedade:',
            'Address:'        => 'Endereço:',
            'Stay Period:'    => 'Período de estadia:',
        ],
        "fr" => [
            'Booking Number:' => 'Numéro de Réservation:',
            'Name:'           => 'Nom:',
            'Property Name:'  => 'Nom de l\'établissement :',
            'Address:'        => 'Adresse:',
            'Stay Period:'    => 'Période de séjour:',
        ],
        "es" => [
            'Booking Number:' => 'Número de reserva:',
            'Name:'           => 'Nombre:',
            'Property Name:'  => 'Nombre del alojamiento:',
            'Address:'        => 'Dirección:',
            'Stay Period:'    => 'Periodo estancia:',
        ],
        "de" => [
            'Booking Number:' => 'Buchungsnummer:',
            'Name:'           => 'Name:',
            'Property Name:'  => 'Name der Unterkunft:',
            'Address:'        => 'Adresse:',
            'Stay Period:'    => 'Zeitraum des Aufenthalts:',
        ],
        "ja" => [
            'Booking Number:' => 'Booking Number:',
            'Name:'           => '名前:',
            'Property Name:'  => '宿泊施設名',
            'Address:'        => '住所:',
            'Stay Period:'    => 'ご滞在期間:',
        ],
        "da" => [
            'Booking Number:' => 'Reservationsnummer:',
            'Name:'           => 'Navn:',
            'Property Name:'  => 'Navn på overnatningsstedet:',
            'Address:'        => 'Adresse:',
            'Stay Period:'    => 'Opholdsperiode:',
        ],
        "it" => [
            'Booking Number:' => 'Numero di prenotazione:',
            'Name:'           => 'Nome:',
            'Property Name:'  => 'Nome della struttura:',
            'Address:'        => 'Indirizzo:',
            'Stay Period:'    => 'Periodo di soggiorno:',
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

        if ($this->http->XPath->query("//text()[" . $this->contains(['Booking.com']) . "]")->length === 0) {
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
            ->confirmation($this->nextTd($this->t("Booking Number:"), "/^\s*(\d{5,})\s*$/"))
            ->traveller($this->nextTd($this->t("Name:"), "/^\s*([[:alpha:] \-]+)\s*$/u"), true)
            ->status('Cancelled')
            ->cancelled()
        ;

        // Hotel
        $h->hotel()
            ->name($this->nextTd($this->t("Property Name:")))
            ->address($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Property Name:'))}]/following::text()[{$this->eq($this->t('Address:'))}])[1]/ancestor::td[1]/following-sibling::td[1]"))
        ;

        // Booked
        $dates = explode(" - ", $this->nextTd($this->t("Stay Period:")));

        if (count($dates) == 2) {
            $h->booked()
                ->checkIn($this->normalizeDate($dates[0]))
                ->checkOut($this->normalizeDate($dates[1]))
            ;
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
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            //Tuesday, 1 September 2020; segunda-feira, 24 de agosto de 2020; mercoledì 26 agosto 2020; Freitag, 18. Juni 2021; søndag den 8. august 2021
            "/^\s*[^\d\s]+(?: den )?,?\s*(\d+)[.]?\s+(?:de\s+)?([^\d\s]+)\s+(?:de\s+)?(\d{4})\s*(?:г\.\s*)?$/u",
            // 2021年8月9日月曜日
            "/^\s*(\d{4})\s*(?:年)\s*(\d+)\s*(?:月)\s*(\d+)\s*(?:日)\D*\s*$/u",
        ];
        $out = [
            "$1 $2 $3",
            "$1-$2-$3",
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
