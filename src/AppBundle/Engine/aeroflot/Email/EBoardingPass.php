<?php

namespace AwardWallet\Engine\aeroflot\Email;

class EBoardingPass extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "aeroflot/it-4952689.eml, aeroflot/it-4976346.eml, aeroflot/it-4976366.eml, aeroflot/it-5023780.eml";

    public $reBody = [
        'en' => ['Please review boarding pass information', 'From'],
    ];
    public $reSubject = [
        'Aeroflot\s+Electronic\s+Boarding\s+Pass\s+[A-Z]{3}\s*-\s*[A-Z]{3}\s*\([A-Z\d]+\)',
    ];
    public $lang = 'en';
    public $subjectEmail;
    public $dateEmail;
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
//        $body = $this->http->Response['body'];
        $body = $parser->getPlainBody();

        if (!$body) {
            $body = text($parser->getHTMLBody());
        }
        $this->AssignLang($body);
        $this->subjectEmail = $parser->getSubject();
        $this->dateEmail = $parser->getDate();
        $its = $this->parseEmail($body);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "EBoardingPass",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        //		$body = $parser->getHTMLBody();
        $body = $parser->getPlainBody();

        if (!$body) {
            $body = text($parser->getHTMLBody());
        }
        $this->AssignLang($body);

        return stripos($body, $this->reBody[$this->lang][0]) !== false && stripos($body, $this->reBody[$this->lang][1]) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match("#{$reSubject}#", $headers["subject"])) {
                    //				if (stripos($headers["subject"], $reSubject) !== false ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "aeroflot.ru") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail($text)
    {
        $year = date('Y', strtotime($this->dateEmail));
        $it = ['Kind' => 'T', 'TripSegments' => []];

        if (preg_match("#PNR\s*:\s+(\w+)\s+#", $text, $m)) {
            $it['RecordLocator'] = $m[1];
        }

        if (preg_match("#" . $this->reBody[$this->lang][0] . "\s*\.\s+(.+?)\s+PNR#", $text, $m)) {
            $it['Passengers'][] = $m[1];
        }
        $seg = [];

        if (preg_match("#(?:Class|Flight)\s*:\s+(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)\s+(?<date>\d+\s*\w+)\s+(?:Class\s*:\s+(?<BookingClass>[A-Z]{1,2})\s+)?From\s*:\s+(?<DepName>.+?)\s+at\s+(?<depTime>\d+:\d+)\s+To\s*:\s+(?<ArrName>.+?)\s+at\s+(?<arrTime>\d+:\d+)\s+Gate.+?Seat\s*:\s+(?<Seats>\d+\w)(?:\s+Frequent Flyer Number:\s+(?<FF>.+))?(?:$|\s)#", $text, $m)) {
            $seg['AirlineName'] = $m['AirlineName'];
            $seg['FlightNumber'] = $m['FlightNumber'];
            $seg['DepName'] = $m['DepName'];
            $seg['ArrName'] = $m['ArrName'];
            $seg['DepDate'] = strtotime($m['date'] . $year . ' ' . $m['depTime']);

            if ($seg['DepDate'] < strtotime($this->dateEmail)) {
                $year++;
                $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
            }
            $seg['ArrDate'] = strtotime($m['date'] . $year . ' ' . $m['arrTime']);
            $seg['Seats'] = $m['Seats'];

            if (isset($m['BookingClass']) && !empty($m['BookingClass'])) {
                $seg['BookingClass'] = $m['BookingClass'];
            }

            if (isset($m['FF']) && !empty($m['FF'])) {
                $it['AccountNumbers'][] = $m['FF'];
            }
        }

        if (preg_match("#Aeroflot\s+Electronic\s+Boarding\s+Pass\s+([A-Z]{3})\s*-\s*([A-Z]{3})\s*\([A-Z\d]+\)#", $this->subjectEmail, $m)) {
            $seg['DepCode'] = $m[1];
            $seg['ArrCode'] = $m[2];
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

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
    }
}
