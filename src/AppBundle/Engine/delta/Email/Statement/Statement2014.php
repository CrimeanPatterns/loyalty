<?php

namespace AwardWallet\Engine\delta\Email\Statement;

class Statement2014 extends \TAccountChecker
{
    // delta personal statement email, new format, appeared in april 2014
    // subject: your <month> skymiles/medallion statement

    public $mailFiles = "delta/it-38188361.eml";

    protected $dict = [
        "MQMs"  => ["ja" => "MQM数", "ko" => "MQM", "es" => "MQM", "it" => "MQM", "fr" => "MQM"],
        "MQSs"  => ["ja" => "MQS数", "ko" => "MQS", "es" => "MQS", "it" => "MQS", "fr" => "MQS"],
        "hello" => [
            "ja"  => "様",
            "en"  => "Hello,",
            "zh1" => "您好",
            "zh2" => "您好",
            "ko"  => "님",
            "pt"  => "OLÁ",
            "de"  => "HALLO",
            "es"  => "HOLA",
            "it"  => "CIAO",
            "fr"  => "BONJOUR", ],
        "helloReg" => [
            "ja"  => "/(.+)様/",
            "en"  => "/Hello, (.+)/",
            "zh1" => "/您好 (.+):/",
            "zh2" => "/(.+) 您好/",
            "ko"  => "/^(.+) 님,/",
            "pt"  => "/OLÁ, ([^\.]+)/",
            "de"  => "/HALLO ([^\.]+)/",
            "es"  => "/HOLA, ([^\.]+)/",
            "it"  => "/CIAO, ([^\.]+)/",
            "fr"  => "/BONJOUR ([^\.]+)/",
        ],
        "TOTAL MILES" => [
            "ja"  => "マイル残高",
            "zh1" => "获得的里程",
            "zh2" => "全部累積哩數",
            "ko"  => "총 마일",
            "pt"  => "TOTAL DE MILHAS",
            "de"  => "SUMME MEILEN",
            "es"  => "TOTAL DE MILLAS",
            "it"  => "MIGLIA TOTALI",
            "fr"  => "NOMBRE DE MILES",
        ],
        "totalReg" => [
            "ja"  => "/^マイル残高\s*\(\d+年\d+月\d+日現在\)\s*(-?[\d\,]+)$/",
            "en"  => "/^TOTAL MILES\s*\(As of \w+ \d{1,2}\, \d{4}\)\s*(-?[\d\,]+)$/",
            "zh1" => "/^获得的里程\s*截止至\d+年\d+月\d+日(-?[\d\,]+)$",
            "zh2" => "/全部累積哩數\s*\(至\d+年\d+月\d+日止\)\s*([\d\,]+)$/",
            "ko"  => "/총 마일\s*\(\d+년 \d+월 \d+일 현재\)\s*([\d\,]+)$/",
            "pt"  => "/TOTAL DE MILHAS\s*\(em [\d\/]+\)\s*([\d\,]+)$/",
            "de"  => "/SUMME MEILEN\s*\(Stand[^\)]+\)\s*([\d\,]+)$/",
            "es"  => "/TOTAL DE MILLAS\s*\(al [\d\/]+\)\s*([\d\,]+)$/",
            "it"  => "/MIGLIA TOTALI\s*\(al [\d\/]+\)\s*([\d\,]+)$/",
            "fr"  => "/NOMBRE DE MILES\s*\(au [\d\/]+\)\s*([\d\,]+)$/",
        ],
    ];

    protected $lang;

    public function ParseStatement()
    {
        $result = [];

        foreach ([$this->trans("MQMs") => "MedallionMilesYTD", $this->trans("MQSs") => "MedallionSegmentsYTD", $this->trans("MQDs") => "MedallionDollarsYTD"] as $text => $field) {
            $xpath = "//td[contains(., '{$text}') and not(.//td)]";
            $regexp = "/{$text}[^:]*:\s*(\\\$?[\d\.\,]+)/";
            $nodes = array_values(array_filter($this->http->FindNodes($xpath, null, $regexp), function ($s) {return isset($s); }));

            if (count($nodes) == 1) {
                $result[$field] = $nodes[0];
            }
        }
        $result["Name"] = trim($this->http->FindSingleNode("//table[not(.//table) and following-sibling::table[contains(., 'SkyMiles')]]//*[contains(text(), '" . $this->trans("hello") . "')]", null, true, $this->trans("helloReg")));
        $result["Number"] = $result["Login"] = $this->http->FindSingleNode("//a[contains(., 'SkyMiles') and contains(., '#')]//b", null, true, "/^\d+/");
        $total = $this->trans("TOTAL MILES");
        $balance = $this->http->FindSingleNode("(//tr[contains(normalize-space(.), '{$total}') and not(.//tr)])[1]/following-sibling::tr[last()]", null, true, "/^-?[\d\.\,]+$/");

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//tr[contains(normalize-space(.), '{$total}') and not(.//tr)]", null, true, $this->trans("totalReg"));
        }

        if (isset($balance)) {
            $result["Balance"] = str_replace(",", "", $balance);
        }

