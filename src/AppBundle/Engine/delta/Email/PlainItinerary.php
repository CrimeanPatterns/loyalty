<?php

namespace AwardWallet\Engine\delta\Email;

class PlainItinerary extends \TAccountChecker
{
    public $mailFiles = "";

    // delta reservation with <pre> blocks with flight info
    // subject: 'DL Itinerary' or 'Delta.com Itinerary from:'
    // these two types are slightly different, but generally look the same
    // it-5.eml

    protected $markers = [];

    protected $type = null;

    protected $columns = [
        7 => [
            "Date"   => 1,
            "Flight" => 2,
            "Name"   => 3,
            "Code"   => 4,
            "Time"   => 5,
            "Cabin"  => 6,
        ],
        9 => [
            "Date"         => 1,
            "Flight"       => 2,
            "Status"       => 3,
            "BookingClass" => 4,
            "Name"         => 5,
            "Time"         => 6,
            "Cabin"        => 8,
        ],
    ];

    public function ParseEmail(\PlancakeEmailParser $parser)
    {
        $it = ["Kind" => "T"];

        $emailDate = strtotime($parser->getHeader('date'));

        if (!$emailDate) {
            return null;
        }
        $emailYear = date('Y', $emailDate);

        $nodes = $this->http->XPath->query("//pre[contains(., 'Seat(s):')]");

        if ($nodes->length > 0) {
            $block = $nodes->item(0)->nodeValue;
            $parts = explode("\n\n", $block);
            $seats = [];

            foreach ($parts as $part) {
                $lines = array_values(array_filter(array_map("CleanXMLValue", explode("\n", $part)), "strlen"));

                if (count($lines) > 0) {
                    $it["Passengers"][] = $lines[0];
                    $idx = null;

                    foreach ($lines as $line) {
                        if (strpos($line, 'Seat') === 0) {
                            $idx = 0;
                        }

                        if (isset($idx)) {
                            if (preg_match("/\d+ \- (\d+[A-Z])$/", $line, $m)) {
                                $seats[$idx][] = $m[1];
                            } else {
                                $seats[$idx][] = "";
                            }
                            $idx++;
                        }
                    }
                }
            }
        }
        $nodes = $this->http->XPath->query('//pre[contains(., "DELTA CONFIRMATION #") or contains(., "Reference #")]');

        if ($nodes->length > 0) {
            $block = $nodes->item(0)->nodeValue;
            $lines = array_filter(explode("\n", $block), 'strlen');
            $i = 0;

            foreach ($lines as $idx => $line) {
                if (preg_match("/^[\-\s]+$/", $line)) {
                    $this->makeColumns($line);
                }

                if (!isset($it["RecordLocator"]) && preg_match("/ \#:\s*([A-Z\d]{6})/", $line, $m)) {
                    $it["RecordLocator"] = $m[1];
                }

                if (!isset($this->type)) {
                    continue;
                }

                if ($this->type == 9 && $this->getColumn($line, 'Status') == 'RQ') {
                    continue;
                }
                $depName = $this->getColumn($line, "Name");

                if (strpos($depName, "LV ") === 0) {
                    $segment = [];
                    $segment["DepName"] = substr($depName, 3);

                    if ($this->type == 7) {
                        $segment["DepCode"] = trim($this->getColumn($line, "Code"), "()");
                    }
                    $segment["Cabin"] = $this->getColumn($line, "Cabin");
                    $flight = trim($this->getColumn($line, "Flight"), "*");

                    if (isset($lines[$idx + 1])) {
                        $flight .= trim($this->getColumn($lines[$idx + 1], "Flight"), "*");
                    }

                    if (preg_match("/^(.*\D)(\d+)$/", $flight, $m)) {
                        $segment["AirlineName"] = trim($m[1]);
                        $segment["FlightNumber"] = $m[2];
                    }
                    $date = $this->getColumn($line, "Date");
                    $time = trim($this->getColumn($line, "Time"), '#');
                    $time = substr($time, 0, -3) . ":" . substr($time, -3, 2) . " " . substr($time, -1, 1) . "M";
                    $segment["DepDate"] = strtotime($time . ' ' . $date . ' ' . $emailYear);
                    $segment["BookingClass"] = $this->getColumn($line, "BookingClass");
                    $idx++;

                    if (isset($lines[$idx])) {
                        $line = $lines[$idx];
                        $name = $this->getColumn($line, 'Name');
                        $line = CleanXMLValue($line);

                        if (CleanXMLValue($line) == $this->getColumn($line, 'Name')
                         || strpos($name, 'AR ') !== 0 && strpos($line, $name) === 0) {
                            // part of location in the next string
                            $segment["DepName"] .= " " . $name;
                            $idx++;
                        }
                    }

                    if (isset($lines[$idx])) {
                        $line = $lines[$idx];
                        $arrName = $this->getColumn($line, 'Name');

                        if (strpos($arrName, "AR ") === 0) {
                            $segment["ArrName"] = substr($arrName, 3);

                            if ($this->type == 7) {
                                $segment["ArrCode"] = trim($this->getColumn($line, "Code"), '()');
                            }

                            if ($newDate = $this->getColumn($line, "Date")) {
                                $date = $newDate;
                            }
                            $time = trim($this->getColumn($line, "Time"), '#');
                            $time = substr($time, 0, -3) . ":" . substr($time, -3, 2) . " " . substr($time, -1, 1) . "M";
                            $segment["ArrDate"] = strtotime($time . ' ' . $date . ' ' . $emailYear);
                        }

                        if (isset($lines[$idx + 1]) && (CleanXMLValue($lines[$idx + 1]) == $this->getColumn($lines[$idx + 1], 'Name'))) {
                            $segment["ArrName"] .= " " . CleanXMLValue($lines[$idx + 1]);
                        }
                    }

                    correctDates($segment['DepDate'], $segment['ArrDate'], $emailDate);

                    if ($this->type == 9) {
                        $segment["DepCode"] = $segment["ArrCode"] = TRIP_CODE_UNKNOWN;
                    }

                    if (isset($seats[$i])) {
                        $seats[$i] = array_filter($seats[$i], "strlen");

                        if (count($seats[$i]) > 0) {
                            $segment["Seats"] = implode(",", $seats[$i]);
                        }
                    }
                    $it["TripSegments"][$i] = $segment;
                    $i++;
                }
            }
        }

        return [$it];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return false;
        //		$its = $this->ParseEmail($parser);
//		return array(
//			'parsedData' => ['Itineraries' => $its],
//			'emailType' => 'PlainItinerary',
//		);
    }

