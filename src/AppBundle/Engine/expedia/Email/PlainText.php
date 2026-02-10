<?php

namespace AwardWallet\Engine\expedia\Email;

class PlainText extends \TAccountChecker
{
    public $mailFiles = "expedia/it-7890582.eml, expedia/it-12037673.eml, expedia/it-11797290.eml";

    private $lang = '';

    private $detects = [
        'en' => ['Your reservation is booked and confirmed'],
    ];

    private $year = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $textBody = $parser->getPlainBody();

        if (preg_match('/<(?:span|div|br|p|a)\b[^>\n]*\/?>/i', $textBody)) { // it-11797290.eml
            $this->http->SetEmailBody($textBody);
            $textNodes = $this->http->FindNodes('//text()[normalize-space(.)]');
            $textBody = implode("\n", $textNodes);
        }

        $this->lang = 'en';
        $this->year = date('Y', strtotime($parser->getDate()));

        $result = [
            'parsedData' => [
                'Itineraries' => $this->parseEmail($textBody),
            ],
            'emailType' => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];

        $total = $this->findCutSection($textBody, 'Price summary', ['Additional information']);

        if (preg_match('/Total\s*.*?\s*(\d[,.\d ]*\b)\*?[>\s]+All prices are quoted in \*?([A-Z]{3})/', $total, $m)) {
            $result['TotalCharge']['Amount'] = $this->normalizePrice($m[1]);
            $result['TotalCharge']['Currency'] = $m[2];
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (
            stripos($body, 'Expedia travel Confirmation') === false
            && stripos($body, 'Expedia, Inc') === false
            && stripos($body, 'update on Expedia.com') === false
        ) {
            return false;
        }

        foreach ($this->detects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($body, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Expedia.com') !== false
            || stripos($from, '@expediamail.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Expedia travel confirmation') !== false;
    }

    private function parseEmail($text)
    {
        $its = [];

        $itsText = explode('Flight overview', $text);
        array_shift($itsText);

        $rls = [];
        $ticketPass = [];

        foreach ($itsText as $itText) {
            $info = $this->findCutSection($itText, 'Confirmation', ['Departure', 'Return']);

            if (preg_match('/Confirmation[>\s]+([A-Z\d]{5,7}\b)/', $info, $m)) {
                $rls[$m[1]] = $itText;
                preg_match_all('/(\d[-\d\s]{7,}\d)\s+\(([^)(]+)\)/', $info, $math);

                if (!empty($math[1]) && !empty($math[2])) {
                    $ticketPass[$m[1]]['TicketNumbers'] = $math[1];
                    $ticketPass[$m[1]]['Passengers'] = $math[2];
                }
            }
        }

        foreach ($rls as $rl => $sText) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T'];

            $it['RecordLocator'] = $rl;

            if (!empty($ticketPass[$rl])) {
                $it['TicketNumbers'] = $ticketPass[$rl]['TicketNumbers'] ?? [];
                $it['Passengers'] = $ticketPass[$rl]['Passengers'] ?? [];
            }

            // TripSegments
            $it['TripSegments'] = [];

            $departureMatches = [];
            $arrivalMatches = [];

            $re1 = '/'
                . '(?:(?:Departure|Return)[>\s]+\w{2,},\s+(\w{3,} \d{1,2}|\d{1,2} \w{3,})|.+stop.+)'
                . '[>\s]*?.*?'
                . '[>\s]+(.+)\s+(\d+)(?:\s+operated by (.+\b))?'
                . '[>\s]+(.+?)\s+\(([A-Z]{3})\)'
                . '[>\s]+(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)(?:\s+(\+\d [Dd]ay))?'
                . '(?:[>\s]+Terminal:\s*([A-Z\d]{1,4}))?\s+.*?'
                . '/';
            preg_match_all($re1, $sText, $departureMatches, PREG_SET_ORDER);

            $re2 = '/'
                . '[>\s]+(.+?)\s+\(([A-Z]{3})\)'
                . '[>\s]*?.*?'
                . '[>\s]+(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)(?:\s+(\+\d [Dd]ay))?'
                . '(?:[>\s]+Terminal:\s*([A-Z\d]{1,4}))?'
                . '[>\s]*?.*?'
                . '[>\s]+Cabin:\s+(.+?)\s*\(([A-Z]{1,2})\)'
                . '[>\s]+(\d.+\b)'
                . '/';
            preg_match_all($re2, $sText, $arrivalMatches, PREG_SET_ORDER);

            if (count($departureMatches) < 1 || count($departureMatches) !== count($arrivalMatches)) {
                return null;
            }

            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            foreach ($departureMatches as $key => $m) {
                $seg = [];

                if (!empty($m[1])) {
                    $date = $m[1];
                }
                $seg['AirlineName'] = $m[2];
                $seg['FlightNumber'] = $m[3];

                if (!empty($m[4])) {
                    $seg['Operator'] = $m[4];
                }
                $seg['DepName'] = $m[5];
                $seg['DepCode'] = $m[6];
                $depTime = $m[7];

                if (isset($date)) {
                    $seg['DepDate'] = $this->normalizeDate($date . ' ' . $depTime);
                }
                $plusDay = empty($m[8]) ? null : $m[8];

                if (!empty($plusDay)) {
                    $seg['DepDate'] = strtotime($plusDay, $seg['DepDate']);
                }

                if (!empty($m[9])) {
                    $seg['DepartureTerminal'] = $m[9];
                }

                $seg['ArrName'] = $arrivalMatches[$key][1];
                $seg['ArrCode'] = $arrivalMatches[$key][2];
                $arrTime = $arrivalMatches[$key][3];

                if (isset($date)) {
                    $seg['ArrDate'] = $this->normalizeDate($date . ' ' . $arrTime);
                }
                $plusDay = empty($arrivalMatches[$key][4]) ? null : $arrivalMatches[$key][4];

                if (!empty($plusDay)) {
                    $seg['ArrDate'] = strtotime($plusDay, $seg['ArrDate']);
                }

                if (!empty($arrivalMatches[$key][5])) {
                    $seg['ArrivalTerminal'] = $arrivalMatches[$key][5];
                }
                $seg['Cabin'] = $arrivalMatches[$key][6];
                $seg['BookingClass'] = $arrivalMatches[$key][7];
                $seg['Duration'] = $arrivalMatches[$key][8];

                $it['TripSegments'][] = $seg;
            }

            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($str)
    {
        $in = [
            '/(\w{3,})\s*(\d{1,2})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)/i', // Aug 11 9:55pm
            '/(\d{1,2})\s*(\w{3,})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)/i', // 2 Aug 18:54
        ];
        $out = [
            "$2 $1 {$this->year}, $3",
            "$1 $2 {$this->year}, $3",
        ];

        return strtotime(preg_replace($in, $out, $str));
    }

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    private function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return $inputResult;
    }
}
