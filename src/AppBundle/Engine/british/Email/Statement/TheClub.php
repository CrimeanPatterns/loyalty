<?php

namespace AwardWallet\Engine\british\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TheClub extends \TAccountChecker
{
    public $mailFiles = "british/statements/it-61401078.eml";
    public $headers = [
        'en' => [
            '/^Important changes to your Executive Club membership$/',
            '/^Thank you for joining our Club$/',
            '/^Bonus Avios when you shop through the eStore$/',
            '/^Mrs? (?<name>[[:alpha:] \-]+)\, your \w+ issue of The Club is online now$/',
            '/^Get ready for British adventures, Mrs? (?<name>[[:alpha:] \-]+)$/',
            '/^The Club magazine is here, (Mrs?|Dr|Miss|Ms|M|Mstr) (?<name>[[:alpha:] \-]+)$/',
        ],
        'ja' => [
            '/^Mrs? (?<name>[[:alpha:] \-]+)、英国の美しさをご発見ください$/u',
            '/^Mrs? (?<name>[[:alpha:] \-]+)、英国での冒険に出かけましょう$/u',
            '/^Mrs? (?<name>[[:alpha:] \-]+)、魅力的な隠れ家を予約しましょう$/u',
        ],
        'pt' => [
            '/^Ótima notícia: utilizar os seus Avios ficou ainda mais fácil$/u',
            '/^Londres está chamando\.\.\. o mundo também está na linha, (?:Sr|Mr|Sra|Dr) (?<name>[[:alpha:] \-]+)$/u',
            '/^Prepare-se para se aventurar em terras britânicas, (?:Sr|Mr|Sra|Dr) (?<name>[[:alpha:] \-]+)$/u',
            '/^Prepare-se para viver uma aventura na Grã-Bretanha, (?:Sr|Mr|Sra|Dr) (?<name>[[:alpha:] \-]+)$/u',
            '/^Descubra a beleza da Grã-Bretanha, (?:Sr|Mr|Sra|Dr) (?<name>[[:alpha:] \-]+)$/u',
            '/^Reserve um retiro agradável, (?:Sr|Mr|Sra|Dr) (?<name>[[:alpha:] \-]+)$/u',
            '/^(?:Sr|Mr|Sra|Dr) (?<name>[[:alpha:] \-]+), reserve sua aventura britânica hoje mesmo$/u',
        ],
        'de' => [
            '/^(?:Herr|Mr|Frau) (?<name>[[:alpha:] \-]+), werden Sie aktiv, um zu verhindern, dass Ihre Avios ungültig werden$/u',
            '/^(?:Herr|Mr|Frau) (?<name>[[:alpha:] \-]+), buchen Sie noch heute Ihr Abenteuer in Großbritannien$/u',
            '/^Auf zum Urlaub in Großbritannien, (?:Herr|Mr|Frau) (?<name>[[:alpha:] \-]+)$/u',
            '/^Buchen Sie einen unvergesslichen Urlaub, (?:Herr|Mr|Frau) (?<name>[[:alpha:] \-]+)$/u',
            '/^Entdecken Sie die Schönheit von Großbritannien, (?:Herr|Mr|Frau) (?<name>[[:alpha:] \-]+)$/u',
        ],
        'zh' => [
            '/^(Mr|Dr) (?<name>[[:alpha:] \-]+)，抓住最后的机会，不要让您的 Avios 积分过期$/u',
            '/^(Mr|Dr) (?<name>[[:alpha:] \-]+)，立即行动，不要让您的 Avios 积分过期$/u',
            '/^准备踏上英国冒险之旅，Mrs? (?<name>[[:alpha:] \-]+)$/u',
        ],
        'fr' => [
            '/^Embarquez pour des aventures britanniques, M (?<name>[[:alpha:] \-]+)$/u',
            '/^Réservez une escapade paradisiaque, M (?<name>[[:alpha:] \-]+)$/u',
        ],
        'es' => [
            '/^Prepárese para vivir aventuras con acento británico, (?:Sr|Mr|Sra) (?<name>[[:alpha:] \-]+)$/u',
            '/^(?:Sr|Mr|Sra) (?<name>[[:alpha:] \-]+), aún está a tiempo de conservar sus Avios$/u',
            '/^(?:Sr|Mr|Sra) (?<name>[[:alpha:] \-]+), descubra la belleza de Gran Bretaña$/u',
        ],
        'ru' => [
            '/^(?:Miss) (?<name>[[:alpha:] \-]+), приготовьтесь к британским приключениям!$/u',
        ],
        'it' => [
            '/^(?:Dott|Sig) (?<name>[[:alpha:] \-]+), preparati a vivere una fantastica avventura nel Regno Unito!$/u',
        ],

        // common
        'common' => [
            '/^.+, (?:Mrs?|Dr|Miss|Ms|M|Mstr|Sr|Sra|Dott|Sig|Herr|Frau) (?<name>[[:alpha:]][[:alpha:]\-]*( [[:alpha:]][[:alpha:]\-]*){0,3})[?!]?$/u',
            '/^(?:Mrs?|Dr|Miss|Ms|M|Mstr|Sr|Sra|Dott|Sig|Herr|Frau) (?<name>[[:alpha:]][[:alpha:]\-]*( [[:alpha:]][[:alpha:]\-]*){0,3}), .+$/u',
        ],
    ];

