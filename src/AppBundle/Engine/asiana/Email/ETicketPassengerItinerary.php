<?php

namespace AwardWallet\Engine\asiana\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketPassengerItinerary extends \TAccountChecker
{
    public $mailFiles = "asiana/it-10000607.eml, asiana/it-2394956.eml, asiana/it-6024058.eml, asiana/it-622070093.eml, asiana/it-674883388.eml, asiana/it-679808766.eml, asiana/it-8654743.eml";

    public $reFrom = "flyasiana.com";
    public $reBody = [
        'en' => ['ASIANA AIRLINES', 'Reservation No'],
    ];
    public $reSubject = [
        'E-Ticket Passenger Itinerary',
    ];
    public $date;
    public $lang = '';
    public $pdf;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $cnt = count($pdfs);

        for ($i = 0; $i < $cnt; $i++) {
            $this->date = strtotime($parser->getDate());
            $text = text(\PDF::convertToHtml($parser->getAttachmentBody($pdfs[$i]), \PDF::MODE_SIMPLE));
            $this->AssignLang($text);
            $this->parseEmail($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
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

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail(Email $email, $textPdf)
    {
        if (strpos($textPdf, 'receipt must be presented to') !== false) {
            $textPdf = strstr($textPdf, 'receipt must be presented to', true);
        }

        if (strpos($textPdf, 'tickets or making changes to') !== false) {
            $textPdf = strstr($textPdf, 'tickets or making changes to', true);
        }

        $f = $email->add()->flight();

        if (preg_match("/{$this->opt($this->t('Passenger Name'))}[\s\S]+\b{$this->opt($this->t('Reservation No.'))}\s+(?<c1>[A-Z\d]{5,})(?:\s*\((?<c2>[A-Z\d]{5,})\))?\n/", $textPdf, $m)) {
            $f->general()
                ->confirmation($m['c1']);

            if (!empty($m['c2'])) {
                $f->general()
                    ->confirmation($m['c2']);
            }
        }

        $f->general()
            ->travellers(preg_replace(["/ (MR|MRS|MISS|MSTR|MS)$/", "/^\s*(.+?)\s*\/\s*(.+?)\s*$/"], ['', '$2 $1'],
                $this->res("/\b{$this->opt($this->t('Passenger Name'))}\s+(.+)/", $textPdf), true));

        $accounts = $this->res("/\b{$this->opt($this->t('Frequent Flyer No.'))}\s+(\d+)\n/", $textPdf);

        if (!empty($accounts)) {
            $f->program()
                ->accounts($accounts, false);
        }
        $f->issued()
            ->tickets($this->res("/\b{$this->opt($this->t('Ticket Number'))}\s+(\d{13}(?:[\s\-]+\d+)?)\n/", $textPdf), false);

        // Price
        $currency = $this->re("#Total Amount\s+([A-Z]{3})\s*#", $textPdf);
        $cost = $this->re("#\n\s*Equivalent Fare\s+{$currency}\s*([\d\., ]+)\n#", $textPdf);

        if (empty($cost)) {
            $cost = $this->re("#\bFare\s+{$currency}\s*([\d\., ]+)\n#", $textPdf);
        }

        if (preg_match("#Total Amount\s+[A-Z]{3}\s*(\d[\d, \.]*)\n#", $textPdf)) {
            $f->price()
                ->cost(PriceHelper::parse($cost, $currency))
                ->currency($currency)
                ->total(PriceHelper::parse($this->re("#Total Amount\s+[A-Z]{3}\s*(\d[\d, \.]*)\n#", $textPdf), $currency))
            ;
        }

        $nodes = $this->splitter("#(.+\n.+\n\s*[A-Z\d]{2}\d+\s+[A-Z]{1,2}\s+\d+)\s*#", $textPdf);

        foreach ($nodes as $node) {
            $s = $f->addSegment();
            $regExp = "(?<depName1>.+)\n(?<arrName1>.+)\n\s*(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)\s+(?<BookingClass>[A-Z]{1,2})\s+"
                . "(?<depDay>\d+)\s*(?<depMnth>\w+?)\s*(?<depYear>\d+)\n\s*(?<depTime>\d+:\d+)\n\s*(?<arrTime>\d+:\d+)(?:\s*(?<addDays>[\+\-]\s*\d))?\s+(?<Duration>\d+H\d+M)?\n(?<Status>.+)\n(?<Seat>.*)\n";

            $depName = $arrName = "";

            if (preg_match("#{$regExp}#", $node, $m)) {
                $depName = $m['depName1'];
                $arrName = $m['arrName1'];

                $s->airline()
                    ->name($m['AirlineName'])
                    ->number($m['FlightNumber'])
                ;

                $s->extra()
                    ->bookingCode($m['BookingClass'])
                    ->duration($m['Duration'], true, true);

                if (!empty($m['Seat']) && preg_match("#^\s*(\d{1,3}[A-Z])\s*$#i", $m['Seat'], $v)) {
                    $s->extra()
                        ->seat($v[1]);
                }
                $date = strtotime($this->dateStringToEnglish($m['depDay'] . ' ' . $m['depMnth'] . ' ' . $m['depYear']));
                $s->departure()
                    ->date(strtotime($m['depTime'], $date));

                $s->arrival()
                    ->date(strtotime($m['arrTime'], $date));

                if (isset($m['addDays']) && !empty($m['addDays']) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($m['addDays'] . " days", $s->getArrDate()));
                }
            }
            $regExp = "(?<Duration>\d+H\d+M)\n(?<Status>.+)\n(?<Seat>.*)\n(?<depName2>.+)\n(?<arrName2>.+)\n\s*\([A-Z]{3}\)";

            if (preg_match("#{$regExp}#", $node, $m)) {
                $depName .= "\n" . $m['depName2'];
                $arrName .= "\n" . $m['arrName2'];
            }
            $regExp = "\n\s*\([A-Z]{3}\)\n(?<depName3>.+)\n(?<arrName3>.+)\nOperated by";

            if (preg_match("#{$regExp}#", $node, $m)) {
                $depName .= "\n" . $m['depName3'];
                $arrName .= "\n" . $m['arrName3'];
            }

            if (preg_match("#^(.+?)\s*(?:Terminal\s*(.+))?$#is", $depName, $m)) {
                $s->departure()
                    ->noCode()
                    ->name(trim(preg_replace("#\s+#", ' ', $m[1])));

                if (isset($m[2]) && !empty($m[2])) {
                    $s->departure()
                        ->terminal($m[2]);
                }
            }

            if (preg_match("#^(.+?)\s*(?:Terminal\s*(.+))?$#is", $arrName, $m)) {
                // $seg['ArrName'] = trim(preg_replace("#\s+#", ' ', $m[1]));
                $s->arrival()
                    ->noCode()
                    ->name(trim(preg_replace("#\s+#", ' ', $m[1])));

                if (isset($m[2]) && !empty($m[2])) {
                    $s->arrival()
                        ->terminal($m[2]);
                }
            }
        }

        $this->logger->debug('$textPdf = ' . print_r($textPdf, true));

        return true;
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

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
