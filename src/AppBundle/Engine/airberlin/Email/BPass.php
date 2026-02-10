<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\airberlin\Email;

class BPass extends \TAccountChecker
{
    public $mailFiles = "airberlin/it-11094780.eml, airberlin/it-11094783.eml, airberlin/it-11094794.eml, airberlin/it-7032898.eml, airberlin/it-7193561.eml, airberlin/it-7790255.eml, airberlin/it-7837990.eml, airberlin/it-7843142.eml";

    private $pdfText = '';

    private $reBody = "airberlin";

    private $detects = [
        'Boarding Pass',
    ];

    private $year = '';

    private $AttachmentFileName = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = date('Y', strtotime($parser->getDate()));
        $name = $parser->getAttachmentHeader(0, 'Content-Type');

        if (preg_match('/name\s*=\s*[\"\'](.+)[\'\"]/', $name, $m)) {
            $this->AttachmentFileName = $m[1];
        }

        if (!$this->detectBody($parser)) {
            return [];
        }

        return [
            'emailType'  => 'BoardingPassEn',
            'parsedData' => [
                'Itineraries'  => $this->parseEmail(),
                'BoardingPass' => $this->parseBP(),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'airberlin.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'airberlin.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $text = $this->pdfText;

        if (($node = $this->getNode($text)) && is_array($node)) {
            $it['Passengers'] = $node['Passengers'];
            $it['RecordLocator'] = $node['RecordLocator'];
            $it['TicketNumbers'] = array_unique($node['TicketNumbers']);
        }

        if ($this->getNode($text) === false) {
            return false;
        }

        $it['TripSegments'] = [];
        $bps = $this->split("#(Boarding Pass\s*\n)#", $text);

        foreach ($bps as $bptext) {
            $seg = $this->findCutSection($bptext, 'FLIGHT', ['Please be prepared', 'Ten preparada', 'Bitte halten Sie diese']);

            $re = '/([A-Z\d]{2}\s*\d+\s+\d{1,2}\s*[^\d\s]+\s+\d{1,2}:\d{2})/';
            $segs = preg_split($re, $seg, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            array_shift($segs);
            $segments = [];

            foreach ($segs as $i => $seg) {
                if (isset($segs[$i + 1]) && preg_match($re, $seg)) {
                    $segments[$seg] = $segs[$i + 1];
                }
            }
            $segs = [];

            foreach ($segments as $key => $segment) {
                $segs[] = $key . ' ' . $segment;
            }

            foreach ($segs as $segT) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                if (($segment = $this->getSegment($segT)) && is_array($segment)) {
                    $seg['AirlineName'] = $segment['AirlineName'];
                    $seg['FlightNumber'] = $segment['FlightNumber'];
                    $date = $segment['date'];
                    $seg['DepDate'] = strtotime($date . ', ' . $segment['DTime']);
                    $seg['DepCode'] = $segment['DepCode'];
                    $seg['ArrCode'] = $segment['ArrCode'];
                    $seg['DepName'] = $segment['DepName'];
                    $seg['ArrName'] = $segment['ArrName'];
                    $seg['BookingClass'] = $segment['BookingClass'];
                    $seg['Cabin'] = $segment['Cabin'];
                    $seg['Seats'][] = $segment['Seats'];

                    if (!empty($seg['DepDate']) && !empty($seg['FlightNumber'])) {
                        $seg['ArrDate'] = MISSING_DATE;
                    }
                }
                $finded = false;

                foreach ($it['TripSegments'] as $key => $value) {
                    if ($seg['FlightNumber'] == $value['FlightNumber'] && $seg['DepDate'] == $value['DepDate']) {
                        $it['TripSegments'][$key]['Seats'] = array_merge($value['Seats'], $seg['Seats']);
                        $it['TripSegments'][$key]['Seats'] = array_filter($it['TripSegments'][$key]['Seats'], function ($s) { return preg_match("#^\d+\w$#", $s); });
                        $finded = true;

                        break;
                    }
                }

                if ($finded == false) {
                    $it['TripSegments'][] = $seg;
                }
            }
        }

        $it['TripSegments'] = array_filter($it['TripSegments']);

        return [$it];
    }

    private function parseBP()
    {
        $it = [];

        $text = $this->pdfText;

        if (($node = $this->getNode($text)) && is_array($node)) {
            $it['Passengers'] = $node['Passengers'];
            $it['RecordLocator'] = $node['RecordLocator'];
            $it['TicketNumber'] = $node['TicketNumbers'];
        }

        $seg = $this->findCutSection($text, 'FLIGHT', ['Please be prepared', 'Ten preparada', 'Bitte halten Sie diese']);

        if (($segment = $this->getSegment($seg)) && is_array($segment)) {
            $it['AirlineName'] = $segment['AirlineName'];
            $it['FlightNumber'] = $segment['FlightNumber'];
            $date = $segment['date'];
            $it['Seats'] = $segment['Seats'];
            $it['BookingClass'] = $segment['BookingClass'];
            $it['DepDate'] = strtotime($date . ', ' . $segment['DTime']);
            $it['DepCode'] = $segment['DepCode'];
            $it['ArrCode'] = $segment['ArrCode'];
            $it['DepName'] = $segment['DepName'];
            $it['ArrName'] = $segment['ArrName'];
        }

        if (!empty($this->AttachmentFileName)) {
            $it['AttachmentFileName'] = $this->AttachmentFileName;
        }

        return [$it];
    }

    private function getNode($text)
    {
        if (empty($text)) {
            return false;
        }
        $passengers = [];
        $ticketNumbers = [];
        $recordLocators = [];
        $countRLInEmail = 0;
        $countUniqueRL = 0;
        preg_match_all('/name:\s+(.+)\s+/i', $text, $m);

        if (count($m[1]) > 0) {
            $passengers = $m[1];
        }
        preg_match_all('/ticket:\s+(\d+)/i', $text, $m);

        if (count($m[1]) > 0) {
            $ticketNumbers = $m[1];
        }
        preg_match_all('/reservation:\s+([A-Z\d]{5,7})\s+/i', $text, $m);

        if (count($m[1]) > 0) {
            $recordLocators = $m[1];
            $countRLInEmail = count($recordLocators);
            $countUniqueRL = count(array_unique($recordLocators));
        }

        $node = $this->findCutSection($text, 'Boarding Pass', 'FLIGHT');

        if ($countRLInEmail >= 1 && $countUniqueRL === 1 && preg_match('/reservation:\s+([A-Z\d]{5,7})\s+/i', $node, $m)) {
            return [
                'Passengers'    => $passengers,
                'RecordLocator' => $m[1],
                'TicketNumbers' => $ticketNumbers,
            ];
        } elseif ($countRLInEmail > 1 && $countUniqueRL > 1) {
            $this->logger->info('In the letter several different reservations');

            return false;
        }

        return $node;
    }

    private function getSegment($segment)
    {
        if (empty($segment)) {
            return false;
        }
        $re = "/(?<AName>[A-Z\d]{2})\s*(?<FNum>\d+)\s+(?<Day>\d{1,2})\s*(?<Month>\D+)\s+\d{1,2}:\d{2}\s+(?:([A-Z\d]{1,3})|[A-Z\d]{1,4}\s+(\d{1,3}[A-Z]))\s+"
                . "(?:\w+\s+)?(?<BClass>[A-Z])\s+\d+\s+.*dep\s+(?<DTime>\d{1,2}:\d{2})\s+(?:\w+\s+)?(?<Cabin>\w+)\s+"
                . "(?<DCode>[A-Z]{3})\s*\/\s*(?<DName>.+)\s+-\s+(?<ACode>[A-Z]{3})\s*\/\s*(?<ArrName>[^\d\n]+)/i";

        if (preg_match($re, $segment, $m)) {
            return [
                'AirlineName'  => $m[1],
                'FlightNumber' => $m[2],
                'date'         => $m['Day'] . ' ' . trim($m['Month']) . ' ' . $this->year,
                'Seats'        => !empty($m[5]) ? $m[5] : $m[6],
                'BookingClass' => $m['BClass'],
                'DTime'        => $m['DTime'],
                'Cabin'        => $m['Cabin'],
                'DepCode'      => $m['DCode'],
                'DepName'      => $m['DName'],
                'ArrCode'      => $m['ACode'],
                'ArrName'      => trim($m['ArrName']),
            ];
        }

        return $segment;
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

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            return false;
        }

        $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                $this->pdfText = $body;

                return true;
            }
        }

        return false;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
