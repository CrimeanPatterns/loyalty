<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Payment extends \TAccountChecker
{
    public $mailFiles = "booking/it-67152039.eml";


    private $detectFrom = 'noreply-payments@booking.com';
    private $detectSubject = [
        "en" => [
            "Payment successful for your booking at",
            "Upcoming payment for your booking at",
        ],
        "pt" => [
            "Pagamento processado para a sua reserva em",
            "Próximo pagamento para sua reserva",
            "Pagamento de parcela realizado com sucesso para sua reserva",
            "Próximo pagamento da sua reserva em:",
        ],
        "it" => [
            "Pagamento per la prenotazione presso ",
        ],
        "pl" => [
            "Udana płatność za rezerwację w obiekcie",
        ],
        "fr" => [
            "Paiement à venir pour votre réservation à l’établissement",
        ],
        "ru" => [
            "платеж по бронированию",
        ],
        "es" => [
            "El pago de tu estancia en el",
        ],
        "de" => [
            "Bevorstehende Zahlung für Ihre Buchung in der",
        ],
    ];

    private $detectBody = [
        'en' => ['Payment schedule', 'Payment Schedule'],
        'pt' => ['Plano de pagamento', 'Cronograma de pagamento'],
        'it' => ['Calendario pagamenti'],
        'pl' => ['Plan płatności'],
        'fr' => ['Calendrier des paiements'],
        'ru' => ['График платежей'],
        'es' => ['Plazos de los pagos'],
        'de' => ['Zahlungsplan'],
    ];
    private $lang = 'en';

    private static $dictionary = [
        "en" => [
            //            'Booking Number:' => '',
            //            'Dear ' => '',
            //            'for your reservation at' => '',
            'reservationRe' => 'for your reservation at (?<hotel>.+), (?<checkin>\w+, (?:\d{1,2} \w+|\w+ \d{1,2},) \d{4}) to (?<checkout>\w+, (?:\d{1,2} \w+|\w+ \d{1,2},) \d{4})\.',
            //            'Total payment' => '',
        ],
        "pt" => [
            'Booking Number:'         => 'Número da Reserva:',
            'Dear '                   => ['Estimado(a) ', 'Prezado(a)', 'Olá,'],
            'for your reservation at' => ['total devido pela sua reserva em', 'cobrada uma parcela da sua reserva', 'saldo pendente para sua reserva', 'O próximo pagamento da sua reserva em'],
            'reservationRe'           => 'sua reserva (?:em|-) (?<hotel>.+), de (?<checkin>[^\d\s]+, \d{1,2} de \w+ de \d{4}) a (?<checkout>[^\d\s]+, \d{1,2} de \w+ de \d{4})[,.]',
            'Total payment'           => 'Pagamento total',
        ],
        "it" => [
            'Booking Number:'         => 'Numero di prenotazione:',
            'Dear '                   => 'Gentile ',
            'for your reservation at' => 'totale dovuto per la tua prenotazione presso',
            'reservationRe'           => 'tua prenotazione presso (?<hotel>.+), da (?<checkin>[^\d\s]+ \d{1,2} \w+ \d{4}) a (?<checkout>[^\d\s]+ \d{1,2} \w+ \d{4})\.',
            'Total payment'           => 'Importo totale',
        ],
        "pl" => [
            'Booking Number:'         => 'Numer rezerwacji:',
            'Dear '                   => 'Witamy ',
            'for your reservation at' => 'odjęta od kwoty należnej za rezerwację',
            'reservationRe'           => 'rezerwację w terminie od (?<checkin>[^\d\s]+, \d{1,2} \w+ \d{4}) do (?<checkout>[^\d\s]+ \d{1,2} \w+ \d{4}) w obiekcie (?<hotel>.+?)\.',
            'Total payment'           => 'Łączna kwota płatności',
        ],
        "fr" => [
            'Booking Number:'         => 'Numéro de Réservation:',
            'Dear '                   => 'Bonjour ',
            'for your reservation at' => ['pour votre réservation à l’établissement', 'de votre réservation à l\'établissement'],
            'reservationRe'           => 'votre réservation à l(?:’|\')établissement (?<hotel>.+), (?:qui aura lieu )?du (?<checkin>\w+ \d{1,2} \w+ \d{4}) au (?<checkout>\w+ \d{1,2} \w+ \d{4})\.',
            'Total payment'           => 'Paiement total',
        ],
        "ru" => [
            'Booking Number:'         => 'Номер бронирования:',
            'Dear '                   => 'Добрый день, ',
            'for your reservation at' => ['платеж по следующему бронированию', 'счет оплаты проживания в данном'],
            'reservationRe'           => '(?:платеж по следующему бронированию|данном объекте размещения): (?<hotel>.+), с (?<checkin>\w+, \d{1,2} \w+ \d{4} г\.) по (?<checkout>\w+, \d{1,2} \w+ \d{4} г\.)\.',
            'Total payment'           => 'Paiement total',
        ],
        "es" => [
            'Booking Number:'         => 'Número de reserva:',
            'Dear '                   => 'Estimado/a ',
            'for your reservation at' => ['restado del importe total de tu estancia en'],
            'reservationRe'           => 'restado del importe total de tu estancia en el (?<hotel>.+), que tendrá lugar entre el (?<checkin>\w+, \d{1,2} de \w+ de \d{4}) y el (?<checkout>\w+, \d{1,2} de \w+ de \d{4})\.',
            'Total payment'           => 'Importe total',
        ],
        "de" => [
            'Booking Number:'         => 'Buchungsnummer:',
            'Dear '                   => 'Hallo ',
            'for your reservation at' => ['für Ihre Buchung in der'],
            'reservationRe'           => 'für Ihre Buchung in der Unterkunft (?<hotel>.+?), vom (?<checkin>\w+, \d{1,2}\. \w+ \d{4}) bis (?<checkout>\w+, \d{1,2}\. \w+ \d{4}), steht eine Zahlung an\.',
            'Total payment'           => 'Gesamtbetrag',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        $this->detectBody();

        // Travel Agency
        $email->obtainTravelAgency();
        $awards = array_sum(array_filter(str_replace(',', '', $this->http->FindNodes("//text()[" . $this->eq("Book again") . "]/following::td[" . $this->starts("+") . " and " . $this->contains("points") . "][1]", null, "#\+\s*([\d,]+)\s*points\b#"))));

        if (!empty($awards)) {
            $email->ota()
                ->earnedAwards($awards . ' points');
        }

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

        if ($this->http->XPath->query('//a[contains(@href,"secure.booking.com/content")]')->length === 0) {
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
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking Number:")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\d{5,})\s*$/"))
        ;
        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
            "/^\s*" . $this->opt($this->t("Dear ")) . " ?(\w+(?: \w+)*)[,\.!]\s*$/u");

        if (!empty($traveller)) {
            $h->general()->traveller($traveller, false);
        }

        // Hotel
        $reservation = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]/following::text()[" . $this->contains($this->t("for your reservation at")) . "][1]");
//        $this->logger->debug('$reservation = '.print_r( $reservation,true));
        if (preg_match("/" . $this->t("reservationRe") . "/u", $reservation, $m)) {
            if (!empty($m['hotel'])) {
                $h->hotel()
                    ->name($m['hotel'])
                    ->noAddress()
                ;
            }

            if (!empty($m['checkin'])) {
                $h->booked()->checkIn($this->normalizeDate($m['checkin']));
            }

            if (!empty($m['checkout'])) {
                $h->booked()->checkOut($this->normalizeDate($m['checkout']));
            }
        }

        // Total
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total payment")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
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

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            //Tuesday, 1 September 2020; segunda-feira, 24 de agosto de 2020; mercoledì 26 agosto 2020; Freitag, 30. Juli 2021
            "/^\s*[^\d\s]+,?\s*(\d+)[.]?\s+(?:de\s+)?([^\d\s]+)\s+(?:de\s+)?(\d{4})\s*(?:г\.\s*)?$/",
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
        if (in_array($this->lang, ['pt', 'es'])) {
            $price = str_replace('.', '', $price);
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
            'US$' => 'USD',
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
            'Rp' => 'IDR',
            'zł'  => 'PLN',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
