<?php

namespace AwardWallet\Engine\brussels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "brussels/it-32714421.eml, brussels/it-32815373.eml, brussels/it-33240507.eml";

    public $reFrom = ["@notifications.brusselsairlines.com"];
    public $reBody = [
        'en' => ['This document is your proof of payment', 'Departure date'],
        'nl' => ['Dit document is je bewijs van betaling', 'Vertrekdatum'],
    ];
    public $reSubject = [
        'Confirmation of your additional services - ',
        // nl
        'Bevestiging aankoop extra diensten - Boekingsreferentie:',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
//            "PASSENGER" => "",
//            "Frequent flyer number" => "",
//            "SERVICE CENTRE" => "",
//            "Contact Phone" => "",
//            "DOCUMENT" => "",
//            "Issued" => "",
            'Reservation number' => ['Reservation number', 'Booking reference'],
            'In connection with e-ticket' => ['In connection with e-ticket', 'In connection with e-ticket number'],
//            "Fee details" => "",
//            "FLIGHT" => "",
//            "Flight no." => "",
//            "Departure date" => "",
//            "Grand total" => "",
//            "Conditions" => "",
        ],
        'nl' => [
            "PASSENGER" => "PASSAGIER",
            "Frequent flyer number" => "Frequent flyer number",
            "SERVICE CENTRE" => "SERVICE CENTRE",
            "Contact Phone" => "Telefoonnummer",
            "DOCUMENT" => "DOCUMENT",
            "Issued" => "Uitgegeven op",
            'Reservation number' => ['Boekingsreferentie'],
            'In connection with e-ticket' => ['Verbonden aan het e-ticketnummer'],
            "Fee details" => "Fee details",
            "FLIGHT" => "VLUCHT",
            "Flight no." => "Vluchtnummer",
            "Departure date" => "Vertrekdatum",
            "Grand total" => "Totaal tarief",
            "Conditions" => "Voorwaarden",
        ],
    ];
    private $keywordProv = 'Brussels Airlines';
    private $contactPhones = [];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $i => $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language in ' . $i . ' pdf');

                        continue;
                    }

                    if (!$this->parseEmailPdf($text, $email)) {
                        return null;
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, $this->keywordProv) !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $text = strstr($textPDF, $this->t('Fee details'));

        if (empty($text)) {
            $this->logger->debug('other format (no fee details)');

            return false;
        }

        $info = $this->re("#(.+)\n{3,}#s", strstr($textPDF, $this->t('Fee details'), true));

        if (empty($info)) {
            $this->logger->debug('other format (info)');

            return false;
        }

        if (preg_match("#^([ ]*{$this->opt($this->t('PASSENGER'))}[ ]+){$this->opt($this->t('DOCUMENT'))}#", $info,
            $m)) {
            $pos = [0, strlen($m[1])];
            $info = $this->splitCols($info, $pos);
        }
        $info = (array) $info;

        if (count($info) !== 2) {
            $this->logger->debug('other format (table info)');

            return false;
        }

        $segments = $this->re("#{$this->opt($this->t('Fee details'))}[^\n]*\n(.+?)\s+(?:{$this->opt($this->t('Grand total'))}|{$this->opt($this->t('Conditions'))})#s",
            $text);

        if (preg_match("#^([ ]*".$this->opt($this->t("FLIGHT"))."[ ]+)".$this->opt($this->t("FLIGHT"))."#", $segments, $m)) {
            $pos = [0, strlen($m[1])];
            $segments = $this->splitCols($segments, $pos);
        } else {
            $segments = (array) $segments;
        }
        $r = $email->add()->flight();

        foreach ($segments as $segment) {
            $s = $r->addSegment();
            $regExp = "#{$this->opt($this->t('FLIGHT'))}\s+(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s+"
                . "(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)\s+"
                . "{$this->opt($this->t('Flight no.'))}\s+{$this->t('Departure date')}\s+"
                . "(?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<flight>\d+)[ ]{3,}(?<day>.+)#";

            if (preg_match($regExp, $segment, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flight']);
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->noDate()
                    ->day($this->normalizeDate($m['day']));
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->noDate();
            }
        }

        $r->general()
            ->travellers(array_filter(array_map("trim", explode("\n",
                $this->re("#{$this->opt($this->t('PASSENGER'))}\s+(.+?)\s+(?:{$this->opt($this->t('Frequent flyer number'))}|{$this->opt($this->t('SERVICE CENTRE'))})#s",
                    $info[0])))))
            ->confirmation($this->re("#{$this->opt($this->t('Reservation number'))}\s+([A-Z\d]{5,})#", $info[1]));

        $dateRes = $this->re("#{$this->t('Issued')}[ ]+(.+)#", $info[1]);

        if (trim($dateRes) !== '//') { //it-33240507.eml
            $r->general()
                ->date($this->normalizeDate($dateRes));
        }

        if (preg_match("#{$this->opt($this->t('Frequent flyer number'))}[ ](.+)#", $info[0], $m)) {
            $r->program()
                ->account($m[1], false);
        }
        $r->issued()
            ->ticket($this->re("#{$this->opt($this->t('In connection with e-ticket'))}[ ]+(\d+)#", $info[1]), false);

        $phone = str_replace(' ', '', $this->re("#{$this->t('Contact Phone')}\s+([\d\+\- \(\)]+)\n#", $info[0]));

        if (!in_array($phone, $this->contactPhones)) {
            $this->contactPhones[] = $phone;
            $email->ota()->phone($phone, $this->t('Contact Phone'));
        }

        if (preg_match("#{$this->opt($this->t('Grand total'))}\s+([A-Z]{3})\s+{$this->opt($this->t('Grand total'))}\s+([\d\.,]+)\s+#", $text, $m)) {
            $r->price()
                ->currency($m[1])
                ->total(PriceHelper::cost($m[2]));
        }

        return true;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            //15/1/2019
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s*$#',
        ];
        $out = [
            '$3-$2-$1',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
//        $this->logger->debug('$str = '.print_r( $str,true));

        return $str;
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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
}
