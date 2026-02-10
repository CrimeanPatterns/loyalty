<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class QantasETicketPlainOrPdf extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-1702380.eml, amadeus/it-2904006.eml, amadeus/it-29373977.eml, amadeus/it-3098864.eml, amadeus/it-37682614.eml, amadeus/it-40548789.eml, amadeus/it-40549525.eml";

    public $reBody = [
        'en' => ['DEPART', 'CUSTOMER NAME'],
    ];
    public $reSubject = [
        '#.*[ ]\d{2}\w{3} [A-Z]{3} [A-Z]{3}$#u',
        '#.*[ ]\d{2}\w{3} [A-Z\d]{5,6}$#u',
    ];
    private $lang = '';
    private $pdfNamePattern = ".*pdf";
    private static $dict = [
        'en' => [
        ],
    ];
    private $text;
    private $code;
    private static $providers = [
        'qantas' => [
            'from' => ['@qantas.com.au'],
            'body' => [
                'QANTAS.COM',
            ],
        ],
        'amadeus' => [
            'from' => ['amadeus'],
            'body' => [
                'eticket@amadeus.com',
                '.amadeus.net',
            ],
        ],
        'frosch' => [
            'from' => ['@frosch.com'],
            'body' => [
                '@frosch.com',
            ],
        ],
        'mta' => [
            'from' => ['@mtatravel.com.au'],
            'body' => [
                'mtatravel.com.au',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $this->text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($this->detectBody($this->text)) {
                    $this->parseEmail($email);
                }
            }
        }

        if (count($email->getItineraries()) === 0) {
            $plain = $parser->getPlainBody();

            if (!empty($plain) && count(array_filter(array_map("trim", explode("\n", $plain)))) > 30) {
                $this->text = $plain;
            } else {
                $this->text = $parser->getHTMLBody();
            }

            $this->text = $this->clearText();

            if (30 > count(array_filter(array_map("trim", explode("\n", $this->text))))) {
                $this->text = $plain;
            }

            if (!$this->assignLang($this->text)) {
                $this->logger->debug('can\'t determine a language');

                return $email;
            }
            $this->parseEmail($email);
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if (null !== $code = $this->getProvider($parser)) {
            $email->setProviderCode($code);
        }

        return $email;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        $bySubj = false;

        foreach ($this->reSubject as $reSubject) {
            if (preg_match($reSubject, $headers['subject']) > 0) {
                $bySubj = true;

                break;
            }
        }

        if (!$bySubj) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            if ($byFrom) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($this->detectBody($text)) {
                    return true;
                }
            }
        }
        $plain = $parser->getPlainBody();

        if (!empty($plain) && count(array_filter(array_map("trim", explode("\n", $plain)))) > 30) {
            $text = $plain;
        } else {
            $text = $parser->getHTMLBody();
        }
        $text = str_replace("<wbr>", '', $text);
        $text = $this->clearText($text);

        if (30 > count(array_filter(array_map("trim", explode("\n", $this->text))))) {
            $text = $plain;
        }

        if ($this->detectBody($text)) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $cnt = 2; // body | attach

        return $cnt * count(self::$dict);
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'amadeus') {
                return null;
            } else {
                return $this->code;
            }
        }

        if (isset($this->text) && !empty($this->text)) {
            return $this->getProviderByText($this->text);
        }

        return $this->getProviderByBody($parser);
    }

    private function getProviderByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (null !== ($code = $this->getProviderByText($text))) {
                    return $code;
                }
            }
        }
        $text = empty($parser->getHTMLBody()) ? $parser->getPlainBody() : $parser->getHTMLBody();
        $text = str_replace("<wbr>", '', $text);

        if (null !== ($code = $this->getProviderByText($text))) {
            return $code;
        }

        return null;
    }

    private function getProviderByText($text)
    {
        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($text, $search) !== false) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function detectBody($text)
    {
        if ((strpos($text, '- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -') !== false
                && strpos($text, '---------------------------') !== false)
            || (strpos($text, '---------------------------') !== false
                && (preg_match("#ECONOMY\s+CLASS\s+\(E\)\s+SPACE#", $text)
                    || preg_match("#\n\s*.+? (?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*\d+ [^\n]+\n\s*DEPART\s+\d+[^\d\s]{3}#", $text)
                    || preg_match("#ECONOMY\s+CLASS\s+\(Q\)\s+CONFIRMED#", $text)
                )
            )
        ) {
            return $this->assignLang($text);
        }

        return false;
    }

    private function clearText(?string $text = null)
    {
        if (!isset($text)) {
            $text = $this->text;
        }
        $text = str_replace("\r", '', $text);
        $text = preg_replace("#([\<])([^\n]+@[^\n>]+)([\>])#", '$2', $text);
        $text = str_replace(['<mailto', 'http://'], ['', ''], $text); // dirty hack
        $text = strip_tags($text);
        $NBSP = chr(194) . chr(160);
        $text = str_replace($NBSP, ' ', html_entity_decode($text));
        $text = str_replace("\n>", "\n", $text);
        $text = str_replace("\n>", "\n", $text);

        return $text;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();
        $r->general()
            ->confirmation($this->re("#{$this->opt($this->t('BOOKING REF'))}\s*:\s*([A-Z\d]{5,6})#"))
            ->traveller($this->re("#\n\s*CUSTOMER NAME\s*:\s*([^\n]+)#"), true);

        if ($dateRes = $this->normalizeDate($this->re("#{$this->opt($this->t('DATE'))}:\s*(\d+\s*\w+\s*\d+)#"))) {
            $r->general()
                ->date($dateRes);
        }

        if (preg_match("#^[ ]*E-TICKET NO\s*:\s*(\d{3}[- ]*\d{5,}[- ]*\d{1,2})[ ]*$#m", $this->text, $m)) {
            // E-TICKET NO:  081 2303469257
            $r->issued()->ticket(preg_replace('/\s+/', ' ', $m[1]), false);
        }

        if (($acc = $this->re("#^[ ]*MEMBERSHIP NO\s*:\s*([A-Z\d][\-A-Z\d ]{5,}[A-Z\d])(?:[ ]{2}|[ ]*$)#m"))) {
            // MEMBERSHIP NO:  QF 5810903
            $r->program()->account(preg_replace('/\s+/', ' ', $acc), false);
        }

        $total = $this->getTotalCurrency($this->re("#\n\s*GRAND TOTAL\s*:\s*([^\n]+)#"));

        if ($total['Total'] !== null) {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
            $cost = $this->getTotalCurrency($this->re("#\n\s*FARE\s*:\s*([^\n]+)#"));

            if ($cost['Total'] !== null && $cost['Currency'] === $total['Currency']) {
                $r->price()
                    ->cost($cost['Total']);
            }
            $tax = $this->getTotalCurrency($this->re("#\n\s*TAXES/FEES/CARRIER CHARGES[\s:]+([^\n]+)#"));

            if ($tax['Total'] !== null && $tax['Currency'] === $total['Currency']) {
                $r->price()
                    ->tax($tax['Total']);
            }
        }

        $segments = $this->splitter("#\n\s*(.+? (?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*\d+ [^\n]+\n\s*DEPART\s+\d+[^\d\s]{3})#",
            $this->text);

        foreach ($segments as $segment) {
            // del garbage
            if ($str = strstr($segment, "-------------------------", true)) {
                $segment = $str;
            }

            $s = $r->addSegment();
            // airline, cabin
            if (preg_match("#^.+? (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(?<fn>\d+)[ ]+\((?<bc>[A-Z]{1,2})\)#", $segment,
                $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
                $s->extra()
                    ->bookingCode($m['bc']);
            } elseif (preg_match("#^.+? (?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(?<fn>\d+) (?<cabin>.+?) \((?<bc>[A-Z]{1,2})\)#",
                $segment, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bc']);
            }
            // departure
            if (preg_match("#^[ ]*DEPART[ ]+(?<day>\d{1,2}[[:alpha:]]{3,}\d{2})[ ]+(?<name>.+?)[ ]+(?<h>\d{2})(?<m>\d{2})[ ]*$#mu", $segment, $m)) {
                $dayDep = $this->normalizeDate($m['day']);
                $s->departure()
                    ->noCode()
                    ->name($m['name'])
                    ->date(strtotime($m['h'] . ':' . $m['m'], $dayDep));
            }

            if (preg_match("#^\s*DEPARTS FROM:[ ]*TERMINAL[ ]+(.+)#m", $segment, $m)) {
                $s->departure()->terminal(trim($m[1]));
            }
            // arrival
            if (preg_match("#^[ ]*ARRIVE[ ]+(?<day>\d{1,2}[[:alpha:]]{3,}\d{2})[ ]+(?<name>.+?)[ ]+(?<h>\d{2})(?<m>\d{2})[ ]*$#mu", $segment, $m)) {
                $dayArr = $this->normalizeDate($m['day']);
                $s->arrival()
                    ->noCode()
                    ->name($m['name'])
                    ->date(strtotime($m['h'] . ':' . $m['m'], $dayArr));
            }

            if (preg_match("#^\s*ARRIVES AT:[ ]*TERMINAL[ ]+(.+)#m", $segment, $m)) {
                $s->arrival()->terminal(trim($m[1]));
            }
            // duration
            if ($duration = $this->re("#\s(\d+:\d+)\s+DURATION#i", $segment)) {
                $s->extra()->duration($duration);
            }
            // smoking
            if (preg_match("#NON[\-\s]*SMOKING#i", $segment)) {
                $s->extra()->smoking(false);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            // 03MAY19    |    4FEB20
            '#^(\d{1,2})\s*([[:alpha:]]{3,})\s*(\d{2})$#u',
        ];
        $out = [
            '$1 $2 20$3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

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
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, ?string $str = null, $c = 1)
    {
        if (!isset($str)) {
            $str = $this->text;
        }

        if (preg_match($re, $str, $m) > 0 && isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
