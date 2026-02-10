<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightSummaryPdf extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-12262064.eml, easyjet/it-12399129.eml, easyjet/it-12484102.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            //            "FLIGHT BOOKING REFERENCE\*?:" => "",
            //            "PASSENGER INFORMATION" => "",
            "OUTBOUND JOURNEY" => ["OUTBOUND JOURNEY", "RETURN JOURNEY"],
            "Departing\s+Date" => "(Seat\s+)?Departing\s+Date",
            //            "ACCOMMODATION VOUCHER" => "",
            //            "BOOKING REFERENCE:" => "",
            //            "ACCOMMODATION REFERENCE:" => "",
            //            "ACCOMMODATION DETAILS" => "",
            //            "Address:" => "",
            //            "Telephone:" => "",
            //            "BOOKING DETAILS" => "",
            //            "Arrival:" => "",
            //            "Departure:" => "",
            //            "Room type:" => "",
            //            "Number of passengers:" => "",
            //            "Your protection" => "",
            //            "Total ATOL protected cost" => "",
        ],
        "fr" => [
            "FLIGHT BOOKING REFERENCE\*?:" => "RÉFÉRENCE DE RÉSERVATION DU VOL\*?:",
            "PASSENGER INFORMATION"        => "INFORMATIONS DU PASSAGER",
            "OUTBOUND JOURNEY"             => ["VOYAGE ALLER", "VOYAGE RETOUR"],
            "Departing\s+Date"             => "(Siège\s+)?Lieu de départ\s+Date",
            "ACCOMMODATION VOUCHER"        => "BONS POUR LE LOGEMENT",
            "BOOKING REFERENCE:"           => "RÉFÉRENCES DE LA RÉSERVATION:",
            "ACCOMMODATION REFERENCE:"     => "RÉFÉRENCES DU LOGEMENT:",
            "ACCOMMODATION DETAILS"        => "INFORMATIONS CONCERNANT LE LOGEMENT",
            "Address:"                     => "Adresse:",
            "Telephone:"                   => "Téléphone:",
            "BOOKING DETAILS"              => "DÉTAILS DE LA RÉSERVATION",
            "Arrival:"                     => "Arrivée:",
            "Departure:"                   => "Départ:",
            //            "Number of passengers:" => "",
            "Room type:" => "Type de chambre:",
            //            "Your protection" => "",
            //            "Total ATOL protected cost" => "",
        ],
        "de" => [
            "FLIGHT BOOKING REFERENCE\*?:" => "FLUGREFERENZNUMMER\*?:",
            "PASSENGER INFORMATION"        => ["INFORMATION FÜR PASSAGIERE", "INFORMATION ZUM PASSAGIER"],
            "OUTBOUND JOURNEY"             => ["HINFLUG", "RÜCKFLUG"],
            "Departing\s+Date"             => "(Sitzplatz\s+)?Abflug\s+Datum",
            "ACCOMMODATION VOUCHER"        => "ÜBERNACHTUNGSGUTSCHEIN",
            "BOOKING REFERENCE:"           => "BUCHUNGSNUMMER:",
            "ACCOMMODATION REFERENCE:"     => "ÜBERNACHTUNGSNUMMER:",
            "ACCOMMODATION DETAILS"        => "ANGABEN ZUR ÜBERNACHTUNG",
            "Address:"                     => "Adresse:",
            "Telephone:"                   => "Telefonnummer:",
            "BOOKING DETAILS"              => "ANGABEN ZUR BUCHUNG",
            "Arrival:"                     => "Ankunft:",
            "Departure:"                   => "Abflugdatum:",
            //            "Number of passengers:" => "",
            "Room type:" => "Zimmertyp:",
            //            "Your protection" => "",
            //            "Total ATOL protected cost" => "",
        ],
        "nl" => [
            "FLIGHT BOOKING REFERENCE\*?:" => "VLUCHT REFERENTIE\*?:",
            "PASSENGER INFORMATION"        => "INFORMATIE PASSAGIERS",
            "OUTBOUND JOURNEY"             => ["HEENREIS", "TERUGREIS"],
            "Departing\s+Date"             => "Vertrek\s+Datum",
            "ACCOMMODATION VOUCHER"        => "ACCOMMODATIE VOUCHER",
            "BOOKING REFERENCE:"           => "BOEKINGS REFERENTIE:",
            "ACCOMMODATION REFERENCE:"     => "ACCOMMODATIE REFERENTIE:",
            "ACCOMMODATION DETAILS"        => "GEGEVENS VAN DE ACCOMMODATIE",
            "Address:"                     => "Adres:",
            "Telephone:"                   => "Telefoon:",
            "BOOKING DETAILS"              => "BOEKINGS GEGEVENS",
            "Arrival:"                     => "Aankomst:",
            "Departure:"                   => "Vertrek:",
            "Room type:"                   => "Kamertype:",
            //            "Number of passengers:" => "",
            //            "Your protection" => "",
            //            "Total ATOL protected cost" => "",
        ],
    ];

    private $detectFrom = "@holiday.easyjet.com";
    private $detectSubject = [
        "en" => "Your reservation confirmation:",
        "fr" => "numro de rservation de votre sjour",
        "de" => "Ihre Reservierungsbestätigung:",
        "nl" => "Uw bevestiging van de reservering:",
    ];

    private $detectCompany = 'easyJet';
    private $detectBody = [
        "en" => "FLIGHT SUMMARY",
        "fr" => "RÉCAPITULATIF DU VOL",
        "de" => "FLUGÜBERSICHT",
        "nl" => "OVERZICHT VAN UW VLUCHTEN",
    ];

    private $pdfPattern = "voucher-[A-Z\d]+.pdf|Vouchers_[A-Z\d]+.pdf|Vouchers_[A-Z\d]+.pdf";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (strpos($text, $this->detectCompany) === false) {
                continue;
            }

            foreach ($this->detectBody as $dBody) {
                if (strpos($text, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (stripos($text, $this->detectCompany) === false) {
                continue;
            }

            foreach ($this->detectBody as $lang => $dBody) {
                if (strpos($text, $dBody) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }

            $this->parsePdf($email, $text);
        }

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

    private function parsePdf(Email $email, string $text)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->re("#\n\s*" . $this->t("BOOKING REFERENCE:") . "\s+(.+)#", $text));

        // Price
        $email->price()
            ->total($this->amount($this->re("#" . $this->opt($this->t("Total ATOL protected cost")) . "\s+(.+)#", $text)), false, true)
            ->currency($this->currency($this->re("#" . $this->opt($this->t("Total ATOL protected cost")) . "\s+(.+)#", $text)), false, true)
        ;

        $travellers = array_filter(explode("|", preg_replace("#\n|\s{2,}#", "|", $this->re("#" . $this->t("PASSENGER INFORMATION") . "\n(.*?)\n\n#s", $text))));

        // FLIGHT
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("#" . $this->t("FLIGHT BOOKING REFERENCE\*?:") . "\s+(.+)#", $text), preg_replace("#^\W*(.+?)\W*$#u", '$1', $this->t("FLIGHT BOOKING REFERENCE\*?:")))
            ->travellers($travellers)
        ;

        // Segments
        preg_match_all("#\n[ ]*(" . $this->opt($this->t("OUTBOUND JOURNEY")) . "[^\n]+\s+.+?)(?=\n\n)#us", $text, $m);

        if (!empty($m[1])) {
            $segments = $m[1];
        } else {
            $this->logger->info("empty flight segments");

            return false;
        }

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            $table = $this->splitCols($this->re("#\n([ ]*" . $this->t("Departing\s+Date") . "[^\n]+\n.+)#s", $stext));

            if (count($table) != 4 && count($table) != 5) {
                $this->logger->info("incorrect parse flight table");

                return false;
            }

            // Extra
            if (count($table) == 5) {
                $seats = array_filter(array_map(function ($v) { if (preg_match("#^\s*(\d{1,3}[A-Z])\s*#", $v)) {return trim($v); }

return false; },
                        explode(",", $this->re("#[^\n]+\n\s*(.+)#s", array_shift($table)))));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            // Airline
            $s->airline()
                ->name('U2')
                ->number($this->re("#" . $this->opt($this->t("OUTBOUND JOURNEY")) . "\s*\|\s*(\d+)\s*\n#", $stext));

            // Departure
            $s->departure()
                ->noCode()
                ->name(preg_replace("#\s+#", ' ', $this->re("#[^\n]+\n\s*(.+)#s", $table[0])))
                ->date($this->normalizeDate($this->re("#[^\n]+\n\s*(.+)#s", $table[1])))
            ;

            // Arrival
            $s->arrival()
                ->noCode()
                ->name(preg_replace("#\s+#", ' ', $this->re("#[^\n]+\n\s*(.+)#s", $table[2])))
                ->date($this->normalizeDate($this->re("#[^\n]+\n\s*(.+)#s", $table[3])))
            ;
        }

        // HOTEL
        preg_match_all("#(" . $this->t("ACCOMMODATION VOUCHER") . ".*?)(?:" . $this->t("Your protection") . "|$)#s", $text, $m);

        if (!empty($m[1])) {
            $segments = $m[1];
        } else {
            $this->logger->info("empty hotel segments");

            return false;
        }

        foreach ($segments as $htext) {
            $h = $email->add()->hotel();

            if (count($table = $this->splitCols($this->re("#\n([^\n\S]*" . $this->t("ACCOMMODATION DETAILS") . ".*?)" . $this->t("BOOKING DETAILS") . "#s", $htext))) != 2) {
                $this->logger->info("incorrect parse hotel table");

                return false;
            }

            // General
            if (preg_match_all("#\n(M.*?|Fr\.\s*.*?|Hr\.\s*.*?|mw\.)(?:\s*\(|$)#m", $table[1], $m)) {
                $travellers = $m[1];
            }
            $h->general()
                ->confirmation($this->re("#" . $this->t("ACCOMMODATION REFERENCE:") . "\s+(.+)#", $htext))
                ->travellers($travellers)
            ;

            // Hotel
            $h->hotel()
                ->name($this->re("#" . $this->t("ACCOMMODATION DETAILS") . "\s+([^\n]+)#s", $table[0]))
                ->phone(trim($this->re("#" . $this->t("Telephone:") . "([^\n]+)#", $table[0])), true, true)
            ;
            $address = trim($this->re("#" . $this->t("Address:") . "([^\n]+)#", $table[0]));

            if (!empty($address)) {
                $h->hotel()->address($address);
            } else {
                $h->hotel()->noAddress();
            }

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->re("#" . $this->t("Arrival:") . "\s*(.*?)\s*\|#", $htext)))
                ->checkOut($this->normalizeDate($this->re("#" . $this->t("Departure:") . "\s*(.*?)\s*\|#", $htext)))
                ->guests($this->re("#" . $this->t("Number of passengers:") . "\s*(\d+)#", $htext), true, true)
            ;

            // Rooms
            $h->addRoom()
                ->setType($this->re("#" . $this->t("Room type:") . "[^\n]+\n(.*?)\s{2,}#", $htext));
        }

        return true;
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
//        $this->http->log($str);
        $in = [
            "#^(\d+)[a-z]{1,3} ([^\s\d]+) (\d{4}) (\d+:\d+)$#", //24APR(SU)
            "#^(\d+) ([^\s\d]+) '(\d{2}) at (\d+:\d+)$#", //21 Aug '17 at 12:30
            "#^(\d+) ([^\s\d]+) '(\d{2}) à (\d+:\d+)$#", //17 Juin '16 à 15:20
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 20$3, $4",
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
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
}
