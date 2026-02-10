<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Receipt extends \TAccountChecker
{
    public $mailFiles = "booking/it-93979917.eml";

    private $detectFrom = 'noreply-payments@booking.com';
    private $detectSubject = [
        "en" => [
            "This is your receipt",
        ],
        "pt" => [
            "Este é seu recibo",
        ],
        "it" => [
            "Questa è la tua ricevuta",
        ],
        "nl" => [
            "Dit is je betaalbewijs",
        ],
        "fr" => [
            "Voici votre reçu",
        ],
        "de" => [
            "Das ist Ihr Zahlungsbeleg",
        ],
        "ja" => [
            "領収書",
        ],
        "ru" => [
            "Ваша квитанция",
        ],
        "lv" => [
            "Šī ir jūsu kvīts",
        ],
        "he" => [
            "זאת הקבלה שלכם",
        ],
        "es" => [
            "Este es tu recibo",
        ],
    ];

    private $detectBody = [
        'en' => ['This is your receipt'],
        'pt' => ['Este é seu recibo', 'Este é o seu recibo'],
        'it' => ['Questa è la tua ricevuta'],
        'nl' => ['Dit is je betaalbewijs'],
        'fr' => ['Voici votre reçu'],
        'de' => ['Das ist Ihr Zahlungsbeleg'],
        'ja' => ['領収書'],
        'ru' => ['Ваша квитанция'],
        'lv' => ['Šī ir jūsu kvīts'],
        'he' => ['זאת הקבלה שלכם'],
        'es' => ['Este es tu recibo'],
    ];


    private $lang = 'en';

    private static $dictionary = [
        "en" => [
            //            'Booking number' => '',
            //            'Name' => '',
            //            'Property name' => '',
            //            'Property address' => '',
            //            'Check-in' => '',
            //            'Check-out' => '',
            //            'Amount paid on' => '',
        ],
        "pt" => [
            'Booking number'   => ['Número da reserva', 'Número da reserva'],
            'Name'             => 'Nome',
            'Property name'    => ['Nome da acomodação', 'Nome do alojamento'],
            'Property address' => ['Endereço da acomodação', 'Morada da propriedade'],
            'Check-in'         => ['Entrada', 'Check-in'],
            'Check-out'        => ['Saída', 'Check-out'],
            'Amount paid on'   => ['Valor pago em', 'Valor pago a'],
        ],
        "it" => [
            'Booking number'   => 'Numero prenotazione',
            'Name'             => 'Nome',
            'Property name'    => 'Nome della struttura',
            'Property address' => 'Indirizzo della struttura',
            'Check-in'         => 'Arrivo',
            'Check-out'        => 'Partenza',
            'Amount paid on'   => 'Importo pagato in',
        ],
        "nl" => [
            'Booking number'   => ['Boekingsnummer'],
            'Name'             => 'Naam',
            'Property name'    => 'Naam accommodatie',
            'Property address' => 'Adres accommodatie',
            'Check-in'         => 'Inchecken',
            'Check-out'        => 'Uitchecken',
            'Amount paid on'   => 'Bedrag betaald op',
        ],
        "fr" => [
            'Booking number'   => ['Numéro De Réservation', 'Numéro de réservation'],
            'Name'             => 'Prénom et nom',
            'Property name'    => 'Nom de l\'établissement',
            'Property address' => 'Adresse de l\'établissement',
            'Check-in'         => 'Arrivée',
            'Check-out'        => 'Départ',
            'Amount paid on'   => 'Montant payé le',
        ],
        "de" => [
            'Booking number'   => ['Buchungsnummer'],
            'Name'             => 'Name',
            'Property name'    => 'Name der Unterkunft',
            'Property address' => 'Adresse der Unterkunft',
            'Check-in'         => 'Anreise',
            'Check-out'        => 'Abreise',
            'Amount paid on'   => 'bezahlter Betrag',
        ],
        "ja" => [
            'Booking number'   => ['予約番号'],
            'Name'             => '氏名',
            'Property name'    => '宿泊施設名',
            'Property address' => '所在地',
            'Check-in'         => 'チェックイン',
            'Check-out'        => 'チェックアウト',
            'Amount paid on'   => 'に支払った額',
        ],
        "ru" => [
            'Booking number'   => ['Номер бронирования'],
            'Name'             => 'Имя',
            'Property name'    => 'Название объекта размещения',
            'Property address' => 'Адрес объекта размещения',
            'Check-in'         => 'Заезд',
            'Check-out'        => 'Отъезд',
            'Amount paid on'   => 'Оплачено ',
        ],
        "lv" => [
            'Booking number'   => ['Rezervējuma numurs'],
            'Name'             => 'Vārds un uzvārds',
            'Property name'    => 'Naktsmītnes nosaukums',
            'Property address' => 'Naktsmītnes adrese',
            'Check-in'         => 'Reģistrēšanās',
            'Check-out'        => 'Izrakstīšanās',
            'Amount paid on'   => 'Summa, kas samaksāta šajā datumā',
        ],
        "he" => [
            'Booking number'   => ['מספר הזמנה'],
            'Name'             => 'שם',
            'Property name'    => 'שם מקום האירוח',
            'Property address' => 'כתובת מקום האירוח',
            'Check-in'         => 'צ\'ק-אין',
            'Check-out'        => 'צ\'ק-אאוט',
            'Amount paid on'   => 'סכום ששולם ב',
        ],
        "es" => [
            'Booking number'   => ['Número de reserva'],
            'Name'             => 'Nombre',
            'Property name'    => 'Nombre del alojamiento',
            'Property address' => 'Dirección del alojamiento',
            'Check-in'         => 'Entrada',
            'Check-out'        => 'Salida',
            'Amount paid on'   => 'Importe pagado el',
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
        if ($this->http->XPath->query('//a[contains(@href,"booking.com")] | //text()[contains(.,"booking.com")] | //img[@alt = "Booking.com"]')->length === 0) {
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
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking number")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\d{5,})\s*$/"))
            ->traveller($this->nextTd($this->t("Name"), "/^\s*([[:alpha:] \-]+)\s*$/u"), true);

        // Hotel
        $h->hotel()
            ->name($this->nextTd($this->t("Property name")))
            ->address($this->nextTd($this->t("Property address")))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextTd($this->t("Check-in"))))
            ->checkOut($this->normalizeDate($this->nextTd($this->t("Check-out"))))
        ;

        // Total
        $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Amount paid on")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");
        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Amount paid on")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");
        }
        if (empty($total)) {
            $total = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Amount paid on")) . "]/ancestor::td[1]/following::td[not(.//td)][normalize-space()][1]");
        }
        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#u", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#u", $total, $m)) {
            $currency = $this->currency($m['curr']);
            $h->price()
                ->total($this->amount($m['amount'], $currency))
                ->currency($currency)
            ;
        } else {
            $h->price()
                ->total(null);
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
        $result = $this->http->FindSingleNode("(.//text()[{$this->eq($field)}])[1]/ancestor::td[1]/following-sibling::td[1]", $root, true, $regexp);
        if ($result === null) {
            $result = $this->http->FindSingleNode("((.//text()[{$this->eq($field)}])[1]/ancestor::*[{$this->eq($field)}]/following-sibling::*[normalize-space()][1])[1]", $root, true, $regexp);
        }
        return $result;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            //Tuesday, 1 September 2020; segunda-feira, 24 de agosto de 2020; mercoledì 26 agosto 2020
            "/^\s*[^\d\s,]+(?: [^\d\s,]+)?,?\s*(\d+)[\.]?\s+(?:de\s+)?([^\d\s]+)\s+(?:de\s+)?(\d{4})\s*(?:г\.\s*)?$/u",
            // 2021年8月9日月曜日
            "/^\s*(\d{4})\s*(?:年)\s*(\d+)\s*(?:月)\s*(\d+)\s*(?:日)\D*\s*$/u",
            // trešdiena, 2021. gada 21. jūlijs
            "/^\s*[^\d\s]+[,\s]+(\d{4})\.\s*gada\s+(\d+)\.\s*([[:alpha:]]+)\s*$/u",

        ];
        $out = [
            "$1 $2 $3",
            "$1-$2-$3",
            "$2 $3 $1",
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price, $currency)
    {
        $price = PriceHelper::parse($price, $currency);

        return $price;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'zł'  => 'PLN',
            'R$'  => 'BRL',
            '€'   => 'EUR',
            'US$' => 'USD',
            '£'   => 'GBP',
            '₪'   => 'ILS',
            'руб.' => 'RUB',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }
}
