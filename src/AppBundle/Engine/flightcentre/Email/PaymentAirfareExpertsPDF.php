<?php

namespace AwardWallet\Engine\flightcentre\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class PaymentAirfareExpertsPDF extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-27815490.eml, flightcentre/it-27817868.eml, flightcentre/it-27818033.eml, flightcentre/it-27818122.eml, flightcentre/it-27818201.eml";

    public $reFrom = ["flightcentre.com.au"];
    public $reBody = [
        'en' => ['Airfare/Tour/Accommodation Details', 'PAYMENT INVOICE'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'segmentRegExp' => '^.*? *?(?:[A-Z\d][A-Z]|[A-Z][A-Z\d]) *\d+\/\d+\w+ \d+:\d+ \d+:\d+',
        ],
    ];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $i => $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug("can't determine a language in {$i}-attach");

                        continue;
                    }

                    if (!$this->parseEmailPdf($text, $email)) {
                        return null;
                    }
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'Flight Centre Travel Group') !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
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

    private function parseEmailPdf($textPDF, Email $email)
    {
        if (!empty($confNo = $this->re("/{$this->opt($this->t('Booking ref'))}: +([A-Z\d]{5,})/", $textPDF))) {
            $email->ota()->confirmation($confNo, $this->t('Booking ref'));
        }
        $this->date = $this->normalizeDate($this->re("/{$this->opt($this->t('Date Of Travel'))}: +(.+)/", $textPDF));

        if (!empty($str = strstr($textPDF, 'Booking Terms and Conditions', true))) {
            $textPDF = $str;
        }
        $textPDF = $this->re("/{$this->opt($this->t('Airfare/Tour/Accommodation Details'))}:[^\n]*\n(.+)/s", $textPDF);

        $reservations = $this->splitter("/^( *(?:Flights for:|Flights:|Tour:|Train:))/m", $textPDF);

        foreach ($reservations as $reservation) {
            if (preg_match("/^ *{$this->opt($this->t('Tour:'))}/", $reservation)) {
                $this->logger->debug('skip Tour-info: no dates');

                continue;
            }

            if (preg_match("/^ *{$this->opt($this->t('Train:'))}\s+\-? *to be advised/", $reservation)) {
                $this->logger->debug('skip Train-info: empty');

                continue;
            }

            if (preg_match("/^ *{$this->opt($this->t('Flights:'))}/", $reservation)) {
                if (!$this->parseFlights($reservation, $email)) {
                    return false;
                }
            } elseif (preg_match("/^ *{$this->opt($this->t('Flights for:'))}[^\n]*\n(.+)/s", $reservation, $m)) {
                if (!$this->parseFlightsFor($m[1], $email)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function parseFlights($text, Email $email)
    {
        $r = $email->add()->flight();
        $r->general()->noConfirmation(); //class fare
        $this->parsePrice($text, $r);
        $this->parseSegments($text, $r);

        return true;
    }

    private function parseFlightsFor($text, Email $email)
    {
        $text = "controlText\n" . $text;
        $flights = $this->splitter("/((?:^|\n)^ *(?:Mr|Sr|Ms)[^\n]+\s*?{$this->t('segmentRegExp')})/sm", $text);

        if (count($flights) === 0) {
            $this->logger->debug('need other regExp');

            return false;
        }

        foreach ($flights as $flight) {
            if (!preg_match("/({$this->t('segmentRegExp')})/m", $flight)) {
                continue;
            }
            $r = $email->add()->flight();
            $r->general()->noConfirmation();
            $paxText = $this->re("/(.+?)\n{$this->t('segmentRegExp')}/sm", $flight);
            $pax = array_filter(explode("\n", trim(preg_replace("/\s+/", ' ', $paxText))));
            $r->general()
                ->travellers($pax);
            $this->parsePrice($flight, $r);
            $this->parseSegments($flight, $r);
        }

        return true;
    }

    private function parseSegments($text, Flight $r)
    {
        $segments = $this->splitter("/({$this->t('segmentRegExp')})/m", $text);

        foreach ($segments as $segment) {
            $s = $r->addSegment();
            $rexExpStr = "/^.*? *?(?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d]) *(?<flight>\d+)\/" .
                "(?<dateDep>\d+ *\w+) (?<depTime>\d+:\d+) (?<arrTime>\d+:\d+) (\(?<dateArr>\d+ *\w+\))? *" .
                "(?<depName>.+)\/(?<arrName>.+)/m";

            if (preg_match($rexExpStr, $segment, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flight']);
                $dateDep = $dateArr = $this->normalizeDate($m['dateDep']);

                if (isset($m['dateArr'])) {
                    $dateArr = $this->normalizeDate($m['dateArr']);
                }
                $s->departure()
                    ->date(strtotime($m['depTime'], $dateDep))
                    ->name($m['depName']);
                $s->arrival()
                    ->date(strtotime($m['arrTime'], $dateArr))
                    ->name($m['arrName']);
            }
            $s->departure()->noCode();
            $s->arrival()->noCode();

            if (preg_match("/{$this->opt($this->t('operated by'))} (.+)/i", $text, $m)) {
                $s->airline()->operator(trim($m[1], ' )'));
            }
        }
    }

    private function parsePrice($text, Flight $r)
    {
        if (preg_match("/Payment for (.+?) {3,}(.\d[\d\.,]+)/", $text, $m)) {
            $r->general()
                ->traveller(preg_replace("/\s+/", " ", $m[1]));
            $payment = $this->getTotalCurrency($m[2]);
            $r->price()
                ->total($payment['Total'])
                ->currency($payment['Currency']);
        } elseif (preg_match("/(?:class fare|are economy) {3,}(.\d[\d\.,]+)/", $text, $m)) {
            $payment = $this->getTotalCurrency($m[1]);
            $r->price()
                ->cost($payment['Total'])
                ->currency($payment['Currency']);
        } elseif (preg_match_all("/per person x \d+ {3,}(.\d[\d\.,]+)/", $text, $m)) {
            $sum = 0.0;
            $cur = '';

            foreach ($m[1] as $v) {
                $payment = $this->getTotalCurrency($v);
                $sum += $payment['Total'];
                $cur = $payment['Currency'];
            }

            if (!empty($sum) && !empty($cur)) {
                $r->price()
                    ->cost($sum)
                    ->currency($cur);
            }
        }
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //09SEP
            '#^(\d+) *(\w+)$#u',
            // 7 SEP 18
            '#^(\d+) *(\w+?) *(\d{2})$#u',
        ];
        $out = [
            '$1 $2 ' . $year,
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
                    $this->lang = $lang;

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
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
