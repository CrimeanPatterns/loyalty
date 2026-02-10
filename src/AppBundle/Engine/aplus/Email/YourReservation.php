<?php

namespace AwardWallet\Engine\aplus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "aplus/it-1663972.eml, aplus/it-1664089.eml, aplus/it-1665122.eml, aplus/it-1665806.eml, aplus/it-1666719.eml, aplus/it-1684360.eml, aplus/it-1696307.eml, aplus/it-1698536.eml, aplus/it-1840578.eml, aplus/it-1919700.eml, aplus/it-2012209.eml, aplus/it-2012211.eml, aplus/it-2040457.eml, aplus/it-2312857.eml, aplus/it-2870755.eml, aplus/it-3017807.eml, aplus/it-3074296.eml, aplus/it-3129287.eml, aplus/it-3129288.eml, aplus/it-3129297.eml, aplus/it-3129898.eml, aplus/it-411282270.eml, aplus/it-5.eml";

    public $lang = "en";
    private $reFrom = ["accorhotels.reservation@accor.com", 'all.reservation@accor.com'];

    private $reSubject = [
        "en" => "Your reservation",
        'Cancellation confirmation (N°',
        "de" => "Ihre Buchung",
        "pt" => "Sua reserva",
        "A sua reserva Nº",
        "Número de anulação",
        'Confirmação de cancelamento (Nº',
        'Confirmação do cancelamento (Nº',
        "nl" => "Uw reservering",
        "es" => "Su reserva",
        "fr" => "Votre réservation",
        "ru" => "Ваше бронирование",
        "it" => "La tua prenotazione N°",
    ];

    private static $dictionary = [
        "en" => [
            "Confirmation of your cancellation" => ["Confirmation of your cancellation", "Your cancellation has been registered", 'This reservation was cancelled on'],
            // "Cancellation number" => "",
            "Your reservation" => "Your reservation",
            // "Reservation number :" => "",
            "Tel :" => ["Tel :", "Tel:"],
            // "Reservation made in the name of :" => "",
            // "Dates of stay :" => "",
            // "from " => "",
            // " to " => "",
            // "Number of persons :" => "",
            // " adult" => "",
            // " child" => "",
            // "Bedroom" => "",
            // "Rate" => "",
            "The amount to be paid at the hotel is" => [
                "The amount to be paid at the hotel is",
                "Total amount including tax",
                "The amount prepaid is",
            ],
            "Cancellation policy :" => ["Cancellation policy :", "Cancellation Policy :"],
            // "Check in Policy :" => "",
            // "Check out Policy :" => "",
        ],
        "de" => [
            // "Confirmation of your cancellation" => "",
            // "Cancellation number" => "",
            "Your reservation"                  => "Ihre Buchung",
            "Reservation number :"              => "Buchungsnummer :",
            "Tel :"                             => "Tel :",
            "Reservation made in the name of :" => "Reservierung erfolgt auf den Namen :",
            "Dates of stay :"                   => "Aufenthaltsdaten :",
            "from "                             => "vom ",
            " to "                              => " bis ",
            "Number of persons :"               => "Anzahl Personen :",
            " adult"                            => " Erwachsene",
            // " child" => "",
            "Bedroom"                               => "Zimmer",
            "Rate"                                  => "Tarif",
            "The amount to be paid at the hotel is" => "Der im Hotel zu zahlende Betrag beläuft sich auf",
            "Cancellation policy :"                 => "Stornierungskonditionen :",
            "Check in Policy :"                     => "Anreisezeit :",
            "Check out Policy :"                    => "Abreisezeit :",
        ],
        "pt" => [
            "Confirmation of your cancellation" => ["Confirmação de seu cancelamento", "Esta reserva foi cancelada em",
                'Confirmação da anulação', ],
            "Cancellation number"                   => ["Número de cancelamento", "Número de anulação"],
            "Your reservation"                      => ["Sua reserva", "A sua reserva"],
            "Reservation number :"                  => ["Número da reserva :", "Número de reserva :"],
            "Tel :"                                 => ["Tel.:", "Tel :"],
            "Reservation made in the name of :"     => ["Reserva feita em nome de :", "Reserva efetuada em nome de :"],
            "Dates of stay :"                       => ["Datas da hospedagem :", "Datas de estadia :"],
            "from "                                 => "de ",
            " to "                                  => " a ",
            "Number of persons :"                   => "Número de pessoas :",
            " adult"                                => " adulto",
            " child"                                => " criança",
            "Bedroom"                               => "Quarto",
            "Rate"                                  => "Tarifa",
            "The amount to be paid at the hotel is" => ["O valor a ser pago no hotel é de", "O valor a pagar no hotel é"],
            "Cancellation policy :"                 => ["Atraso no cancelamento :", "Prazo de cancelamento :"],
            "Check in Policy :"                     => ["Politica de chegada :", "Política de check-in :"],
            "Check out Policy :"                    => ["Politica de saida :", "Política de check-out :"],
        ],
        "nl" => [
            // "Confirmation of your cancellation" => "",
            // "Cancellation number" => "",
            "Your reservation"                      => "Uw reservering",
            "Reservation number :"                  => "Reserveringsnummer :",
            "Tel :"                                 => "Tel :",
            "Reservation made in the name of :"     => "Gereserveerd op naam van :",
            "Dates of stay :"                       => "Verblijfsdata :",
            "from "                                 => "van ",
            " to "                                  => " t/m ",
            "Number of persons :"                   => "Aantal personen :",
            " adult"                                => " volwassene",
            " child"                                => " kind",
            "Bedroom"                               => "Kamer",
            "Rate"                                  => "Tarief",
            "The amount to be paid at the hotel is" => ["Het vooruitbetaalde bedrag is", "Het bedrag dat in het hotel moet worden betaald is"],
            "Cancellation policy :"                 => "Annuleringsvoorwaarden :",
            "Check in Policy :"                     => "Aankomsttijd :",
            "Check out Policy :"                    => "Vertrektijd :",
        ],
        "es" => [
            // "Confirmation of your cancellation" => "",
            // "Cancellation number" => "",
            "Your reservation"                      => "Su reserva",
            "Reservation number :"                  => "Número de reserva :",
            "Tel :"                                 => "Tel :",
            "Reservation made in the name of :"     => "Reserva realizada a nombre de: :",
            "Dates of stay :"                       => "Fechas de la estancia :",
            "from "                                 => "del ",
            " to "                                  => " al ",
            "Number of persons :"                   => "Número de personas :",
            " adult"                                => " adulto",
            " child"                                => " niño",
            "Bedroom"                               => "Habitación",
            "Rate"                                  => "Tarifa",
            "The amount to be paid at the hotel is" => "El importe que debe abonar en el hotel son",
            "Cancellation policy :"                 => "Tiempo limite de cancelación :",
            "Check in Policy :"                     => "Política de entrada :",
            "Check out Policy :"                    => "Política de salida :",
        ],
        "fr" => [
            // "Confirmation of your cancellation" => "",
            // "Cancellation number" => "",
            "Your reservation"                  => "Votre réservation",
            "Reservation number :"              => "Numéro de réservation :",
            "Tel :"                             => "Tél :",
            "Reservation made in the name of :" => "Réservation effectuée au nom de :",
            "Dates of stay :"                   => "Dates du séjour :",
            "from "                             => "du ",
            " to "                              => " au ",
            "Number of persons :"               => "Nombre de personnes :",
            " adult"                            => " adulte",
            // " child" => "",
            "Bedroom"                               => "Chambre",
            "Rate"                                  => "Tarif",
            "The amount to be paid at the hotel is" => "Le montant à régler sur place est de",
            "Cancellation policy :"                 => "Délai d'annulation :",
            "Check in Policy :"                     => "Heure d'enregistrement :",
            "Check out Policy :"                    => "Heure de départ :",
        ],
        "ru" => [
            // "Confirmation of your cancellation" => "",
            // "Cancellation number" => "",
            "Your reservation"                  => "Ваше бронирование",
            "Reservation number :"              => "Номер бронирования :",
            "Tel :"                             => "Телефон:",
            "Reservation made in the name of :" => "Бронирование на имя :",
            "Dates of stay :"                   => "Даты пребывания :",
            "from "                             => "с ",
            " to "                              => " по ",
            "Number of persons :"               => "Число гостей :",
            " adult"                            => " взрослых",
            // " child" => "",
            "Bedroom"                               => "Номер",
            "Rate"                                  => "Тариф",
            "The amount to be paid at the hotel is" => "Сумма, которую необходимо будет уплатить в отеле, составляет",
            "Cancellation policy :"                 => "Отмена при задержке :",
            "Check in Policy :"                     => "Отмена при задержке :",
            "Check out Policy :"                    => "Политика расчетного часа :",
        ],
        "it" => [
            // "Confirmation of your cancellation" => "",
            // "Cancellation number" => "",
            "Your reservation"                      => "La tua prenotazione",
            "Reservation number :"                  => "Numero di prenotazione :",
            "Tel :"                                 => "Tel :",
            "Reservation made in the name of :"     => "Prenotazione effettuata a nome di :",
            "Dates of stay :"                       => "Date del soggiorno :",
            "from "                                 => "dal ",
            " to "                                  => " al ",
            "Number of persons :"                   => "Numero di persone :",
            " adult"                                => " adulto",
            " child"                                => " bambin",
            "Bedroom"                               => "Camera",
            "Rate"                                  => "Tariffa",
            "The amount to be paid at the hotel is" => "L'importo da pagare in hotel è pari a",
            "Cancellation policy :"                 => "Orario limite di annullamento :",
            "Check in Policy :"                     => "Orario d'arrivo :",
            "Check out Policy :"                    => "Orario di partenza :",
        ],
    ];

    public function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->nextText($this->t("Reservation number :")))
            ->traveller($this->nextText($this->t("Reservation made in the name of :")))
            ->cancellation(implode(" ", array_unique($this->http->FindNodes("//td[" . $this->eq($this->t("Cancellation policy :")) . "]/following-sibling::td[1]"))), true, true)
        ;

        if (!empty($this->http->FindSingleNode("(//node()[{$this->contains($this->t('Confirmation of your cancellation'))}])[1]"))) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();

            $number = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Cancellation number'))}])[1]/following::text()[normalize-space()][1]",
                null, true, "/^\s*:\s*(\d+)$/");

            if (!empty($number)) {
                $h->general()
                    ->cancellationNumber($number);
            }
        }

        // Hotel
        $hotelLink = "@href[{$this->contains(['accor.com', 'accorhotels.com', 'sofitel.com', '.ibis.com', 'novotel.com'])}][{$this->contains(['/ficheHotel/', '/frm_fiche_hotel.'])}]";
        $hotelName = $this->http->FindSingleNode("(//a[{$hotelLink}])[1]");

        $hotelInfo = implode("\n", $this->http->FindNodes("(//a[{$hotelLink}])[1]/ancestor::tr[1]/ancestor::*[1]/tr/td[normalize-space()][1]"));
        $hotelInfo = preg_replace("/\n\s*GPS .+/", '', $hotelInfo);

        if (preg_match("/^.+\n{$this->opt($this->t("Tel :"))} *(?<phone>[\d \W]+)\n(?<address>.{14,})/", $hotelInfo, $m)) {
            $address = $m['address'];
            $phone = $m['phone'];
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address ?? '')
            ->phone($phone ?? null, true, true)
        ;

        // Phone
        $phone = $this->nextText($this->t("Tel :"));

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Tel :")) . "]", null, true, "#:\s+(.+)#");
        }
        $h->hotel()
            ->phone($phone, true, true);

        // Booked
        $date = $this->normalizeDate($this->re("#" . $this->t("from ") . "(.*?)" . $this->t(" to ") . "#", $this->nextText($this->t("Dates of stay :"))));
        $time = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Check in Policy :")) . "]/following-sibling::td[1]", null, true, "#(\d+:\d+(?:\s*[AP]M)?|\d+[AP]M)#i");

        if (!empty($time) && !empty($date)) {
            $date = strtotime($time, $date);
        }
        $h->booked()
            ->checkIn($date);

        // CheckOutDate
        $date = $this->normalizeDate($this->re("#" . $this->t(" to ") . "(.+)#", $this->nextText($this->t("Dates of stay :"))));
        $time = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Check out Policy :")) . "]/following-sibling::td[1]", null, true, "#(\d+:\d+(?:\s*[AP]M)?|\d+[AP]M)#i");

        if (!empty($time) && !empty($date)) {
            $date = strtotime($time, $date);
        }
        $h->booked()
            ->checkOut($date);

        $h->booked()
            ->guests(array_sum($this->http->FindNodes("//text()[" . $this->eq($this->t("Number of persons :")) . "]/following::text()[normalize-space(.)][1]", null, "#(\d+)" . $this->t(" adult") . "#")))
            ->kids(array_sum($this->http->FindNodes("//text()[" . $this->eq($this->t("Number of persons :")) . "]/following::text()[normalize-space(.)][1]", null, "#(\d+)" . $this->t(" child") . "#")))
        ;

        // Rooms
        $rXpath = "//table[" . $this->eq($this->t("Bedroom")) . "]/following::tr[td[3][" . $this->eq($this->t("Rate")) . "]][1]";
        $roomsNodes = $this->http->XPath->query($rXpath);

        foreach ($roomsNodes as $rRoot) {
            $type = $this->http->FindSingleNode("preceding::table[" . $this->eq($this->t("Bedroom")) . "][1]/following::table[1]/descendant::text()[normalize-space(.)][1]", $rRoot);
            $count = $this->http->FindSingleNode("following-sibling::tr[1]/td[1]", $rRoot, true, "/^\s*(\d+) [[:alpha:]]+/u") ?? 1;
            $rates = $this->http->FindNodes("following-sibling::tr/td[3]", $rRoot);

            for ($i = 1; $i <= $count; $i++) {
                $h->addRoom()
                    ->setType($type)
                    ->setRates($rates);
            }
        }

        if (count($h->getRooms()) == 0 && $h->getCancelled() == false) {
            $h->addRoom();
        }

        // Price
        if ($h->getCancelled() == false) {
            $h->price()
                ->total($this->amount($this->http->FindSingleNode("//text()[" . $this->eq($this->t("The amount to be paid at the hotel is")) . "]/following::text()[normalize-space(.)][1]")))
                ->currency($this->currency($this->http->FindSingleNode("//text()[" . $this->eq($this->t("The amount to be paid at the hotel is")) . "]/following::text()[normalize-space(.)][2]")));
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->containsText($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'accorhotels.com') or contains(@href, '.accor.com')] | node()[contains(., 'accorhotels.com') or contains(., '.accor.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your reservation']) && $this->http->XPath->query("//text()[{$this->starts($dict['Your reservation'])}]")->length > 0) {
                return true;
            }

            if (!empty($dict['Confirmation of your cancellation']) && $this->http->XPath->query("//text()[{$this->starts($dict['Confirmation of your cancellation'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your reservation']) && $this->http->XPath->query("//text()[{$this->starts($dict['Your reservation'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }

            if (!empty($dict['Confirmation of your cancellation']) && $this->http->XPath->query("//text()[{$this->starts($dict['Confirmation of your cancellation'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        $in = [
            "#^(\d+)/(\d+)/(\d{4})$#", //28/04/2014
        ];
        $out = [
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str, $relDate);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function containsText($text, $needle): bool
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
}
