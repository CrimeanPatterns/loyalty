<?php

// bcdtravel

namespace AwardWallet\Engine\fairmont\Email;

class ReservationPdf2017En extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('[[:alpha:]\s]+\.pdf');

        if (empty($pdf)) {
            $this->http->Log('Pdf is not found!', LOG_LEVEL_ERROR);

            return false;
        }

        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));
        $its[] = $this->parseHotel(mb_substr($text, 0, 2500));

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Hotel',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (stripos($parser->getHTMLBody(), 'This message and any attachment(s) are intended only for the use')) {
            return true;
        }
        $pdf = $parser->searchAttachmentByName('[[:alpha:]\s]+\.pdf');
        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));

        return stripos($text, 'Thank you for choosing the Fairmont Miramar Hotel') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@bcdtravel.com') !== false;
    }

    protected function parseHotel($text)
    {
        $i = ['Kind' => 'R'];

        $i['Status'] = $this->match('#reservation\s+(confirmation)#', $text);

        $i['ReservationDate'] = strtotime($this->match('#^\s*(\w+ \d+, \d{4})#', $text));

        $i['ConfirmationNumber'] = $this->match('/Confirmation Number:\s*(\d+)\b/', $text);

        $i['GuestNames'][] = $this->match('/Dear\s+([[:alpha:]\s\.]+),\n/', $text);

        $i['HotelName'] = $this->match('/Thank you for choosing the\s*(.+?)\. While/', $text);

        $i['CheckInDate'] = strtotime(str_replace('-', '/', $this->match('/Arrival Date:\s*(\d+-\d+-\d+)/', $text)), false);
        $i['CheckOutDate'] = strtotime(str_replace('-', '/', $this->match('/Departure Date:\s*(\d+-\d+-\d+)/', $text)), false);

        $i['RoomType'] = $this->match('/Accommodation Type:\s*([[:alpha:]\s]+)\n/', $text);
        $i['Rate'] = $this->match('/Nightly Room Rate:\s*(.+?)\n/', $text);
        $i['CancellationPolicy'] = $this->match('/Cancellation Policy:\s+(.+?\.)\n/s', $text);

        $i['Address'] = $this->match('/\.com\n(.+?)\n\s*T\s+\(\d+\)/s', $text);

        $i['Phone'] = $this->match('/Tel\s+([+\d\s()-]+)\n/s', $text);
        $i['Fax'] = $this->match('/Fax\s+([+\d\s()-]+)\n/s', $text);

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
}
