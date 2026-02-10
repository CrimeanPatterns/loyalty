<?php

namespace AwardWallet\Engine\china\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelReminder extends \TAccountChecker
{
    public $mailFiles = "china/it-13236094.eml, china/it-13332352.eml, china/it-13588022.eml, china/it-6433369.eml, china/it-6450553.eml, china/it-69094600.eml, china/it-70061198.eml";

    public $detectSubject = [
        // en
        'CHINA AIRLINES - My Travel Reminder',
        // zh
        '中華航空 - 我的旅遊小叮嚀',
    ];
    public $detectBody = [
        'en' => [
            'Ticket and Flight Information',
        ],
        'zh' => [
            '機票/班機資訊',
        ],
    ];

    public $dataTime;
    public $emailDate;

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            //            'Passenger Name' => '',
            //            'Booking Relocator' => '',
            //            'Flight Time' => '',
            //            'Class' => '',
            //            'Data Time' => '',
        ],
        "zh" => [
            'Passenger Name'    => '旅客姓名',
            'Booking Relocator' => '訂位代號',
            'Flight Time'       => '飛行時間',
            'Class'             => '艙等',
            'Data Time'         => '資料產生時間',
        ],
    ];

    private $detectFrom = 'cal-notice@email.china-airlines.com';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->emailDate = strtotime($parser->getDate());

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'china-airlines.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"]) == false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.china-airlines.com')] | //img[contains(@src, '.china-airlines.com')]")->length < 3) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return true;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking Relocator")) . "]",
                null, true, "/" . $this->opt($this->t("Booking Relocator")) . "[：:\s]*([A-Z\d]{5,7})$/"))
            ->travellers(array_filter(array_map('trim', explode(',',
                $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Passenger Name")) . "]",
                    null, true, "/" . $this->opt($this->t("Passenger Name")) . "[：:\s]*([-,.[:alpha:]\s\/]+)$/u")))))
        ;

        $this->dataTime = $this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking Relocator")) . "]/following::text()[" . $this->starts($this->t("Data Time")) . "][1]",
            null, true, "/" . $this->opt($this->t("Data Time")) . "[:\s]+([\d\/]+)\s+/"));

        // Segments
        $segments = $this->http->XPath->query("//img[contains(@src, 'icon_arrow')]/ancestor::tr[1]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $from = $this->http->FindSingleNode('./descendant::text()[normalize-space(.)][1]', $segment);
            $from = preg_replace(['/\(TAOYUAN\)\s*$/i'], ['(TPE)'], $from);

            if (preg_match("/^\s*(\S.+)\s*(?:\(([A-Z]{3})\))\s*$/", $from, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                ;
            } elseif (!empty($from)) {
                $s->departure()
                    ->name($from)
                    ->noCode()
                ;
            }

            $to = $this->http->FindSingleNode('./descendant::text()[normalize-space(.)][last()]', $segment);
            $to = preg_replace(['/\((?:TAOYUAN|桃園)\)\s*$/i'], ['(TPE)'], $to);

            if (preg_match("/^\s*(\S.+)\s*(?:\(([A-Z]{3})\))\s*$/", $to, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                ;
            } elseif (!empty($to)) {
                $s->arrival()
                    ->name($to)
                    ->noCode()
                ;
            }

            $flight = implode("\n", $this->http->FindNodes('./following-sibling::tr[normalize-space(.)][1]//text()[normalize-space()]', $segment)) . "\n";

            if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})\s+[[:alpha:] ]+[:：]\s+(?<dDate>.+)\n\s*[[:alpha:] ]+[:：]\s+(?<aDate>.+)\n/u", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                $s->departure()
                    ->date($this->normalizeDateRelative($m['dDate']));

                $s->arrival()
                    ->date($this->normalizeDateRelative($m['aDate']));
            }

            if (preg_match("/" . $this->opt($this->t("Flight Time")) . "\s*[:：]\s*(\d{1,2}:\d{2})/u", $flight, $m)) {
                $s->extra()
                    ->duration($m[1])
                ;
            }

            $info = "\n" . implode("\n", $this->http->FindNodes('./following-sibling::tr[normalize-space(.)][2]//text()[normalize-space()]', $segment)) . "\n";

            if (preg_match("/\n\s*" . $this->opt($this->t("Class")) . "[：:\s]*(?<class>[A-Z]{1,2})\s+/", $info, $m)) {
                $s->extra()
                    ->bookingCode($m['class']);
            }

            if (preg_match("/^\s*(?<aircraft>\S.+)\n\s*" . $this->opt($this->t("Class")) . "[：:\s]*/", $info, $m)) {
                $s->extra()
                    ->aircraft($m['aircraft']);
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date in: ' . $date);

        $in = [
            // 2020/06/19
            "/^\s*(\d{4})\/(\d{2})\/(\d{1,2})\s*$/",
            // 2020/6/19
            "/^\s*(\d{4})\/(\d{1})\/(\d{1,2})\s*$/",
            // 5/14/2018
            "/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$/",
        ];
        $out = [
            "$3.$2.$1",
            "$3.0$2.$1",
            "$2.$1.$3",
        ];
        $date = preg_replace($in, $out, $date);

//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $date = str_replace($m[1], $en, $date);
//        }

//        $this->logger->debug('$date out: ' . $date);

        return strtotime($date);
    }

    private function normalizeDateRelative($date)
    {
//        $this->logger->debug('$date Relative in: ' . $date);

        $in = [
            // 03JAN 16:35
            "/^\s*(\d{2})([A-Z]{3})\s*(\d{1,2}:\d{1,2})\s*$/",
        ];
        $out = [
            "$1 $2 %Y%, $3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
//        $this->logger->debug('$date Relative out: ' . $date);

        if (!empty($this->dataTime)) {
//            $this->logger->debug('$this->dataTime: ' . $this->dataTime);
            return EmailDateHelper::parseDateRelative($date, $this->dataTime, true, $date);
        }

        if (!empty($this->emailDate)) {
            return EmailDateHelper::parseDateRelative($date, $this->emailDate, null, $date);
        }

        return null;
    }
}
