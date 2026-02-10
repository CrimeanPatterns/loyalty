<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AirLvArPlain extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-39618782.eml, amadeus/it-39618854.eml, amadeus/it-40358774.eml, amadeus/it-40358865.eml";

    private $lang = 'en';

    private $detects = [
        'en' => ['Reference No.', 'LV:', 'AR:'],
        'ja' => ['リファレンスNo', '出発', '到着'],
    ];

    private $from = '/[@\.]amadeus\.com/';

    private $prov = 'amadeus';

    private $text = '';

    private $year;

    private static $dict = [
        'en' => [],
        'ja' => [
            'DATE OF ISSUE'              => '発行日',
            'View your itinerary online' => 'インターネットで旅程を確認できます',
            'Reference No'               => ['リファレンスNo', 'リファレンス No'],
            'Flight No'                  => '便',
            'Terminal'                   => 'ターミナル',
            'AR'                         => '到着',
            'LV'                         => '出発',
            'Duration'                   => '所要時間',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!empty($body = $parser->getHTMLBody())) {
            $this->text = text($body);
        } else {
            $this->text = $parser->getPlainBody();
        }
        $nbsp = chr(194) . chr(160);
        $this->text = str_replace([$nbsp, '&nbsp;'], [' ', ' '], $this->text);
        $this->year = date('Y', strtotime($parser->getDate()));
        $this->assingLang();
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach ($this->detects as $detect) {
            if (
                0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect[0]}')]")->length
                && 0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect[1]}')]")->length
                && 0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect[2]}')]")->length
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        $segText = $this->cutText($this->t('DATE OF ISSUE'), $this->t('View your itinerary online'), $this->text);

        if (empty($segText)) {
            $segText = $this->cutText($this->t('DATE OF ISSUE'), null, $this->text);
        }

        if (preg_match("/{$this->opt($this->t('Reference No'))}\.[ ]+([A-Z\d]{5,9})/u", $segText, $m)) {
            $f->addConfirmationNumber($m[1]);
        }

        // 12月30日(月)  日本航空  JL407便
        // Mon 30 Dec  JAPAN AIRLINES  Flight No. JL407
        // 11 月 10 日 ( 日 )
        // チャイナエアライン
        $roots = $this->splitText($segText, '/^[ ]*((?:\w+ \d{1,2} \w+|\d{1,2}[ ]*\w+[ ]*\d{1,2}[ ]*\w+[ ]*\([ ]*\w+[ ]*\))\s+.+)/um', true);

        foreach ($roots as $root) {
            $s = $f->addSegment();

            $root = str_replace('<br>', '', $root);

            $date = '';

            if (preg_match("/(\w+) (\d{1,2} \w+)\s+.+\s+{$this->t('Flight No')}\.[ ]+([A-Z\d]{2})[ ]*(\d+)/iu", $root, $m)) {
                $date = $m[2] . ' ' . $this->year;
                $s->airline()
                    ->name($m[3])
                    ->number($m[4]);
                $week = $m[1];
            } elseif (preg_match("/(\d{1,2})[ ]*\D+[ ]*(\d{1,2})[ ]*\w+[ ]*\([ ]*(\w+)[ ]*\)\s+.+\s+[ ]*([A-Z\d]{2})[ ]*(\d+)[ ]*{$this->t('Flight No')}/u", $root, $m)) {
                $date = $this->year . '-' . $m[1] . '-' . $m[2];
                $s->airline()
                    ->name($m[4])
                    ->number($m[5]);
                $week = $m[3];
            }
            // bug fix
            $date = str_replace("８", '8', $date);

            if (isset($week)) { //correct year by weekDay
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
                $date = date("Y-m-d", EmailDateHelper::parseDateUsingWeekDay($date, $weeknum));
            }

            if (preg_match("/{$this->t('LV')}[ ]*:[ ]+(\d{1,2}:\d{2})\s+(.+)/u", $root, $m)) {
                $s->departure()
                    ->date(strtotime($date . ', ' . $m[1]))
                    ->noCode()
                ;

                if (preg_match("/(.+)[ ]+{$this->t('Terminal')}[ ]+(.+)/u", $m[2], $match)) {
                    $s->departure()
                        ->name($match[1])
                        ->terminal($match[2]);
                } else {
                    $s->departure()
                        ->name($m[1]);
                }
            }

            if (preg_match("/{$this->t('AR')}[ ]*:[ ]+(?:(?<newDate>(?:\d{1,2} \w+|\d{1,2}\w+\d{1,2}\w+))[ ]+)?(?<time>\d{1,2}:\d{2})\s+(?<name>.+)/u", $root, $m)) {
                if (!empty($m['newDate'])) {
                    $newDate = strtotime($m['newDate'], strtotime($date));

                    if (false === $newDate) {
                        $date = $this->normalizeDate($m['newDate'], date('Y', strtotime($date)));
                    } else {
                        $date = $newDate;
                    }
                    $s->arrival()
                        ->date(strtotime($m['time'], $date));
                } else {
                    $s->arrival()
                        ->date(strtotime($date . ', ' . $m['time']));
                }

                if (preg_match("/(.+)[ ]+{$this->t('Terminal')}[ ]+(.+)/u", $m['name'], $match)) {
                    $s->arrival()
                        ->name($match[1])
                        ->terminal($match[2]);
                } else {
                    $s->arrival()
                        ->name($m['name']);
                }
                $s->arrival()
                    ->noCode();
            }

            if (preg_match("/{$this->t('Duration')}[ ]*:[ ]+(.+)[ ]{2,}.*\s+(\w+)[ ]+\-[ ]+(.+)/u", $root, $m)) {
                $s->extra()
                    ->duration($m[1])
                    ->status($m[2])
                    ->cabin($m[3]);
            }
        }
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }

    private function normalizeDate(string $str, ?string $year = null)
    {
        $in = [
            '/^(\d{1,2})\w+(\d{1,2})\w+$/u', // 1月4日
        ];
        $out = [
            "{$year}-$1-$2",
        ];

        return strtotime(preg_replace($in, $out, $str));
    }

    private function assingLang()
    {
        foreach ($this->detects as $lang => $detect) {
            if (
                0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect[0]}')]")->length
                && 0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect[1]}')]")->length
                && 0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detect[2]}')]")->length
            ) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function cutText($start, ?string $end = null, $text)
    {
        if (empty($start) && empty($text)) {
            return false;
        }

        if (empty($end)) {
            return stristr($text, $start);
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);

            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
