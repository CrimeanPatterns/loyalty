<?php

namespace AwardWallet\Engine\delta\Email;

class NonRevenue extends \TAccountChecker
{
    public $mailFiles = "delta/it-38156095.eml";

    protected $cells = [
        0 => ["/^[A-Z\d]{2}$/", "AirlineName"],
        1 => ["/^\d+$/", "FlightNumber"],
        2 => ["/^[A-Z]{3}$/", "DepCode"],
        3 => ["/^[A-Z]{3}$/", "ArrCode"],
        4 => ["/^(\d+)(\w{3}) (\d+\:\d+[AP]M)$/", "DepDate"],
        5 => ["/^(\d+)(\w{3}) (\d+\:\d+[AP]M)$/", "ArrDate"],
    ];

    public function ParseEmail($emailDate)
    {
        $it = ["Kind" => "T"];
        $it["RecordLocator"] = $this->http->FindSingleNode("//text()[contains(., 'Record Locator')]", null, true, "/Record Locator\s*:\s*([A-Z\d]{6})/");
        $it["TripSegments"] = [];
        $rows = $this->http->XPath->query("//table[.//tr[*[contains(., 'Carrier') and not(.//tr)] and *[contains(., 'Flight') and not(.//tr)]] and not(.//table)]//tr");

        foreach ($rows as $row) {
            $tds = $this->http->FindNodes("td", $row);
            $segment = [];

            foreach ($this->cells as $i => $cell) {
                if (!isset($tds[$i]) || !preg_match($cell[0], $tds[$i], $m)) {
                    $segment = false;

                    break;
                }
                $segment[$cell[1]] = $m[0];

                if (stripos($cell[1], "Date") == 3) {
                    $year = date("Y", $emailDate);
                    $date = null;

                    for ($i = 0; $i < 2; $i++) {
                        if (abs(strtotime($m[3] . " " . $m[1] . " " . $m[2] . " " . ($year + $i)) - $emailDate) < 60 * 60 * 24 * 30 * 6) {
                            $date = strtotime($m[3] . " " . $m[1] . " " . $m[2] . " " . ($year + $i));

                            break;
                        }

                        if (abs(strtotime($m[3] . " " . $m[1] . " " . $m[2] . " " . ($year - $i)) - $emailDate) < 60 * 60 * 24 * 30 * 6) {
                            $date = strtotime($m[3] . " " . $m[1] . " " . $m[2] . " " . ($year - $i));

                            break;
                        }
                    }
                    $segment[$cell[1]] = $date;
                }
            }

            if ($segment !== false) {
                $it["TripSegments"][] = $segment;
            }
        }
        $it["Passengers"] = $this->http->FindNodes("//thead[contains(., '#') and contains(., 'Name')]/following-sibling::tbody/tr/td[2]");

        return [$it];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $emailDate = strtotime($parser->getHeader('date'));
        $its = $this->ParseEmail($emailDate);

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "NonRevenue",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Non Revenue Listing') !== false
        || isset($headers['from']) && stripos($headers['from'], 'no-reply@delta.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($this->http->Response["body"], 'Non Revenue Listing') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]delta\.com/", $from) > 0;
    }
}
