<?php

namespace AwardWallet\Engine\aplus\Email;

/**
 * it-4964745.eml(bcd).
 */
class TripPdf2016En extends \TAccountChecker
{
    protected $totalCharge = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.+?\.pdf');

        if (empty($pdf)) {
            $this->http->Log('Pdf is not found or is empty!');

            return false;
        }

        $pdfText = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

        return [
            'parsedData' => [
                'Itineraries' => $this->parseReservations(str_replace(' ', ' ', $pdfText)),
                'TotalCharge' => $this->totalCharge,
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.+?\.pdf');
        $pdfText = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

        return stripos($pdfText, 'BOOKING CONFIRMATION') !== false && stripos($pdfText, 'Room type/ Rate') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    protected function parseReservations($pdfText)
    {
        $reservation['Kind'] = 'R';

        if (preg_match('/located in downtown Ho Chi\s*Minh City/', join($this->findСutSectionAll($pdfText, 'LOCAL POINTS OF INTEREST', ['shopping and entertainment'])), $matches)) {
            $reservation['Address'] = '167 Hai Bà Trưng, phường 6, Quận 3, Hồ Chí Minh 722413, Vietnam';
        }

        $pdfText = join($this->findСutSectionAll($pdfText, 'Phone  ', ['Payment  ']));

        if (preg_match('#Room type/ Rate\s*:.*?at\s+([A-Z]{3})\s*([\d,]+)\s+#', $pdfText, $matches)) {
            $this->totalCharge = [
                'Amount'   => (float) str_replace(',', '', $matches[2]),
                'Currency' => $matches[1],
            ];
        }

        if (preg_match('/Thank you for choosing\s+(.+?)\.\s+/', $pdfText, $matches)) {
            $reservation['HotelName'] = $matches[1];
        }

        if (preg_match('/Date\s*:\s*(\d+\s*\w+,\s*\d+)/s', $pdfText, $matches)) {
            $reservation['ReservationDate'] = strtotime(str_replace(',', '', $matches[1]));
        }

        if (preg_match('/Check in time\s*:\s*(\d+:\d+).+?Check out time\s*:\s*(\d+:\d+)/s', $pdfText, $matches)) {
            $reservation['CheckInDate_'] = $matches[1];
            $reservation['CheckOutDate_'] = $matches[2];
        }

        return $this->parseReservationForeach(join($this->findСutSectionAll($pdfText, 'Booking No', ['Room type/ Rate'])), $reservation);
    }

    protected function parseReservationForeach($pdfText, $reservation)
    {
        $reservations = [];
        $arrays = preg_split('/\n+/', $pdfText, null, PREG_SPLIT_NO_EMPTY);

        // Guest Names
        foreach ($arrays as $value) {
            if (preg_match('/(.+?)?\s*(?:\d+\s*\w+\s+\d+\s*\w+\s+\d+)?$/', $value, $matches)) {
                $matches[1] = trim($matches[1]);

                if (!empty($matches[1])) {
                    $reservation['GuestNames'][] = $matches[1];
                }
            }
        }
        // Reservations
        foreach ($arrays as $value) {
            if (preg_match('/(.+?)?\s*(?:(\d+\s*\w+)\s+(\d+\s*\w+)\s+(\d+))?$/', $value, $matches)) {
                if (count($matches) >= 4) {
                    $reservations[] = $this->parseReservation($matches, $reservation);
                }
            }
        }

        return $reservations;
    }

    protected function parseReservation($matches, $reservation)
    {
        $reservation['ConfirmationNumber'] = $matches[4];
        $reservation += $this->increaseDate($reservation['ReservationDate'], $matches[2] . ', ' . $reservation['CheckInDate_'], $matches[3] . ', ' . $reservation['CheckOutDate_']);

        unset($reservation['CheckInDate_'], $reservation['CheckOutDate_']);

        return $reservation;
    }

    protected function increaseDate($dateYear, $dete1, $date2)
    {
        $timeIn = strtotime($dete1, $dateYear);

        if ($dateYear > $timeIn) {
            $timeIn = strtotime('+1 year', $timeIn);
        }
        $timeOut = strtotime($date2, $timeIn);

        while ($timeIn > $timeOut) {
            $timeOut = strtotime('+1 day', $timeOut);
        }

        return [
            'CheckInDate'  => $timeIn,
            'CheckOutDate' => $timeOut,
        ];
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * <b>LEFT</b> <i>cut text2</i> <b>RIGHT2</b>.
     */
    protected function findСutSectionAll($input, $searchStart, $searchFinish)
    {
        $array = [];

        while (empty($input) !== true) {
            $right = mb_strstr($input, $searchStart);

            foreach ($searchFinish as $value) {
                $left = mb_strstr($right, $value, true);

                if (!empty($left)) {
                    $input = mb_strstr($right, $value);
                    $array[] = mb_substr($left, mb_strlen($searchStart));

                    break;
                }
            }

            if (empty($left)) {
                $input = false;
            }
        }

        return $array;
    }
}
