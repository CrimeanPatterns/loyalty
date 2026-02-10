<?php

namespace AwardWallet\Engine\expedia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourActivity extends \TAccountChecker
{
    public $mailFiles = "expedia/it-61950604.eml, expedia/it-61985482.eml, expedia/it-62873236.eml, expedia/it-63095297.eml, expedia/statements/it-75501708.eml, expedia/statements/it-100721870.eml";

    public static $dictionary = [
        'en' => [
            "SubjectRe" => "(?:you['’]ve earned|you have) (?<balance>\d[\d,.]*) points worth (?<worth>\D{1,5}\s?\d[\d,\.]*|\d[\d,\.]*\s?\D{1,5})",
            "Hi "       => ["Hi ", "Welcome to Expedia,"],
            //            "You have" => "",
            "available point(s)" => ["available point(s)", "available points"],
            //            "available point(s)Re" => "(?<points>\d[\d,.]*)",
            //            "towards your next hotel booking" => "",
            //            "points pending." => "",
            "*As of" => ["*As of", "Data valid as of"],
            //            "Hotel nights:" => "",
            //            "Amount spent:" => "",
            //            "Your gold benefits" => "",
            //            "Your blue benefits" => "",
            //            "Your silver benefits" => "",
        ],
        'pt' => [
            "SubjectRe"          => "Seu extrato mensal – você tem (?<balance>\d[\d,.]*) pontos que valem (?<worth>\D{1,5}\s?\d[\d,\.]*|\d[\d,\.]*\s?\D{1,5})",
            "Hi "                => "Olá,",
            "You have"           => "Você tem",
            "available point(s)" => "pontos disponíveis",
            //            "available point(s)Re" => "(?<points>\d[\d,.]*)",
            "towards your next hotel booking" => "para a sua próxima reserva de hotel",
            "points pending."                 => "pontos pendentes.",
            "*As of"                          => "*Em",
            "Hotel nights:"                   => "Diárias de hotel:",
            "Amount spent:"                   => "Valor gasto:",
            //            "Your gold benefits" => "",
            //            "Your blue benefits" => "",
            //            "Your silver benefits" => "",
        ],
        'es' => [
            "SubjectRe"                       => "tienes (?<balance>\d[\d,.]*) puntos con valor de (?<worth>\D{1,5}\s?\d[\d,\.]*|\\$?\d[\d,\.]*\s?\D{1,5})$",
            "Hi "                             => ["¡Hola,", "Estimado "],
            "You have"                        => "Tienes",
            "available point(s)"              => ["puntos disponibles", "punto(s) disponible(s)"],
            "available point(s)Re"            => "Tienes (?<points>\d[\d,.]*) punto\(?s\)? disponible\(?s\)?",
            "towards your next hotel booking" => "para tu próxima reservación de hotel",
            "points pending."                 => ["pontos pendentes.", "puntos pendientes de usar."],
            "*As of"                          => ["*A fecha de", "*Al"],
            "Hotel nights:"                   => "Noches de hotel:",
            "Amount spent:"                   => ["Importe gastado:", "Monto utilizado:"],
            //            "Your gold benefits" => "",
            //            "Your blue benefits" => "",
            //            "Your silver benefits" => "",
        ],
        'it' => [ // it-61950604.eml
            "SubjectRe"                       => "Riepilogo mensile: hai (?<balance>\d[\d,.]*) punti, del valore di (?<worth>\D{1,5}\s?\d[\d,\.]*|\d[\d,\.]*\s?\D{1,5})",
            "Hi "                             => "Gentile ",
            "You have"                        => "hai a disposizione",
            "available point(s)"              => "punti",
            "available point(s)Re"            => "hai a disposizione (?<points>\d[\d,.]*) punti",
            "towards your next hotel booking" => "se li utilizzi per prenotare un hotel",
            "points pending."                 => "I punti in sospeso sono:",
            "*As of"                          => "*In data",
            "Hotel nights:"                   => "Notti in hotel:",
            "Amount spent:"                   => "Importo speso:",
            //            "Your gold benefits" => "",
            //            "Your blue benefits" => "",
            //            "Your silver benefits" => "",
        ],
        'ja' => [ // it-61985482.eml
            "SubjectRe" => "ポイント明細のお届け – (?<balance>\d[\d,.]*) ポイント \((?<worth>\D{1,5}\s?\d[\d,\.]*|\d[\d,\.]*\s?\D{1,5}) 円相当\) を獲得しました",
            //            "Hi " => "", // without name: "エクスペディア会員様"
            "You have"                        => "現在ご利用可能なポイントは",
            "available point(s)"              => "ポイント",
            "available point(s)Re"            => "現在ご利用可能なポイントは (?<points>\d[\d,.]*) ポイント",
            "towards your next hotel booking" => "(一般のホテルをご予約の場合)",
            "points pending."                 => "ポイントあります",
            "*As of"                          => "現在",
            "Hotel nights:"                   => "宿泊数 :",
            "Amount spent:"                   => "ご利用額 :",
            //            "Your gold benefits" => "",
            //            "Your blue benefits" => "",
            //            "Your silver benefits" => "",
        ],
        'zh' => [
            "SubjectRe" => "本月份點數明細 - 您已經累積了 (?<balance>\d[\d,.]*) 點，價值 (?<worth>\D{1,5}\s?\d[\d,\.]*|\d[\d,\.]*\s?\D{1,5})",
            //            "Hi " => "", // without name: "Expedia Rewards 會員您好 :"
            "You have"           => "您已累積了",
            "available point(s)" => "點",
            //            "available point(s)Re" => "(?<points>\d[\d,.]*)",
            "towards your next hotel booking" => "飯店房價",
            "points pending."                 => "點正在處理中",
            "*As of"                          => "* 截至",
            "Hotel nights:"                   => "住宿晚數：",
            "Amount spent:"                   => "消費金額：",
            //            "Your gold benefits" => "",
            //            "Your blue benefits" => "",
            //            "Your silver benefits" => "",
        ],
        'ko' => [ // it-62873236.eml
            "SubjectRe" => "월간 내역 - 고객님께서는 (?<balance>\d[\d,.]*)포인트\((?<worth>.*\s?\d[\d,\.]*|\d[\d,\.]*\s?.*) 상당\)를 보유하고 계십니다",
            //            "Hi " => "",// without name: "익스피디아 리워드 회원님"
            "You have"           => "이용 가능한",
            "available point(s)" => "213",
            //            "available point(s)Re" => "(?<points>\d[\d,.]*)",
            "towards your next hotel booking" => "다음 호텔 예약 시",
            "points pending."                 => "포인트가 적립 대기 중입니다.",
            "*As of"                          => "현재",
            "Hotel nights:"                   => "호텔 숙박 일수:",
            "Amount spent:"                   => "예약하신 금액:",
            //            "Your gold benefits" => "",
            //            "Your blue benefits" => "",
            //            "Your silver benefits" => "",
        ],
        'nl' => [
            "SubjectRe"          => "Jouw maandelijkse overzicht - je hebt (?<balance>\d[\d,.]*) punten t\.w\.v\. (?<worth>\D{1,5}\s?\d[\d,\.]*|\d[\d,\.]*\s?\D{1,5})",
            "Hi "                => "Beste ",
            "You have"           => "Je hebt",
            "available point(s)" => ["beschikbare punt(en)", "beschikbare punten"],
            //            "available point(s)Re" => "Je hebt (?<points>\d[\d,.]*) beschikbare punten",
            "towards your next hotel booking" => "korting op je volgende hotelboeking",
            "points pending."                 => "punten in behandeling.",
            "*As of"                          => "*Op ",
            "Hotel nights:"                   => "Hotelnachten:",
            "Amount spent:"                   => "Besteed bedrag:",
            //            "Your gold benefits" => "",
            //            "Your blue benefits" => "",
            //            "Your silver benefits" => "",
        ],
        'de' => [
            "SubjectRe" => "Sie haben (?<balance>\d[\d,.]*) Punkte im Wert von (?<worth>\D{1,5}\s?\d[\d,\.]*|\d[\d,\.]*\s?\D{1,5})",
            //            "Hi " => "Liebes ",
            "You have"                        => "Sie haben",
            "available point(s)"              => ["verfügbare Punkte"],
            "available point(s)Re"            => "Sie haben (?<points>\d[\d,.]*) verfügbare Punkte",
            "towards your next hotel booking" => "für Ihre nächste Hotelbuchung",
            "points pending."                 => "ausstehende Punkte.",
            "*As of"                          => "* Stand:",
            "Hotel nights:"                   => "Hotelübernachtungen:",
            "Amount spent:"                   => "Ausgegebener Betrag:",
            //            "Your gold benefits" => "",
            //            "Your blue benefits" => "",
            //            "Your silver benefits" => "",
        ],
        'no' => [
            //            "SubjectRe" => "du har (?<balance>\d[\d,.]*) poeng verdt (?<worth>\D{1,5}\s?\d[\d,\.]*|\d[\d,\.]*\s?\D{1,5})",
            "Hi "                             => "Hei,",
            "You have"                        => "Du har",
            "available point(s)"              => ["poeng tilgjengelig"],
            "available point(s)Re"            => "Du har (?<points>\d[\d,.]*) poeng tilgjengelig",
            "towards your next hotel booking" => "som du kan bruke ved neste hotellbestilling",
            "points pending."                 => "poeng på vent.",
            "*As of"                          => "*Per",
            "Hotel nights:"                   => "Hotellnetter:",
            "Amount spent:"                   => "Beløp brukt:",
            //            "Your gold benefits" => "",
            //            "Your blue benefits" => "",
            //            "Your silver benefits" => "",
        ],
        'id' => [
            //            "SubjectRe" => "du har (?<balance>\d[\d,.]*) poeng verdt (?<worth>\D{1,5}\s?\d[\d,\.]*|\d[\d,\.]*\s?\D{1,5})",
            "Hi "                  => "Halo ",
            "You have"             => "Anda memiliki",
            "available point(s)"   => ["poin tersedia"],
            "available point(s)Re" => "Anda memiliki (?<points>\d[\d,.]*) poin tersedia",
            //            "towards your next hotel booking" => "som du kan bruke ved neste hotellbestilling",
            //            "points pending." => "poeng på vent.",
            "*As of"        => "*Hingga",
            "Hotel nights:" => "Malam inap:",
            "Amount spent:" => "Jumlah yang dibelanjakan:",
            //            "Your gold benefits" => "",
            //            "Your blue benefits" => "",
            //            "Your silver benefits" => "",
        ],
    ];

