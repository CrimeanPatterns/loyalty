<?php

namespace AwardWallet\Engine\cheapnl\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketPdf2016 extends \TAccountChecker
{
    public $mailFiles = "cheapnl/it-3183487.eml, cheapnl/it-3183488.eml, cheapnl/it-3712208.eml, cheapnl/it-3877772.eml, cheapnl/it-3879541.eml, cheapnl/it-4416671.eml, cheapnl/it-4484148.eml, cheapnl/it-4566025.eml, cheapnl/it-4646067.eml, cheapnl/it-4814305.eml, cheapnl/it-5106812.eml, cheapnl/it-5137078.eml, cheapnl/it-5250523.eml, cheapnl/it-5872462.eml, cheapnl/it-5876050.eml, cheapnl/it-5898216.eml, cheapnl/it-6052427.eml, cheapnl/it-6083890.eml, cheapnl/it-6174672.eml, cheapnl/it-7179296.eml";

    public static $dictionary = [
        "en" => [
            //			"Booking number" => "",
            //			"E-ticket number" => "",
            //			"Flight information" => "",
            //			"Departure" => "",
            //			"Arrival" => "",
            //			"Duration" => "",
            //			"Flight" => "",
            "Airline reference" => ["Airline reference", "Airline number", 'Reservation number'],
            //			"Aircraft" => "",
            //			"Class" => "",
            //			"Operated by" => "",
        ],
        "de" => [
            "Booking number"     => ["Reservierungsnummer", "Buchungsnummer", "CheapTickets.de Nummer"],
            "E-ticket number"    => ["E-ticket Nummer", "E-Ticket nummer"],
            "Flight information" => "Flug Informationen",
            "Departure"          => "Abflug",
            "Arrival"            => "Ankunft",
            "Duration"           => "Flugzeit",
            "Flight"             => "Flug",
            "Airline reference"  => ["Reservierungsnummer", "Fluggesellschaftsreferenz"],
            "Aircraft"           => "Flugzeugtyp",
            "Class"              => "Klasse",
            "Operated by"        => "Ausgeführt durch",
        ],
        "nl" => [
            "Booking number"     => ["Boekingsnummer", "CheapTickets.nl nummer"],
            "E-ticket number"    => "E-ticket nummer",
            "Flight information" => "Vluchtinformatie",
            "Departure"          => "Vertrek",
            "Arrival"            => "Aankomst",
            "Duration"           => "Vluchtduur",
            "Flight"             => "Vlucht",
            "Airline reference"  => ["Airline nummer", "Airline referentie", "Reserveringsnummer"],
            "Aircraft"           => "Vliegtuigtype",
            "Class"              => "Klasse",
            "Operated by"        => "Uitgevoerd door",
        ],
        "fr" => [
            "Booking number"     => "Numéro de réservation",
            "E-ticket number"    => "Numéro E-Ticket",
            "Flight information" => "Information de vol",
            "Departure"          => "Départ",
            "Arrival"            => "Arrivée",
            "Duration"           => "Durée",
            "Flight"             => "Vol",
            "Airline reference"  => ["Référence de la compagnie", "Numéro de réservation"],
            "Aircraft"           => "Avion",
            "Class"              => "Classe",
            "Operated by"        => "Effectué par",
        ],
        "pt" => [
            "Booking number"     => "Número de reserva",
            "E-ticket number"    => "E-ticket número",
            "Flight information" => ["Informação de voo", "Informação de vôo"],
            "Departure"          => "Saída",
            "Arrival"            => "chegada",
            "Duration"           => "Duração",
            //			"Flight" => "",
            "Airline reference" => ["da companhia aérea", "número de reserva"],
            "Aircraft"          => "Tipo de Aeronave",
            "Class"             => "Classe",
            //			"Operated by" => "",
        ],
        "it" => [
            "Booking number"     => "Numero di prenotazione",
            "E-ticket number"    => "Numero di e-ticket",
            "Flight information" => "Informazioni di volo",
            "Departure"          => "partenza",
            "Arrival"            => "arrivo",
            "Duration"           => "durata volo",
            "Flight"             => "volo",
            "Airline reference"  => ['Codice compagnia aerea', 'Numero di prenotazione'],
            "Aircraft"           => "velivolo",
            "Class"              => "Classe",
            "Operated by"        => "Operato da",
        ],
        "pl" => [
            "Booking number"     => "Numer rezerwacji",
            "E-ticket number"    => "Numer E-bilet",
            "Flight information" => "Informacja o locie",
            "Departure"          => "Wylot",
            "Arrival"            => "Przylot",
            "Duration"           => "Czas lotu",
            "Flight"             => "Lot",
            "Airline reference"  => ["Numer linii lotniczej"],
            "Aircraft"           => "Samoloty",
            "Class"              => "Klasy",
            "Operated by"        => "Obsługiwany przez",
        ],
    ];

