<?php

namespace AwardWallet\Engine\airasia\Email;

class FlightReminder2017 extends \TAccountChecker
{
    use \DateTimeTools;
    public static $reBody = [
        'en' => ["Don't miss your flight"],
    ];
    public $dict = [
        'en' => [
        ],
    ];
    public $lang = '';
    public $reSubject = [
        "en" => ['AirAsia Flight Reminder to'],
    ];
    public $reProvider = 'airasia';
    public $mailFiles = "airasia/it-7912560.eml, airasia/it-7917500.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach (self::$reBody as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    $this->lang = $lang;
                }
            }
        }
        $its = [];
        $its[] = $this->parseEmail();

        return [
            'emailType'  => 'FlightReminder2017_' . $this->lang,
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $subject) {
                if (stripos($headers["subject"], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach (self::$reBody as $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$reBody);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$reBody);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function t($str)
    {
        if (!isset($this->dict) || !isset($this->dict[$this->lang][$str])) {
            return $str;
        }

        return $this->dict[$this->lang][$str];
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t("Booking number") . "')]", null, true, '#:\s+([A-Z\d]{5,6})#');
        $it['Passengers'][] = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t("Hello") . "')]", null, true, '#' . $this->t("Hello") . ',\s*(.+)!#');

        $it['ReservationDate'] = strtotime($this->http->FindSingleNode("//*[contains(text(),'" . $this->t("Booking number") . "')]/following::text()[string-length(normalize-space(.))>4][1]", null, true, '/(\d+\s*\w+\s*\d+)/'));

        $xpath = "//*[contains(text(),'Depart')]/ancestor::table[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            $seg = [];
            $flight = $this->http->FindSingleNode("(.//tr[2]/td)[1]", $root, true, '#([A-Z\d]{2}\d{1,5})#');

            if (preg_match("#([A-Z\d]{2})(\d{1,5})#", $flight, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $depart = $this->http->FindSingleNode("(.//tr[2]/td)[2]", $root);
            //Samui (Lipa Noi Pier) (1AA)  -> Samui (Lipa Noi Pier) (USM)
            $depart = str_replace('(1AA)', '(USM)', $depart);

            if (preg_match("#(.*?)(?:-\s*([^(]+))?\(([A-Z]{3})\)\s*\w+\s+(\d{1,2}\s+\w+\s+\d{4},\s*\d{1,2}:\d{2}(?:\s+[AP]M)?)#", $depart, $m)) {
                $seg['DepName'] = trim($m[1]);

                if (!empty($m[2])) {
                    if (preg_match("#[\dA-Z]\s*$#", $m[2])) {
                        if (strlen(trim($m[2])) === 2 && strpos(trim($m[2]), 'T') === 0) {
                            $seg['DepartureTerminal'] = substr(trim($m[2]), 1, 1);
                        } else {
                            $seg['DepartureTerminal'] = trim($m[2]);
                        }
                    } else {
                        $seg['DepName'] .= ' - ' . trim($m[2]);
                    }
                }
                $seg['DepCode'] = $m[3];
                $seg['DepDate'] = strtotime($m[4]);

                if (empty($seg['DepName'])) {
                    unset($seg['DepName']);
                }
            }
            unset($depart);
            $arrive = $this->http->FindSingleNode("(.//tr[2]/td)[3]", $root);
            //Samui (Lipa Noi Pier) (1AA)  -> Samui (Lipa Noi Pier) (USM)
            $arrive = str_replace('(1AA)', '(USM)', $arrive);

            if (preg_match("#(.*?)(?:-\s*([^(]+))?\(([A-Z]{3})\)\s*\w+\s+(\d{1,2}\s+\w+\s+\d{4},\s*\d{1,2}:\d{2}(?:\s+[AP]M)?)#", $arrive, $m)) {
                $seg['ArrName'] = trim($m[1]);

                if (!empty($m[2])) {
                    if (preg_match("#[\dA-Z]\s*$#", $m[2])) {
                        if (strlen(trim($m[2])) === 2 && strpos(trim($m[2]), 'T') === 0) {
                            $seg['ArrivalTerminal'] = substr(trim($m[2]), 1, 1);
                        } else {
                            $seg['ArrivalTerminal'] = trim($m[2]);
                        }
                    } else {
                        $seg['ArrName'] .= ' - ' . trim($m[2]);
                    }
                }
                $seg['ArrCode'] = $m[3];
                $seg['ArrDate'] = strtotime($m[4]);

                if (empty($seg['ArrName'])) {
                    unset($seg['ArrName']);
                }
            }
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }
}
