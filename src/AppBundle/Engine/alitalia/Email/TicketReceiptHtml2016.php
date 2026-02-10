<?php
namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Schema\Parser\Common\Flight;

class TicketReceiptHtml2016 extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-249107048.eml, alitalia/it-4195200.eml, alitalia/it-5397069.eml, alitalia/it-7555435.eml, alitalia/it-7625208.eml, alitalia/it-7625271.eml, alitalia/it-7625275.eml, alitalia/it-8356907.eml, alitalia/it-8425703.eml, alitalia/it-9338994.eml";

    public $subject = [
        'it' => ['Ricevuta Biglietto Elettronico', 'RICEVUTA BIGLIETTO ALITALIA'],
        'en' => ['ALITALIA TICKET RECIPT', 'ALITALIA TICKET RECEIPT', 'TICKET RECEIPT'],
    ];

    public $lang = '';

    public static $detectProvider = [
        'itaairways' => [
            'from' => ['@itaspa.com', '@ita-airways.com'],
            'text' => ['@itaspa.com', 'ITA Airways'],
        ],
        'alitalia' => [
            'from' => ['@alitalia.com'],
            'text' => ['ALITALIA'],
        ],
    ];
    public $langDetectors = [
        'it' => ['RICEVUTA BIGLIETTO ALITALIA'],
        'en' => ['ALITALIA TICKET RECEIPT', 'ALITALIA TICKET RECIPT', 'TICKET RECEIPT'],
    ];

    protected $providerCode;
    protected $dateYear = null;

    protected static $dictionary = [
        'it' => [
            "(PNR) IS"       => " (PNR) È",
            "passenger"      => ['Adulto'],
            "Ticket number:" => "N° Biglietto:",
            "DIRECT"         => "DIRETTO",
            ":"              => [":", "."],
            "Operated by"    => "Operato da",
            //			"SCALO" => "",
            "TAXES" => "TASSE",
//            "SUPPLEMENTS" => "",
            "TOTAL" => "TOTALE PAGATO",
            //			" days" => "",
        ],
        'en' => [
            //			"(PNR) IS" => "",
            "passenger" => ['Adult', 'Young', 'Child', 'Infant'],
            //			"Ticket number:" => "",
            //			"DIRECT" => "",
            //			":" => "",
            //			"Operated by" => "",
            "SCALO" => ["SCALO", "SCALI"],
            //			"TAXES" => "",
//            "SUPPLEMENTS" => "",
            //			"TOTAL" => "",
            //			" days" => "",
        ],
    ];

    private $result = [];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->dateYear = date('Y', strtotime($parser->getHeader('date')));

        if ($this->assignLang() === false) {
            return false;
        }

        $this->parseEmail($email);

        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $code => $detect) {
                if (!empty($detect['text']) && $this->http->XPath->query("//text()[{$this->contains($detect['text'])}]")->length > 0) {
                    $this->providerCode = $code;
                    break;
                }

                if (!isset($detect['from'])) {
                    continue;
                }
                foreach ($detect['from'] as $df) {
                    if (stripos($parser->getCleanFrom(), $df) !== false) {
                        $this->providerCode = $code;
                        break 2;
                    }
                }
            }
        }
        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $foundProvider = false;
        foreach (self::$detectProvider as $code => $detect) {
            if (!isset($detect['from'])) {
                continue;
            }
            foreach ($detect['from'] as $df) {
                if (stripos($headers['from'], $df) !== false) {
                    $foundProvider = true;
                    $this->providerCode = $code;
                    break 2;
                }
            }
        }

        if ($foundProvider == false) {
            return false;
        }

        foreach ($this->subject as $phrases) {
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
        foreach (self::$detectProvider as $code => $detect) {
            if (!empty($detect['text']) && $this->http->XPath->query("//text()[{$this->contains($detect['text'])}]")->length > 0) {
                $this->providerCode = $code;
                return $this->assignLang();
            }
        }


        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    protected function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('(PNR) IS'))}]/following::text()[normalize-space(.)][1]", null, false, '/\b[A-Z\d]{5,7}$/'))
            ->travellers($this->http->FindNodes("//h3[ ./following::text()[normalize-space(.)][1][{$this->contains($this->t('passenger'))}] ]", null, '/^[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]$/u'))
        ;

        // Issued
        $f->issued()
            ->tickets($this->http->FindNodes("//*[{$this->eq($this->t('Ticket number:'))}]/following-sibling::*[self::h4 or self::pre]", null, '/^\d{3}[-\s]*\d{4,}$/'), false);

        $xpathFragmentRow = '(self::tr or self::div)';

        $segments = $this->http->XPath->query("//*[$xpathFragmentRow and ./*[1][{$this->contains($this->t(':'))}] and ./*[2][{$this->contains('✈')} or .//img] and ./*[3][{$this->contains($this->t(':'))}] ]");
        if ($segments->length == 0) {
            $segments = $this->http->XPath->query("//*[$xpathFragmentRow and count(*) = 3 and ./*[1][{$this->contains($this->t(':'))}] and ./*[2][not(normalize-space())] and ./*[3][{$this->contains($this->t(':'))}] ]");
        }

        foreach ($segments as $segment) {

            $dateTexts = $this->http->FindNodes("./preceding::text()[{$this->contains($this->t('DIRECT'))} or {$this->contains($this->t('SCALO'))}][1]/ancestor::*[$xpathFragmentRow and normalize-space(.) and count(./*)>1][1]/descendant::text()[normalize-space(.)]", $segment);
            $dateText = implode("\n", $dateTexts);

            $flightTexts = $this->http->FindNodes("./preceding-sibling::*[$xpathFragmentRow and normalize-space(.)][1]/descendant::text()[normalize-space(.)]", $segment);
            $flightText = implode("\n", $flightTexts);

            $airportsTexts = $this->http->FindNodes("./descendant::text()[normalize-space(.)]", $segment);
            $airportsText = implode("\n", $airportsTexts);

            $this->parseSegment($f, preg_replace('/[\r\n]+|\s{2,}/', '  ', $dateText . '  ' . $flightText . '  ' . $airportsText));
        }

        // Price
        $currency = null;
        $total = implode(' ', $this->http->FindNodes('//text()[' . $this->contains($this->t('TOTAL')) . ']/following::*[normalize-space(.)][1]'));
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $currency = $m['currency'];
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        } else {
            $f->price()
                ->total(null);
        }
        $tax = str_replace(' ', '', $this->http->FindSingleNode('//text()[' . $this->contains($this->t('TAXES')) . ']/following::*[normalize-space(.)][1]'));
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $tax, $m)) {
            $f->price()
                ->tax(PriceHelper::parse($m['amount'], $m['currency']))
            ;
        }
        $fee = str_replace(' ', '', $this->http->FindSingleNode('//text()[' . $this->eq($this->t('SUPPLEMENTS')) . ']/following::*[normalize-space(.)][1]'));
        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $fee, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $fee, $m)) {
            $f->price()
                ->fee($this->http->FindSingleNode('//text()[' . $this->eq($this->t('SUPPLEMENTS')) . ']'),
                    PriceHelper::parse($m['amount'], $m['currency']))
            ;
        }

        return true;
    }

    protected function parseSegment(Flight $f, $text)
    {
        $s = $f->addSegment();

        if (preg_match("/(?:{$this->preg_implode($this->t('DIRECT'))}|{$this->preg_implode($this->t('SCALO'))})\s*(\d+\s+\w{3})\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)(?:\s*{$this->preg_implode($this->t('Operated by'))} ([A-z\s\-]+?))?\s*(Economy|Business|First|Premium)\s*\d/iu", $text, $match)) {
            // 15 MAR  AZ 2125  Operated by ALITALIA CITYLINER  Economy
            $date = strtotime($this->dateToEnglish($match[1] . ' ' . $this->dateYear));

            $s->airline()
                ->name($match[2])
                ->number($match[3])
            ;

            if (!empty($match[4])) {
                $s->airline()
                    ->operator($match[4]);
            }

            $s->extra()
                ->cabin($match[5]);
        }

        if (isset($date) && preg_match_all('/\b(\d{1,2}[:.]\d{2})\s*(?:\(([\+\-]\s*\d+)\))?\s+(?:([A-Z]{3})|[^(]+\(([A-Z]{3})\))/', $text, $match)) {
            // 19:00  Roma (FCO)  ✈  20:10  Milano (LIN)
            // 06.20  CAG  ✈  07.25  ROM

            $s->departure()
                ->date(strtotime($match[1][0], $date))
                ->code(!empty($match[3][0]) ? $match[3][0] : $match[4][0])
            ;

            $s->arrival()
                ->date(strtotime($match[1][1], $date))
                ->code(!empty($match[3][1]) ? $match[3][1] : $match[4][1])
            ;
            if (!empty($match[2][1]) && !empty($s->getArrDate())) {
                $s->arrival()
                    ->date(strtotime($match[2][1] . $this->t(' days'), $s->getArrDate()));
            }
        }

        return true;
    }

    protected function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function preg_implode($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function dateToEnglish($str)
    {
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if (($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) || ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], 'pl'))) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