    public $lang = '';

    private $froms = [
        'budgetair' => ['budgetair.'],
        'vayama'    => ['vayama.'],
        'flugladen' => ['flugladen.'],
        'cheapnl'   => ['CheapTickets.'],
    ];

    private $reSubject = [
        "en" => [" - E-ticket "],
        "nl" => [" - e-ticket "],
        "nl" => [" - Jouw e-ticket "],
        "fr" => [" - E-ticket "],
        "pt" => [" - O seu bilhete eletrónico "],
        "pl" => [" - Twój e-bilet "],
        "it" => [" - e-ticket "],
    ];

    private $reBody = [
        'budgetair' => ['@budgetair.'],
        'vayama'    => ['@vayama.'],
        'flugladen' => ['@flugladen.'],
        'cheapnl'   => ['@cheaptickets.'],
    ];

    private $reBody2 = [
        "de" => ["Flug Informationen"],
        "nl" => ["Vluchtinformatie"],
        "fr" => ["Information voyageur"],
        "en" => ["Flight information"],
        "pt" => ["Informação de voo", "Informação de vôo"],
        "pl" => ["Informacja o locie"],
        "it" => ["Informazioni di volo"],
    ];

    public static function getEmailProviders()
    {
        return ['budgetair', 'vayama', 'flugladen', 'cheapnl'];
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*.pdf");

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $detect = false;

            foreach ($this->reBody2 as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if (stripos($text, $re) !== false) {
                        $this->lang = $lang;
                        $detect = true;

                        break 2;
                    }
                }
            }

            if ($detect) {
                $text = str_replace(chr(194) . chr(160), ' ', $text);
                $this->flight($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!empty($this->codeProvider)) {
            $email->setProviderCode($this->codeProvider);
            $email->ota()->code($this->codeProvider);
        } else {
            $email->ota();
        }

        return $email;
    }

