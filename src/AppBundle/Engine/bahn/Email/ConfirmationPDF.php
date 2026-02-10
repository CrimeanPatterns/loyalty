<?php

namespace AwardWallet\Engine\bahn\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "bahn/it-37840934.eml, bahn/it-466391600.eml, bahn/it-472404335.eml"; // +1 bcdtravel(pdf)[de]

    public $pdfNamePattern = '.*pdf';

    public $subject;

    public $lang = "de";
    public static $dict = [
        "de" => [
            // 'DB Online Ticket für' => '', // subject
            // 'Auftrag-Nr.:' => '',
            // 'Ihre Reise' => '',
            // 'Reservierung' => '',
            // 'Gesamtpreis' => '',
        ],
        "fr" => [
            // 'DB Online Ticket für' => '', // subject
            'Auftrag-Nr.:' => 'N° de commande :',
            'Ihre Reise'   => 'Votre voyage',
            'Reservierung' => 'Réservation',
            'Gesamtpreis'  => 'Prix total',
        ],
        "en" => [
            // 'DB Online Ticket für' => '', // subject
            'Auftrag-Nr.:' => 'Order no.:',
            'Ihre Reise'   => 'Your journey',
            'Reservierung' => 'Reservation',
            'Gesamtpreis'  => 'Total fare',
        ],
    ];

    private $detectFrom = "@bahn."; // noreply@deutschebahn.com
    private $detectSubject = [
        "de"  => "Reservierungsbestätigung für",
        "de2" => "DB Online Ticket für",
        "en"  => "Reservation confirmation (Order",
        "fr"  => "Confirmation de réservation (commande",
    ];

    private $detectBody = [
        'de' => ['Sitzplatzreservierung online', 'Bitte drucken Sie diese Bestätigung als Beleg für Ihre'],
        'en' => ['Online seat reservation', 'Please print out this confirmation as a booking receipt'],
        'fr' => ['Réservation de place assise en ligne', "Merci d'imprimer cette confirmation en guise de justificatif de votre réservation"],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($text)) {
                $this->parseEmail($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            $text .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        return $this->assignLang($text);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email, string $text)
    {
        $t = $email->add()->train();

        // General
        $t->general()
            ->confirmation($this->re("#\n\s*{$this->opt($this->t('Auftrag-Nr.:'))}[ ]*([A-Z\d/-]+)\s+#", $text), "Auftrag-Nr")
        ;

        if (preg_match("#{$this->opt($this->t('DB Online Ticket für'))} ([A-Z]+(?: [A-Z]+)+)[ ,]#", $this->subject, $m)) {
            $t->general()->traveller($m[1]);
        }

        if (preg_match("#{$this->opt($this->t('Gesamtpreis'))}[ ]+(\d.+)#", $text, $m)) {
            $total = $this->getTotalCurrency($m[1]);
            $t->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        $segmentText = '';

        if (preg_match("#{$this->opt($this->t('Ihre Reise'))}\n(?:\s*\n)*([\s\S]+)\n[ ]{0,10}{$this->opt($this->t('Reservierung'))}:? {2,}#", $text, $m)) {
            $segmentText = $m[1];
        }
        $segmentText = preg_replace("#(Produkte)\s*(Reservierung)#", "$1|$2", $segmentText);
        $headerPos = $this->TableHeadPos($segmentText);
        $segments = $this->split("#(.+ ab \d{1,2}:\d{2} )#", $segmentText);

        foreach ($segments as $stext) {
            $s = $t->addSegment();

            $table = $this->SplitCols($stext, $headerPos);

            if (count($table) !== 7 && count($table) !== 8) {
                $this->logger->debug("table parsing error. segment: $stext");
            }

            $arrivalStarts = preg_quote($this->re("#\n[^\n\w]*([^\n]+?)[ ]{3,}an \d+:\d+#u", $stext), '#');

            if (preg_match("#(.+?)\n[ ]*({$arrivalStarts}.*)#s", trim($table[1]), $route)) {
                $s->departure()->name(trim(preg_replace("#\s+#", ' ', $route[1])));
                $s->arrival()->name(trim(preg_replace("#\s+#", ' ', $route[2])));
            }

            $date = trim($table[2]);

            if (!empty($date) && preg_match("#^\s*ab (\d{1,2}:\d{2})#m", $table[3], $m)) {
                $s->departure()->date($this->normalizeDate($date . ' ' . $m[1]));
            }

            if (!empty($date) && preg_match("#^\s*an (\d{1,2}:\d{2})#m", $table[3], $m)) {
                $s->arrival()->date($this->normalizeDate($date . ' ' . $m[1]));
            }

            // Extra
            if (preg_match("#^\s*([A-Z]+)?\s*(\d{1,5})\s*$#s", $table[5], $m)
                || preg_match("#^\s*([A-Z]+)\s+([A-Z]+\d{1,5})\s*$#s", $table[5], $m)
            ) {
                // STB U12
                // ICE 1163
                $s->extra()
                    ->service($m[1], true, true)
                    ->number($m[2])
                ;
            }

            if (preg_match("#,\s+(.*?Klasse.*?)\s*,#i", $table[6], $m)) {
                $s->extra()->cabin($m[1]);
            }

            if (preg_match("#,\s+Wg\.\s*(\d+),\s+Pl.*?\s*,#si", $table[6], $m)) {
                $s->extra()->car($m[1]);
            }

            if (preg_match("#,\s+Wg\.\s*\d+,\s+Pl\.\s*([\d\s]+)\s*,#si", $table[6], $m)
                    || preg_match("#,\s+Wg\.\s*\d+,\s+Pl\.Mit Tisch,\s*([\d\s]+)\s*(?:,|\n\s*[^\d\s])#si", $table[6], $m)) {
                $s->extra()->seats(array_map('trim', preg_split("#[\s]+#", $m[1])));
            }

            if (preg_match("#Res.Nr. ([\d\s]{14,})#", $table[6], $m) && !empty(trim($m[1]))) {
                $t->general()->confirmation(preg_replace("#\s+#", '', trim($m[1])), "Res.Nr.(" . $this->re("#^\s*(.+)#", $table[5]) . ")");
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if (stripos($body, $dBody[0]) !== false && stripos($body, $dBody[1]) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        //		 $this->http->log($str);
        $in = [
            "#^\s*(\d{2})\.(\d{2})\.(\d{2})\s+(\d{1,2}:\d{2})\s*$#", //13.03.19 17:07
            "#^\s*(?:[^\d\s]+[\s,]+)?(\d{2})\.(\d{2})\.(\d{4})\s+(\d{1,2}:\d{2})\s*$#", //Di, 26.03.2019 17:07
        ];
        $out = [
            "$1.$2.20$3 $4",
            "$1.$2.$3 $4",
        ];

        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|Y)#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        if (preg_match("#\d{4}#", $str)) {
            return strtotime($str);
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

    private function TableHeadPos($text)
    {
        $row = explode("\n", $text)[0];
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
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

    private function getTotalCurrency($node)
    {//9,00 €
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);

        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t'], '.', ',');
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
