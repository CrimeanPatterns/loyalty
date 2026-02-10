<?php

// bcdtravel

namespace AwardWallet\Engine\designh\Email;

class ReservationPdf2017En extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('ConfirmationLetter - [\w\s.]+\.pdf');

        if (empty($pdf)) {
            $this->http->Log('Pdf is not found!', LOG_LEVEL_ERROR);

            return false;
        }

        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

        if ($this->stripos($text, ['RESERVATION']) == false) {
            $this->http->Log('Pdf is not found!', LOG_LEVEL_ERROR);

            return false;
        }

        $its[] = $this->parseHotel($text);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "reservation",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && isset($headers['subject'])
                && stripos($headers['from'], '@sensehotel.com') !== false
                && $this->stripos($headers['subject'], ['Request for reservation -']) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'sensehotel') !== false
                && strpos($parser->getHTMLBody(), 'Reservations Department') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sensehotel.') !== false;
    }

    protected function parseHotel($text)
    {
        $i = ['Kind' => 'R'];
        $i['Status'] = $this->match('#^\s*RESERVATION\s+([[:alpha:]]+)#', $text);
        $i['ReservationDate'] = strtotime($this->match('#\s+Date:\s*(\d+-\w+-\d+)#', $text));
        $i['ConfirmationNumber'] = $this->match('/Reservation No\.:\s*(\d+)\n/', $text);
        $i['GuestNames'] = preg_split('/,\s*/', $this->match('/Guest Name\(s\):\s*([[:alpha:]\s,.]+)Reservation/', $text));
        $i['HotelName'] = $this->match('/Thank you for choosing the\s+(.+?)\. We/', $text);

        $addr = $this->match('/' . strtoupper($i['HotelName']) . '\s+(.+?)\s+Tel:([()+\d\s-]+)$/', $text, true);

        if (!empty($addr)) {
            $i['Address'] = $addr[0];
            $i['Phone'] = $addr[1];
        }

        $rooms = $this->match('/Room type:\s+Departure flight:\s+(\d+)\s+(.+?)Airport/s', $text, true);

        if (!empty($rooms)) {
            $i['Rooms'] = (int) $rooms[0];
            $i['RoomType'] = $rooms[1];
        }

        $guests = $this->match('#Adults/Child:\s*(\d+)/(\d+)#', $text, true);

        if (!empty($guests)) {
            $i['Guests'] = (int) $guests[0];
            $i['Kids'] = (int) $guests[1];
        }

        $i['CheckInDate'] = strtotime($this->match('#Arrival Date:\s*(\d+-\w+-\d+)#', $text), false);
        $i['CheckOutDate'] = strtotime($this->match('#Departure Date:\s*(\d+-\w+-\d+)#', $text), false);

        $i['Rate'] = $this->match('/Room rate:\s*([\w\s.,]+)\s{3,}/', $text);
        $i['CancellationPolicy'] = $this->match('/Cancellation Policy:\s+(.+?)\s+For further information/s', $text);

        return $i;
    }

    //========================================
    // Auxiliary methods
    //========================================

    protected function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }
    }

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    protected function stripos($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
