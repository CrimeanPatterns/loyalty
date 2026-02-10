<?php

namespace AwardWallet\Engine\airmaroc\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class CurrentMilesBalance extends \TAccountChecker
{
    public $mailFiles = "airmaroc/statements/it-212307078.eml, airmaroc/statements/it-222505173.eml, airmaroc/statements/it-223207592.eml, airmaroc/statements/it-223215179.eml, airmaroc/statements/it-223244502.eml";

    public $detectSubject = [
        // en
        'Your current Miles balance in',
        // fr
        'Votre solde actuel de Miles,',
    ];
    public $lang;

    public static $dictionary = [
        'en' => [
            'Safar Flyer ID' => 'Safar Flyer ID',
            'Your balance' => ['Your balance', 'Your Balance'],
            'Awards Miles' => ['Awards Miles', 'Award Miles'],
            'achievements' => 'achievements',
            'Status Miles' => 'Status Miles',
//            'Status Flights' => 'name',
//            'Status' => '',
        ],
        'fr' => [
            'Safar Flyer ID' => 'ID Safar Flyer',
            'Your balance' => 'Votre Solde',
            'Awards Miles' => 'Miles Primes',
            'achievements' => 'réalisations',
            'Status Miles' => ['Miles Statut', 'Miles Statuts'],
            'Status Flights' => ['Vols Statut', 'Vols Statuts'],
            'Status' => 'Statut',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'contact@news.royalairmaroc.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"http://click.news.royalairmaroc.com/")]')->length === 0) {
            return false;
        }
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Safar Flyer ID']) && !empty($dict['Your balance']) && !empty($dict['Status Miles'])
                && ($this->http->XPath->query("//tr[td[1]//text()[{$this->starts($dict['Safar Flyer ID'])}] and td[2][{$this->starts($dict['Your balance'])}] and td[3][{$this->contains($dict['Status Miles'])}] ]")->length > 0
                || $this->http->XPath->query("//tr[td[1][{$this->contains($dict['Status Miles'])}]//text()[{$this->starts($dict['Safar Flyer ID'])}] and td[2][{$this->starts($dict['Your balance'])}][{$this->contains($dict['Status Miles'])}] ]")->length > 0)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Safar Flyer ID']) && !empty($dict['Your balance']) && !empty($dict['Status Miles'])) {
                if ($this->http->XPath->query("//tr[td[1]//text()[{$this->starts($dict['Safar Flyer ID'])}] and td[2][{$this->starts($dict['Your balance'])}] and td[3][{$this->contains($dict['Status Miles'])}] ]")->length > 0) {
                    $type = '1x3';
                    $this->lang = $lang;
                    break;
                }
                if ($this->http->XPath->query("//tr[td[1][{$this->contains($dict['Status Miles'])}]//text()[{$this->starts($dict['Safar Flyer ID'])}] and td[2][{$this->starts($dict['Your balance'])}][{$this->contains($dict['Status Miles'])}] ]")->length > 0) {
                    $type = '2x2';
                    $this->lang = $lang;
                    break;
                }
            }
        }

        $st = $email->add()->statement();

        if ($type === '1x3') {
            $xpath = "//tr[td[1]//text()[{$this->starts($this->t('Safar Flyer ID'))}] and td[2][{$this->starts($this->t('Your balance'))}] and td[3][{$this->contains($this->t('Status Miles'))}] ]";

            // Td 1
            $td = implode("\n", $this->http->FindNodes($xpath . "/td[1]//text()[normalize-space()]"));
            if (preg_match("/^(?<name>.+)\n\s*{$this->opt($this->t("Safar Flyer ID"))}[\s:]+(?<number>\d{5,})\s*\n\s*Safar Flyer (?<status>.+)/",
                $td, $m)) {
                $st
                    ->setNumber($m['number'])
                    ->setLogin($m['number'])
                    ->addProperty('Name', $m['name'])
                    ->addProperty('Status', $m['status']);
            } else {
                // for error
                $st->setNumber(null);
            }

            // Td 2
            $td = implode("\n", $this->http->FindNodes($xpath . "/td[2]//text()[normalize-space()]"));
            if (preg_match("/^\s*{$this->opt($this->t("Your balance"))}\s*\D*\s+(?<date>\d{1,2}\\/\d{1,2}\\/\d{4})\s*\n\s*(?<balance>\d[\d,. ]*)\s*{$this->opt($this->t("Awards Miles"))}/",
                $td, $m)) {
                $st
                    ->setBalanceDate(strtotime(str_replace('/', '.', $m['date'])))
                    ->setBalance(preg_replace('/\D+/', '', $m['balance']));
            }

            // Td 3
            $td = implode("\n", $this->http->FindNodes($xpath . "/td[3]//text()[normalize-space()]"));
            if (preg_match("/\s+{$this->opt($this->t("achievements"))}.*\s*\n\s*(?<sm>\d[\d,. ]*)\s*{$this->opt($this->t("Status Miles"))}\s+(?<sf>\d[\d,. ]*)\s*{$this->opt($this->t("Status Flights"))}\s*$/",
                $td, $m)) {
                $st
                    ->addProperty('QualifyingMiles', preg_replace('/\D+/', '', $m['sm']))
                    ->addProperty('QualifyingSectors', preg_replace('/\D+/', '', $m['sf']));
            } else {
                $st->addProperty('QualifyingMiles', null);
            }
        }

        if ($type === '2x2') {
            $xpath = "//tr[td[1][{$this->contains($this->t('Status Miles'))}]//text()[{$this->starts($this->t('Safar Flyer ID'))}] and td[2][{$this->starts($this->t('Your balance'))}][{$this->contains($this->t('Status Miles'))}] ]";

            // Td 1
            $td1 = implode("\n", $this->http->FindNodes($xpath . "/td[1]//text()[normalize-space()]"));
            if (preg_match("/^(?<name>.+)\n\s*{$this->opt($this->t("Safar Flyer ID"))}[\s:]+(?<number>\d{5,})\s*\n\s*(?:{$this->opt($this->t("Status"))}\s+)?Safar Flyer (?<status>.+)\s*(?:\s+{$this->opt($this->t("Status"))})?/",
                $td1, $m)) {
                $st
                    ->setNumber($m['number'])
                    ->setLogin($m['number'])
                    ->addProperty('Name', $m['name'])
                    ->addProperty('Status', $m['status']);
            } else {
                // for error
                $st->setNumber(null);
            }

            // Td 2
            $td2 = implode("\n", $this->http->FindNodes($xpath . "/td[2]//text()[normalize-space()]"));
            if (preg_match("/^\s*{$this->opt($this->t("Your balance"))}\s*\D*\s+(?<date>\d{1,2}\\/\d{1,2}\\/\d{4})\s*\n\s*(?<balance>\d[\d,. ]*)\s*{$this->opt($this->t("Awards Miles"))}/",
                $td2, $m)) {
                if ($this->lang != 'en') {
                    $m['date'] = str_replace('/', '.', $m['date']);
                }
                $st
                    ->setBalanceDate(strtotime( $m['date']))
                    ->setBalance(preg_replace('/\D+/', '', $m['balance']));
            }

            // Td 1 + 2
            if (preg_match("/\n\s*(?<sm>\d[\d,. ]*|-)\s*{$this->opt($this->t("Status Miles"))}\b/", $td1, $m1)
                && preg_match("/\n\s*(?<sm>\d[\d,. ]*|-)\s*{$this->opt($this->t("Status Miles"))}\b/", $td2, $m2)
            ) {
                $m1['sm'] = preg_replace('/\D+/', '', $m1['sm']);
                $m2['sm'] = preg_replace('/\D+/', '', $m2['sm']);

                $st
                    ->addProperty('QualifyingMiles', (!empty($m1['sm'])? $m1['sm'] : 0) + (!empty($m2['sm'])? $m2['sm'] : 0));
            } else {
                $st->addProperty('QualifyingMiles', null);
            }

            if (preg_match("/\s+(?<sf>\d[\d,. ]*|-)\s*{$this->opt($this->t("Status Flights"))}\s*$/", $td1, $m1)
                && preg_match("/\s+(?<sf>\d[\d,. ]*|-)\s*{$this->opt($this->t("Status Flights"))}\s*$/", $td2, $m2)
            ) {
                $m1['sf'] = preg_replace('/\D+/', '', $m1['sf']);
                $m2['sf'] = preg_replace('/\D+/', '', $m2['sf']);
                $st
                    ->addProperty('QualifyingSectors', (!empty($m1['sf'])? $m1['sf'] : 0) + (!empty($m2['sf'])? $m2['sf'] : 0));
            } else {
                $st->addProperty('QualifyingSectors', null);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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
}
