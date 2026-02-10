<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationAVIS extends \TAccountChecker
{
    public $mailFiles = "avis/it-37325896.eml";

    public $reFrom = ["@avis.com.mx"];
    public $reBody = [
        'es' => ['¡Tu vehículo ya esta prepagado!'],
    ];
    public $reSubject = [
        'Reservación AVIS México - Prepago',
    ];
    public $lang = '';
    public static $dict = [
        'es' => [
            'Entrega'    => 'Entrega',
            'Devolución' => 'Devolución',
        ],
    ];
    private $keywordProv = 'AVIS';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Avis México' or contains(@src,'avis.mx')] | //a[contains(@href,'avis.mx')]")->length > 0
            && $this->detectBody()
        ) {
            return $this->assignLang();
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
                if (($fromProv || (stripos($headers["subject"], $this->keywordProv) !== false))
                    && stripos($headers["subject"], $reSubject) !== false
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

    private function parseEmail(Email $email)
    {
        $r = $email->add()->rental();

        // general info
        $r->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Gracias'))}]/following::text()[normalize-space()!=''][1]"))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Número de reservación:'))}]/following::text()[normalize-space()!=''][1]"),
                trim($this->t('Número de reservación:'), ":"));

        // sums
        $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Garantizado'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));
        $r->price()
            ->total($total['Total'])
            ->currency($total['Currency']);

        // pick-up
        $xpath = "//text()[{$this->eq($this->t('Entrega'))}]/ancestor::td[1]";
        $node = $this->http->XPath->query($xpath);

        if ($node->length == 1) {
            $root = $node->item(0);
            $dt = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]",
                $root));
            $r->pickup()->date($dt);
            $node = implode("\n",
                $this->http->FindNodes("./descendant::text()[normalize-space()!=''][position()>2]", $root));

            if (preg_match("#(.+)\s+{$this->opt($this->t('Tel.'))}\s+([\d\-\+\(\) ]+)#", $node, $m)) {
                $r->pickup()
                    ->location(preg_replace("#\s+#", ' ', $m[1]))
                    ->phone(trim($m[2]));
            }
        }

        //drop-off
        $xpath = "//text()[{$this->eq($this->t('Devolución'))}]/ancestor::td[1]";
        $node = $this->http->XPath->query($xpath);

        if ($node->length == 1) {
            $root = $node->item(0);
            $dt = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]",
                $root));
            $r->dropoff()->date($dt);
            $node = implode("\n",
                $this->http->FindNodes("./descendant::text()[normalize-space()!=''][position()>2]", $root));

            if (preg_match("#(.+)\s+{$this->opt($this->t('Tel.'))}\s+([\d\-\+\(\) ]+)#", $node, $m)) {
                $r->dropoff()
                    ->location(preg_replace("#\s+#", ' ', $m[1]))
                    ->phone(trim($m[2]));
            }
        }

        // carImage
        $src = $this->http->FindSingleNode("//img[@alt='{$this->t('Vehículo Avis')}' and contains(@src,'www.avis.com/content/')]/@src");

        if (!empty($src)) {
            $r->car()
                ->image($src);
        }

        // carType
        $r->car()
            ->type($this->http->FindSingleNode("//text()[normalize-space()='Vehículo']/following::text()[normalize-space()!=''][1]"));

        return true;
    }

    private function normalizeDate($date)
    {
        //	    $this->logger->debug($date);
        $in = [
            //Sábado, 13 de Julio, 2019, @12:00 p. m.
            '#^(\w+),\s+(\d+)\s+de\s+(\w+),\s+(\d{4}),\s*\@(\d+:\d+)\s+([ap])[\. ]*(m)[\. ]*$#iu',
        ];
        $out = [
            '$2 $3 $4, $5$6$7',
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

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Entrega'], $words['Devolución'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Entrega'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Devolución'])}]")->length > 0
                ) {
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
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
}
