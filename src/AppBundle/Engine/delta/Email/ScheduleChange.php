<?php

namespace AwardWallet\Engine\delta\Email;

class ScheduleChange extends \TAccountCheckerExtended
{
    public $mailFiles = "delta/it-1694238.eml, delta/it-1694302.eml, delta/it-1988384.eml, delta/it-1988435.eml, delta/it-6331464.eml";

    public function ParseEmail(\PlancakeEmailParser $parser)
    {
        $year = re('#\d{4}#i', $parser->getHeader('Date'));

        if (!$year) {
            return null;
        }
        $it = ["Kind" => "T", "Passengers" => [], "TripSegments" => []];
        $nodes = $this->http->FindNodes("//*[contains(normalize-space(text()), 'Delta Confirmation #')]/following-sibling::*[1]", null, "/^[A-Z\d]{6}$/");

        if (count($nodes) === 0 || empty($nodes[0])) {
            $nodes = $this->http->FindNodes("//*[contains(normalize-space(text()), 'Delta Confirmation #')]", null, "/\#\s*([A-Z\d]{6})$/");
        }
        $reclocs = array_values(array_filter($nodes, function ($s) {
            return isset($s);
        }));

        if (isset($reclocs[0])) {
            $it["RecordLocator"] = $reclocs[0];
        }
        $passengers = $this->http->FindSingleNode("(//b | //strong)[starts-with(., 'Dear')]", null, true, "/Dear (.+)/");

        if (strpos($passengers, ' and ') !== false) {
            $it["Passengers"] = explode(" and ", $passengers);
        } else {
            $it["Passengers"][] = $passengers;
        }
        $eDate = strtotime($parser->getHeader('date'));
        $rows = $this->http->XPath->query("//a[contains(., 'Seat')]/ancestor::td[preceding-sibling::td[contains(., 'Departs')]]/parent::tr");

        foreach ($rows as $row) {
            $segment = [];
            $info = $this->http->FindNodes("td[1]//tr[not(.//s) and not(.//tr) and normalize-space(.) != '']", $row);

            if (isset($info[0]) && preg_match("/^\w+\, (\w+ \d+)$/", $info[0], $date) && isset($info[1]) && preg_match("/^([\w\d\s]+) (\d+)$/", $info[1], $flight)) {
                $date = $date[1] . ', ' . $year;
                $segment["AirlineName"] = $flight[1];
                $segment["FlightNumber"] = $flight[2];
                $dep = $this->http->FindSingleNode("td[3]//tr[not(.//s) and not(.//tr) and contains(., 'Departs')]", $row);

                if ($dep && preg_match("/^Departs\s*(?<time>[\d\:]+ [ap]m)( \(\w+, (?<date>\w+ \d+)\))? (?<name>.+)$/", $dep, $m)) {
                    $segment["DepName"] = $m["name"];
                    $segment["DepCode"] = TRIP_CODE_UNKNOWN;
                    $segment["DepDate"] = $this->realTime($date . " " . $m["time"], $eDate);
                }
                $arr = $this->http->FindSingleNode("td[3]//tr[not(.//s) and not(.//tr) and contains(., 'Arrives')]", $row);

                if ($dep && preg_match("/^Arrives\s*(?<time>[\d\:]+ [ap]m)( \(\w+, (?<date>\w+ \d+)\))? (?<name>.+)$/", $arr, $m)) {
                    $segment["ArrName"] = $m["name"];
                    $segment["ArrCode"] = TRIP_CODE_UNKNOWN;

                    if (!empty($m["date"])) {
                        $date = $m["date"] . ', ' . $year;
                    }
                    $segment["ArrDate"] = $this->realTime($date . " " . $m["time"], $eDate);
                }
                $segment["Cabin"] = $this->http->FindSingleNode("td[5]", $row);
                $segment["Seats"] = $this->http->FindSingleNode("td[7]", $row, true, "/Seat (\d+[A-Z])/");
            }
            $it["TripSegments"][] = $segment;
        }

        return [$it];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->ParseEmail($parser);

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "ScheduleChange",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], "Schedule Changes That Impact Your Upcoming Trip") !== false
        || isset($headers['from']) && stripos($headers['from'], "DeltaMessenger@delta.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]delta\.com/", $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, "We have experienced a change in schedule that impacts your upcoming trip") !== false;
    }

    protected function realTime($time, $issue)
    {
        $time = str_replace("12:00N", "12:00PM", $time);
        $time = strtotime($time);

        if (!empty($issue) && $time < $issue) {
            $time = strtotime("+1 year", $time);
        }

        return $time;
    }
}
