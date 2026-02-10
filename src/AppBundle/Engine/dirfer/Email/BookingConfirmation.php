<?php

namespace AwardWallet\Engine\dirfer\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "dirfer/it-149815128.eml, dirfer/it-154199629.eml, dirfer/it-34773831.eml, dirfer/it-35702343.eml, dirfer/it-35708498.eml, dirfer/it-415967808.eml";

    public $reFrom = ["@directferries.com"];
    public $reBody = [
        'en' => ['for booking through Direct Ferries', 'How to get to the ports'],
        'de' => 'Meine Buchung verwalten',
        'fr' => 'Gérer ma réservation',
    ];
    public $reSubject = [
        'Direct Ferries Booking Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Reference Number'     => ['Reference Number'],
            'You are sailing with' => 'You are sailing with',
            'Vehicle'              => ['vehicle', 'Vehicle'],
            'totalPrice'           => ['Total Debited', 'Total paid'],
        ],
        'de' => [
            'Reference Number'     => ['Referenznummer'],
            'You are sailing with' => 'Sie fahren mit',
            'Vehicle'              => ['Fahrzeug'],
            'totalPrice'           => ['Vollständig bezahlt'],
            'Crossing Duration'    => 'Überfahrtsdauer',
            'Meals'                => 'Verpflegung',
            'Accommodation'        => 'Unterkunft an Bord',
            'Passengers Details'   => 'Passagierangaben',
        ],
        'fr' => [
            'Reference Number'     => ['Numéro de référence'],
            'You are sailing with' => 'Vous naviguez avec',
            'Vehicle'              => ['Véhicule'],
            'totalPrice'           => ['Total payé'],
            'Crossing Duration'    => 'Durée de la traversée',
            'Meals'                => 'Repas',
            'Accommodation'        => 'Installation',
            'Passengers Details'   => 'Détails des Passagers',
        ],
    ];
    private $keywordProv = 'Direct Ferries';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $NBSP = chr(194) . chr(160);
        $this->http->SetEmailBody(str_replace($NBSP, ' ', html_entity_decode($this->http->Response['body'])));

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectBody()) {
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

        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
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
        $r = $email->add()->ferry();

        $confOta = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Direct Ferries'))} and ({$this->contains($this->t('Reference Number'))})]/ancestor::*[1]",
            null, false, "#{$this->opt($this->t('Reference Number'))}[:\s]+([-A-Z\d]+)$#");

        if (empty($confOta)) {
            $confOta = $this->http->FindSingleNode("//text()[{$this->contains($this->t('DFP'))}]/ancestor::tr[1]", null, true, "/\:\s*([A-Z\d]+)/u");
        }

        $r->ota()
            ->confirmation($confOta, $this->t('Direct Ferries'));

        if ($phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Give us a call on'))}]/following::text()[normalize-space()!=''][1]")) {
            $r->ota()->phone(
                $phone,
                $this->http->FindSingleNode("//text()[{$this->starts($this->t('Give us a call on'))}]/following::text()[normalize-space()!=''][2]"));
        }

        $confNo = $this->http->FindSingleNode("//text()[not({$this->contains($this->t('Direct Ferries'))}) and ({$this->contains($this->t('Reference Number'))})]/ancestor::*[1]",
            null, false, "#{$this->opt($this->t('Reference Number'))}[:\s]+([-A-z\d\/\:]+)$#");

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'DFP')]/ancestor::tr[1]/following::text()[{$this->starts($this->t('Reference Number'))}][1]", null, true, "/\:\s*([A-Z\d]+)/u");
        }

        if (empty($confNo)) {
            $confNo = $this->http->FindNodes("//text()[contains(normalize-space(), 'DFP')]/ancestor::table[1]/descendant::tr[contains(normalize-space(), 'Reference Number')][not(contains(normalize-space(), 'DFP'))]", null, "/\:\s*([A-Z\d]+)/u");
        }

        $descr = $this->http->FindSingleNode("//text()[not({$this->contains($this->t('Direct Ferries'))}) and ({$this->contains($this->t('Reference Number'))})]/ancestor::*[1]",
            null, false, "#(.+)\s+{$this->opt($this->t('Reference Number'))}[:\s]+[-A-Z\d]+$#");

        if (is_array($confNo)) {
            foreach ($confNo as $conf) {
                $r->general()
                    ->confirmation($conf, $descr);
            }
        } else {
            if (stripos($confNo, "/") !== false) {
                $confs = explode("/", $confNo);

                foreach ($confs as $conf) {
                    $r->general()
                        ->confirmation($conf, $descr);
                }
            } elseif (stripos($confNo, ":") !== false) {
                $r->general()
                    ->confirmation($confNo, $descr, null, "/{$confNo}/");
            } else {
                $r->general()
                    ->confirmation($confNo, $descr);
            }
        }

        $vehicles = [];
        $tickets = [];
        $nodes = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers Details'))}]/ancestor::table[1][{$this->starts($this->t('Passengers Details'))}]/descendant::tr[position()>1]");

        foreach ($nodes as $node) {
            if (preg_match("#([[:alpha:]\W]+)\s+\([[:alpha:]\W]+\)$#", $node, $m)) {
                $r->general()
                    ->traveller(str_replace(['M.', 'Frau', 'Herr', 'Mme'], '', $m[1]));
            } elseif (preg_match("/(.+)\s+\(\w+\).*?Ticket Number:[,\s]*([-,\s\w]{5,}?)[,\s]*$/", $node, $m)) {
                $r->general()
                    ->traveller($m[1]);
                $tickets = array_merge($tickets, preg_split('/\s*[,]+\s*/', $m[2]));
            } elseif (preg_match("#{$this->opt($this->t('Vehicle'))}#", $node)
                && strpos($node, $this->t('no vehicle chosen')) === false
            ) {
                $vehicles[] = $node;
            }
        }
        $tickets = array_filter(array_unique($tickets));

        foreach ($tickets as $ticket) {
            $r->addTicketNumber($ticket, false);
        }

        $totalPrice = $this->http->FindSingleNode("//td[{$this->eq($this->t('totalPrice'))}]/following-sibling::td[normalize-space()][last()]");

        if (empty($totalPrice)) {
            $totalPrice = $this->http->FindSingleNode("//td[{$this->contains($this->t('totalPrice'))}]/following-sibling::td[normalize-space()][last()]");
        }

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $105.62
            $currency = $this->normalizeCurrency($matches['currency']);
            $r->price()->currency($currency)
                ->total(PriceHelper::parse($matches['amount'], $currency));
        }

        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd'),'d:dd')";

        $xpath = "//text()[{$this->eq($this->t('You are sailing with'))}]/following::table[1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH]: " . $xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $s->extra()
                ->carrier($this->http->FindSingleNode("./preceding::text()[normalize-space()!=''][1]", $root))
                ->duration($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Crossing Duration'))}][1]",
                    $root, false, "#{$this->opt($this->t('Crossing Duration'))}[\s:]+(\d.+?)[*\s]*$#"))
                ->meal($this->http->FindSingleNode("./following-sibling::table[normalize-space()!=''][1]/descendant::text()[{$this->starts($this->t('Meals'))}]/following::text()[normalize-space()!=''][1]",
                    $root));

            $s->booked()
                ->accommodation($this->http->FindSingleNode("./following-sibling::table[normalize-space()!=''][1]/descendant::text()[{$this->starts($this->t('Accommodation'))}]/following::text()[normalize-space()!=''][1]",
                    $root));

            $s->departure()
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$ruleTime}][1]", $root)));

            $name = $this->http->FindSingleNode("./descendant::text()[{$ruleTime}][1]/following::text()[normalize-space()!=''][1]",
                $root);

            if (!empty($address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('How to get to the ports'))}]/ancestor::table[1]/descendant::text()[{$this->eq($name)}]/following::text()[normalize-space()!=''][1][not({$this->contains($this->t('open in maps'))})]"))) {
                $s->departure()
                    ->address($address);
            } elseif (preg_match("#(.+?)\s*:#", $name, $m)) {
                $subName = trim($m[1]);

                if (!empty($address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('How to get to the ports'))}]/ancestor::table[1]/descendant::text()[{$this->eq($subName)}]/following::text()[normalize-space()!=''][1][not({$this->contains($this->t('open in maps'))})]"))) {
                    $s->departure()
                        ->address($address);
                }
            }
            $s->departure()->name($name);

            $s->arrival()
                ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$ruleTime}][2]", $root, false,
                    "#(.+?)\s*(?:\(\s*\+\s*\d+\s*{$this->t('day')}|$)#")));

            if ($s->getDepDate() > $s->getArrDate()) {
                $r->setAllowTzCross(true);
            }

            $name = $this->http->FindSingleNode("./descendant::text()[{$ruleTime}][2]/following::text()[normalize-space()!=''][1]",
                $root);

            if (!empty($address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('How to get to the ports'))}]/ancestor::table[1]/descendant::text()[{$this->eq($name)}]/following::text()[normalize-space()!=''][1][not({$this->contains($this->t('open in maps'))})]"))) {
                $s->arrival()
                    ->address($address);
            } elseif (preg_match("#(.+?)\s*:#", $name, $m)) {
                $subName = trim($m[1]);

                if (!empty($address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('How to get to the ports'))}]/ancestor::table[1]/descendant::text()[{$this->eq($subName)}]/following::text()[normalize-space()!=''][1][not({$this->contains($this->t('open in maps'))})]"))) {
                    $s->arrival()
                        ->address($address);
                }
            }
            $s->arrival()->name($name);

            foreach ($vehicles as $vehicle) {
                $values = preg_split("#((?:Vehicle:|Vehicle Length:|Vehicle Height:))\b#", $vehicle, -1,
                    PREG_SPLIT_DELIM_CAPTURE);

                if (count($values) > 1) {
                    $v = $s->addVehicle();

                    foreach ($values as $i => $value) {
                        if ($value === 'Vehicle:') {
                            $v->setModel($values[$i + 1]);

                            continue;
                        }

                        if ($value === 'Vehicle Length:') {
                            $v->setLength($values[$i + 1]);

                            continue;
                        }

                        if ($value === 'Vehicle Height:') {
                            $v->setHeight($values[$i + 1]);

                            continue;
                        }
                    }
                }
            }
        }

        return true;
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
        if (isset($this->reBody) && $this->http->XPath->query("//a[contains(@href,'.directferries.com/')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Reference Number'], $words['You are sailing with'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Reference Number'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['You are sailing with'])}]")->length > 0
                ) {
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            "#^\w+\.?\s*(\d+)\s+(\w+)\s*(\d{4})\s*([\d\:]+)\s*.*$#u", //Mo 11 April 2022 18:45
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'AUD' => ['A$'],
            '$'   => ['$'],
            'JPY' => ['¥'],
            'PLN' => ['zł'],
            'THB' => ['฿'],
            'CAD' => ['C$'],
            'COP' => ['COL$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
