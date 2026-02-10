<?php

namespace AwardWallet\Engine\edreams\Email;

class AirTicket extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "edreams/it-4050620.eml";
    public $reBody = [
        'it' => ['Gentile Cliente'],
    ];
    public $lang = '';
    public static $dict = [
        'it' => [
            'Record locator' => 'Booking Reference',
            'Return'         => 'Arrival',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false) {
                    $this->lang = $lang;

                    break;
                } else {
                    $this->lang = '';
                }
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "@edreams.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@edreams.com") !== false;
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., '" . $this->t('Record locator') . "')]", null, true, "#" . $this->t('Record locator') . ":\s+(\S+)#");
        $it['Passengers'] = $this->http->FindNodes("(//*[contains(text(), 'Name')]/following-sibling::text()[normalize-space(.)!=''][1])[1]", null, "#:\s*(.+)#");
        $xpath = "//*[contains(text(), '" . $this->t('Return') . "')]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->http->Log("roots not found $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($roots as $root) {
            $seg = [];
            $seg['DepDate'] = strtotime($this->dateStringToEnglish($this->http->FindSingleNode("following::*[normalize-space(.)!=''][1]", $root)));
            $dep = $this->getInfoAir($this->http->FindSingleNode("following-sibling::text()[normalize-space(.)!=''][1]", $root));

            if (count($dep) === 2) {
                $seg['DepName'] = $dep['Name'];
                $seg['DepCode'] = $dep['Code'];
            }
            $arr = $this->getInfoAir($this->http->FindSingleNode("following-sibling::text()[normalize-space(.)!=''][2]", $root));

            if (count($arr) === 2) {
                $seg['ArrName'] = $arr['Name'];
                $seg['ArrCode'] = $arr['Code'];
            }
            $seg['ArrDate'] = strtotime($this->dateStringToEnglish($this->http->FindSingleNode("following-sibling::*[normalize-space(.)!=''][2]", $root)));
            $seg['Cabin'] = $this->http->FindSingleNode("preceding-sibling::*[contains(., 'Class')][1]/following::text()[1]", $root, true, "#(Economy)#");
            $flightName = $this->http->FindSingleNode("preceding-sibling::text()[contains(., 'Flight code')][1]/following::text()[1]", $root);

            if (preg_match("#(\D{1,2})\s*(\d+)#", $flightName, $math)) {
                $seg['FlightNumber'] = $math[2];
                $seg['AirlineName'] = $math[1];
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getInfoAir($str)
    {
        if (preg_match("#(\w{3})\s+(.+)#", $str, $m)) {
            return ['Code' => $m[1], 'Name' => $m[2]];
        }

        return [];
    }

    private function t($s)
    {
        if (empty($this->lang) && empty(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
