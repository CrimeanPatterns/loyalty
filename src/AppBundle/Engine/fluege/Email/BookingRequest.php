<?php

namespace AwardWallet\Engine\fluege\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingRequest extends \TAccountChecker
{
    public $mailFiles = "fluege/it-5947130.eml";

    public $reBody = [
        'de' => [
            [
                'Ihre Buchungsanfrage hat die Bestätigungsnummer:',
                'Ihre Buchungsanfrage für den Hinflug hat die Bestätigungsnummer:',
                'Ihre Buchungsanfrage für den Rückflug hat die Bestätigungsnummer:',
            ],
            'Abflug',
        ],
    ];
    public $reSubject = [
        'Ihre Buchungsanfrage',
    ];
    public $lang = 'de';
    public $pdf;
    public static $dict = [
        'de' => [
            'Ihre Buchungsanfrage hat die Bestätigungsnummer:' => [
                'Ihre Buchungsanfrage hat die Bestätigungsnummer:',
                'Ihre Buchungsanfrage für den Hinflug hat die Bestätigungsnummer:',
                'Ihre Buchungsanfrage für den Rückflug hat die Bestätigungsnummer:',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if (!$this->assignLang($body)) {
            $this->logger->debug('can\'t determine a language');

            return null;
        }

        if (!$this->parseEmail($email)) {
            return null;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'fluege')]")->length > 0) {
            $body = $parser->getHTMLBody();

            return $this->assignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
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
        return stripos($from, "fluege.de") !== false;
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
        $airs = [];
        $rls = $this->http->FindNodes("//text()[{$this->eq($this->t('Ihre Buchungsanfrage hat die Bestätigungsnummer:'))}]/following::text()[normalize-space(.)!=''][1]",
            null, "#\/.*?([A-Z\d]+)$#");
        $xpath = "//text()[contains(.,'Abflug')]/ancestor::tr[1][contains(.,'Ankunft')]";
        $nodes = $this->http->XPath->query($xpath);

        if (count($rls) == 2) {
            foreach ($nodes as $root) {
                if ($this->http->XPath->query("./preceding::tr[starts-with(normalize-space(),'Hinflug:') or starts-with(normalize-space(),'Rückflug:')][1][contains(.,'Hinflug')]",
                        $root)->length > 0
                ) {
                    $airs[$rls[0]][] = $root;
                } elseif ($this->http->XPath->query("./preceding::tr[starts-with(normalize-space(),'Hinflug:') or starts-with(normalize-space(),'Rückflug:')][1][contains(.,'Rückflug')]",
                        $root)->length > 0
                ) {
                    $airs[$rls[1]][] = $root;
                } else {
                    $this->logger->debug('other format on RecordLocator (2)');

                    return false;
                }
            }
        } elseif (count($rls) === 1) {
            foreach ($nodes as $root) {
                $airs[$rls[0]][] = $root;
            }
        } else {
            $this->logger->debug('other format on RecordLocator (1)');

            return false;
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space()='Gesamtpreis']/ancestor::tr[1]/td[2]"));

        if (!empty($tot['Total'])) {
            $email->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);

            $fees = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space()='ServiceFee']/ancestor::tr[1]/td[2]",
                null, false, "#^[\d\.\,]+\D+#"));
            $email->price()
                ->fee('ServiceFee', $fees['Total']);
        }

        $i = -1;

        foreach ($airs as $rl => $roots) {
            $i++;
            $f = $email->add()->flight();
            $f->general()
                ->confirmation($rl)
                ->travellers($this->http->FindNodes("//text()[normalize-space(.)='Angaben zu den Reisenden']/ancestor-or-self::td[1]/following::table[1]/descendant::tr[count(descendant::tr)=0 and string-length(normalize-space(.))>3]/td[2]"));

            if (count($airs) == 1) {
                if (empty($tot['Total'])) {
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space()='Zwischensumme']/ancestor::tr[1]/following::td[starts-with(normalize-space(.), 'Summe')]/following-sibling::td[1]"));
                }
                $f->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
                $fee = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space()='ServiceFee']/ancestor::tr[1]/td[2]",
                    null, false, "#^[\d\.\,]+\D+#"));

                if (!empty($fee['Total'])) {
                    $f->price()
                        ->fee('ServiceFee', $fee['Total']);
                }
            } else {
                if ($i == 0) {
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space()='Hinflug']/ancestor::tr[1]/following-sibling::tr[starts-with(normalize-space(),'Summe')][1]/td[2]",
                        null, false, "#^[\d\.\,]+\D+#"));
                } elseif ($i == 1) {
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space()='Rückflug']/ancestor::tr[1]/following-sibling::tr[starts-with(normalize-space(),'Summe')][1]/td[2]",
                        null, false, "#^[\d\.\,]+\D+#"));
                } else {
                    return false;
                }
                $f->price()
                    ->cost($tot['Total'])
                    ->currency($tot['Currency']);
            }

            foreach ($roots as $root) {
                $s = $f->addSegment();
                $s->departure()
                    ->date($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root, true,
                        "#Abflug\s+(.+)#")));
                $s->arrival()
                    ->date($this->normalizeDate($this->http->FindSingleNode("./td[2]", $root, true,
                        "#Ankunft\s+(.+)#")));
                $node = $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root);

                if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->code($m[2]);
                }
                $node = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root);

                if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                    $s->arrival()
                        ->name($m[1])
                        ->code($m[2]);
                }
                $node = $this->http->FindSingleNode("./following-sibling::tr[2]/td[1]", $root);

                if (preg_match("#\(([A-Z\d]{2})\s*(\d+)\)#", $node, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }
                $s->extra()
                    ->cabin($this->http->FindSingleNode("./following-sibling::tr[2]/td[2]", $root, true,
                        "#Klasse:\s+(.+)#"));
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [//08. Mrzch 2017, 13:35
            '#(\d+)\.\s+(\S+)\s+(\d+),\s+(\d+:\d+)#',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        return strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
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
                if (stripos($body, $reBody[1]) !== false) {
                    $re = (array) $reBody[0];

                    foreach ($re as $r) {
                        if (stripos($body, $r) !== false) {
                            $this->lang = $lang;

                            return true;
                        }
                    }
                }
            }
        }

        return true;
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

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
}
