<?php

namespace AwardWallet\Engine\brussels\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1854761 extends \TAccountChecker
{
    public $mailFiles = "brussels/it-1854761.eml, brussels/it-2166744.eml, brussels/it-2200808.eml, brussels/it-4097788.eml, brussels/it-4375780.eml, brussels/it-5006401.eml, brussels/it-5070753.eml, brussels/it-5070757.eml, brussels/it-5070758.eml, brussels/it-5070766.eml, brussels/it-5070767.eml";

    public static $dictionary = [
        "en" => [
            //            "PASSENGER NAME:" => "",
            //            "TICKET NUMBER:" => "",
            //            "Frequent Flyer No:" => "",
            //            "Issued:" => "",
            //            "Booking Reference:" => "",
            //            "FROM" => "",
            //            "TO" => "",
            //            "FLIGHT" => "",
            //            "Class of Travel" => "",
            //            "Operated by" => "",
            //            "FARE DETAILS" => "",
            //            "Fare" => "",
            //            "Grand Total" => "",
        ],
        "nl" => [
            "PASSENGER NAME:"    => "PASSAGIERSNAAM:",
            "TICKET NUMBER:"     => "Frequent Flyer Nr:",
            "Frequent Flyer No:" => "Frequent Flyer Nr:",
            "Issued:"            => "Uitgegeven:",
            "Booking Reference:" => "Reservatie Nr:",
            "FROM"               => "VAN",
            "TO"                 => "NAAR",
            "FLIGHT"             => "VLUCHT",
            "Class of Travel"    => "Klasse",
            "Operated by"        => "Uitgevoerd door",
            "FARE DETAILS"       => "TARIEFDETAILS",
            "Fare"               => "Tarief",
            "Grand Total"        => "Totaal",
        ],
        "fr" => [
            "PASSENGER NAME:"    => "NOM DU PASSAGER:",
            "TICKET NUMBER:"     => "NUMÉRO DE BILLET:",
            "Frequent Flyer No:" => "Nº Passager Frequent:",
            "Issued:"            => "Emis par:",
            "Booking Reference:" => "Nº Réservation:",
            "FROM"               => "DE",
            "TO"                 => "A",
            "FLIGHT"             => "VOL",
            "Class of Travel"    => "Classe",
            "Operated by"        => "Vol effectué par",
            "FARE DETAILS"       => "DÉTAILS DU PRIX",
            "Fare"               => "Tarif",
            "Grand Total"        => "Montant Total",
        ],
        "it" => [
            "PASSENGER NAME:"    => "NOME DEL PASSEGGERO:",
            "TICKET NUMBER:"     => "NUMERO DI BIGLIETTO:",
            "Frequent Flyer No:" => "No. Viaggatori Frequenti:",
            "Issued:"            => "Emesso da:",
            "Booking Reference:" => "No. Prenotazione:",
            "FROM"               => "DA",
            "TO"                 => "A",
            "FLIGHT"             => "VOLO",
            "Class of Travel"    => "Classe",
            "Operated by"        => "Operato da",
            "FARE DETAILS"       => "DETTAGLI PREZZO",
            "Fare"               => "Tariffa",
            "Grand Total"        => "Totale",
        ],
        "es" => [
            "PASSENGER NAME:"    => "NOMBRE DEL PASAJERO:",
            "TICKET NUMBER:"     => "NUMERO DEL BILLETE:",
            "Frequent Flyer No:" => "No. de Pasajero Frecuente:",
            "Issued:"            => "Fecha de emisión:",
            "Booking Reference:" => "Código de la reserva:",
            "FROM"               => "DE",
            "TO"                 => "A",
            "FLIGHT"             => "VUELO",
            "Class of Travel"    => "Clase",
            "Operated by"        => "Operado por",
            "FARE DETAILS"       => "INFORMACIÓN DE LA TARIFA",
            "Fare"               => "Tarifa",
            "Grand Total"        => "Total",
        ],
    ];

    private $detectFrom = 'brusselsairlines.com';

    private $detectSubject = [
        'en, es, it, fr' => "E-TICKET CONFIRMATION",
        'it'             => "Conferma biglietto elettronico",
        'nl'             => "E-TICKET BEVESTIGING",
    ];

    private $detectCompany = 'Brussels Airlines Service Center';

    private $detectBody = ['E-TICKET TRAVEL ITINERARY'];
    private $detectLang = [
        'en' => ["Your electronic ticket is stored"],
        'es' => ["Su billete electrónico está almacenado en nuestro sistema de reservas"],
        'it' => ["Il suo biglietto elettronico è memorizzato nel nostro sistema"],
        'fr' => ["Votre billet electronique est enregistre dans notre systeme de reservation"],
        'nl' => ["Uw elektronisch ticket is veilig bewaard in ons reservatie systeem"],
    ];

    private $pdfPattern = '.+\.pdf';
    private $lang = 'en';
    private $relDate;

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

            foreach ($this->detectBody as $detectBody) {
                if (strpos($text, $detectBody) === false) {
                    continue 2;
                }
            }

