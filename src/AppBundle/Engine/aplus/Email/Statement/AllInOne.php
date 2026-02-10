<?php

namespace AwardWallet\Engine\aplus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

// TODO: add $dictionary

class AllInOne extends \TAccountChecker
{
    public $mailFiles = "aplus/statements/it-62702113.eml, aplus/statements/it-62750017.eml, aplus/statements/it-62750121.eml, aplus/statements/it-62779035.eml, aplus/statements/it-62810721.eml, aplus/statements/it-62823135.eml, aplus/statements/it-62852968.eml, aplus/statements/it-62874852.eml, aplus/statements/it-77611955.eml, aplus/statements/it-78089745.eml, aplus/statements/it-78356392.eml";

    private $format = null;
    private $enDatesInverted = false;

    private $statusVariants = ['Status', 'STATUS', 'Statut', 'ESTATUS', 'Статусные', 'Accor Plus', 'ACCOR PLUS',
        //zh
        '状态 状态',
        '状态',
        // pl, pt
        'STATUS',
        // it
        'LIVELLO',
        // id, de
        'Status',
        // ko
        '등급',
        // fr
        'STATUT',
        // es
        'Estatus',
        // ru
        'Статус',
        // ja
        'ステータス ステータス',
    ];
    private $rewardPointsVariants = ['Point(s) Reward', 'Reward points', 'Rewards points', 'Pontos Rewards', 'Pontos Reward', 'Punkty Reward', 'Points Reward',
        'OS SEUS PONTOS REWARDS', 'YOUR REWARDS POINTS', 'Vos points Reward', 'Наградные баллы', 'YOUR REWARD POINTS',
        //zh
        '奖励积分',
        // fr
        'VOS POINTS REWARD',
        // pl
        'TWOJE PUNKTY REWARD',
        'Punkty Reward',
        // pt
        'SEUS PONTOS REWARD',
        // it
        'PUNTI REWARD',
        // it
        'Poin Reward',
        // de
        'Prämien-Punkte', 'Prämienpunkte:',
        // ko
        '리워드 포인트',
        // nl
        'Reward-punten',
        // pt
        'OS SEUS PONTOS REWARD',
        // ja
        'リワードポイント',
        // es
        'Puntos Reward',
    ];
    private $untilVariants = ['Until', 'Válido até', "Jusqu'au", 'Действительны до',
        //pl
        'Do',
        //pt
        'Até',
        // de
        ' - ',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]accor-mail\.com/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".accorhotels.com/") or contains(@href,".accor-mail.com/") or contains(@href,"www.accorhotels.com") or contains(@href,"mid.accor-mail.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Your AccorHotels.com team") or contains(normalize-space(),"Copyright Accor Plus. All rights reserved") or contains(.,"@accor-mail.com") or contains(.,"all.accor.com") or contains(.,"www.accorplus.com") or contains(., "ALL.ACCOR.COM")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

