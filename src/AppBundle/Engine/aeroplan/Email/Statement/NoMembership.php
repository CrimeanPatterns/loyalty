<?php

namespace AwardWallet\Engine\aeroplan\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NoMembership extends \TAccountChecker
{
    public $mailFiles = "aeroplan/statements/it-62807145.eml, aeroplan/statements/it-62807283.eml, aeroplan/statements/it-63544717.eml, aeroplan/statements/it-63545221.eml, aeroplan/statements/it-63547059.eml, aeroplan/statements/it-73250280.eml";
    private $lang = '';
    private $format = null;
    private $reFrom = ['@rel1.aeroplan.com', '@mail.aircanada.com'];
    private $statusVariants = ['Member', '25K', '35K', '50K', '75K', 'Elite', 'Super Elite'];

    private static $dictionary = [
        'fr' => [
            'As a member since'     => 'En tant que membre depuis',
            'name'                  => ['Afin de créer un programme de fidélité inégalé', 'Vous voulez vos primes plus vite'],
            'Hi'                    => 'Bonjour',
            'miles'                 => 'milles',
            'View Online'           => 'Version Web',
            'you currently have'    => 'vous avez',
            'Mileage balance as of' => 'Solde de milles en date du',
        ],
        'en' => [
            'name'                  => 'To guide us in building the very best travel Loyalty program',
            'Hi'                    => ['Hi', 'Dear'],
            'Mileage balance as of' => ['Mileage balance as of', 'Individual points balance as of'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('NoMembership' . ucfirst($this->lang));

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $enrollmentDate = $balance = $status = $name = $login = null;

        // As a member since August 2016,
        $enrollmentDate = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('As a member since'))}]", $root,
            false, "/\s+([[:alpha:]]{3,} \d{4}),/u");

        if (!empty($enrollmentDate)) {
            $st->addProperty('EnrollmentDate', $enrollmentDate);
        }

        $balance = $this->normalizeAmount(
            $this->http->FindSingleNode('.', $root, true, "/{$this->opt($this->t('you currently have'))}\s+(\d[,.\'\d ]*?)[ ]*{$this->opt($this->t('miles'))}/")
            ?? $this->http->FindSingleNode('*[1]', $root, true, "/^(\d[,.\'\d ]*?)[ ]*pts/i") // it-73250280.eml
        );

        if ($balance !== null) {
            $st->setBalance($balance);

            // *Mileage balance as of July 27, 2020
            $balanceDate = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Mileage balance as of'))}]", null, false, "/{$this->opt($this->t('Mileage balance as of'))}\s+(.+)/");

            if (!empty($balanceDate)) {
                $st->setBalanceDate(strtotime($this->normalizeDate($balanceDate)));
            }
        }

        if ($this->format === 2 // it-73250280.eml
            && ($status = $this->http->FindSingleNode('*[2]', $root, true, "/^{$this->opt($this->statusVariants)}$/i"))
        ) {
            $st->addProperty('Status', $status);
        }

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:[ ]*[,:;!?]|$)/u");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('name'))}]/ancestor::*[1]", null, true, "/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]),\s*{$this->opt($this->t('name'))}/u");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $login = $this->http->FindSingleNode("(//a[{$this->eq($this->t('View Online'))}])[1]/@href", null, false, '/&eid=(\S+?@\S+?)[&#]/')
            ?? $this->http->FindSingleNode("//meta[@name='EMAIL_ADDRESS']/@content", null, true, '/^\S+@\S+$/');

        if (!empty($login)) {
            $st->setLogin($login);
        }

        if ($balance === null && ($enrollmentDate || $status || $name)) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".aeroplan.com/") or contains(@href,".aircanada.com/") or contains(@href,"rel1.aeroplan.com") or contains(@href,"mail.aircanada.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.aeroplan.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findRoot()->length === 1;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $this->format = 1;
        $nodes = $this->http->XPath->query("//*[count(tr[normalize-space()])=1]/tr[ not(.//tr) and count(*[normalize-space()])=1 and descendant::text()[{$this->eq($this->t('you currently have'))}] ]");

        if ($nodes->length !== 1) {
            // it-73250280.eml
            $this->format = 2;
            $nodes = $this->http->XPath->query("//*[count(tr[normalize-space()])=1]/tr[ *[1][contains(.,'pts')] and *[2][normalize-space()] ]");
        }

        return $nodes;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function normalizeDate($str)
    {
        $in = [
            // July 27, 2020
            '#^([[:alpha:]]{3,}) (\d+), (\d{4})$#',
            // 27 juillet 2020
            '#^(\d+) ([[:alpha:]]{3,}) (\d{4})$#',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