    public function explodePre($block)
    {
        $result = explode("\n", $block);
        $result = array_map('trim', $result);
        $result = array_flip($result);
        unset($result[""]);
        $result = array_flip($result);

        return $result;
    }

    //	private function realTime($time) {
    //		if ($time < strtotime('- 4 months'))
    //			$time = strtotime("+ 1 year", $time);
    //		return $time;
    //	}

    public function detectEmailByHeaders(array $headers)
    {
        // Parser toggled off as it is covered by emailYourDeltaAirLinesConfirmationChecker.php and emailDLItineraryChecker.php
        return false;
        //		return isset($headers['subject']) && (stripos($headers['subject'], 'DL Itinerary') !== false || stripos($headers['subject'], "Delta.com Itinerary from:") !== false)
//			|| isset($headers['from']) && stripos($headers['from'], 'deltaitinerary@delta.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        // Parser toggled off as it is covered by emailYourDeltaAirLinesConfirmationChecker.php and emailDLItineraryChecker.php
        return false;
        //		return preg_match("/[@\.]delta\.com/", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Parser toggled off as it is covered by emailYourDeltaAirLinesConfirmationChecker.php and emailDLItineraryChecker.php
        return false;
        //		return $this->http->XPath->query('//pre[contains(., "DELTA CONFIRMATION #") or contains(., "Reference  #")]')->length > 0;
    }

    public static function getEmailTypesCount()
    {
        // Parser toggled off as it is covered by emailYourDeltaAirLinesConfirmationChecker.php and emailDLItineraryChecker.php
        return 0;
        //		return 2;
    }

    protected function makeColumns($line)
    {
        $markers = explode(" ", trim($line));
        $this->type = count($markers);
        $this->markers[] = 0;

        foreach ($markers as $idx => $marker) {
            $this->markers[$idx + 1] = $this->markers[$idx] + strlen($marker) + 1;
        }
    }

    protected function getColumn($line, $column)
    {
        if (!isset($this->type) || !isset($this->columns[$this->type][$column])) {
            return null;
        }
        $column = $this->columns[$this->type][$column];

        return CleanXMLValue(substr($line, $this->markers[$column], $this->markers[$column + 1] - $this->markers[$column] - 1));
    }
}
