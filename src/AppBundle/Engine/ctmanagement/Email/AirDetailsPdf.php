<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\ctmanagement\Email;

class AirDetailsPdf extends \TAccountChecker
{
    public $mailFiles = "ctmanagement/it-8032895.eml";

    private $lang = 'en';

    private $detects = [
        'All remittances should be made payable to "Corporate Travel Management (S) Pte Ltd" or deposited at',
    ];

    private $pdfText = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->detectBody($parser)) {
            return [];
        }

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail()],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'travelctm.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'travelctm.com') !== false;
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

        $rl = $this->findCutSection($text, 'PNR', ['Booking Type', 'Reason Code']);

        if (preg_match('/[:]*\s+([A-Z\d]{5,7})\s+Attn:\s*(.+)/', $rl, $m)) {
            $it['RecordLocator'] = $m[1];
//            $it['Passengers'][] = trim($m[2]);
        }
        $passengers = $this->findCutSection($text, 'Passenger Details', 'Air Details');

        if (is_string($passengers) && !empty($passengers)) {
            $it['Passengers'] = array_filter(explode("\n", $passengers));
        }

        $tickets = $this->findCutSection($text, 'Ticket Details', ['Due Date']);
        preg_match_all('/[A-Z\d]{2}\s*\d+\s+(\d+)\s+/', $tickets, $m);

        if (!empty($m[1])) {
            $it['TicketNumbers'] = $m[1];
        }

        if (preg_match('/Grand Total Amount:\s+([A-Z]{3})\s+([\d\.,]+)/', $tickets, $m)) {
            $it['Currency'] = $m[1];
            $it['TotalCharge'] = (float) str_replace([','], [''], $m[2]);
        }

        $segmentsText = $this->findCutSection($text, 'Air Details', 'Ticket Details');
        preg_match_all('/(([A-Z\d]{2})\s*(\d+)\s{2,}(\w+)\s{2,}(\d{1,2}\D+\d{2,4} \d{1,2}:\d{2})\s{2,}(.+?)\s{2,}(.+))/', $segmentsText, $m);

        if (empty($m[1])) {
            $this->logger->info('Segments not found by regExp.');

            return false;
        }

        foreach ($m[1] as $i => $sText) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $seg['AirlineName'] = $m[2][$i];
            $seg['FlightNumber'] = $m[3][$i];
            $seg['Cabin'] = $m[4][$i];
            $seg['DepDate'] = $this->normalizeDate($m[5][$i]);
            $seg['DepName'] = $m[6][$i];
            $seg['ArrName'] = $m[7][$i];
            $seg['ArrDate'] = MISSING_DATE;

            if (!empty($seg['DepDate']) && !empty($seg['FlightNumber'])) {
                $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '/(\d{1,2})\s*(\D+)\s*(\d{2,4})\s*(\d+:\d+)/',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        return strtotime(preg_replace($in, $out, $str));
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

        if (empty($pdfs)) {
            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                $this->pdfText = $body;

                return true;
            }
        }

        return false;
    }
}
