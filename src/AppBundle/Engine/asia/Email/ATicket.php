<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ATicket extends \TAccountChecker
{
    public $mailFiles = "asia/it-404771399.eml, asia/it-7344318.eml";

    public $lastFlight = '';

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            'Hello' => ['Hello', 'Dear'],
            // 'Booking reference' => '',
            // 'Passenger' => '',
            // 'to' => '',
            // 'Terminal' => '',
            // 'Operated by' => '',
            // 'Duration' => '',
        ],
        "zh" => [
            'Hello'             => ['您好,'],
            'Booking reference' => ['預訂參考編號', '预订参考编号'],
            'Passenger'         => ['旅客', '乘客'],
            'to'                => '至',
            'Terminal'          => '客運大樓',
            'Operated by'       => ['營運航空公司：', '营运航空公司： '],
            'Duration'          => '航行時間',
        ],
    ];

    private $detects = [
        'en' => [
            'is available for online check in now',
            'You may now check in online for your flight',
            'Sign up to Cathay and enjoy',
        ],
        'zh' => [
            '你現可為於 48 小時內出發前往',
            '网上预办登机服务现可使用',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'cathaypacific.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'cathaypacific.com') !== false;
    }

    public function detectLang()
    {
        foreach ($this->detects as $lang => $detect) {
            if ($this->http->XPath->query("//text()[" . $this->contains($detect) . "]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'cathaypacific')]")->length > 0
            && $this->detectLang() === true
        ) {
            return true;
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

    private function parseEmail(Email $email)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//td[{$this->contains($this->t('Booking reference'))}][not(descendant::td)]", null, true, "/{$this->opt($this->t('Booking reference'))}\s+([A-Z\d]{5,8})/"));

        $names = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::tr[following-sibling::tr]/following-sibling::tr/descendant::*[count(table) = 3]/table[1]",
            null, "/^\s*([[:alpha:] \-]+)\s*$/u");

        if (empty($names)) {
            $names[] = $this->http->FindSingleNode("//td[{$this->starts($this->t('Hello'))}][not(.//td)]", null, true,
                "/^\s*{$this->opt($this->t('Hello'))} (.+)/");
        }

        $names = array_filter(array_map('trim', preg_replace('/(?:^\s*(Mr|Ms|Mrs) | (先生|女士|小姐)\s*$)/', '', $names)));

        if (!empty($names) && empty(array_filter(preg_replace('/^\s*passenger$/i', '', $names)))) {
        } else {
            $f->general()
                ->travellers($names, true);
        }

        $idx = -1;
        $rows = $this->http->XPath->query('//tr[last() and not(.//tr) and position() != 1]');

        foreach ($rows as $row) {
            /** @var \DOMNode $row */
            if (strpos($row->nodeValue, '°c') !== false) {
                break;
            }

            if (preg_match('/^\s*(?<depcode>[A-Z]{3}) (?<deptime>\d{1,2}:\d{2})\s+(?<arrcode>[A-Z]{3}) (?<arrtime>\d{1,2}:\d{2})\s*(?<overnight>[+-]\d)?/u', CleanXMLValue($row->nodeValue), $data) === 0) {
                continue;
            }
            $root = $row;

            for ($i = 0; $i < 10; $i++) {
                $root = $this->http->XPath->query('parent::*', $root);

                if ($root->length === 0) {
                    break;
                }
                $root = $root->item(0);
                $text = CleanXMLValue($root->nodeValue);

                if (!isset($data['date'])
                    && (preg_match('/^\w{3}, (\d{1,2} \w{3} \d{4})/', $text, $m) > 0
                     || preg_match('/^\s*(\d{4}年\d{1,2}月\d{1,2}日) [[:alpha:]]+/u', $text, $m) > 0)
                ) {
                    $data['date'] = $m[1];
                }

                if (preg_match('/^([A-Z\d]{2})\s*(\d{1,4}) ([\w ]{1,20}) (\w{3}, \d{1,2} \w{3}|\d{4}年\d{1,2}月\d{1,2}日)/u', $text, $m)) {
                    $s = $f->addSegment();

                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);

                    $s->extra()
                        ->cabin($m[3]);

                    $s->departure()
                        ->code($data['depcode']);

                    $s->arrival()
                        ->code($data['arrcode']);

                    if (isset($data['date'])) {
                        $s->departure()
                            ->date($this->normalizeDate($data['date'] . ' ' . $data['deptime']));

                        $s->arrival()
                            ->date($this->normalizeDate($data['date'] . ' ' . $data['arrtime']));

                        if (!empty($data['overnight'])) {
                            $s->arrival()
                                ->date(strtotime($data['overnight'] . ' day', $s->getArrDate()));
                        }
                    }
                    $s->airline()
                        ->operator($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Operated by'))}][1]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/u"));

                    if (preg_match("/{$this->opt($this->t('Duration'))} (?<d>\d+h \d+m)/", $text, $d) > 0) {
                        $s->extra()
                            ->duration($d['d']);
                    }

                    if ($this->lastFlight !== $s->getDepCode() . ' ' . $s->getDepDate()) {
                        $this->lastFlight = $s->getDepCode() . ' ' . $s->getDepDate();
                        $idx++;
                    } else {
                        $f->removeSegment($s);
                    }
                }
            }
        }

        if ($idx >= 0) {
            $segments = $f->getSegments();

            if (isset($segments[0]) && isset($segments[$idx])) {
                $title = $this->http->FindSingleNode(sprintf('//td[not(.//td) and contains(., "%s") and contains(., "%s") and ' . $this->contains($this->t('Terminal')) . ']', $segments[0]->getDepCode(), $segments[$idx]->getArrCode()));

                if (isset($title) && preg_match("/\([A-Z]{3}( {$this->opt($this->t('Terminal'))} (?<ter1>[^\)]{1,3}))?\) {$this->opt($this->t('to'))} [^\(]+\([A-Z]{3}( {$this->opt($this->t('Terminal'))} (?<ter2>[^\)]{1,3}))?\)/u", $title, $m) > 0) {
                    if (!empty($m['ter1'])) {
                        $segments[0]->departure()->terminal($m['ter1']);
                    }

                    if (!empty($m['ter2'])) {
                        $segments[$idx]->arrival()->terminal($m['ter2']);
                    }
                }
            }
        }
    }

    private function normalizeDate($str)
    {
        $in = [
            "/^\s*(\d{4})年(\d{1,2})月(\d{1,2})日\s+(\d{1,2}:\d{2})\s*$/",
        ];
        $out = [
            '$1-$2-$3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }

    private function t($word)
    {
        // $this->http->log($word);
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
