<?php

namespace AwardWallet\Engine\airpanama\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class ETicketPlain extends \TAccountChecker
{
    public $mailFiles = "airpanama/it-9016676.eml";

    public $reFrom = "airpanama.com";

    public $reSubject = [
        "en" => "E-TICKET ITINERARY RECEIPT",
    ];

    public $reBody = 'AIRPANAMA';

    public $reBody2 = [
        "en" => "PASSENGER ITINERARY RECEIPT",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $date;

    public $lang = '';

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

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = \AwardWallet\Common\Parser\Util\EmailDateHelper::calculateOriginalDate($this, $parser);

        $itineraries = [];
        $this->text = $parser->getHTMLBody();

        //		foreach($this->reBody2 as $lang=>$re){
        //			if(strpos($this->text, $re) !== false){
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parsePlain(&$itineraries)
    {
        $text = strip_tags(preg_replace("#<br[^>]*>#", "\n", $this->text));
        $text = str_replace('&nbsp;', ' ', $text);

        $posFlightBegin = stripos($text, 'FROM/TO');

        if (empty($posFlightBegin)) {
            return [];
        }
        $posFlightEnd = stripos($text, 'ENDORSEMENTS/', $posFlightBegin);
        $textInfo = substr($text, 0, $posFlightBegin);

        if ($posFlightEnd) {
            $textFlight = substr($text, $posFlightBegin, $posFlightEnd - $posFlightBegin);
            $textPayment = substr($text, $posFlightEnd);
        } else {
            $textFlight = substr($text, $posFlightBegin);
            $textPayment = substr($text, $posFlightBegin);
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#BOOKING REF\./CODIGO DE RESERVA:\s*.*/([A-Z\d]{5,7})#", $textInfo);

        // TripNumber
        // Passengers
        $it['Passengers'] = [trim(str_replace('&nbsp;', ' ', $this->re("#NAME/NOMBRE:\s*(.+)#", $textInfo)))];

        // TicketNumbers
        $it['TicketNumbers'] = [trim($this->re("#TICKET NUMBER/NRO DE BOLETO\s+:\s*([\d\-]+)#", $textInfo))];

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->re("#GRAND TOTAL\s*:\s*[A-Z]{3}\s*([\d\.\, ]+)#", $textPayment));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->re("#AIR FARE/TARIFA\s*:\s*[A-Z]{3}\s*([\d\.\, ]+)#", $textPayment));

        // Currency
        $it['Currency'] = $this->re("#GRAND TOTAL\s*:\s*([A-Z]{3})\s*[\d\.\, ]+#", $textPayment);

        // Tax
        // Fees
        $tax = $this->re("#TAX/IMPUESTOS\s*:\s*[A-Z]{3}\s*(.+)#", $textPayment);
        $taxAr = explode("   ", $tax);
        $fees = $this->re("#FEE\s*:\s*[A-Z]{3}\s*(.+)#", $textPayment);
        $taxAr = array_filter(array_merge($taxAr, explode("   ", $fees)));

        foreach ($taxAr as $value) {
            if (preg_match("#^\s*([\d\.\, ]+)(.+)$#", $value, $m)) {
                $it['Fees'][] = [
                    "Name"   => trim($m[2]),
                    "Charge" => $this->amount($m[1]),
                ];
            }
        }

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->re("#ISSUE DATE/FECHA DE EMISION:\s*(.+)#", $textInfo)));

        if (!empty($it['ReservationDate'])) {
            $this->date = $it['ReservationDate'];
        }
        // NoItineraries
        // TripCategory

        if (preg_match_all("#\n\s*(?<depname>.+)\s+(?<airline>[A-Z\d]{2})\s+(?<flight>\d{1,5})\s+(?<class>[A-Z]{1,2})\s+(?<depdate>\d{1,2}[A-Z]{3,10}\s+\d{4}).*\n\s*(?<arrname>[A-Z\-\. ]+)#", $textFlight, $mat)) {
            foreach ($mat[0] as $i => $value) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $mat['flight'][$i];

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = trim($mat['depname'][$i]);

                // DepartureTerminal

                // DepDate
                $itsegment['DepDate'] = EmailDateHelper::parseDateRelative($this->normalizeDate($mat['depdate'][$i]), $this->date);

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = trim($mat['arrname'][$i]);

                // ArrivalTerminal

                // ArrDate
                $itsegment['ArrDate'] = MISSING_DATE;

                // AirlineName
                $itsegment['AirlineName'] = $mat['airline'][$i];

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                $itsegment['BookingClass'] = $mat['class'][$i];

                // PendingUpgradeTo
                // Seats
                // Duration
                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }
        }
        $itineraries[] = $it;
    }

    //	private function t($word){
    //		if(!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word]))
    //			return $word;
//
    //		return self::$dictionary[$this->lang][$word];
    //	}

    private function normalizeDate($str)
    {
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\s\d]+)\s+(\d{2})(\d{2})$#", //19SEP 1900
        ];
        $out = [
            "$3:$4, $1 $2",
        ];
        $str = preg_replace($in, $out, $str);

//
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }
}