            foreach ($this->detectLang as $lang => $detectLang) {
                foreach ($detectLang as $dLang) {
                    if (strpos($text, $dLang) !== false) {
                        $this->lang = $lang;
                        $this->parsePdf($email, $text);

                        continue 3;
                    }
                }
            }
        }

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

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, $this->detectCompany) === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                if (stripos($textPdf, $detectBody) !== false) {
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

    private function parsePdf(Email $email, string $text)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("#" . $this->preg_implode($this->t("Booking Reference:")) . "\s*([A-Z\d]{5,7})\s*\n#", $text))
            ->traveller($this->re("#" . $this->preg_implode($this->t("PASSENGER NAME:")) . "\s*(.+?)(?:[ ]{3,}|\s*\n)#", $text))
            ->date($this->normalizeDate($this->re("#" . $this->preg_implode($this->t("Issued:")) . "\s*(.+?)(?:\s*/|[ ]{3,}|\s*\n)#", $text)))
        ;

        $this->relDate = $f->getReservationDate();

        // Program
        $account = trim($this->re("#" . $this->preg_implode($this->t("Frequent Flyer No:")) . "[ ]{0,10}(\W.+?)(?:[ ]{3,}|\s*\n)#", $text));

        if (!empty($account)) {
            $f->program()->account($account, false);
        }

        // Issued
        $ticket = str_replace(' ', '', $this->re("#" . $this->preg_implode($this->t("TICKET NUMBER:")) . "\s*([ \d]+?)(?:[ ]{3,}|\s*\n)#", $text));

        if (!empty($ticket)) {
            $f->issued()->ticket($ticket, false);
        }

        // Price
        $f->price()
            ->currency($this->re("#\n\s*" . $this->preg_implode($this->t("FARE DETAILS")) . "[ ]{2,}([A-Z]{3})\s*\n#", $text))
            ->total($this->amount($this->re("#\n\s*" . $this->preg_implode($this->t("Grand Total")) . "[ ]{2,}(.+?)\s*\n#", $text)))
            ->cost($this->amount($this->re("#\n\s*" . $this->preg_implode($this->t("Fare")) . "[ ]{2,}(.+?)\s*\n#", $text)))
        ;

        $segments = $this->split("#\n([ ]*\d+\)[ ]+" . $this->preg_implode($this->t("FROM")) . "[ ]+.+?[ ]+" . $this->preg_implode($this->t("TO")) . "[ ]+.*?[ ]*\([A-Z]{3}\)\s+)#", $text);

        foreach ($segments as $stext) {
            $date = null;

            $s = $f->addSegment();

            if (preg_match("#[ ]{3,}" . $this->preg_implode($this->t("FLIGHT")) . "\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\s*\n#", $stext, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("#" . $this->preg_implode($this->t("Operated by")) . "[ ]+.*\n(?:[ ]{20,}\n){0,3}[ ]{0,10}(\w.+?)(?:[ ]{2,}.*|\n)\s*" . $this->preg_implode($this->t("Not valid before")) . "#", $stext, $m)) {
                $s->airline()->operator($m[1]);
            }

            $info = $this->re("#[ ]+" . $this->preg_implode($this->t("FLIGHT")) . ".+\s*\n+((?:.*\n)+)[ ]{0,10}(?:" . $this->preg_implode($this->t("Operated by")) . "|Oper)#", $stext);
            $table = $this->splitCols(
                $info,
                $this->tableHeadPos($this->inOneRow($info))
                );

            if (!isset($table[6]) || !preg_match("#^\s*" . $this->preg_implode($this->t("Class of Travel")) . "\s+#", $table[6])) {
                continue;
            }

            if (preg_match("#\n(.*\d.*)#", $table[0], $m)) {
                $date = $this->normalizeDate($m[1], true);
            }

            if (preg_match("#\d+\)[ ]+" . $this->preg_implode($this->t("FROM")) . "[ ]+(.+?)[ ]+" . $this->preg_implode($this->t("TO")) . "[ ]+(.+?)[ ]*\(([A-Z]{3})\)\s+#", $stext, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m[1]);
                $s->arrival()
                    ->code($m[3])
                    ->name($m[2]);
            }

            if ($date && preg_match("#\n(.*\d.*)#", $table[2], $m)) {
                $s->departure()
                    ->date(strtotime($m[1], $date));
            }

            if (preg_match("#Terminal\s+(.+)#", $table[3], $m)) {
                $s->departure()
                    ->terminal(trim(preg_replace(['#\s*TERMINAL\s*#', '#\*#'], [' ', ''], $m[1])), true, true);
            }

            if ($date && preg_match("#\n(.*\d.*)#", $table[4], $m)) {
                $s->arrival()
                    ->date(strtotime($m[1], $date));
            }

            if (preg_match("#Terminal\s+(.+)#", $table[5], $m)) {
                $s->arrival()
                    ->terminal(trim(preg_replace(['#\s*TERMINAL\s*#', '#\*#'], [' ', ''], $m[1])), true, true);
            }

            if (preg_match("#^\s*(?:" . $this->preg_implode($this->t("Class of Travel")) . "\s+)+(.+)#", $table[6], $m)) {
                $s->extra()
                    ->cabin($m[1]);
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
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

    private function normalizeDate($str, $noYear = false)
    {
        $year = date('Y', $this->relDate);
        $in = [
            "#^\s*(\d{1,2})\s+([^\d\s]+)\s+(\d{4})\s*$#", // 18 NOVEMBER 2014
            "#^\s*(\d{1,2})\s+([^\d\s]+)\s*$#", // 18 NOVEMBER
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if ($noYear) {
            if (empty($this->relDate)) {
                return null;
            }
            $str = EmailDateHelper::parseDateRelative($str, $this->relDate);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
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

    private function tableHeadPos($row)
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

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
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

    private function inOneRow($text)
    {
        $textRows = explode("\n", $text);
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                if (isset($row[$l]) && (trim($row[$l]) !== '')) {
                    $notspace = true;
                    $oneRow[$l] = $row[$l];
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
