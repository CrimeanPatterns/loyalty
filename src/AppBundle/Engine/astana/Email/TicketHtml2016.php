<?php

namespace AwardWallet\Engine\astana\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TicketHtml2016 extends \TAccountChecker
{
    public $mailFiles = "astana/it-5947078.eml, astana/it-639510784.eml, astana/it-642223779.eml";

    public $detectSubjects = [
        // ru, en
        'Your Electronic Ticket Receipt',
    ];

    public $lang = '';

    public $detectBody = [
        'ru' => [
            'Ваш электронный билет хранится в нашей электронной',
        ],
        'en' => [
            'Your e-ticket is stored in our Computer Reservations System',
        ],
    ];
    public static $dictionary = [
        'ru' => [
            //     'ИМЯ ПАССАЖИРА:' => '',
            //     'ЛЕТАЮЩЕГО ПАССАЖИРА:' => '',
            //     'НОМЕР БИЛЕТА:' => '',
            //     'НОМЕР БРОНИ:' => '',
            //     'ДАТА ОФОРМЛЕНИЯ:' => '',
            //     'ТЕРМИНАЛ:' => '',
            //     'Авиакомпания-перевозчик' => '',
            //     'МЕСТО:' => '',
            //     'БАГАЖ' => '',
            //     '/ТАРИФ:' => '',
            //     '/СБОРЫ:' => '',
            //     'СТОИМОСТЬ:' => '',
            //     'ОБЩИЙ ИТОГ:' => '',
        ],
        'en' => [
            'ИМЯ ПАССАЖИРА:'          => 'PASSENGER NAME:',
            'ЛЕТАЮЩЕГО ПАССАЖИРА:'    => 'FREQUENT FLYER NUMBER:',
            'НОМЕР БИЛЕТА:'           => 'TICKET NUMBER:',
            'НОМЕР БРОНИ:'            => 'BOOKING REFERENCE:',
            'ДАТА ОФОРМЛЕНИЯ:'        => 'ISSUE DATE:',
            'ТЕРМИНАЛ:'               => 'TERMINAL:',
            'Авиакомпания-перевозчик' => 'Operated by',
            'МЕСТО:'                  => 'Seat:',
            'БАГАЖ'                   => 'BAG',
            '/ТАРИФ:'                 => 'AIR FARE:',
            '/СБОРЫ:'                 => 'TAX:',
            'СТОИМОСТЬ:'              => 'TOTAL:',
            //     'ОБЩИЙ ИТОГ:' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['subject']) || !isset($headers['from'])) {
            return false;
        }

        if (stripos($headers['from'], 'confirmation@amadeus.com') === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[contains(., 'airastana')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//text()[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//text()[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseAir($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

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

    protected function parseAir(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->nextTd($this->t('НОМЕР БРОНИ:'), '/^[A-Z\d]{5,7}$/'))
            ->traveller(preg_replace("/^\s*(.+?)\s*\/\s*(.+?)(?:\s+(?:MR|MS|MRS|MISS))?\s*$/", '$2 $1', $this->nextTd($this->t('ИМЯ ПАССАЖИРА:'))))
        ;
        $createDate = $this->normalizeDate($this->nextTd($this->t('ДАТА ОФОРМЛЕНИЯ:')));

        if (!empty($createDate)) {
            $f->general()
                ->date($createDate);
        }

        // Program
        $account = $this->nextTd($this->t('ЛЕТАЮЩЕГО ПАССАЖИРА:'), '/^[\w\s-]+$/');

        if (!empty($account)) {
            $f->program()->account($account, false);
        }

        // Issued
        $ticket = $this->nextTd($this->t('НОМЕР БИЛЕТА:'), '/^[\d\s-]+$/');

        if (!empty($ticket)) {
            $f->issued()->ticket($ticket, false);
        }

        // Price
        $total = $this->nextTd($this->t('ОБЩИЙ ИТОГ:'));

        if (empty($total)) {
            $total = $this->nextTd($this->t('СТОИМОСТЬ:'));
        }

        if (preg_match('/([A-Z]{3})\s*(\d[\d,.]*)/', $total, $matches)) {
            $f->price()
                ->total((float) str_replace(',', '', $matches[2]))
                ->currency($matches[1]);

            $cost = $this->nextTd($this->t('/ТАРИФ:'), "/^\s*" . $matches[1] . "\s*(\d[\d,.]*)\s*$/");

            if (!empty($cost)) {
                $f->price()->cost((float) str_replace(',', '', $cost));
            }

            $taxes = $this->http->FindNodes("//td[not(./td)][{$this->contains($this->t('/СБОРЫ:'))}]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]");

            foreach ($taxes as $tax) {
                if (preg_match("/^\s*" . $matches[1] . "\s*(\d[\d,.]*)\s*([A-Z]\w+)\s*$/", $tax, $m)) {
                    $f->price()
                        ->fee($m[2], (float) str_replace(',', '', $m[1]));
                }
            }
        }

        $xpath = "//text()[{$this->eq($this->t('БАГАЖ'))}]/ancestor::tr[1]/following::tr[normalize-space()][1]/ancestor::*[1]/tr[normalize-space()][not(.//text()[{$this->eq($this->t('БАГАЖ'))}])][count(.//td[not(.//td)][normalize-space()]) > 5]";
        $this->logger->debug('$xpath = ' . print_r($xpath, true));

        foreach ($this->http->XPath->query($xpath) as $root) {
            // AMSTERDAM (AMS)  ATYRAU (GUW)  KC 0904  Y  23NOV 11:25   23NOV 20:20
            $pattern = '^\s*(?<dname>.+?)\s*\((?<dcode>[A-Z]{3})\)\s*(?<aname>.+?)\s*\((?<acode>[A-Z]{3})\)\s*';
            $pattern .= '(?<al>[A-Z]{2})\s*(?<fn>\d+)\s*(?<cabin>[A-Z])\s*(?<ddate>\d+\w+) (?<dtime>\d+:\d+)\s+(?<adate>\d+\w+) (?<atime>\d+:\d+)';

            $s = $f->addSegment();

            if (preg_match("/{$pattern}/", $root->nodeValue, $m)) {
                // Airline
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                // Departure
                $s->departure()
                    ->code($m['dcode'])
                    ->name($m['dname'])
                    ->date($this->normalizeDate($m['ddate'] . ', ' . $m['dtime'], true, $createDate))
                ;

                // Arrival
                $s->arrival()
                    ->code($m['acode'])
                    ->name($m['aname'])
                    ->date($this->normalizeDate($m['adate'] . ', ' . $m['atime'], true, $createDate))
                ;

                // Extra
                $s->extra()
                    ->cabin($m['cabin']);

                $terminals = $this->http->FindNodes("following-sibling::tr[1]//td[not(.//td)][{$this->contains($this->t('ТЕРМИНАЛ:'))}]", $root, "/:\s*(.+)/");

                if (count($terminals) == 2) {
                    $s->departure()
                        ->terminal($terminals[0]);
                    $s->arrival()
                        ->terminal($terminals[1]);
                } elseif (count($terminals) == 1) {
                    $terminalCols = 1 + count($this->http->FindNodes("following-sibling::tr[1]//td[not(.//td)][{$this->contains($this->t('ТЕРМИНАЛ:'))}]/preceding-sibling::td", $root));
                    $arrCols = 1 + count($this->http->FindNodes(".//td[not(.//td)][contains(., '{$m['acode']}')]/preceding-sibling::td", $root));

                    if ($arrCols == 3) {
                        if ($terminalCols == 2) {
                            $s->departure()
                                ->terminal($terminals[0]);
                        } elseif ($terminalCols == 4) {
                            $s->arrival()
                                ->terminal($terminals[0]);
                        }
                    }
                }
            }

            $info = $this->http->FindSingleNode("following::tr[normalize-space()][not({$this->contains($this->t('ТЕРМИНАЛ:'))})][1][count(.//td[not(.//td)][normalize-space()]) = 1]", $root);

            if (preg_match("/^\s*[A-Z\d]{2} ?\d{1,5}\s*-\s*(?:.*\/\s*)?{$this->opt($this->t('Авиакомпания-перевозчик'))}\s+([^\/]+?)\s*(?:\/|$)/", $info, $m)) {
                $s->airline()
                    ->operator($m[1]);
            }

            if (preg_match("/^\s*[A-Z\d]{2} ?\d{1,5}\s*-\s*(?:.*\/\s*)?{$this->opt($this->t('МЕСТО:'))}\s*(\d{1,3}[A-Z])\s*(?:\/|$)/", $info, $m)) {
                $s->extra()
                    ->seat($m[1]);
            }
            $this->logger->debug('$info = ' . print_r($info, true));
        }

        return $email;
    }

    private function nextTd($title, $regexp = null)
    {
        return $this->http->FindSingleNode("//td[not(./td)][contains(normalize-space(.), '" . $title . "')]/following-sibling::td[position() < 3][normalize-space()][1]", null, true, $regexp);
    }

    private function normalizeDate($str, $correctDate = false, $relativeDate = null)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $year = !empty($relativeDate) ? date("Y", $relativeDate) : '';
        $in = [
            // 02FEB22
            "#^\s*(\d{1,2})\s*([[:alpha:]]+)\s*(\d{2})\s*$#iu",
            "#^\s*(\d{1,2})\s*([[:alpha:]]+)\s*,\s*(\d{1,2}:\d{2})\s*$#iu",
        ];
        $out = [
            "$1 $2 20$3",
            "$1 $2 " . $year . ", $3",
        ];
        $str = preg_replace($in, $out, $str);

        if ($correctDate === true) {
            $str = EmailDateHelper::parseDateRelative($str, $relativeDate);

            return $str;
        }

        return (!empty($str)) ? strtotime($str) : null;
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

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