//        $this->logger->error($this->format);

        if ($this->format === 1 || $this->format === 2 || $this->format === 3) {
            if (preg_match("/^\s*{$this->opt($this->statusVariants)}[:\s]+(.+?)(?:[ ]*\n+[ ]*{$this->opt($this->untilVariants)}\s+(.{6,}?))?\s*$/ui", $this->htmlToText($this->http->FindHTMLByXpath('table[1]', null, $root)), $m)
                || preg_match("/^\s*{$this->opt($this->statusVariants)}[:\s]+(.+?)(?:[ ]*\n+[ ]*{$this->opt($this->untilVariants)}\s+(.{6,}?))?\s*$/ui", $this->htmlToText($this->http->FindHTMLByXpath('*[3]', null, $root)), $m)
                || preg_match("/^\s*{$this->opt($this->statusVariants)}[:\s]+(.+?)(?:[ ]*\n+[ ]*{$this->opt($this->untilVariants)}\s+(.{6,}?))?\s*$/ui", $this->htmlToText($this->http->FindHTMLByXpath('*[2]', null, $root)), $m)
            ) {
                $st->addProperty('Status', $m[1]);

                if (!empty($m[2])) {
                    $st->addProperty('StatusExpirationDate', $m[2]);
                }
            }

            $rewardPoints = preg_match("/^{$this->opt($this->rewardPointsVariants)}(?:\s*\(\s*\d{1,3}\s*\))?\s*(.+)$/ui", $this->htmlToText($this->http->FindHTMLByXpath('table[2]', null, $root)), $m)
                || preg_match("/^{$this->opt($this->rewardPointsVariants)}(?:\s*\(\s*\d{1,3}\s*\))?\s*(.+)$/ui", $this->htmlToText($this->http->FindHTMLByXpath('*[4]', null, $root)), $m)
                || preg_match("/^{$this->opt($this->rewardPointsVariants)}[:\s]+(\d[,.\'\d ]*)(?:\D|$)/ui", $this->htmlToText($this->http->FindHTMLByXpath('*[3]', null, $root)), $m)
                || preg_match("/^{$this->opt($this->rewardPointsVariants)}(?:\s*\(\s*\d{1,3}\s*\))?\s*(.+)$/ui", $this->htmlToText($this->http->FindHTMLByXpath('*[normalize-space()][last()]', null, $root)), $m)
                ? $m[1] : null;

            if ($rewardPoints === '—') {
                $st->setNoBalance(true);
            } elseif (preg_match("/^(\d[,.\'\d ]*)(?:\s+-\s+(.{6,}))?$/", $rewardPoints, $m)) {
                // 639    |    639 - 07/03/2020
                $st->setBalance($this->normalizeAmount($m[1]));

                if (!empty($m[2])) {
                    if (preg_match_all('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $m[2], $dateMatches)) {
                        foreach ($dateMatches[1] as $simpleDate) {
                            if ($simpleDate > 12) {
                                $this->enDatesInverted = true;

                                break;
                            }
                        }
                    }

                    $st->parseExpirationDate($this->normalizeDate($m[2]));
                }
            }

            if (preg_match("/^\s*({$patterns['travellerName']})[ ]*\n+[ ]*N°\s*(?-i)([A-Z\d ]{5,}?)\s*$/iu", $this->htmlToText($this->http->FindHTMLByXpath('*[normalize-space()][1]', null, $root)), $m)) {
                /*
                    Christopher Housley
                    N° 30810310836502AM
                */
                $name = $m[1];
                $number = $m[2];
            } else {
                $name = $number = null;
            }

            $xpathLeftTables = 'ancestor::table[1]/preceding-sibling::table[1]/descendant::*[ count(table)=2 ]';

            if (!$name
                && (preg_match("/^\s*({$patterns['travellerName']})\s*$/u", $this->htmlToText($this->http->FindHTMLByXpath($xpathLeftTables . '/table[1]', null, $root)), $m)
                    || preg_match("/^\s*({$patterns['travellerName']})\s*$/u", $this->htmlToText($this->http->FindHTMLByXpath('*[1]', null, $root)), $m))
            ) {
                $name = $m[1];
            }
            $st->addProperty('Name', $name);

            if (!$number
                && (preg_match("/^\s*N°\s*(?-i)([A-Z\d ]{5,}?)\s*$/i", $this->htmlToText($this->http->FindHTMLByXpath($xpathLeftTables . '/table[2]', null, $root)), $m)
                    || preg_match("/^\s*N°\s*(?-i)([A-Z\d ]{5,}?)\s*$/i", $this->htmlToText($this->http->FindHTMLByXpath('*[2]', null, $root)), $m))
            ) {
                $number = $m[1];
            }
            $st->setNumber($number)
                ->setLogin($number);
        } elseif ($this->format === 4) {
            $number = $this->http->FindSingleNode('table[1]', $root, true, "/^{$this->opt(["Member's ID", "Votre numéro d'adhérent", "Número de associado"])}\s*([A-Z\d ]{5,})$/");
            $st->setNumber($number)
                ->setLogin($number);

            if (preg_match("/^\s*{$this->opt($this->statusVariants)}[:\s]+(?:Accor Plus[ ]+)?(.+?)(?:[ ]*\n+[ ]*{$this->opt($this->untilVariants)}\s+(.{6,}?))?\s*$/i", $this->htmlToText($this->http->FindHTMLByXpath('table[2]', null, $root)), $m)) {
                /*
                    Status : Accor Plus Silver
                    Until 31 Aug 2020
                */
                $st->addProperty('Status', $m[1]);

                if (!empty($m[2])) {
                    $st->addProperty('StatusExpirationDate', $m[2]);
                }
            }

            $xpathRightTables = 'ancestor::table[1]/following-sibling::table[1]/descendant::*[ count(table)=2 ]';

            if (preg_match("/^\s*(\d[,.\'\d ]*)\s*{$this->opt($this->rewardPointsVariants)}[ ]*[*]*(?:[ ]*\n+[ ]*{$this->opt($this->untilVariants)}\s+(.{6,}?))?\s*$/i", $this->htmlToText($this->http->FindHTMLByXpath($xpathRightTables . '/table[1]', null, $root)), $m)) {
                /*
                    1240
                    Reward points**
                    Until 30 Apr 2021
                */
                $st->setBalance($this->normalizeAmount($m[1]));

                if (!empty($m[2])) {
                    if (preg_match_all('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $m[2], $dateMatches)) {
                        foreach ($dateMatches[1] as $simpleDate) {
                            if ($simpleDate > 12) {
                                $this->enDatesInverted = true;

                                break;
                            }
                        }
                    }

                    $st->parseExpirationDate($this->normalizeDate($m[2]));
                }
            }

            $name = $this->http->FindSingleNode("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()]", $root, true, "/^({$patterns['travellerName']})(?:\s*[,!?]|$)/u");
            $st->addProperty('Name', $name);
        } elseif ($this->format === 5) {
            $name = $this->http->FindSingleNode("following::text()[{$this->starts('Dear ')}]", $root, true, "/^Dear\s+({$patterns['travellerName']})(?:\s*[,!?]|$)/u");
            $st->addProperty('Name', $name);

            $username = $this->http->FindSingleNode("following::text()[contains(normalize-space(),'your customer account is')]/ancestor::td[1]", $root, true, "/your customer account is\s*(\S+@\S+\b)(?: |[,.;!?]|$)/");
            $st->setLogin($username);

            if ($name || $username) {
                $st->setNoBalance(true);
            }
        } elseif ($this->format === 6) {
            $cellLeft = implode("\n", $this->http->FindNodes("*[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^({$patterns['travellerName']})\s+Card No:\s*(?-i)([A-Z\d ]{5,})$/iu", $cellLeft, $m)) {
                /*
                    DUNG TRUNG PHAM
                    Card No: 30840941604971DU
                */
                $st->addProperty('Name', $m[1])
                    ->setNumber($m[2]);

                if ($this->http->XPath->query('//node()[contains(normalize-space(),"To start enjoying your Accor Plus member benefits, you’ll first need to set up your Accor Plus website login")]')->length === 0) {
                    $st->setLogin($m[2]);
                }
            }

            $cellRight = implode("\n", $this->http->FindNodes("*[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?:Accor Plus\s+)?(.+?)\s+Account Balance:\s*(\d[,.\'\d ]*)$/i", $cellRight, $m)) {
                /*
                    Accor Plus Silver
                    Account Balance: 0
                */
                $st->addProperty('Status', $m[1])
                    ->setBalance($this->normalizeAmount($m[2]));
            }
        } elseif ($this->format === 7) {
            $name = $this->http->FindSingleNode("preceding::text()[{$this->starts(['Dear ', 'Hi '])}]", $root, true, "/^{$this->opt(['Dear ', 'Hi '])}\s*({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u");
            $st->addProperty('Name', $name);

            if ($name) {
                $st->setNoBalance(true);
            }
        } elseif ($this->format === 8) {
            $cellRight = $this->http->FindSingleNode(".", $root);
            $this->logger->error($cellRight);

            if (preg_match("/\s*(\D+)\s*N[°]\s*([A-Z\d]+)\s*STATUS\:?\s*(\D+)\s*YOUR REWARD POINTS\(1\)\—\s*/u", $cellRight, $m)) {
                $st->addProperty('Name', $m[1]);
                $st->addProperty('Status', $m[3]);
                $st->setNumber($m[2]);
                $st->setNoBalance(true);
            }

            if (preg_match("/\s*(?<name>\D+)\s*N[°]\s*(?<number>[A-Z\d]+)\s*STATUS\:?\s*(?<status>\D+)\s*Until\s*(?<expireDate>\w+\,\s*\d+\s*\d{4})\s*YOUR REWARDS? POINTS\(1\)\—?\s*(?<balance>[\d\,]+)\s*\-\s*(?<dateBalance>\w+\,\s*\d+\s*\d{4})/u", $cellRight, $m)) {
                $st->addProperty('Name', $m['name']);
                $st->addProperty('Status', $m['status']);
                $st->setNumber($m['number']);
                $st->setExpirationDate(strtotime($this->normalizeDate($m['expireDate'])));
                $st->setBalanceDate((strtotime($this->normalizeDate($m['dateBalance']))));
                $st->setBalance(str_replace(',', '', $m['balance']));
            }
        } elseif ($this->format === 9) {
            $cellRight = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^(\D+)\s*N[°]\s*([\dA-Z]+)\s*Status:\s*(\w+)\s*$/u", $cellRight, $m)) {
                $st->addProperty('Name', $m[1]);
                $st->addProperty('Status', $m[3]);
                $st->setNumber($m[2]);
                $st->setNoBalance(true);
            }
        }

        $email->setType('AllInOne' . $this->format);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $this->format = 1;
        $nodes = $this->http->XPath->query("//*[ count(table)=2 and table[1][{$this->starts($this->statusVariants)}] and table[2][{$this->starts($this->rewardPointsVariants)}] ]");

        if ($nodes->length === 0) {
            $this->format = 2;
            $nodes = $this->http->XPath->query("//tr[ count(*)=4 and *[3][{$this->starts($this->statusVariants)}] and *[4][{$this->starts($this->rewardPointsVariants)}] ]");
        }

        if ($nodes->length === 0) {
            $this->format = 2;
            $nodes = $this->http->XPath->query("//tr[ count(*[normalize-space()])=3 and *[normalize-space()][2][{$this->starts($this->statusVariants)}] and *[normalize-space()][3][{$this->starts($this->rewardPointsVariants)}] ]");
        }

        if ($nodes->length === 0) {
            $this->format = 8;
            $nodes = $this->http->XPath->query("//img[contains(@src, 'logo_ALL')]/following::tr[contains(normalize-space(), 'STATUS')][1]");
        }

        if ($nodes->length === 0) {
            $this->format = 3;
            $nodes = $this->http->XPath->query("//*[ count(*)=3 and *[2][{$this->starts($this->statusVariants)}] and *[3][{$this->starts($this->rewardPointsVariants)}] ]");
        }

        if ($nodes->length === 0) {
            $this->format = 4;
            $nodes = $this->http->XPath->query("//*[ count(table)=2 and table[1][{$this->starts(["Member's ID", "Votre numéro d'adhérent", "Número de associado"])}] and table[2][{$this->starts($this->statusVariants)}] ]");
        }

        if ($nodes->length === 0) {
            $this->format = 5;
            $nodes = $this->http->XPath->query("//tr[normalize-space()='Account created']");
        }

        if ($nodes->length === 0) {
            $this->format = 6;
            $nodes = $this->http->XPath->query("//tr[ count(*)=3 and *[1]/descendant::text()[{$this->starts('Card No:')}] and *[3]/descendant::text()[{$this->starts('Account Balance:')}] ]");
        }

        if ($nodes->length === 0) {
            $this->format = 9;
            $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Dear')]/preceding::text()[contains(normalize-space(), 'tatus:')][1]/ancestor::tr[1]");
            $this->logger->error($nodes->length);
        }

        return $nodes;
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

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        // 09/13/2019
        $in[0] = '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/';
        $out[0] = $this->enDatesInverted ? '$2/$1/$3' : '$1/$2/$3';
        // 2021年04月22日
        $in[1] = '/^(\d{4})年(\d{1,2})月(\d{1,2})日$/';
        $out[1] = '$3.$2.$1';
        // Dec, 31 2021
        $in[2] = '/^(\w+)\,\s*(\d+)\s*(\d{4})$/';
        $out[2] = '$2 $1 $3';
        // 21.09.21
        $in[3] = '/^(\d{1,2})\.(\d{2})\.([234]\d)$/';
        $out[3] = '$1.$2.20$3';

        return preg_replace($in, $out, $text);
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
