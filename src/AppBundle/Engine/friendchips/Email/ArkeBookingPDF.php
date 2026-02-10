<?php

namespace AwardWallet\Engine\friendchips\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ArkeBookingPDF extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-7064891.eml, friendchips/it-7122869.eml, friendchips/it-7279090.eml";
    public static $dict = [
        'nl' => [],
    ];

    private $detectFrom = ["noreply@arke.nl", "noreply@tui.nl"];
    private $detectSubject = [
        '#Bevestiging\s+van\s+boeking\s+met\s+nummer\s+[A-Z\d]+\s+bij\s+(?:Arke|TUI)\.nl#',
    ];
    private $detectBody = [
        'nl' => ['REISSPECIFICATIE', 'Reispakket'],
    ];
    private $lang = '';
    private $pdfNamePattern = "Reisspecificatie.*pdf";
    private $textPay; //for to get TotalCharge
    private $pdfNamePatternPay = "Factuur.*pdf";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePatternPay);

        foreach ($pdfs as $pdf) {
            if (($text .= text(\PDF::convertToText($parser->getAttachmentBody($pdf)))) !== null) {
            }
        }
        $this->textPay = $text;

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $pdfText = '';

        foreach ($pdfs as $pdf) {
            if (($pdfText .= \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
            } else {
                return $email;
            }
        }
        $this->AssignLang($pdfText);

        $this->parseEmail($email, $pdfText);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $foundFrom = false;

        if (isset($headers['from'])) {
            foreach ($this->detectFrom as $dFrom) {
                if (stripos($headers['from'], $dFrom) !== false) {
                    $foundFrom = true;

                    break;
                }
            }

            if ($foundFrom === false) {
                return false;
            }
        }

        if (isset($headers["subject"])) {
            foreach ($this->detectSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, 'Arke.nl') !== false || stripos($text, 'TUI.nl') !== false) {
                return $this->AssignLang($text);
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
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
            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($m[0], $this->lang)) {
                return preg_replace("#{$m[0]}}#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function parseEmail(Email $email, $textPDF)
    {
        $segmentsTexts = $this->findСutSection($textPDF, 'Algemene reisgegevens', 'Mededelingen');

        // Flight
        if (preg_match("#\n\s*(Vervoer\s+[\s\S]+?)\n\s*(?:Verblijf|Transfer|Mededelinge|$)#s", $segmentsTexts, $mat)) {
            $f = $email->add()->flight();
            $segments = $this->split("#\n\s*(Reiziger.+\s+\d+[\s\S]+?\n\s*(?:Vluchtnummer|Naar):)#", $mat[1]);

            foreach ($segments as $stext) {
                $s = $f->addSegment();

                // Airline
                if (preg_match("#Vluchtnummer:\s+([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]+(\d{1,5})\s+#", $stext, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2])
                    ;
                } elseif (!preg_match("#Vluchtnummer#", $stext)) {
                    $s->airline()
                        ->noName()
                        ->noNumber()
                    ;
                }

                // Departure
                $s->departure()
                    ->noCode()
                    ->name($this->re("#Van:[ ]+(.+?)[ ]{2,}#", $stext))
                    ->date($this->normalizeDate($this->re("#Vertrekdatum\/-tijd:[ ]+(.+?\d+:\d+)\s+#", $stext)))
                ;

                // Arrival
                $s->arrival()
                    ->noCode()
                    ->name($this->re("#Naar:[ ]+(.+?)[ ]{2,}#", $stext))
                    ->date($this->normalizeDate($this->re("#Aankomstdatum\/-tijd:[ ]+(.+?\d+:\d+)(?:\s+|$)#", $stext)))
                ;

                // Extra
                $s->extra()
                    ->stops($this->re("#Tussenstop:[ ]+(\d+)#", $stext), true, true)
                    ->aircraft($this->re("#Vliegtuigtype:[ ]+(.+)?(?:[ ]{2,}|\s*\n)#", $stext), true, true)
                    ->cabin($this->re("#Zitcomfort en service:[ ]+(.+)?(?:[ ]{2,}|\s*\n|\s*$)#", $stext), true, true)
                ;
            }
        }

        // Hotel
        if (preg_match("#\n\s*(Verblijf\s+[\s\S]+?)\n\s*(?:Transfer|Mededelinge|$)#s", $segmentsTexts, $mat)) {
            $segments = $this->split("#(\n\s*Accommodatie:.+\s+\d.+)#", $mat[1]);

            foreach ($segments as $stext) {
                $h = $email->add()->hotel();

                // Hotel
                $h->hotel()
                    ->name($this->re("#\n\s*Accommodatie:[ ]+(.+)#", $stext))
                    ->address($this->re("#\n\s*Plaats:[ ]+(.+)#", $stext))
                ;

                // Booked
                $h->booked()
                    ->checkIn($this->normalizeDate($this->re("#Aankomstdatum in accommodatie:[ ]+(.+)#", $stext)))
                    ->checkOut(strtotime('+' . $this->re("#Aantal nachten in deze accommodatie:[ ]+(\d+)\s+#", $stext) . ' days', $h->getCheckInDate()))
                ;

                $h->addRoom()->setType($this->re("#Kamertype:[ ]+(.+)#", $stext));
            }
        }

        // General
        $textInfo = strstr($textPDF, 'Algemene reisgegevens', true);
        $conf = $this->re("#Reserveringsnr:\s+([A-Z\d]+)#", $segmentsTexts);
        $reservationDate = $this->normalizeDate($this->re("#Boekingsdatum:\s+(.+)#", $textInfo));
        $textPax = $this->findСutSection($textInfo, 'Nat.', 'Reispakket');
        $passengers = [];

        if (preg_match_all("#\d\s+\w+\s+(.+?)\s+\d+\/\d+\/\d+#s", $textPax, $m)) {
            foreach ($m[1] as $p) {
                $passengers[] = preg_replace("#\s+#", " ", $p);
            }
        }

        foreach ($email->getItineraries() as $it) {
            $it->general()
                ->confirmation($conf)
                ->date($reservationDate)
                ->travellers($passengers);
        }

        $tripNumber = $this->re("#Boekingsnummer:[ ]+([A-Z\d]{5,})#", $textInfo);
        $email->ota()
            ->confirmation($tripNumber);

        if (!empty($this->textPay) && preg_match("#Boekingsnummer:[ ]*" . $tripNumber . "#", $this->textPay)
                 && preg_match("#\n\s*Totaalbedrag[ ]*(.+)#", $this->textPay, $m)) {
            $tot = $this->getTotalCurrency($m[1]);

            if (!empty($tot['Total'])) {
                $email->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency'])
                ;
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

    private function AssignLang($body)
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if (stripos($body, $dBody[0]) !== false && stripos($body, $dBody[1]) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        if (empty($date)) {
            return false;
        }
        $in = [
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#i',
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s*$#i',
        ];
        $out = [
            '$1.$2.$3 $4',
            '$1.$2.$3',
        ];
        $date = preg_replace($in, $out, $date);
        //		$date = $this->dateStringToEnglish($date);
        return strtotime($date);
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace('€', 'EUR', $node);
        $node = str_replace('$', 'USD', $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
}
