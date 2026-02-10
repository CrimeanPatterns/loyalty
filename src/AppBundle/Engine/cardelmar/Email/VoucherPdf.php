<?php

namespace AwardWallet\Engine\cardelmar\Email;

use AwardWallet\Engine\MonthTranslate;

class VoucherPdf extends \TAccountChecker
{
    public $mailFiles = "cardelmar/it-2756732.eml";

    public $reFrom = '@cardelmar.com';
    public $reSubject = [
        'de' => ['Ihre CarDelMar Buchung'],
        'it' => ['La Sua prenotazione CarDelMar'],
    ];

    public $langDetectors = [
        'de' => ['Drop-off station:'],
        'it' => ['Condizioni di utilizzo:'],
    ];

    public $pdfPattern = 'Voucher[_ ]\d+.pdf';
    public $confirmationPdfPattern = '(Rechnung|Buchungsbestätigung|Conferma della prenotazione\s*)[_ ]\d+.pdf';

    public static $dictionary = [
        'de' => [
            'Ihr Rechnungsbetrag' => ['Ihr Rechnungsbetrag', 'Ihr Gesamtbetrag'],
        ],
        'it' => [
            'Ihr Rechnungsbetrag' => 'Importo totale',
        ],
    ];

    public $lang = '';

    public function parsePdf($text, &$itineraries)
    {
        $patterns = [
            'phone' => '[+]?[-.\d)( ]{5,}', // 351 21 8486191
        ];

        $cpdftext = '';
        $pdfs = $this->parser->searchAttachmentByName($this->confirmationPdfPattern);

        if (isset($pdfs[0])) {
            $cpdftext = \PDF::convertToText($this->parser->getAttachmentBody($pdfs[0]));
        }

        $textTable = $this->re('/^([ ]*Pick-up station:.*?Flight number:)/ims', $text);
        $posColumn1 = strpos($textTable, 'Pick-up station:');
        $posColumn2 = strpos($textTable, 'Drop-off station:');
        $table = $this->SplitCols($textTable, [$posColumn1, $posColumn2]);

        if (count(array_filter($table)) !== 2) {
            $this->logger->info("incorrect parse table");

            return;
        }

        $it = [];
        $it['Kind'] = "L";

        // Number
        $it['Number'] = $this->re("#Booking No:\s+(\d+)#", $text);

        // AccountNumbers
        $customerNumber = $this->re('/' . $this->t('Kundennummer') . ':?[ ]+([A-Z\d][-A-Z\d\/]{4,})(?:[ ]{2}|$)/m', $cpdftext); // P2340735

        if ($customerNumber) {
            $it['AccountNumbers'] = [$customerNumber];
        }

        // TripNumber
        // PickupDatetime
        $it['PickupDatetime'] = strtotime($this->normalizeDate($this->re("#Rental period:\s+(.*?) -#", $text) . ', ' . $this->re("#Pick-up time:\s+(.+)#", $table[0])));

        // PickupLocation
        $it['PickupLocation'] = trim(str_replace("\n", " ", $this->re("#Address:\s+(.*?)Phone:#ms", $table[0])));

        // DropoffDatetime
        $it['DropoffDatetime'] = strtotime($this->normalizeDate(trim($this->re("#Rental period:\s+.*?\s+-\s+([^\n]+)#ms", $text)) . ', ' . $this->re("#Return time:\s+(.+)#", $table[1])));

        // DropoffLocation
        $it['DropoffLocation'] = trim(str_replace("\n", " ", $this->re("#Address:\s+(.*?)Phone:#ms", $table[1])));

        // PickupPhone
        $phonePickup = $this->re('/Phone:[ ]*(' . $patterns['phone'] . ')$/m', $table[0]);

        if ($phonePickup) {
            $it['PickupPhone'] = $phonePickup;
        }

        // PickupFax
        $faxPickup = $this->re('/Fax:[ ]*(' . $patterns['phone'] . ')$/m', $table[0]);

        if ($faxPickup) {
            $it['PickupFax'] = $faxPickup;
        }

        // PickupHours
        $it['PickupHours'] = trim(str_replace("\n", " ", $this->re("#Opening times:\s+([\d\s:-]+)#ms", $table[0])));

        // DropoffPhone
        $phoneDropoff = $this->re('/Phone:[ ]*(' . $patterns['phone'] . ')$/m', $table[1]);

        if ($phoneDropoff) {
            $it['DropoffPhone'] = $phoneDropoff;
        }

        // DropoffFax
        $faxDropoff = $this->re('/Fax:[ ]*(' . $patterns['phone'] . ')$/m', $table[1]);

        if ($faxDropoff) {
            $it['DropoffFax'] = $faxDropoff;
        }

        // DropoffHours
        $it['DropoffHours'] = trim(str_replace("\n", " ", $this->re("#Opening times:\s+([\d\s:-]+)#ms", $table[1])));

        // RentalCompany
        $localPartner = $this->re('/\bLocal partner:[ ]+(.*?)[ ]+Booking No:/i', $text);

        if ($localPartner) {
            $it['RentalCompany'] = $localPartner;
        }

        // CarType
        // CarModel
        $textCarInfo = $this->re('/^([ ]*Car group:.*?Issue date:)/ims', $text);
        $posColumn1 = strpos($textCarInfo, 'Car group:');
        $tableCarInfo = $this->SplitCols($textCarInfo, [$posColumn1, $posColumn2]);

        if (!empty($tableCarInfo[0])) {
            if (preg_match('/Car group:[ ]+([^\n]+)(?:\s+(e\.g\..+?)\s+Issue date:)?/is', $tableCarInfo[0], $matches)) {
                $it['CarType'] = $matches[1];

                if (!empty($matches[2])) {
                    $it['CarModel'] = str_replace("\n", ' ', $matches[2]);
                }
            }
        }

        // RenterName
        $it['RenterName'] = $this->re('/\bDriver name:[ ]+(.+)/', $text);

        // Currency
        // TotalCharge
        if (preg_match('/^' . $this->opt($this->t('Ihr Rechnungsbetrag')) . ':?[ ]+([A-Z]{3})[ ]+(\d[,.\d ]*)$/m', $cpdftext, $matches)) {
            $it['Currency'] = $matches[1];
            $it['TotalCharge'] = $this->normalizePrice($matches[2]);
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($textPdf, 'CarDelMar') === false) {
            return false;
        }

        return $this->assignLang($textPdf);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->parser = $parser;
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        if ($this->assignLang($textPdf) === false) {
            return null;
        }

        $itineraries = [];
        $this->parsePdf($textPdf, $itineraries);
        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+\.\d+\.\d{4}, \d+:\d+)$#", //31.10.2017, 18:00
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
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

    private function TableHeadPos($row)
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

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
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
