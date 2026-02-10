<?php

namespace AwardWallet\Engine\expy\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "expy/it-35659863.eml, expy/it-35659905.eml";

    public $reFrom = ["@expy.jp"];
    public $reBody = [
        'ja1' => ['発売内容', 'エクスプレス予約をご利用いただきありがとうございます。'],
        'ja2' => ['払戻額は', 'エクスプレス予約をご利用いただきありがとうございます。'],
    ];
    public $reSubject = [
        '【EX】 新幹線予約内容',
        '【EX】 新幹線予約変更内容',
    ];
    public $lang = '';
    public static $dict = [
        'ja' => [
            'content'      => '■発売内容',
            'checkNum'     => 'お預かり番号',
            'tripNum'      => '出張番号',
            'boardingDate' => '乗車日',
            'issue'        => '号',
            'number'       => '番',
            'noSmoking'    => '普通　禁煙',
            'car'          => '車',
            'seat'         => '席',
            'amount'       => '発売額',
            'fee'          => '手数料',
            'formatChange' => '払戻額は',
        ],
    ];
    private $keywordProv = ['【EX】', 'expy.jp'];
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $body = html_entity_decode($this->http->Response['body']);

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        if (!$this->assignLang($body)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $type = '';
        $body = $parser->getPlainBody();
        $this->parseEmail($email, $body);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $body = $body = $parser->getPlainBody();
        }

        if ($this->detectBody($body)) {
            return $this->assignLang($body);
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

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || $this->stripos($headers["subject"], $this->keywordProv) !== false
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
        $formats = 2;
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    private function parseEmail(Email $email, string $text)
    {
        $mainText = $text;
        $colon = "[:：]";
        $comma = "[,、]";
        $space = '[　　]';
        $openBr = "[\(（]";
        $closeBr = "[\)）]";
        $slash = "[／\/]";

        $r = $email->add()->train();

        $r->general()
            ->confirmation($this->re("#\n[ >]*{$this->opt($this->t('checkNum'))}\s*{$colon}?\s*(.+?)(?:{$space}{2,}|\n)#u",
                $mainText), $this->t('checkNum'));

        if (!empty($item = $this->re("#\s+{$this->opt($this->t('tripNum'))}\s*{$colon}?\s*(.+?)(?:[ ]{2,}|\n)#u",
            $mainText))
        ) {
            $r->general()
                ->confirmation($item, $this->t('tripNum'));
        }

        if (mb_strpos($this->t('formatChange'), $text) !== false
            && preg_match_all("#{$this->opt($this->t('boardingDate'))}#", $mainText, $m,
                PREG_SET_ORDER) && count($m) > 1
        ) {
            $this->logger->debug("several segments");

            return false;
        }

        $date = $this->normalizeDate($this->re("#\n[ >]*{$this->opt($this->t('boardingDate'))}{$space}+(.+?)(?:[ ]{2,}|\n)#u",
            $mainText));
        $s = $r->addSegment();
        $node = $this->re("#\n[ >]*{$this->opt($this->t('boardingDate'))}.+\n[ >]*(.+\n.+)#u", $mainText);
        //新大阪(13:33)→のぞみ164号→東京(16:03)
        if (preg_match("#(.+){$openBr}(\d+:\d+){$closeBr}→(.+?)(\d+){$this->t('issue')}→(.+){$openBr}(\d+:\d+){$closeBr}\s*[ >]*(.+?){$slash}#u",
            $node, $m)) {
            $s->departure()
                ->name($m[1])
                ->date(strtotime($m[2], $date));
            $s->extra()
                ->service($m[3])
                ->number($m[4]);
            $s->arrival()
                ->name($m[5])
                ->date(strtotime($m[6], $date));
            $s->extra()
                ->type($m[7]);
        }

        if (preg_match("#\n[ >]*{$this->t('noSmoking')}\n#", $text) > 0) {
            $s->extra()->smoking(false);
        }

        if (preg_match("#(\d+){$this->t('issue')}{$this->t('car')}(.+){$this->t('seat')}#", $text, $m)) {
            $s->extra()
                ->car($m[1])
                ->seat($m[2]);
        }

        $total = $this->re("#\n[ >]*{$this->t('amount')}[ ]*(.+?)(?:\(|\n)#", $text);

        $total = $this->getTotalCurrency($total);
        $r->price()
            ->total($total['Total'])
            ->currency($total['Currency']);
        $fee = $this->re("#\n[ >]*{$this->t('fee')}[ ]*(.+?)(?:\(|\n)#", $text);

        if (!empty($fee)) {
            $descr = $this->re("#\n[ >]*({$this->t('fee')})[ ]*.+?(?:\(|\n)#", $text);
            $total = $this->getTotalCurrency($fee);
            $r->price()
                ->fee($descr, $total['Total']);
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //4月30日
            '#^\s*(\d+)月(\d+)日\s*$#u',
        ];
        $out = [
            $year . '-$1-$2',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if ($this->stripos($body, $this->keywordProv) === false) {
            return false;
        }

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["checkNum"], $words["boardingDate"])) {
                if (stripos($body, $words["checkNum"]) !== false
                    && stripos($body, $words["boardingDate"]) !== false
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
        $node = str_replace("円", "JPY", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
