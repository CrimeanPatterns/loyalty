<?php

namespace AwardWallet\Engine\egyptair\Email;

class FlightStatusNotification extends \TAccountChecker
{
    public $mailFiles = "egyptair/it-10290719.eml, egyptair/it-10290722.eml, egyptair/it-8565241.eml";

    public $reFrom = "Flightnotification@egyptair.com";
    public $reSubject = [
        'EGYPT AIR - Flight Status Notification',
    ];
    public $reBody = 'EGYPTAIR';
    public $reBody2 = [
        "en"=> "Thank you for subscribing ",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $body = strip_tags(str_replace(['<br>', '<br/>'], ["\n", "\n"], $body));
        $its = $this->parseHtml($body);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];

        return $result;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
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
        return count(self::$dict);
    }

    private function parseHtml($body)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $seg = [];

        if (preg_match("#Dear\s+([^,]+),#", $body, $m)) {
            $it['Passengers'][] = $m[1];
        }

        if (preg_match("#Your flight number is:\s*([A-Z\d]{2})\s*(\d{1,5})\b#", $body, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
        }

        if (preg_match("#\n\s*From\s+(.+)#", $body, $m)) {
            $seg['DepName'] = trim($m[1]);
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        if (preg_match("#\n\s*To\s+(.+)#", $body, $m)) {
            $seg['ArrName'] = trim($m[1]);
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        }

        if (preg_match("#(?:depart|departed) on (.+)\.?#", $body, $m)) {
            $seg['DepDate'] = strtotime($m[1]);
        }

        if (strpos($body, 'depart on') === false && strpos($body, 'departed on') === false) {
            $seg['DepDate'] = MISSING_DATE;
        }

        if (preg_match("#(?:arrive|arrived) on (.+)\.?#", $body, $m)) {
            $seg['ArrDate'] = strtotime($m[1]);
        }

        if (strpos($body, 'arrive on') === false && strpos($body, 'arrived on') === false) {
            $seg['ArrDate'] = MISSING_DATE;
        }
        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s+(\d{1.2}:\d{1,2}):\d+\s*([AP]M)\s*$#", //2017-08-27 17:25,
        ];
        $out = [
            "$2.$1.$1 $4$5",
        ];
        $str = preg_replace($in, $out, $str);

        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return $str;
    }
}
