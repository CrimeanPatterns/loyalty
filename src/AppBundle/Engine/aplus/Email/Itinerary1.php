<?php

namespace AwardWallet\Engine\aplus\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "aplus/it-1.eml, aplus/it-2.eml, aplus/it-3.eml, aplus/it-4.eml";

    private $_emails = [
        'accorhotels.reservation@accor.com',
    ];
    private $_subjects = [
        'Your reservation N',
        'Accorhotels',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        $from = $this->_checkInHeader($headers, 'from', $this->_emails);
        $subject = $this->_checkInHeader($headers, 'subject', $this->_subjects);

        return $from || $subject;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->FindSingleNode("(//img[contains(@src, 'accorhotels.com')])[1]/@src")) {
            return true;
        }

        // If forwarded message
        $body = $parser->getPlainBody();
        $from = $this->_checkInBody($body, 'From:', $this->_emails);
        $subject = $this->_checkInBody($body, 'Subject:', $this->_subjects);

        return $from || $subject;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $root = "//h1[contains(text(), 'Your reservation') or contains(text(), 'Votre réservation')]/ancestor::table[2]/tbody";
        $it = [];
        $it['Kind'] = 'R';
        $it['ConfirmationNumber'] = str_replace('-', '', $this->http->FindSingleNode($root . "//td[contains(text(), 'Reservation number') or contains(text(), 'Numéro de réservation')]/../td[2]"));
        $it['GuestNames'] = [$this->http->FindSingleNode($root . "//td[contains(text(), 'Reservation made in the name of') or contains(text(), 'Réservation effectuée au nom de')]/../td[2]")];
        $it['HotelName'] = $this->http->FindSingleNode($root . "/tr[3]/td/table[1]//h3/a");

        if (preg_match("#(?:du|from)\s+([\d/]+)\s+(?:au|to)#i", $this->http->FindSingleNode($root . "/tr[3]/td/div/table[2]//tr[2]/td[2]/span"), $m)) {
            $it['CheckInDate'] = $this->_dateToTimestamp($m[1]);
        }

        if (preg_match("#(?:au|to)\s+([\d/]+)\s*#i", $this->http->FindSingleNode($root . "/tr[3]/td/div/table[2]//tr[2]/td[2]/span"), $m)) {
            $it['CheckOutDate'] = $this->_dateToTimestamp($m[1]);
        }

        $it['Address'] = $this->http->FindSingleNode($root . "/tr[3]/td/table[1]//tr[5]/td[2]");

        if (preg_match("/(.+)\s+\-\s+(\d+)\s+([\w\s\-]+)/i", $it['Address'], $m)) {
            $it['DetailedAddress'] = [
                "AddressLine" => $m[1],
                "CityName"    => $m[3],
                "PostalCode"  => $m[2],
                "StateProv"   => '',
                "Country"     => '',
            ];
        }

        $it['Phone'] = $this->http->FindSingleNode($root . "//*[contains(text(), 'Tél') or contains(text(), 'Tel')]/ancestor-or-self::td[1]", null, true, "#:\s*(.+)#");

        if (preg_match("/(\d+)\sadult\(s\)/i", $this->http->FindSingleNode($root . "//td[contains(text(), 'Number of persons') or contains(text(), 'Nombre de personnes')]/span"), $m)) {
            $it['Guests'] = $m[1];
        }

        if (preg_match("/(\d+)\schild\(ren\)/i", $this->http->FindSingleNode($root . "//td[contains(text(), 'Number of persons') or contains(text(), 'Nombre de personnes')]/span"), $m)) {
            $it['Kids'] = $m[1];
        }

        $it['Guests'] = $this->http->FindSingleNode($root . "//*[contains(text(), 'Nombre de personnes')]/ancestor-or-self::tr[1]", null, true, "#([\d.]+)#");

        $it['Rooms'] = $this->http->FindSingleNode($root . "/tr[3]/td/div/table[1]//td[3]");
        $it['Rate'] = $this->http->FindSingleNode($root . "/tr[3]/td/div/table[5]//tr[2]/td[3]");
        $it['RateType'] = $this->http->FindSingleNode($root . "/tr[3]/td/div/table[3]//tr[1]/td[2]");
        $it['CancellationPolicy'] = $this->http->FindSingleNode($root . "//td[contains(text(), 'Cancellation policy') or contains(text(), \"Délai d'annulation\")]/../td[2]");
        $it['RoomType'] = $this->http->FindSingleNode($root . "/tr[3]/td/div/table[4]//table//tr[1]/td[1]");
        $it['RoomTypeDescription'] = $this->http->FindSingleNode($root . "/tr[3]/td/div/table[4]//table//tr[2]/td[1]");
        $it['Total'] = null;

        if (!$it['Total']) {
            $value = $this->http->FindSingleNode($root . "//*[contains(text(), 'Total booking price') or contains(text(),'Le montant prépayé est de')]/ancestor-or-self::tr[1]");

            if (preg_match("#([\d\.]+)\s+(\w+)#i", $value, $m)) {
                $it['Total'] = $m[1];
                $it['Currency'] = $m[2];
            }
        }

        if (!$it['Total']) {
            $it['Total'] = $this->http->FindSingleNode($root . "//*[contains(text(), 'The amount to be paid at the hotel is') or contains(text(), 'Montant total de votre réservation')]/ancestor-or-self::tr[1]", null, true, "#([\d.]+)\s+\b[A-Z]{3}\b#");
            $it['Currency'] = $this->http->FindSingleNode($root . "//*[contains(text(), 'The amount to be paid at the hotel is') or contains(text(), 'Montant total de votre réservation')]/ancestor-or-self::tr[1]", null, true, "#(\b[A-Z]{3}\b)#");
        }

        $it['Taxes'] = $this->http->FindSingleNode($root . "//*[contains(text(), 'Taxes not included') or contains(text(), 'Taxes non incluses')]/following-sibling::td[1]/*[1]");

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]accor\.com$/ims', $from);
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "fr"];
    }

    private function _checkInHeader(&$headers, $field, $source)
    {
        if (isset($headers[$field])) {
            foreach ($source as $temp) {
                if (stripos($headers[$field], $temp) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function _checkInBody(&$body, $field, $source)
    {
        if ($start = strpos($body, $field)) {
            $end = strpos($body, "\n", $start);
            $header = substr($body, $start, $end - $start);

            foreach ($source as $temp) {
                if (stripos($header, $temp) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function _dateToTimestamp($str)
    {
        $dt = explode('/', $str);

        return strtotime($dt[1] . '/' . $dt[0] . '/' . $dt[2]);
    }
}
