<?php

namespace AwardWallet\Engine\czech\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class OnlineBooking extends \TAccountChecker
{
    public $mailFiles = "czech/it-1.eml, czech/it-13951403.eml, czech/it-6786714.eml, czech/it-6787279.eml";

    public $reFrom = ["csa.cz", "czechairlines.com"];
    public $reBody = [
        'en' => ['Thank you for booking on-line', 'We are pleased that you chose Czech Airlines'],
        'cs' => [
            'Děkujeme Vám za použití systému On-line booking',
            'Jsme rádi, že jste si pro svou cestu vybrali České aerolinie',
        ],
    ];
    public $reSubject = [
        'CSA on-line booking',
    ];
    public static $dict = [
        'en' => [
        ],
        'cs' => [
            'Booking reference:' => 'Knihovací kód:',
            'Order number:'      => 'Číslo objednávky:',
            'Date of order:'     => 'Datum vytvoření:',
            'PASSENGERS'         => 'CESTUJÍCÍ',
            'Phone No.'          => 'Telefon',
            'Amount:'            => 'Částka:',
            'Flight:'            => 'Let:',
            'Operating carrier'  => 'Operující přepravce',
        ],
    ];
    private $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'csa.cz')]/@src | //a[contains(@href,'csa.cz') or contains(@href,'czechairlines.com')]/@href")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
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

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#^\s*([A-Z\d]{5,})\s*$#"),
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]"), true)
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Order number:'))}]/following::text()[normalize-space(.)!=''][1]",
                null, false, "#^\s*([A-Z\-\d]{5,})\s*$#"),
                $this->http->FindSingleNode("//text()[{$this->eq($this->t('Order number:'))}]"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Date of order:'))}]/following::text()[normalize-space(.)!=''][1]")))
            ->travellers($this->http->FindNodes("//text()[ ./preceding::h2[{$this->contains($this->t('PASSENGERS'))}] and ./following::text()[{$this->contains($this->t('Phone No.'))}] ][normalize-space(.)]", null, "/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u"));

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Amount:'))}]/following::text()[normalize-space(.)!=''][1]"));

        if (!empty($tot['Total'])) {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $xpath = "//text()[{$this->starts($this->t('Flight:'))}]/ancestor::*[2]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0) {
            $this->logger->debug('[Flight segments XPath]: ' . $xpath);
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $segmentHtml = $root->ownerDocument->saveHTML($root);
            $segmentText = $this->htmlToText($segmentHtml);

            $date = 0;

            if (preg_match("/^[ ]*{$this->opt($this->t('Flight:'))}[ ]*[^|\n]+?[ ]*\|[ ]*([^|\n]+?)[ ]*\|[ ]*([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)[ ]*$/m", $segmentText, $m)) {
                // Flight: Prague - Brussels | 13-11-2018 | OK 0630
                $date = strtotime($m[1]);
                $s->airline()
                    ->name($m[2])
                    ->number($m[3]);
            }

            if (preg_match("#[(]([A-Z]{3})[)].*[(]([A-Z]{3})[)]#s", $segmentText, $m)) {
                $s->departure()
                    ->code($m[1]);
                $s->arrival()
                    ->code($m[2]);
            }

            if (preg_match("#{$this->opt($this->t('Operating carrier'))}[\s:]+(.+)#", $segmentText, $m)) {
                $s->airline()->operator($m[1]);
            }

            $patterns['time'] = '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?';

            if ($date && preg_match("/^[ ]*({$patterns['time']}).+[ ]*$\s+^[ ]*({$patterns['time']}).+[ ]*$/m", $segmentText, $m)) {
                $s->departure()->date(strtotime($m[1], $date));
                $s->arrival()->date(strtotime($m[2], $date));
            }

            //check seats
            $node = str_replace(" ", '',
                $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight:'))}]/following::text()[normalize-space(.)!=''][1]",
                    $root, true, "#(.+?)\s*\|#"));
            $seats = array_values(array_filter($this->http->FindNodes("//text()[translate(normalize-space(.),' ','')='{$node}']/following::text()[normalize-space(.)!=''][1]",
                null, "/\((\d{1,5}[A-Z])\)/")));

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)[\.\-](\d+)[\.\-](\d+)$#',
        ];
        $out = [
            '$3-$2-$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function htmlToText($string = ''): string
    {
        $string = str_replace("\n", '', $string);
        $string = preg_replace('/<br\b[ ]*\/?>/i', "\n", $string); // only <br> tags
        $string = preg_replace('/<[A-z]+\b.*?\/?>/', '', $string); // opening tags
        $string = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $string); // closing tags
        $string = htmlspecialchars_decode($string);

        return trim($string);
    }
}