        if (isset($result['Balance'])) {
            $date = $this->http->FindSingleNode("(//tr[contains(normalize-space(.), '{$total}') and not(.//tr)])[1]", null, true, '/\(As of (\w+ \d+, \d+)\)/');

            if (isset($date) && strtotime($date) > strtotime('2000-01-01')) {
                $result['BalanceDate'] = strtotime($date);
            }
        }
        // dont know how basic current level is displayed
        $levels = [
            "hdrSilver" => "Silver Medallion",
            "hdrGold"   => "Gold Medallion",
            "hdrPlat"   => "Platinum Medallion",
            "hdrDiam"   => "Diamond Medallion",
        ];

        foreach ($levels as $src => $level) {
            if ($this->http->XPath->query("//img[contains(@src, '{$src}')]")->length > 0) {
                $result["Level"] = $level;

                break;
            }
        }

        // dont know how next year diamond level is displayed
        $levels = [
            "Silver"   => "SkyMiles Member",
            "Gold"     => "Silver Medallion",
            "Platinum" => "Gold Medallion",
            "Diamond"  => "Platinum Medallion",
        ];
        $to = $this->http->FindSingleNode("//tr[td[contains(., '" . $this->trans("MQSs") . "') and not(.//td)]]/td[last()]", null, true, "/^\d+ to (\w+)$/");

        if (isset($to) && isset($levels[$to])) {
            $result["NextYearLevel"] = $levels[$to];
        }
        $imgSrc = $this->http->FindSingleNode('(//img[contains(@src, "movable-ink") and contains(@src, "mi_MQM=")])[1]/@src', null, true, '/[.]png[?](.+)$/');

        if ($imgSrc) {
            parse_str($imgSrc, $params);

            if (isset($params['mi_MQM'])) {
                $result['MedallionMilesYTD'] = $params['mi_MQM'];
            }

            if (isset($params['mi_MQS'])) {
                $result['MedallionSegmentsYTD'] = $params['mi_MQS'];
            }

            if (isset($params['mi_MQD'])) {
                $result['MedallionDollarsYTD'] = '$' . $params['mi_MQD'];
            }

            if (isset($params['mi_blanceToTextMQM_MQS']) && preg_match('/^to (.+)$/', $params['mi_blanceToTextMQM_MQS'], $ms) > 0 && isset($levels[$ms[1]])) {
                $result['NextYearLevel'] = $levels[$ms[1]];
            }
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $pos = stripos($body, "<meta http-equiv=\"Content-Type\"");

        if ($pos === false) {
            $pos = stripos($body, "<meta http-equiv=Content-Type");
        }

        if ($pos !== false) {
            $body = substr($body, 0, $pos) . substr($body, stripos($body, ">", $pos));
            $this->http->SetEmailBody($body);
        }
        $this->detectLang();
        $props = $this->ParseStatement();

        return [
            'parsedData' => ["Properties" => $props],
            'emailType'  => "Statements2014",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && preg_match("/your [\S]+ (skymiles|medallion) statement/ims", $headers['subject'])
            || isset($headers['from']) && stripos($headers['from'], 'DeltaAirLines@e.delta.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->detectLang();

        $body = $parser->getHTMLBody();

        if ($this->http->XPath->query("//table[not(.//table) and following-sibling::table[contains(., 'SkyMiles')]]//*[contains(text(), '" . $this->trans("hello") . "')]")->length > 0
        && (strpos($body, 'SkyMiles') !== false
            || $this->http->XPath->query("//img[contains(@src, 'e.delta.com') and contains(@src, 'insider')]")->length > 0)
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]delta\.com/", $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return ["en", "zh", "ja", "ko", "pt", "de", "es", "it", "fr"];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    protected function trans($s)
    {
        if (isset($this->lang) && isset($this->dict[$s][$this->lang])) {
            return $this->dict[$s][$this->lang];
        } else {
            return $s;
        }
    }

    protected function detectLang()
    {
        if (stripos($this->http->Response["body"], "様") !== false) {
            $this->lang = "ja";
        } elseif (stripos($this->http->Response["body"], "获得的里程") !== false) {
            $this->lang = "zh1";
        } elseif (stripos($this->http->Response["body"], "全部累積哩數") !== false) {
            $this->lang = "zh2";
        } elseif (stripos($this->http->Response["body"], "총 마일") !== false) {
            $this->lang = "ko";
        } elseif (stripos($this->http->Response["body"], "TOTAL DE MILHAS") !== false) {
            $this->lang = "pt";
        } elseif (stripos($this->http->Response["body"], "SUMME MEILEN") !== false) {
            $this->lang = "de";
        } elseif (stripos($this->http->Response["body"], "TOTAL DE MILLAS") !== false) {
            $this->lang = "es";
        } elseif (stripos($this->http->Response["body"], "MIGLIA TOTALI") !== false) {
            $this->lang = "it";
        } elseif (stripos($this->http->Response["body"], "NOMBRE DE MILES") !== false) {
            $this->lang = "fr";
        } else {
            $this->lang = "en";
        }
    }
}
