<?php

namespace AwardWallet\Engine\airchina\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MonthlyStatement extends \TAccountChecker
{
    public $mailFiles = "airchina/statements/it-113375351.eml, airchina/statements/it-114848787.eml, airchina/statements/it-65112921.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
//            'Card No：' => '',
//            'Expiry Mileages/end of' => '',
            'CardLevel' => [// replace Card level with %CardLevel%
                'You are PhoenixMiles %CardLevel% Member.'
            ],
        ],
        "zh" => [
            'Card No：' => '卡号：',
            'Expiry Mileages/end of' => '底将到期里程',
            'CardLevel' => [// replace Card level with %CardLevel%
                '您现在为“凤凰知音”%CardLevel%会员。'
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'ffp@bill.airchina.com.cn') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'ffp@airchina.com')]")->length == 0) {
            return false;
        }

        $conditions = ["normalize-space() = 'km'", "contains(normalize-space(), ' km')"];
        foreach ($conditions as $cond) {
            $xpath = "//text()[{$cond}]/ancestor::td[1]/following::td[not(.//td)][normalize-space()][2][.//text()[{$cond}]]/following::td[not(.//td)][normalize-space()][2][.//text()[{$cond}]]";
            if (!empty($this->http->XPath->query($xpath)->length > 0)) {
                return true;
            }
        }
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'ffp@bill.airchina.com.cn') !== false || stripos($from, 'ffp@enewsletter.airchina.com.cn') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {

        foreach (self::$dictionary as $lang => $dict) {
            $this->lang = $lang;
            if (empty($this->http->FindSingleNode("(//text()[".$this->contains($this->t("Card No："))."])[1]"))) {
                continue;
            }

            $st = $email->add()->statement();

            $number = $this->http->FindSingleNode("//text()[".$this->starts($this->t("Card No："))."]", null, true,
                "/{$this->opt($this->t('Card No：'))}\s*([A-Z\d]{5,})\s*$/u");

            $st->setNumber($number);
            $st->setNoBalance(true);

            $conditions = ["normalize-space() = 'km'", "contains(normalize-space(), ' km')"];
            foreach ($conditions as $cond) {
                $xpath = "//text()[{$cond}]/ancestor::td[1][following::td[normalize-space()][1][".$this->contains($this->t("Expiry Mileages/end of"))."]]"
                    ."[following::td[not(.//td)][normalize-space()][2][.//text()[{$cond}]] or preceding::td[not(.//td)][normalize-space()][2][.//text()[{$cond}]]]";
                $miles = $this->http->FindNodes($xpath, null, "/^\s*(\d+)\s*km\s*$/");

                if (count($miles) === 3) {
                    $st->addProperty('ExpiringBalance', array_sum($miles));
                    break;
                }
            }

            foreach ($this->t("CardLevel") as $row) {
                if (strpos($row, '%CardLevel%') !== false) {
                    $strs = array_filter(explode('%CardLevel%', $row));
                    if (!empty($strs)) {
                        $status = $this->http->FindSingleNode("//text()[".$this->contains($strs, 'and')."]", null, true,
                            "/^\s*".str_replace('%CardLevel%', '(?<level>[[:alpha:] ]{2,15})', $row)."/u");
                        if (!empty($status)) {
                            $st->addProperty('Status', $status);
                        }
                    }
                }
            }

            break;
        }

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
        return 0;
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

    private function contains($field, $delimiter = 'or')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $delimiter = trim($delimiter);
        if ($delimiter !== 'and') {
            $delimiter =  'or';
        }

        return implode(" ".$delimiter." ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
