<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ElectronicDocumentPdf extends \TAccountChecker
{
    public $mailFiles = "bcd/it-13232136.eml";

    public $reFrom = "@bcdtravel.com";
    public $reBody = [
        'en' => ['ELECTRONIC TICKET', 'PASSENGER ITINERARY/RECEIPT'],
    ];
    public $reSubject = [
        'BCD Travel Documentos Electronicos Reserva',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];
    public $pdfNamePattern = "ET\-[A-Z]{5,}.*pdf";
    private $date;
    private $keywords = [
        'bcd' => [
            'BCD TRAVEL',
        ],
        'aeromexico' => [
            'AEROMEXICO',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    $this->assignLang($text);

                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    }
                    $this->parseEmailPdf($email, $text);
                }
            }
        } else {
            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->assignLang($text);
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

    private function parseEmailPdf(Email $email, string $text)
    {
//        $this->logger->debug($text);
        $keyword = $this->re("#{$this->opt($this->t('ISSUING AGENT'))}[:\s]+(.+)#u", $text);

        foreach ($this->keywords as $code=> $kws) {
            foreach ($kws as $kw) {
                if (stripos($keyword, $kw) === 0) {
                    $email->ota()->code($code);
                }
            }
        }
        $email->ota()->keyword($keyword);

        $r = $email->add()->flight();
        $r->general()
            ->traveller($this->re("#{$this->opt($this->t('NAME'))}[:\s]+([\w \-]+\/[\w \-]+)#u", $text))
            ->date($this->normalizeDate($this->http->FindPreg("#{$this->opt($this->t('DATE OF ISSUE'))}[:\s]+(.+?)\s*(?:{$this->opt($this->t('IATA'))}|$)#", false, $text)))
            ->confirmation($this->http->FindPreg("#{$this->opt($this->t('BOOKING REFERENCE'))}[:\s]+([A-Z\d]{5,})#", false, $text));

        if (!empty($accNum = $this->re("#{$this->opt($this->t('FREQ TVL ID'))}[:\s]+(\d+)#u", $text))) {
            $r->program()
            ->account($accNum, false);
        }
        $keyword = $this->re("#{$this->opt($this->t('ISSUING AIRLINE'))}[:\s]+(.+)#u", $text);

        foreach ($this->keywords as $code=> $kws) {
            foreach ($kws as $kw) {
                if (stripos($keyword, $kw) === 0) {
                    $r->program()->code($code);
                }
            }
        }
        $r->program()->keyword($keyword);
        $r->issued()->ticket($this->re("#{$this->opt($this->t('ETKT NBR'))}[:\s]+([\d ]{5,})#", $text), false);

        $sumText = strstr($text, $this->t('FORM OF PAYMENT'));
        $tot = $this->getTotalCurrency($this->re("#^ *{$this->opt($this->t('FARE'))}[:\s]+(.+)#m", $sumText));

        if (!empty($tot['Total'])) {
            $r->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);
        }
        $tot = $this->getTotalCurrency($this->re("#^ *{$this->opt($this->t('TAX'))}[:\s]+(.+)#m", $sumText));

        if (!empty($tot['Total'])) {
            $r->price()
                ->tax($tot['Total'])
                ->currency($tot['Currency']);
        }
        $tot = $this->getTotalCurrency($this->re("#^ *{$this->opt($this->t('TOTAL'))}[:\s]+(.+)#m", $sumText));

        if (!empty($tot['Total'])) {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $v = $this->re("#{$this->opt($this->t('DATE'))}\s+{$this->opt($this->t('AIRLINE'))}[^\n]+\n\s*(\-{10,}[^\n]*\n.+)#s", $text);
        $reservations = strstr($v, $this->t('ENDORSEMENTS'), true);
        $segments = $this->splitter("#^(\d{2}\w+\s+.+?\s+\d+\s+{$this->opt($this->t('CLASE'))})#m", $reservations);

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            if (preg_match("#(\d{2}\w+)\s+(.+?)\s+(\d+)\s+{$this->opt($this->t('CLASE'))}.+?(\w+)\n#", $segment, $m)) {
                $date = $this->normalizeDate($m[1]);
                $s->airline()
                    ->name($m[2])
                    ->number($m[3]);
                $s->extra()->status($m[4]);
            }

            if (preg_match("#^ *{$this->opt($this->t('LV'))}[:\s]+(.+)\s+{$this->opt($this->t('AT'))}[:\s]+(\d{4})#m", $segment, $m)) {
                $time = $this->normalizeTime($m[2]);

                if (isset($date)) {
                    $s->departure()->date(strtotime($time, $date));
                }
                $s->departure()
                    ->name($m[1])
                    ->noCode();
            }

            if (preg_match("#^ *{$this->opt($this->t('AR'))}[:\s]+(.+)\s+{$this->opt($this->t('AT'))}[:\s]+(\d{4})#m", $segment, $m)) {
                $time = $this->normalizeTime($m[2]);

                if (isset($date)) {
                    $s->arrival()->date(strtotime($time, $date));
                }
                $s->arrival()
                    ->name($m[1])
                    ->noCode();
            }

            if (preg_match("#^ *{$this->opt($this->t('OPERATED BY'))}\s+(.+?)\s*(?:{$this->opt($this->t('DBA'))}|$)#m", $segment, $m)) {
                $s->setOperatedBy($m[1]);
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($text)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //08SEP16
            '#^(\d{2})\s*(\w{3})\s*(\d{2})$#u',
            //08SEP
            '#^(\d{2})\s*(\w{3})$#u',
        ];
        $out = [
            '$1 $2 20$3',
            '$1 $2 ' . $year,
        ];
        $outWeek = [
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = strtotime($str);
        }

        return $str;
    }

    private function normalizeTime($str)
    {
        $in = [
            //12.00
            '#^(\d+)\.(\d+(?:\s*[ap]m)?)$#i',
            //1200
            '#^(\d{2})(\d{2}(?:\s*[ap]m)?)$#i',
        ];
        $out = [
            '$1:$2',
            '$1:$2',
        ];
        $str = str_replace('.', '', preg_replace($in, $out, $str));

        return $str;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
