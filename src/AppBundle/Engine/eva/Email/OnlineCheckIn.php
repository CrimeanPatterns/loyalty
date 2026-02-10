<?php

namespace AwardWallet\Engine\eva\Email;

// TODO: delete what not use
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class OnlineCheckIn extends \TAccountChecker
{
    public $mailFiles = "eva/it-637911801.eml, eva/it-637916491.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking Ref'       => 'Booking Ref',
            'Passenger'         => 'Passenger',
            'Membership number' => 'Membership number',
            'Terminal'          => 'Terminal',
            'Cabin / Seat'      => 'Cabin / Seat',
            'Aircraft type'     => 'Aircraft type',
        ],
        'zh' => [
            'Booking Ref'       => '訂位代號',
            'Passenger'         => '旅客姓名',
            'Membership number' => '會員卡號',
            'Terminal'          => ['航廈', '航站'],
            'Cabin / Seat'      => '艙等 / 座位',
            'Aircraft type'     => '機 型',
        ],
    ];

    private $detectFrom = "eservice@mh1.evaair.com";
    private $detectSubject = [
        // en
        'Travel tips would like to remind you that the online check-in is available.',
        // zh
        '旅遊叮嚀提醒您，航班已開放網路報到。',
    ];
    private $detectBody = [
        'en' => [
            'remind you that the online check-in is available',
        ],
        'zh' => [
            '提醒您航班已開放網路報到',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]evaair\.com\b/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        if (
            $this->http->XPath->query("//a[{$this->contains(['.evaair.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Copyright c EVA Airways Corp'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Booking Ref"], $dict["Passenger"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking Ref'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Passenger'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Ref'))}]",
                null, true, "/{$this->opt($this->t('Booking Ref'))}\s*:\s*([A-Z\d]{5,7})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::node()[not({$this->eq($this->t('Passenger'))})][1]",
                null, true, "/{$this->opt($this->t('Passenger'))}\s*(\D+)\s*$/"), true)
        ;

        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Membership number'))}]/ancestor::node()[not({$this->eq($this->t('Passenger'))})][1]",
            null, true, "/{$this->opt($this->t('Membership number'))}\s*(\d+)\s*$/");

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        $xpath = "//text()[{$this->starts($this->t('Booking Ref'))}]/following::img[contains(@src, '\arrow.gif')][1]/ancestor::*[.//text()[{$this->starts($this->t('Booking Ref'))}]][1]//img[contains(@src, '\arrow.gif')]/ancestor::tr[1][count(*) = 3]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();

            $date = null;
            $info = implode("\n", $this->http->FindNodes("preceding::text()[normalize-space()][1]/ancestor::tr[1]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*[[:alpha:]]+(?: *\d+)?\n\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\n(?<date>.*\d{4}.*)\s*(\n|$)/u", $info, $m)) {
                $date = $this->normalizeDate($m['date']);

                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }
            $re1 = "/^\s*(?<time>\d{1,2}\s*:\s*\d{2})\n\s*(?<code>[A-Z]{3})\s*(?:\((?<terminal>\S.+)\))?\s*$/";
            $re2 = "/^\s*(?<time>\d{1,2}\s*:\s*\d{2})\n\s*(?<name>.+?)\s*\((?<code>[A-Z]{3})\)\s*(?:\((?<terminal>\S.+)\))?\s*$/";
            $depart = implode("\n", $this->http->FindNodes("*[1]//text()[normalize-space()]", $root));

            if (preg_match($re1, $depart, $m) || preg_match($re2, $depart, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->date(!empty($date) ? strtotime(preg_replace('/\s+/', '', $m['time']), $date) : null)
                    ->terminal(preg_replace("/\s*\b(?:{$this->opt($this->t('Terminal'))}|terminal)\b\s*/iu", '', trim($m['terminal'] ?? '')), true, true);
            }
            $arrive = implode("\n", $this->http->FindNodes("*[3]//text()[normalize-space()]", $root));

            if (preg_match($re1, $arrive, $m) || preg_match($re2, $arrive, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->date(!empty($date) ? strtotime(preg_replace('/\s+/', '', $m['time']), $date) : null)
                    ->terminal(preg_replace("/\s*\b(?:{$this->opt($this->t('Terminal'))}|terminal)\b\s*/iu", '', trim($m['terminal'] ?? '')), true, true);
            }

            $nextInfo = implode("\n", $this->http->FindNodes("following::text()[normalize-space()][count(following::img[contains(@src, '\arrow.gif')]) = " . ($nodes->length - $i - 1) . "]", $root));

            if (preg_match("/(?:^|\n){$this->opt($this->t('Cabin / Seat'))}\s*([^\/\n]+?)\s*\/\s*(\d{1,3}[A-Z])\n/u", $nextInfo, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->seat($m[2]);
            }

            if (preg_match("/(?:^|\n)\s*{$this->opt($this->t('Aircraft type'))}\s*(.+)/u", $nextInfo, $m)) {
                $s->extra()
                    ->aircraft($m[1]);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // 2024年01月30日(星期二)
            '/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\(\w+\)\s*$/iu',
        ];
        $out = [
            '$1-$2-$3',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
