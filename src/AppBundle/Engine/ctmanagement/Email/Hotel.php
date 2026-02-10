<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\ctmanagement\Email;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "ctmanagement/it-8032899.eml";

    private $lang = 'en';

    private $detects = [
        'All remittances should be made payable to "Corporate Travel Management (S) Pte Ltd" or deposited at',
    ];

    private $pdfText = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->detectBody($parser)) {
            return false;
        }

        return [
            'parsedData' => ['Itineraries' => $this->parseEmail()],
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'travelctm.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'travelctm.com') !== false;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\Hotel $it */
        $it = ['Kind' => 'R'];
        $text = $this->pdfText;

        $total = $this->findCutSection($text, 'Grand Total Amount', PHP_EOL);

        if (preg_match('/[:]*\s+([A-Z]{3})\s+([\d\.,]+)/', $total, $m)) {
            $it['Currency'] = $m[1];
            $it['Total'] = (float) str_replace([','], [''], $m[2]);
        }

        $reservation = $this->findCutSection($text, 'Your Reservation', 'Hotel Information');

        if (preg_match('/Booking Reference\s*:\s*\(([\d\-]+)\)\s+.+?\s+Guest Name\(s\)\s*:\s*(.+)/', $reservation, $m)) {
            $it['ConfirmationNumber'] = $m[1];
            $it['GuestNames'] = (stripos($m[2], ',') !== false) ? explode(', ', $m[2]) : $m[2];
        }

        $hotelInfo = $this->findCutSection($text, 'Hotel Information', 'Service Details');

        if (preg_match('/Hotel Name\s*:\s+([\S\s]+)\s+Address\s*:\s*([\s\S]+)\s+Phone\s*:\s*([\d\s]+)/', $hotelInfo, $m)) {
            $it['HotelName'] = trim(preg_replace('/\s+/', ' ', $m[1]));
            $it['Address'] = trim(preg_replace('/\s+/', ' ', $m[2]));
            $it['Phone'] = trim($m[3]);
        }

        $serviceDetails = $this->findCutSection($text, 'Service Details', 'Hotel Reference');
        $re = '/Reference\s*:\s*(\d+)\s+Service\s*:\s*(.+)\s+.*\s*Check-In\s*:\s*(\d+ \w+ \d+)\s+Check-Out\s*:\s*(\d+ \w+ \d+)\s+.+\s*Special Request\s*:\s*(.+)\s+.*\s*Arrival\s*:\s*Flight (\d+:\d+ \d+ \w+ \d+)\s+Departure\s*:\s*Flight (\d+:\d+ \d+ \w+ \d+)/';

        if (preg_match($re, $serviceDetails, $m)) {
            $reference = $m[1];
            $it['RoomType'] = $m[2];
//            $it['CheckInDate'] = strtotime($m[3]);
//            $it['CheckOutDate'] = strtotime($m[4]);
            $it['RoomTypeDescription'] = $m[5];
            $checkInDateWithTime = $m[6];
            $checkOutDateWithTime = $m[7];
            $it['CheckInDate'] = $this->normalizeDate($checkInDateWithTime);
            $it['CheckOutDate'] = $this->normalizeDate($checkOutDateWithTime);
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        $in = [
            '/.*?\s*(\d+:\d+)\s+(\d+ \w+ \d+)/',
        ];
        $out = [
            '$2, $1',
        ];

        return strtotime(preg_replace($in, $out, $str));
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $body = '';

        foreach ($pdfs as $pdf) {
            $body .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                $this->pdfText = $body;

                return true;
            }
        }

        return false;
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
}
