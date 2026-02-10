<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It2430816 extends \TAccountChecker
{
    public $mailFiles = "british/it-10236691.eml, british/it-10315984.eml, british/it-2430816.eml, british/it-2442844.eml, british/it-2863641.eml, british/it-2877893.eml, british/it-4597375.eml, british/it-4600714.eml, british/it-7800697.eml, british/it-85620660.eml, british/it-85693169.eml, british/it-85835018.eml";

    private static $detectors = [
        'en' => ["Your new flight details"],
        'es' => ["Datos de su nuevo vuelo"],
        'pl' => ["Obecne szczegóły dotyczące lotu są następujące:"],
        'zh' => ["您的新航班详细信息"],
    ];

    private static $dictionary = [
        'en' => [
            "Booking Reference:"      => ["Booking Reference:", "Booking reference:"],
            "Your new flight details" => "Your new flight details",
            "status"                  => ["there has been a change to the time", "We have rebooked you"],
            "Passengers"              => ["Passengers", "Traveller names"],
        ],

        'es' => [
            "Booking Reference:"      => ["Referencia de la reserva:"],
            "Your new flight details" => "Datos de su nuevo vuelo",
            "Passengers"              => "Pasajeros",
            "status"                  => ["Hemos efectuado una nueva"],
        ],

        'pl' => [
            "Booking Reference:"      => ["Odniesienie dla rezerwacji:"],
            "Your new flight details" => "Obecne szczegóły dotyczące lotu są następujące:",
            "Passengers"              => "Pasażerowie",
            "status"                  => ["Zmieniliśmy Państwa"],
        ],

        'zh' => [
            "Booking Reference:"      => ["订位代号:"],
            "Your new flight details" => "您的新航班详细信息",
            "Passengers"              => "乘客",
            "status"                  => ["已改变"],
            "Terminal"                => "航站楼",
        ],
    ];

    private $from = ["@email.ba.com", "@messages.ba.com"];

    private $body = "British Airways";

    private $subject = ["Time change", "Changes", "Cancellation"];

    private $lang;

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->from as $re) {
            if (stripos($from, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $email->setType('It2430816');
        $this->parseEmail($email);

        return $email;
    }

    private function parseEmail(Email $email)
    {
        if (!$this->detectBody()) {
            return false;
        }
        $it = [];
        $r = $email->add()->flight();

        $xpath = "//text()[" . $this->starts($this->t("Your new flight details")) . "]";

        if ($this->http->XPath->query("//*[" . $this->contains($this->t('status')) . "]")->length > 0) {
            $r->general()->status("updated");
        }

        $confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference:'))}]", null, true, "/{$this->opt($this->t('Booking Reference:'))}\s*([A-Z\d]{5,})/");

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Booking Reference:")) . "]|//*[" . $this->starts($this->t("Booking Reference:")) . "])[1]", null, false, "/" . $this->opt($this->t("Booking Reference:")) . "(.+)/");
        }

        if (!empty($confNo)) {
            $r->general()->confirmation($confNo, $this->t("Booking Reference:")[0]);
        }

        $pax = array_diff($this->http->FindNodes("//text()[" . $this->starts($this->t("Your flight details are now as follows:")) . "]/following::td[1]/descendant::tr[" . $this->starts($this->t("Passengers")) . "]/descendant::td[not(" . $this->contains($this->t("Passengers")) . ")]"), ['']);

        if (empty($pax)) {
            $pax = $this->http->FindNodes("//text()[{$this->starts($this->t('Passengers'))}]/ancestor::tr[1]/following-sibling::tr");
        }

        if (empty($pax)) {
            $pax = array_diff($this->http->FindNodes("(//text()[" . $this->starts($this->t("Passengers")) . "]/ancestor::td[1]/following::table[1]/descendant::text())|(//text()[normalize-space(.)='Passenger']/following::td[1])"), ['']);
        }

        if (!empty($pax)) {
            $r->general()->travellers($pax, true);
        }

        $air = $this->http->FindNodes($xpath . "/ancestor::tr[3]/descendant::tr/td[3]/descendant::td[position() = 1 and not(" . $this->starts($this->t("Your new flight details")) . ")]");

        if (empty($air)) {
            $air = array_values(array_diff($this->http->FindNodes($xpath . "/ancestor::tr[1]/following::tr[position() <= 3]/td[3]"), ['']));
        }

        if (empty($air)) {
            $air = $this->http->FindNodes("//img[contains(@src, '/flight-icon-angled-Blue')]/ancestor::tr[1]");
        }

        if (!empty($air)) {
            foreach ($air as $k => $item) {
                if (preg_match("/^([A-Z]{2})[\s]?(\d{1,5})/", $item, $m)) {
                    $it["airName"][$k] = $m[1];
                    $it["airNum"][$k] = $m[2];
                    $it["air"][$k] = $m[1] . $m[2];
                }
            }
        }

        $dep = $this->http->FindNodes($xpath . "/following::tr/descendant::td[contains(@bgcolor,'#ffffbb') and not(" . $this->starts($it["air"]) . ")][1]");

        if (empty($dep)) {
            $dep = $this->http->FindNodes($xpath . "/ancestor::tr[3]/descendant::tr/td[3]/table[2]/descendant::td[1]");
        }

        if (empty($dep)) {
            $dep = $this->http->FindNodes($xpath . "/ancestor::tr[1]/following::tr[2]/td[4]");
        }

        if (empty($dep)) {
            if (count($air) == 1) {
                $dep[] = implode(" ", $this->http->FindNodes("//img[contains(@src, '/flight-icon-angled-Blue')]/ancestor::tr[2]/following::td[3]/descendant::tr/td[1]"));
            } else {
                $depAll = implode(" ", $this->http->FindNodes("//img[contains(@src, '/flight-icon-angled-Blue')]/ancestor::tr[2]/following::td[3]/descendant::tr/td[1]"));
                $dep = array_values(array_filter(preg_split("/{$this->opt($this->t('DEPARTS'))}/", $depAll)));
            }
        }

        if (!empty($dep)) {
            foreach ($dep as $k => $item) {
                $this->logger->debug($item);

                if (preg_match("/(\d{1,2})\s(\D+)\s+(\d{4})\s*(\d{1,2}:\d{1,2})\s+(.+)\s+\d+\:\d+\s+(?:{$this->opt($this->t('Terminal'))}\s*([A-Z\d]+))/s", $item, $m)
                    || preg_match("/(\d{1,2})\s(\D+)\s(\d{4})([\s]?\d{1,2}:\d{1,2})[\s]?(?:(.+)[\s]?{$this->opt($this->t('Terminal'))}\s([A-z\d]{1,2})|(.+))/", $item, $m)
                ) {
                    if (!empty($m[6]) || $m[6] == '0') {
                        $it["depDate"][$k] = $this->normalizeDate($m[1] . " " . $m[2] . " " . $m[3] . " " . $m[4]);
                        $it["depName"][$k] = preg_replace("/(?:\+\d+|[\d\:]+)/", "", $m[5]);
                        $it["depTerminal"][$k] = $m[6];
                    } else {
                        $it["depDate"][$k] = $this->normalizeDate($m[1] . " " . $m[2] . " " . $m[3] . " " . $m[4]);
                        $it["depName"][$k] = preg_replace("/(?:\+\d+|[\d\:]+)/", "", $m[7]);
                    }
                }
            }
        }

        $arr = $this->http->FindNodes($xpath . "/following::tr/descendant::td[contains(@bgcolor,'#ffffbb') and not(" . $this->starts($it["air"]) . ")][2]");

        if (empty($arr)) {
            $arr = $this->http->FindNodes($xpath . "/ancestor::tr[3]/descendant::tr/td[3]/table[2]/descendant::td[6]");
        }

        if (empty($arr)) {
            $arr = $this->http->FindNodes($xpath . "/ancestor::tr[1]/following::tr[2]/td[5]");
        }

        if (empty($arr)) {
            if (count($air) == 1) {
                $arr[] = implode(" ", $this->http->FindNodes("//img[contains(@src, '/flight-icon-angled-Blue')]/ancestor::tr[2]/following::td[3]/descendant::tr/td[2]"));
            } else {
                $arrAll = implode(" ", $this->http->FindNodes("//img[contains(@src, '/flight-icon-angled-Blue')]/ancestor::tr[2]/following::td[3]/descendant::tr/td[2]"));
                $arrAll = preg_replace("/^[\d\:]+\s*/", "", $arrAll);
                $arr = array_values(array_filter(preg_split("/{$this->opt($this->t('ARRIVES'))}/", $arrAll)));
            }
        }

        if (!empty($arr)) {
            foreach ($arr as $k => $item) {
                if (preg_match("/(\d{1,2})\s(\D+)\s(\d{4})([\s]?\d{1,2}:\d{1,2})[\s]?(?:(.+)[\s]?{$this->opt($this->t('Terminal'))}\s([A-z\d]{1,2})|(.+))/", $item, $m)) {
                    if (!empty($m[6]) || $m[6] == '0') {
                        $it["arrDate"][$k] = $this->normalizeDate($m[1] . " " . $m[2] . " " . $m[3] . " " . $m[4]);
                        $it["arrName"][$k] = preg_replace("/(?:\+\d+|[\d\:]+)/", "", $m[5]);
                        $it["arrTerminal"][$k] = $m[6];
                    } else {
                        $it["arrDate"][$k] = $this->normalizeDate($m[1] . " " . $m[2] . " " . $m[3] . " " . $m[4]);
                        $it["arrName"][$k] = preg_replace("/(?:\+\d+|[\d\:]+)/", "", $m[7]);
                    }
                }
            }
        }

        foreach ($it["airName"] as $k => $v) {
            $s = $r->addSegment();
            $s->departure()->noCode();
            $s->arrival()->noCode();

            if (!empty($it["airName"][$k])) {
                $s->airline()->name($it["airName"][$k]);
            }

            if (!empty($it["airNum"][$k])) {
                $s->airline()->number($it["airNum"][$k]);
            }

            if (!empty($it["depName"][$k])) {
                $s->departure()->name($it["depName"][$k]);
            }

            if (!empty($it["depDate"][$k])) {
                $s->departure()->date($it["depDate"][$k]);
            }

            if (isset($it["depTerminal"][$k])) {
                if (!empty($it["depTerminal"][$k]) || $it["depTerminal"][$k] == '0') {
                    $s->departure()->terminal($it["depTerminal"][$k]);
                }
            }

            if (!empty($it["arrName"][$k])) {
                $s->arrival()->name($it["arrName"][$k]);
            }

            if (!empty($it["arrDate"][$k])) {
                $s->arrival()->date($it["arrDate"][$k]);
            }

            if (isset($it["arrTerminal"][$k])) {
                if (!empty($it["arrTerminal"][$k]) || $it["arrTerminal"][$k] == '0') {
                    $s->arrival()->terminal($it["arrTerminal"][$k]);
                }
            }
        }

        return $email;
    }

    private function detectBody()
    {
        foreach (self::$detectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Booking Reference:"], $words["Your new flight details"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking Reference:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Your new flight details'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $this->logger->error($str);
        $in = [
            "#^\s*(\d+)\s*(\w+)\s*(\d{4})\s*([\d\:]+)\s*$#", //20 Jul 2021  11:40
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