    public function flight(Email $email, string $text)
    {
        $f = $email->add()->flight();

        $f->general()->noConfirmation();

        if (is_array($this->t("Flight information"))) {
            foreach ($this->t("Flight information") as $value) {
                $flightBegin = stripos($text, $value);

                if (!empty($flightBegin)) {
                    break;
                }
            }
        } else {
            $flightBegin = stripos($text, $this->t("Flight information"));
        }

        if (empty($flightBegin)) {
            $this->http->log("empty text 'Flight information'");

            return $email;
        }

        $passText = substr($text, 0, $flightBegin);

        if (preg_match_all('#\n\s*(\S.+)\n[ ]*' . $this->preg_implode($this->t('E-ticket number')) . '[ ]*(\d[\d-]{7,})?(?=\s|$)#', $passText, $m)) {
            $f->general()->travellers(array_map('trim', $m[1]), true);

            if (!empty(array_filter($m[2]))) {
                $f->issued()->tickets(array_filter($m[2]), false);
            }
        }

        if (preg_match("#\s+(" . $this->preg_implode($this->t('Booking number')) . ")[ ]*([A-Z\d-]{5,})\s+#", $passText, $m)) {
            $email->ota()->confirmation($m[2], $m[1]);
        }

        if (preg_match("#^\s*([\d\+\-\(\) \.]{5,})(?: \([^\)]+\))?\s*\n\s*.*@#", $passText, $m)) {
            $email->ota()->phone($m[1]);
        }

        $flightText = substr($text, $flightBegin);
        $segments = $this->split("#\n\s*(" . $this->t('Departure') . "[ ]{3,})#", $flightText);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            // Airline
            if (preg_match("#" . $this->preg_implode($this->t('Airline reference')) . "[ ]+([A-Z\d]{5,7})\s#", $stext, $m)) {
                $s->airline()->confirmation($m[1]);
            }

            if (preg_match('#' . $this->t('Flight') . '\s*.*?\s*\|\s*([A-Z\d]{2})\s*(\d{1,5})#', $stext, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            } elseif (preg_match('#' . $this->t('Duration') . '\s.*\n.*?\s*\|\s*([A-Z\d]{2})\s*(\d{1,5})#', $stext, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match('#' . $this->t('Operated by') . '[ ]+(.+?)(?:\s{2,}|\n|$)#', $stext, $m)) {
                $s->airline()->operator($m[1]);
            }

            // Departure
            if (preg_match("#" . $this->t('Departure') . "[ ]+(?<date>.+)\s+(?<country>.+?)[ ]*\|[ ]*(?<city>.+?)[ ]+(?<code>[A-Z]{3})(?:[ ]+(?<term>\S.*))?\s+#", $stext, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name(implode(', ', array_filter([trim($m['city']), trim($m['country'])])))
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(!empty($m['term']) ? trim($m['term']) : null, true, true);
            }

            // Arrival
            if (preg_match("#" . $this->t('Arrival') . "[ ]+(?<date>.+)\s+(?<country>.+?)[ ]*\|[ ]*(?<city>.+?)[ ]+(?<code>[A-Z]{3})(?:[ ]+(?<term>\S.*))?\s+#", $stext, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name(implode(', ', array_filter([trim($m['city']), trim($m['country'])])))
                    ->date($this->normalizeDate($m['date']))
                    ->terminal(!empty($m['term']) ? trim($m['term']) : null, true, true);
            }

            // Extra
            if (preg_match('#' . $this->t('Aircraft') . '[ ]+(.+?)(?:\s{2,}|\n|$)#', $stext, $m)) {
                $s->extra()->aircraft($m[1]);
            }

            if (preg_match('#' . $this->t('Duration') . '[ ]+(.+)#', $stext, $m)) {
                $s->extra()->duration($m[1]);
            }

            if (preg_match('#' . $this->t('Class') . '[ ]+([A-Z]{1,2})\s+#', $stext, $m)) {
                $s->extra()->bookingCode($m[1]);
            } elseif (preg_match('#' . $this->t('Class') . '[ ]+(.+?)(?:\s{2,}|\n|$)#', $stext, $m)) {
                $s->extra()->cabin($m[1]);
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->froms as $froms) {
            foreach ($froms as $value) {
                if (stripos($from, $value) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    $finded = true;
                }
            }
        }

        if ($finded === false) {
            foreach ($this->froms as $prov => $froms) {
                foreach ($froms as $value) {
                    if (stripos($headers["from"], $value) !== false || stripos($headers["subject"], $value) !== false) {
                        $this->codeProvider = $prov;

                        return false;
                    }
                }
            }

            return false;
        }

        foreach ($this->froms as $prov => $froms) {
            foreach ($froms as $value) {
                if (stripos($headers["from"], $value) !== false || stripos($headers["subject"], $value) !== false) {
                    $this->codeProvider = $prov;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*.pdf");
        $body = '';

        foreach ($pdfs as $pdf) {
            $body .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        $finded = false;

        foreach ($this->reBody as $prov => $froms) {
            foreach ($froms as $from) {
                if (stripos($body, $from) !== false) {
                    $finded = true;

                    break 2;
                }
            }
        }

        if ($finded === false) {
            return false;
        }

        foreach ($this->reBody2 as $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    $this->codeProvider = $prov;

                    return true;
                }
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
        return count(self::$dictionary);
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
        $in = [
            "#^[^\d\s]+\s+(\d+)-(\d+)-(\d{4})\s+(\d+:\d+)$#", // Friday 25-11-2016 13:25
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})\s+(\d+:\d+)$#", // Mittwoch 15 Juni 2016 19:35
        ];
        $out = [
            "$1.$2.$3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
