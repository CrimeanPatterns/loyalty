<?php

namespace AwardWallet\Engine\edreams\Email;

class Request extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "edreams/it-4956441.eml";

    public $reBody = [
        'it' => ['Avrai bisogno di QUESTI Codici', 'Come da sua mail abbiamo provveduto'],
    ];
    public $reSubject = [
        'Your request [',
    ];
    public $lang = 'it';
    public $pdf;
    public static $dict = [
        'it' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail($parser);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "Request",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $this->AssignLang($body);

        return stripos($body, $this->reBody[$this->lang][0]) !== false && stripos($body, $this->reBody[$this->lang][1]) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from'])) {
            if (isset($this->reSubject)) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "edreams.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail($parser)
    {
        $text = $parser->getPlainBody();

        if (!$text) {
            $text = text($parser->getHTMLBody());
        }
        $text = substr($text, strpos($text, $this->reBody[$this->lang][0]));
        $text = substr($text, 0, strpos($text, $this->reBody[$this->lang][1]));

        $its = [];
        $recLocs = array_unique($this->http->FindNodes("//a[.=\"Visualizza i dettagli dell'itinerario\"]/ancestor-or-self::div[1]/preceding-sibling::text()[1]"));

        foreach ($recLocs as $recLoc) {
            $flightsToLoc = $this->http->FindNodes("//a[.=\"Visualizza i dettagli dell'itinerario\"]/ancestor-or-self::div[1]/preceding-sibling::text()[position()=1 and contains(.,'{$recLoc}')]/preceding::text()[2]");
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $recLoc;

            if (preg_match("#{$recLoc}\s+EDREAMS\s+(.+?)\s+ABOUT#", $text, $m)) {
                $it['Passengers'] = array_unique(explode("\n", $m[1]));
            }

            foreach ($flightsToLoc as $flight) {
                if (preg_match("#^.+\(([A-Z]{3})\).+\(([A-Z]{3})\).+$#", $flight, $fl)) {
                    $xpath = "//text()[contains(.,'duration')]/ancestor::div[2]";
                    $nodes = $this->http->XPath->query($xpath);

                    if ($nodes->length == 0) {
                        $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
                    }

                    foreach ($nodes as $i => $root) {
                        $nodeFly = $this->http->FindSingleNode(".", $root);

                        if ($nodeFly !== null && preg_match("#.+?{$fl[1]}.+?{$fl[2]}#", $nodeFly)) {
                            $seg = [];

                            if (preg_match("#\|\s*(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)\s*(?<Status>[Na-z\s]+)\s+(?<dateFly>.+?)\s*\|\s*duration\s*(?<Duration>\d+\:\d+)\s*Dep\:(?<depTime>\d+\:\d+\s*[AP]M)\s*(?<depCity>.+?)\s*\|\s*(?<depAirport>.+?)\s*(?<DepCode>[A-Z]{3})\s*(?:\|\s*(?<DepartureTerminal>.*?Terminal.*?))?\s*Arr\:(?<arrTime>\d+\:\d+\s*[AP]M)\s*(?<arrCity>.+?)\s*\|\s*(?<arrAirport>.+?)\s*(?<ArrCode>[A-Z]{3})\s*(?:\|\s*(?<ArrivalTerminal>.*?Terminal.*?))?$#", $nodeFly, $val)) {
                                $seg['AirlineName'] = $val['AirlineName'];
                                $seg['FlightNumber'] = $val['FlightNumber'];
                                $seg['DepDate'] = strtotime($val['dateFly'] . ' ' . $val['depTime']);
                                $seg['ArrDate'] = strtotime($val['dateFly'] . ' ' . $val['arrTime']);
                                $seg['DepName'] = $val['depCity'] . '-' . $val['depAirport'];
                                $seg['ArrName'] = $val['arrCity'] . '-' . $val['arrAirport'];
                                $seg['Duration'] = $val['Duration'];
                                $seg['DepCode'] = $val['DepCode'];
                                $seg['ArrCode'] = $val['ArrCode'];

                                if (isset($val['DepartureTerminal'])) {
                                    $seg['DepartureTerminal'] = $val['DepartureTerminal'];
                                }

                                if (isset($val['ArrivalTerminal'])) {
                                    $seg['ArrivalTerminal'] = $val['ArrivalTerminal'];
                                }
                                $it['Status'] = $val['Status'];
                                $seg['Cabin'] = $this->http->FindSingleNode("//*[contains(text(),'" . $seg['AirlineName'] . " " . $seg['FlightNumber'] . "') or contains(text(),'" . $seg['AirlineName'] . $seg['FlightNumber'] . "')]/ancestor::ul[1]//li[contains(.,'Fare type')]/strong[1]");
                                $seg['Meal'] = $this->http->FindSingleNode("//*[contains(text(),'" . $seg['AirlineName'] . " " . $seg['FlightNumber'] . "') or contains(text(),'" . $seg['AirlineName'] . $seg['FlightNumber'] . "')]/ancestor::ul[1]//li[contains(.,'Meal')]/strong[1]");
                                $seg['Aircraft'] = $this->http->FindSingleNode("//*[contains(text(),'" . $seg['AirlineName'] . " " . $seg['FlightNumber'] . "') or contains(text(),'" . $seg['AirlineName'] . $seg['FlightNumber'] . "')]/ancestor::ul[1]//li[contains(.,'Aircraft')]/strong[1]");
                                $seg = array_filter($seg);
                            }
                            $it['TripSegments'][] = $seg;
                        }
                    }
                } else {
                    return null;
                }
            }
            $its[] = $it;
        }

        return $its;
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
