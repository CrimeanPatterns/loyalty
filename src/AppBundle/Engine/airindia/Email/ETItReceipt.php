<?php

namespace AwardWallet\Engine\airindia\Email;

class ETItReceipt extends \TAccountChecker
{
    public $mailFiles = "airindia/it-5483132.eml, airindia/it-5574734.eml";
    public $reBody = "airindia";
    public $reBody2 = [
        "en" => ["eSuperSaverEconomy", "TRAVEL INFORMATION"],
    ];
    public $reSubject = [
        'eSuperSaverEconomy - E-Ticket Itinerary Receipt',
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'en' => [
        ],
    ];
    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs) && count($pdfs) > 0) {
            $text = '';
//            $this->pdf = clone $this->http;
//            $html='';
            foreach ($pdfs as $pdf) {
                if (($text .= \PDF::convertTotext($parser->getAttachmentBody($pdf))) !== null) {
                } else {
                    return null;
                }
//                if(($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null){

//                } else{
//                    return null;
//                }
            }
            //			$NBSP = chr(194) . chr(160);
//			$html = str_replace($NBSP, ' ', html_entity_decode($html));
//			$this->pdf->SetBody($html);
        } else {
            return null;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($text, $re[0]) !== false && strpos($text, $re[1]) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->dateYear = date('Y', strtotime($parser->getDate()));

        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->getParam($text, '#' . $this->t('Booking Reference Number') . '\s*:\s*([A-Z\d]+)#');
        $this->result['Passengers'][] = $this->getParam($text, '#' . $this->t('Passenger Name') . '\s*:\s*(.*?)\s*Issuing#');
        $this->result['ReservationDate'] = strtotime($this->getParam($text, '#' . $this->t('Issue Date') . '\s*:\s+(\d+\s+\S{3}\s+\d+,\s*\d+:\d+)#'));
        $this->result['TripSegments'] = $this->parseSegments($this->findСutSection($text, $this->t('TRAVEL INFORMATION'), $this->t('OTHER DETAILS')));

        $this->result['TotalCharge'] = $this->getParam($text, '#' . $this->t('Total Fare') . '\s*:?\s*(\d+)#');
        $this->result['BaseFare'] = $this->getParam($text, '#' . $this->t('Base Fare') . '\s*:?\s*(\d+)#');
        $this->result['Tax'] = $this->getParam($text, '#Service Tax \[JN\]\s*:?\s*(\d+)#');
        //		$this->result['TotalCharge'] = $this->pdf->FindSingleNode("//text()[normalize-space(.)='Total Fare']/following::p[1]");
        //		$this->result['BaseFare'] = $this->pdf->FindSingleNode("//text()[normalize-space(.)='Base Fare']/following::p[1]");
        //		$this->result['Tax'] = $this->pdf->FindSingleNode("//text()[normalize-space(.)='Service Tax [JN]']/following::p[1]");
        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertTotext($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re[0]) !== false && strpos($text, $re[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'esupersaver@airindia.in') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parseSegments($text)
    {
        $segment = [];
        $regStr = "(?<AirlineName>[A-Z\d]{2})[-\s]+(?<FlightNumber>\d+)\s+\S{3},(?<dayFly>\d+)\s*(?<monthFly>\S+?)\s*(?<yearFly>\d+)";
        $regStr .= "\s+(?<DepName>.+?)\s+\(\s*(?<timeDep>\d+:\d+)\s*\)\s+(?<ArrName>.+?)\s+\(\s*(?<timeArr>\d+:\d+)\s*\)";
        $regStr .= "\s+(?<Cabin>.+?)\s*-\s*(?<Class>[A-Z]{1,2})";

        if (preg_match_all("#{$regStr}#", $text, $matches)) {
            foreach ($matches[0] as $i => $m) {
                $seg = [];
                $seg['AirlineName'] = $matches['AirlineName'][$i];
                $seg['FlightNumber'] = $matches['FlightNumber'][$i];
                $seg['DepName'] = $matches['DepName'][$i];
                $seg['ArrName'] = $matches['ArrName'][$i];

                $date = strtotime($matches['dayFly'][$i] . ' ' . $matches['monthFly'][$i] . ' ' . $matches['yearFly'][$i]);
                $seg['DepDate'] = strtotime($matches['timeDep'][$i], $date);
                $seg['ArrDate'] = strtotime($matches['timeArr'][$i], $date);
                $seg['Cabin'] = $matches['Cabin'][$i];
                $seg['BookingClass'] = $matches['Class'][$i];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $segment[] = $seg;
            }
        }

        return $segment;
    }

    protected function getParam($subject, $pattern = null)
    {
        if (preg_match($pattern, $subject, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