    private $detectFrom = "expediamail.com";

    private $detectBody = [
        "en" => [
            "Expedia Rewards Activity",
            "You are receiving this transactional email based on a recent booking or account-related update",
        ],
        "pt" => [
            "Sua atividade do programa Expedia Rewards em",
        ],
        "es" => [
            "Tu actividad de Expedia Rewards en",
        ],
        "it" => [
            "La tua attività Expedia Rewards per il",
        ],
        "ja" => [
            "年のエクスペディア会員プログラム利用履歴",
        ],
        "zh" => [
            "Expedia Rewards 獎勵計畫活動記錄",
        ],
        "ko" => [
            "년 익스피디아 리워드 적립 현황",
        ],
        "nl" => [
            "Jouw Expedia Rewards-activiteit in",
        ],
        "de" => [
            "Ihre Expedia Rewards-Aktivität",
        ],
        "no" => [
            "Expedia Rewards-aktiviteten din i",
        ],
        "id" => [
            "Aktivitas Expedia Rewards",
        ],
    ];

    private $lang = 'en';

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getCleanFrom()) === false) {
            return false;
        }

        return $this->assignLang()
            || $this->http->FindSingleNode("(//img[@alt='Your points balance']/@src)[1]") !== null;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->add()->statement();

        $name = $balance = null;

        if (preg_match("#" . $this->t("SubjectRe") . "#u", $parser->getSubject(), $m)) {
            if (isset($m['balance'])) {
                $balance = (int) (str_replace([',', '.'], '', trim($m['balance'])));
            }

            if (isset($m['worth'])) {
                $balanceWorth = trim($m['worth']);
            }
        }

        if (($balance === null || !isset($balanceWorth))
            && $url = $this->http->FindSingleNode("(//img[@alt='Your points balance']/@src)[1]")
        ) {
            if (preg_match("/&available_points=(\d[\d,.]*)(?:&|$)/", $url, $m)) {
                $balance = (int) (str_replace([',', '.'], '', trim($m[1])));
            }

            if (preg_match("/&currency_symbol=([^\d&]{1,5})(?:&|$)/", $url, $m1)
                && preg_match("/&available_points_value=(\d[\d,.]*)(?:&|$)/", $url, $m2)) {
                $balanceWorth = $m2[1] . ' ' . $m1[1];
            }
        }

        // Balance
        if ($balance === null) {
            $balanceText = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("available point(s)")) . "][preceding::text()[" . $this->eq($this->t("You have")) . "]]");

            if (preg_match("#^\s*(\d[\d,.]*)\s*" . $this->preg_implode($this->t("available point(s)")) . "#", $balanceText, $m)) {
                $balance = (int) (str_replace([',', '.'], '', trim($m[1])));
            }
        }

        if ($balance === null) {
            $balanceText = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("available point(s)")) . " and " . $this->contains($this->t("You have")) . "]");

            if ((preg_match("#" . $this->t("available point(s)Re") . "#", $balanceText, $m)
                    || preg_match("#" . $this->preg_implode($this->t("You have")) . "\s*(?<points>\d[\d,.]*)\s*" . $this->preg_implode($this->t("available point(s)")) . "#", $balanceText, $m)) && isset($m['points'])) {
                $balance = (int) (str_replace([',', '.'], '', trim($m['points'])));
            }
        }

        $date = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("*As of")) . "]", null, true, "#" . $this->preg_implode($this->t("*As of")) . "\s*(.+)#");

        if (in_array($this->lang, ['ja', 'ko'])) {
            $date = $this->http->FindSingleNode("//text()[" . $this->starts("*") . " and " . $this->contains($this->t("*As of")) . "]", null, true, "#\*(?:\s*\[[^\]]*\])?\s*(.+)\s*" . $this->preg_implode($this->t("*As of")) . "#");
        }

        if (preg_match("#\d{1,2}/\d{1,2}/\d{4}#", $date) && !preg_match('#\busmail\b#', $parser->getCleanFrom())) {
            $date = str_replace('/', '.', $date);
        }

        if (!empty($date)) {
            $st->setBalanceDate($this->normalizeDate($date));
        }

        // BalanceWorth
        if (!isset($balanceWorth)) {
            $balanceWorth = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("towards your next hotel booking")) . "]/preceding::text()[normalize-space()][1]");
        }

        if (preg_match("#^\s*(?:(?:\D{1,5}|&\#.*)\s?\d[\d,\.]*|\\$?\d[\d,\.]*\s?(?:\D{1,5}|&\#.*))\s*$#", $balanceWorth)) {
            // &#x20A9;8,664
            $st->addProperty('BalanceWorth', trim($balanceWorth));
        }

        // PendingPoints
        $pendingPoints = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("points pending.")) . "]");

        if (preg_match("#\b(\d[\d,]*)\s*" . $this->preg_implode($this->t("points pending.")) . "#", $pendingPoints, $m)
            || preg_match("#" . $this->preg_implode($this->t("points pending.")) . "\s*(\d[\d,]*)\b#", $pendingPoints, $m)) {
            $st->addProperty('PendingPoints', (int) (str_replace(',', '', trim($m[1]))));
        }

        // YTDNights
        $YTDNights = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hotel nights:")) . "]");

        if (!empty($YTDNights) && preg_match("#" . $this->preg_implode($this->t("Hotel nights:")) . "\s*(\d+)#", $YTDNights, $m)) {
            $st->addProperty('YTDNights', (int) trim($m[1]));
        } elseif (($n = $this->http->FindSingleNode("(//img[@alt='Your room nights']/@src)[1]", null, true, "/&confirmed_room_nights=(\d+)(?:&|$)/"
        )) !== null) {
            $st->addProperty('YTDNights', (int) $n);
        }

        // moneySpent
        $moneySpent = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Amount spent:")) . "]");

        if (preg_match("#" . $this->preg_implode($this->t("Amount spent:")) . "\s*(\D{1,5}\s?\d[\d,.]*|\\$?\d[\d,.]*\s*\D{1,5})$#", $moneySpent, $m)) {
            // $76.03 MXN
            $st->addProperty('MoneySpent', trim($m[1]));
        }

        if (!empty($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your gold benefits")) . "][ancestor::*[@bgcolor = '#e7b40d']]"))
                || !empty($this->http->FindSingleNode("//img[@alt='Gold_Badge' or contains(@src, '/g_tier_badge_')]/@src"))) {
            $st->addProperty('Status', 'Gold');
        }

        if (!empty($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your silver benefits")) . "][ancestor::*[@bgcolor = '#b2b3b7']]"))
            || !empty($this->http->FindSingleNode("//img[@alt='Silver_Badge' or contains(@src, '/s_tier_badge_')]/@src"))
        ) {
            $st->addProperty('Status', 'Silver');
        }

        if (!empty($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your blue benefits")) . "][ancestor::*[@bgcolor = '#1787c8']]"))
            || !empty($this->http->FindSingleNode("//img[@alt='Blue_Badge' or contains(@src, '/b_tier_badge_')]/@src"))) {
            $st->addProperty('Status', 'Blue');
        }

        if (empty($st->getProperties()['Status'])) {
            $status = trim($this->http->FindSingleNode("(//img[@alt='Your points balance' or @alt='Your room nights']/@src[contains(., 'tier_status')])[1]", null, true,
                "/&tier_status=([A-Z ]+)(?:&|$)/"));

            if (!empty($status)) {
                $st->addProperty('Status', $status);
            }
        }

        // Name
        $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hi ")) . "]", null, true,
            "/^{$this->preg_implode($this->t("Hi "))}\s*([^\d\W]+(?: [^\d\W]+){0,4})(?:\s*[,.;:!?]|$)/u");

        if (!empty($name) and !preg_match("#(?:expedia|member)#ui", $name)) {
            $st->addProperty('Name', $name);
        }

        if ($balance !== null) {
            $st->setBalance($balance);
        } elseif ($name) {
            // it-100721870.eml
            $st->setNoBalance(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class) . ucfirst($this->lang));

        return $email;
    }

    private function assignLang(): bool
    {
        if (!isset($this->detectBody, $this->lang)) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug($str);
        $in = [
            "#^\s*(\d{4})[./](\d{1,2})[./](\d{1,2})\s*$#iu", //  2020.04.13
        ];
        $out = [
            "$3.$2.$1",
        ];
        $str = preg_replace($in, $out, $str);

        return strtotime($str);
    }
}
