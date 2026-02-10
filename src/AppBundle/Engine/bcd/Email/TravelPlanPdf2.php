<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelPlanPdf2 extends \TAccountChecker
{
    public $mailFiles = "bcd/it-1645201.eml, bcd/it-1645203.eml, bcd/it-1649646.eml, bcd/it-1649647.eml, bcd/it-1649648.eml, bcd/it-1649649.eml, bcd/it-1649650.eml, bcd/it-1649651.eml, bcd/it-1649652.eml, bcd/it-1649653.eml, bcd/it-1649654.eml, bcd/it-1652532.eml, bcd/it-1652542.eml, bcd/it-1654244.eml, bcd/it-1750375.eml";

    public $reFrom = "itinerary@pcsoffice02.de";
    public $reSubject = [
        "de"  => "Reiseplan fuer",
        "de2" => "Reiseplan für",
    ];
    public $reBody = 'BCD TRAVEL';
    public $reBody2 = [
        "de" => "IHR PERSÖNLICHER REISEPLAN",
    ];
    public $pdfPattern = "\d{8}_[^\s\d]+_[A-Z\d]+.pdf";

    public static $dictionary = [
        "de" => [],
    ];

    public $lang = "de";
    private $text;

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // print_r($parser->getAttachments());
        // die();
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (stripos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($email);
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

    private function parsePdf(Email $email)
    {
        $text = $this->text;

        if ($date = $this->re("#\n\s*(\d+\.\d+\.\d{4})\s*\n#", $text)) {
            $this->date = strtotime($this->normalizeDate($date));
        }

        $tripNumber = $this->re("#Auftragsnummer:\s+(.+)#", $text);
        $pax = $this->re("#Reisedaten für:\s+(.+)#", $text);
        $reservations = $this->split("#(Reiseverbindung\n.*?Haltestelle.*?\n\n)#s", $text);

        foreach ($reservations as $rails) {
            $r = $email->add()->train();
            $r->general()
                ->noConfirmation()
                ->traveller($pax);
            $r->ota()
                ->confirmation($tripNumber, 'Auftragsnummer');
            $ticket = $this->re("#Beleg-Nr.:[ ]*(\d+)#", $rails);

            if (!empty($ticket)) {
                $r->addTicketNumber($ticket, false);
            }

            $sum = $this->re("#(?:Summe \(.*\)|Preis:)\s+(.+)#", $rails);

            if (!empty($sum)) {
                $sum = $this->getTotalCurrency($sum);
                $r->price()
                    ->total($sum['Total'])
                    ->currency($sum['Currency']);
            }

            $tableHead = $this->re("#\n(Haltestelle[^\n]+)#", $text);
            $tablesPos = [];
            $p = -1;

            while (($p = strpos($tableHead, 'Haltestelle', $p + 1)) !== false) {
                $tablesPos[] = $p;
            }
            $segments = $this->splitCols($this->re("#(.+?)(?:\n\nBemerkungen:|\n\nFahrkarte Globalpreis Hinfahrt)#ms",
                $rails), $tablesPos, false);

            foreach ($segments as $stext) {
                $table = $this->re("#\n(Haltestelle.+)#ms",
                    $stext);
                $rows = explode("\n", $table);
                $head = array_shift($rows);
                $pos = $this->colsPos($table);
                $rows = array_merge([], array_filter($rows, function ($s) {
                    return preg_match("#\d+:\d+#", $s);
                }));

                for ($i = 0; $i <= count($rows) - 2; $i = $i + 2) {
                    $body = implode("\n", [$rows[$i], $rows[$i + 1]]);
                    $table = $this->splitCols($body, $pos);

                    if (count($table) != 5 && count($table) != 6) {
                        $this->logger->info("Incorrect parser table");

                        return;
                    }

                    $date = strtotime($this->normalizeDate($this->re("#(?:Hinfahrt|Rückfahrt) am (.*?)(?:[ ]{2,}|[ ]*\n)#",
                        $stext)));

                    $s = $r->addSegment();
                    $s->extra()
                        ->number($this->re("#^.*?[ ]*(\d+)#", $table[3]))
                        ->type($this->re("#^(.*?)[ ]*\d+#", $table[3]))
                        ->cabin($this->re("#Klasse:[ ]+(.+?)[ ]{2,}#", $rails), false, true);
                    $node = strstr($rails, 'Sitzplatz');

                    if (empty($node) && preg_match("#Zug[ ]+Wagen[ ]+Platz#", $stext)) {
                        $node = $stext;
                    }

                    if ($s->getNumber() && $s->getTrainType()
                        && !empty($node) && preg_match("#Zug[ ]+Wagen[ ]+Platz#", $node)
                    ) {
                        if (preg_match_all("#\s{2,}{$s->getTrainType()}[ ]*{$s->getNumber()}\s+(\d+)\s+(\d+)\s+#",
                            $node, $m)) {
                            $s->extra()
                                ->car(implode(', ', array_unique($m[1])))
                                ->seats($m[2]);
                        }
                    }

                    $s->departure()
                        ->name($this->re("#^(.*?)\n#", $table[0]))
                        ->date(strtotime($this->normalizeDate($this->re("#^(.*?)\n#", $table[1])), $date));

                    $s->arrival()
                        ->name($this->re("#\n(.+)#", $table[0]))
                        ->date(strtotime($this->normalizeDate($this->re("#\n(.+)#", $table[1])), $date));

                    if (count($rows) == 2) {
                        $s->extra()
                            ->duration($this->re("#Dauer:\s+(.+)#", $stext), false, true);
                    }
                }
            }
        }
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $m['t'] = preg_replace('/\s+/', '', $m['t']);			// 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);	// 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);	// 18800,00		->	18800.00
            $tot = $m['t'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $this->http->log($str);
        $in = [
            "#^a[nb] (\d+:\d+)$#", //an 21:08
            "#^a[nb] (\d+:\d+) \d+$#", //ab 14:51 3
            "#^(\d+)\. ([^\s\d]+)$#", //15. Jul
        ];
        $out = [
            "$1",
            "$1",
            "$1 $2 %Y%",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (strpos($str, "%Y%") !== false && isset($this->date)) {
            return date("Y-m-d H:i:s", EmailDateHelper::parseDateRelative(null, $this->date, true, $str));
        }

        return $str;
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
        $result = [];

        $array = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function rowColsPos($row, $splitter = "#\s{2,}#")
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace($splitter, "|", $row))));
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

        foreach ($pos as $i => $p) {
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

    private function SplitCols($text, $pos = false, $trim = true)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $sel = mb_substr($row, $p, null, 'UTF-8');

                if ($trim) {
                    $sel = trim($sel);
                }
                $cols[$k][] = $sel;
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