    private $lang;

    private static $dictionary = [
        'en' => [
            "My Executive Club account" => ["My Executive Club account", "My Executive Club Account"],
            "MEMBERSHIP"                => "MEMBERSHIP",
            //            "Dear" => "",
            //            "AVIOS" => "",
            //            "TIER POINTS" => "",
            "LIFETIME TIER POINTS" => ["LIFETIME TIER POINTS", "LIFETIME TIER POINT"],
        ],
        'ja' => [
            "My Executive Club account" => "私のExecutive Clubアカウント",
            "MEMBERSHIP"                => "メンバーシップ",
            //            "Dear" => "",
            "AVIOS"                => "AVIOS",
            "TIER POINTS"          => "ティアポイント",
            "LIFETIME TIER POINTS" => "ライフタイムティアポイント",
        ],
        'pt' => [
            "My Executive Club account" => ["Minha conta do Executive Club", "A minha conta do Executive Club"],
            "MEMBERSHIP"                => ["ASSOCIAÇÃO", "ESTATUTO DE MEMBRO"],
            "Dear"                      => "Dear",
            "AVIOS"                     => "AVIOS",
            "TIER POINTS"               => "PONTOS DE NÍVEL",
            "LIFETIME TIER POINTS"      => "PONTOS DE NÍVEL VITALÍCIOS",
        ],
        'de' => [
            "My Executive Club account" => "Mein Executive Club-Konto",
            "MEMBERSHIP"                => "MITGLIEDSCHAFT",
            "Dear"                      => "Sehr Geehrter",
            "AVIOS"                     => "AVIOS",
            "TIER POINTS"               => "STATUSPUNKTE",
            "LIFETIME TIER POINTS"      => "LIFETIME-STATUSPUNKTE",
        ],
        'zh' => [
            "My Executive Club account" => "我的 Executive Club 帐户",
            "MEMBERSHIP"                => "会员资格",
            "Dear"                      => "Dear",
            "AVIOS"                     => "AVIOS",
            "TIER POINTS"               => "等级积点",
            "LIFETIME TIER POINTS"      => "历年等级积点",
        ],
        'fr' => [
            "My Executive Club account" => "Mon compte Executive Club",
            "MEMBERSHIP"                => "ADHÉSION",
            //            "Dear" => "",
            "AVIOS"                => "AVIOS",
            "TIER POINTS"          => "POINTS EXECUTIVE CLUB",
            "LIFETIME TIER POINTS" => "POINTS EXECUTIVE CLUB POUR TOUTE LA VIE",
        ],
        'es' => [
            "My Executive Club account" => "Mi cuenta de Executive Club",
            "MEMBERSHIP"                => "MEMBRESÍA",
            "Dear"                      => ["Estimada"],
            "AVIOS"                     => "AVIOS",
            "TIER POINTS"               => "PUNTOS DE ESTATUS",
            "LIFETIME TIER POINTS"      => "PUNTOS DE ESTATUS PARA TODA LA VIDA",
        ],
        'ru' => [
            "My Executive Club account" => "Мой аккаунт Executive Club",
            "MEMBERSHIP"                => "ЧЛЕНСТВО",
            //            "Dear" => [""],
            "AVIOS"                => "БАЛЛЫ AVIOS",
            "TIER POINTS"          => "СТАТУСНЫЕ БАЛЛЫ TIER POINTS",
            "LIFETIME TIER POINTS" => "БАЛЛЫ LIFETIME TIER POINTS",
        ],
        'it' => [
            "My Executive Club account" => "Il mio conto Executive Club",
            "MEMBERSHIP"                => "ISCRIZIONE",
            //            "Dear" => [""],
            "AVIOS"                => "AVIOS",
            "TIER POINTS"          => "TIER POINTS",
            "LIFETIME TIER POINTS" => "LIFETIME TIER POINTS",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@my.ba.com') !== false) {
            foreach ($this->headers as $header) {
                foreach ($header as $head) {
                    if (preg_match($head, $headers['subject'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'British Airways')]")->length == 0) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['My Executive Club account']) && $this->http->XPath->query("//text()[" . $this->contains($dict['My Executive Club account']) . "]")->length > 0
                && isset($dict['MEMBERSHIP']) && $this->http->XPath->query("//text()[" . $this->contains($dict['MEMBERSHIP']) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/executiveclub@my\.ba\.com$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['My Executive Club account']) && $this->http->XPath->query("//text()[" . $this->contains($dict['My Executive Club account']) . "]")->length > 0
                && isset($dict['MEMBERSHIP']) && $this->http->XPath->query("//text()[" . $this->contains($dict['MEMBERSHIP']) . "]")->length > 0) {
                $this->lang = $lang;
            }
        }

        if (empty($this->lang)) {
            return false;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('MEMBERSHIP')) . "]/preceding::text()[" . $this->starts($this->t('Dear')) . "][1]", null, true,
            "/^" . $this->opt($this->t('Dear')) . "(?:\s+(?:Mrs?|Dr|Miss|Ms|M|Mstr|Sr|Sra|Dott|Sig|Herr|Frau))?\s+(\D+)$/u");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        } elseif (isset($this->headers[$this->lang])) {
            $subject = $parser->getSubject();

            foreach (array_merge($this->headers[$this->lang], $this->headers['common']) as $re) {
                if (preg_match($re, $subject, $m)) {
                    if (!empty($m['name'])) {
                        $st->addProperty('Name', trim($m['name']));
                    }

                    break;
                }
            }
        }

        $number = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('MEMBERSHIP')) . "]", null, true, "/^" . $this->opt($this->t('MEMBERSHIP')) . "\s+(\d{7,})$/u");

        if (!empty($number)) {
            $st->addProperty('Number', $number);
        }

        $balance = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('AVIOS')) . "]/ancestor::tr[1]/preceding::tr[1]", null, true, "/^\s*([\d\,\.]+)$/iu");

        if ($balance === null) {
            $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('AVIOS'))}]/ancestor::th[1]", null, true, "/^\s*([\d\,\.]+)\s*{$this->opt($this->t('AVIOS'))}/iu");
        }

        if (!empty($balance)) {
            $balance = str_replace(',', '', $balance);

            if (preg_match("/^(\d+)\.(\d+\.\d+)$/", $balance, $m)) {
                $balance = $m[1] . $m[2];
            }
            $st->setBalance($balance);
        } elseif ($balance == '0') {
            $st->setBalance(0);
        }

        $tierPoints = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TIER POINTS'))}]/ancestor::th[1]", null, true, "/^\s*([\d\,\.]+)\s*{$this->opt($this->t('TIER POINTS'))}/");

        if ($tierPoints === null) {
            $tierPoints = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('TIER POINTS')) . "]/ancestor::tr[1]/preceding::tr[1]", null, true, "/^([\d\,\.]+)$/");
        }

        $this->logger->error($tierPoints);

        if (!empty($tierPoints)) {
            $st->addProperty('TierPoints', str_replace(',', '.', $tierPoints));
        } elseif ($tierPoints == 0) {
            $st->addProperty('TierPoints', $tierPoints);
        }

        $lifitimeTierPoints = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('LIFETIME TIER POINTS')) . "]/ancestor::th[1]", null, true, "/^\s*([\d\,\.]+)\s*{$this->opt($this->t('LIFETIME TIER POINTS'))}/");

        if ($lifitimeTierPoints === null) {
            $lifitimeTierPoints = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('LIFETIME TIER POINTS')) . "]/ancestor::tr[1]/preceding::tr[1]", null, true, "/^([\d\,\.]+)$/");
        }

        if (!empty($lifitimeTierPoints)) {
            $st->addProperty('LifetimeTierPoints', str_replace(',', '.', $lifitimeTierPoints));
        } elseif ($lifitimeTierPoints == 0) {
            $st->addProperty('LifetimeTierPoints', $lifitimeTierPoints);
        }

        $level = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('MEMBERSHIP')) . "]/preceding::img[1][contains(@src, 'www.britishairways.com') and contains(@src, '/Executive-Club-tiers/')]/@src", null, true,
            "/\/Executive-Club-tiers\/(Blue|Bronze|Silver|Gold)\//u");

        if (!empty($level)) {
            $st->addProperty('Level', $level);
        }
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

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
