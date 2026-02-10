<?php

namespace AwardWallet\Engine\aircorsica\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "aircorsica/it-700583782.eml";

    public $date;
    public $pdfNamePattern = ".*\.pdf";
    public $lang;
    public static $dictionary = [
        'fr' => [
            // Html
            'Votre numéro de réservation' => 'Votre numéro de réservation',
            //            'Passager %' => '', // Passager 1
            'Heure de départ' => 'Heure de départ',
            //            'TOTAL DE VOS BILLETS' => '',

            // pdf
            'Facture N°' => 'Facture N°',
            // 'Vol' => '',
            // 'Passager(s) :' => '',
            // 'Montant Hors Taxes' => '',
            // 'Montant total de la facture en' => '',
        ],

        'en' => [
            // Html
            'Votre numéro de réservation' => 'Your booking number',
            'Passager %'                  => 'Passenger %', // Passager 1
            'Heure de départ'             => 'Departure time',
            'TOTAL DE VOS BILLETS'        => 'TOTAL AMOUNT OF YOUR FLIGHTS',
            'TTC'                         => 'INCL. TAXES',

            // pdf
            'Facture N°' => 'Facture N°',
            // 'Vol' => '',
            // 'Passager(s) :' => '',
            // 'Montant Hors Taxes' => '',
            // 'Montant total de la facture en' => '',
        ],
    ];

    private $detectFrom = "aircorsica@aircorsica.com";
    private $detectSubject = [
        // fr
        'Confirmation de votre réservation sur Air Corsica',
    ];
    private $detectBody = [
        'fr' => [
            'Détail de votre voyage',
        ],

        'en' => [
            'Details of your trip',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]aircorsica\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Air Corsica') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.aircorsica.com/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['choisi Air Corsica'])}]")->length === 0
        ) {
            return $this->detectPdfFiles($parser);
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return $this->detectPdfFiles($parser);
    }

    public function detectPdfFiles(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->assignLang($text);

            if ($this->detectPdf($text) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        if (empty($text)) {
            return false;
        }

        if (strpos($text, "@aircorsica.com") === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Facture N°']) && $this->containsText($text, $dict['Facture N°']) === true) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->date = strtotime($parser->getDate());

        $this->parseFlight($email);

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->assignLang($text);

            if ($this->detectPdf($text) === true) {
                if (count($email->getItineraries()) === 0 || count($email->getItineraries()[0]->getSegments()) === 0) {
                    $this->parseFacture($email, $text);
                } else {
                    $this->parsePrice($email, $text);
                }
            }
        }

        if (count($email->getItineraries()) === 1) {
            foreach ($email->getItineraries() as $it) {
                if (!$it->getPrice()) {
                    $totalText = $this->http->FindSingleNode("//*[{$this->eq($this->t('TOTAL DE VOS BILLETS'))}]/following-sibling::*[normalize-space()][1]",
                        null, true, "/^(.+?)\s*{$this->opt($this->t('TTC'))}\s*$/");

                    if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $totalText, $m)
                        || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $totalText, $m)
                    ) {
                        $m['currency'] = $this->currency($m['currency']);
                        $it->price()
                            ->total(PriceHelper::parse($m['amount'], $m['currency']))
                            ->currency($m['currency'])
                        ;
                    } else {
                        $it->price()
                            ->total(null);
                    }
                }
            }
        }

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

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Votre numéro de réservation'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->travellers($this->http->FindNodes("//text()[{$this->starts($this->t('Passager %'), 'translate(normalize-space(), "123456789", "%%%%%%%%%")')}]/following::text()[normalize-space()][1]",
                null, "/^\s*([[:alpha:] \-]{5,})\s*$/u"), true);

        $xpath = "//text()[{$this->eq($this->t('Heure de départ'))}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $node = $this->http->FindSingleNode("preceding::td[not(.//td)][normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\s*$/", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            $date = $this->http->FindSingleNode("preceding::td[not(.//td)][normalize-space()][3]", $root);
            $re = "/^\s*(?<time>\d{1,2}[h:]\d{2})\s+-\s+(?<name>.+?) (?<code>[A-Z]{3})(?:\s+-\s+(?i)(?<terminal>.*terminal.*))?\s*$/";

            // Departure
            $node = $this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][2]", $root);

            if (preg_match($re, $node, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date($this->normalizeDate($date . ', ' . $m['time']))
                    ->terminal(trim(preg_replace("/\s*\b{$this->opt($this->t('Terminal'))}\b\s*/", ' ', $m['terminal'] ?? '')), true, true)
                ;
            }

            // Arrival
            $node = $this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][4]", $root);

            if (preg_match($re, $node, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name'])
                    ->date($this->normalizeDate($date . ', ' . $m['time']))
                    ->terminal(trim(preg_replace("/\s*\b{$this->opt($this->t('Terminal'))}\b\s*/", ' ', $m['terminal'] ?? '')), true, true)
                ;
            }
        }

        return true;
    }

    private function parseFacture(Email $email, $text)
    {
        if ($email->getItineraries()[0]) {
            $email->removeItinerary($email->getItineraries()[0]);
        }
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("/\s+PNR ?: ?([A-Z\d]{5,7})(?: {3,}|\n)/", $text));

        $travellersText = $this->re("/\n\s*{$this->opt($this->t('Passager(s) :'))}\s*([\s\S]+?)\n\s*{$this->opt($this->t('Montant Hors Taxes'))}/", $text);
        $travellers = array_filter(explode("\n", $travellersText));
        $travellers = preg_replace('/^\s*(.+?)\s*\(.+\)$/s', '$1', $travellers);
        $f->general()
            ->travellers($travellers, true);

        $segmentsText = $this->re("/\s+PNR ?: ?.+\n([\s\S]+?)\n\s*{$this->opt($this->t('Passager(s) :'))}/", $text);
        $segments = array_filter(explode("\n", $segmentsText));

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            if (preg_match("/^\s*(?<day>[\d\/]{6,}) +{$this->opt($this->t('Vol'))} +(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5}) +(?<dname>.+?) - (?<aname>.+?)\s*$/", $sText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                if (in_array($this->lang, ['fr'])) {
                    $m['day'] = str_replace('/', '.', $m['day']);
                }
                $s->departure()
                    ->noCode()
                    ->name($m['dname'])
                    ->noDate()
                    ->day(strtotime($m['day']))
                ;

                $s->arrival()
                    ->noCode()
                    ->name($m['aname'])
                    ->noDate()
                ;
            }
        }

        $this->parsePrice($email, $text);

        return true;
    }

    private function parsePrice(Email $email, $text)
    {
        $f = $email->getItineraries()[0] ?? $email->getItineraries()[1] ?? $email->add()->flight();

        $currency = $this->re("/{$this->opt($this->t('Montant total de la facture en'))} *([A-Z]{3}) /", $text);
        $f->price()
            ->cost(PriceHelper::parse($this->re("/{$this->opt($this->t('Montant Hors Taxes'))} {3,}(\d[\d,. ]*)\n/", $text), $currency))
            ->total(PriceHelper::parse($this->re("/{$this->opt($this->t('Montant total de la facture en'))}.*? {3,}(\d[\d,. ]*)\n/", $text), $currency))
            ->currency($currency)
        ;

        $feesText = $this->re("/{$this->opt($this->t('Montant Hors Taxes'))}.+\n([\s\S]+?)\n\s*{$this->opt($this->t('Montant total de la facture en'))}/", $text);
        $feesRows = array_filter(explode("\n", $feesText));

        foreach ($feesRows as $fRow) {
            if (preg_match("/^ *(.+?) {3,}(\d[\d,. ]*?)\s*$/", $fRow, $m)) {
                $f->price()
                    ->fee($m[1], PriceHelper::parse($m[2], $currency));
            }
        }

        return true;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Votre numéro de réservation"], $dict["Heure de départ"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Votre numéro de réservation'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Heure de départ'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'starts-with(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date) || empty($this->date)) {
            return null;
        }

        $year = date('Y', $this->date);

        $in = [
            // Sun, Apr 09
            '/^\s*([[:alpha:]]+)\s+(\d+)\s+([[:alpha:]]+),\s*(\d{1,2})[:h](\d{2}(?:\s*[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1, $2 $3 ' . $year . ', $4:$5',
        ];

        $date = preg_replace($in, $out, $date);

        // $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>[\w\-]+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weekT = WeekTranslate::translate($m['week'], $this->lang);
            $weeknum = WeekTranslate::number1($weekT);
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return $date;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
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

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
