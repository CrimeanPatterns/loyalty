<?php

namespace AwardWallet\Engine\ctrip\Email;

class TrainTicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-7622477.eml, ctrip/it-8048565.eml";

    public $reFrom = "@ctrip.com";
    public $reBody = [
        'zh' => '火车票成交确认单',
    ];
    public $reSubject = [
        '火车票成交确认单',
    ];
    public $lang = '';
    public static $dict = [
        'zh' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();
        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, "ctrip.com") !== false) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'订单号')]/following::text()[normalize-space(.)][1]");
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='护照' or normalize-space(.)='身份证']/ancestor::td[1]/preceding::td[1]");
        $it['TicketNumbers'] = explode(",", $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'请您携带窗口取票号')]/following::text()[normalize-space(.)][1]"));
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'车票费用')]/ancestor::p[1]", null, true, "#车票费用\s*(.+?);#"));

        if ($this->http->XPath->query("//text()[contains(normalize-space(.),'很高兴通知您以下订单已经成功')]")->length > 0) {
            $it['Status'] = '下订单已经成功';
        }

        if (!empty($tot['Total'])) {
            $it['BaseFare'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'金额总计')]/ancestor::td[1]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $xpath = "//text()[normalize-space(.)='车次']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $seg = [];
            $date = strtotime($this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[string-length(normalize-space(.))>2][last()]", $root));
            $seg['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()]", $root);

            $seg['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root), $date);
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root), $date);
            $seg['DepName'] = implode(" ", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)][position()>1]", $root));
            $seg['ArrName'] = implode(" ", $this->http->FindNodes("./td[4]/descendant::text()[normalize-space(.)][position()>1]", $root));
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            $node = $this->http->FindSingleNode("./following::table[1]//td[contains(.,'seat')]", $root);

            if (preg_match("#(.+?)\s*seat:\s*(\d+)\s*车厢(\d+[A-Z]+)#i", $node, $m)) {
                $seg['Cabin'] = $m[1];
                $seg['Type'] = $seg['FlightNumber'] . '/' . $m[2];
                $seg['Seats'] = $m[3];
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody}')]")->length > 0) {
                    //				if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        switch ($this->lang) {
            case 'zh':
                $node = str_replace('¥', 'CNY', $node);

                break;
        }
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
