<?php

namespace AwardWallet\Engine\friendchips\Email;

class ThomsonConfirmation extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-7206354.eml, friendchips/it-7264416.eml, friendchips/it-7279523.eml, friendchips/it-7305373.eml, friendchips/it-7383298.eml, friendchips/it-7403706.eml";

    public $reFrom = [
        '@cs.tuiuk.com',
        '@thomson.co.uk',
    ];

    public $lang = '';

    public $langDetectors = [
        'en' => ['BOOKING CONFIRMATION', 'Your flight booking reference'],
    ];

    public static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => $its,
            'emailType'  => 'ThomsonConfirmation' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"@cs.tuiuk.com") or contains(.,"Thomson.co.uk") or contains(.,"www.thomson.co.uk") or contains(normalize-space(.),"The Team at Thomson") or contains(normalize-space(.),"than Thomson Airways") or contains(normalize-space(.),"with Thomson Airways") or contains(normalize-space(.),"Thomson Airways Flight") or contains(.,"@thomson.co.uk") or contains(normalize-space(.),"provided by Thomson Airways")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//thomson.co.uk") or contains(@href,"//flightextras.thomson.co.uk")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Thomson Holidays') !== false
            && stripos($headers['subject'], 'Booking Reference') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $re) {
            if (stripos($from, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function normalizePrice($price)
    {
        if (preg_match("#([.,])\d{2}($|[^\d])#", $price, $m)) {
            $delimiter = $m[1];
        } else {
            $delimiter = '.';
        }
        $price = preg_replace('/[^\d\\' . $delimiter . ']+/', '', $price);
        $price = (float) str_replace(',', '.', $price);

        return $price;
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseFlightSeg($root, $num)
    {
        $seg = [];
        $strdate = $this->http->FindSingleNode(".//td[5]", $root);

        if (preg_match("#(\d+\s+[a-z]{3}\s+\d{4})\s*(\d{2}:\d{2})\s*\D\s*(\d{2}:\d{2})\s*(\((\d+\s+[a-z]{3}\s+\d{4})\))?#i", $strdate, $m)) {
            $seg['DepDate'] = strtotime($m[1] . ', ' . $m[2]);

            if (!empty($m[5])) {
                $seg['ArrDate'] = strtotime($m[5] . ', ' . $m[3]);
            } else {
                $seg['ArrDate'] = strtotime($m[1] . ', ' . $m[3]);
            }
        }

        $node = implode("\n", $this->http->FindNodes(".//td[3]/*", $root));

        if (preg_match("#(.*)\s*-\s*(.*)\n(?:([\w\s]+)\s+Terminal)?(.*\n)*.*Flight no:\s*([A-Z]{2,3})\s*(\d{1,5})#", $node, $m)) {
            $seg['DepName'] = trim($m[1]);
            $seg['ArrName'] = trim($m[2]);
            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;

            if (!empty($m[3])) {
                $seg['DepartureTerminal'] = $m[3];
            }

            if ($m[5] === 'TOM') {
                $seg['AirlineName'] = 'BY';
            } else {
                $seg['AirlineName'] = $m[5];
            }
            $seg['FlightNumber'] = $m[6];
        }

        return $seg;
    }

    private function parseEmail()
    {
        $its = [];
        $data = [];

        $i = 1;
        $passenger = [];
        $passengerAdult = 0;
        $s = trim($this->http->FindSingleNode("(//*[contains(.,'PASSENGER DETAILS')]/following::text()[starts-with(normalize-space(.),'Guest')][1]/ancestor::table)[1]/following-sibling::table[" . $i . "]//td[2]"));

        while (!empty($s) && $i < 10) {
            $passenger[] = str_replace("*", '', $s);

            if ($this->http->FindSingleNode("(//*[contains(.,'PASSENGER DETAILS')]/following::text()[starts-with(normalize-space(.),'Guest')])[1]/ancestor::table[1]/following-sibling::table[" . $i . "]//td[3]", null, true, "#(adult)#i")) {
                $passengerAdult++;
            }
            $i++;
            $s = trim($this->http->FindSingleNode("(//*[contains(.,'PASSENGER DETAILS')]/following::text()[starts-with(normalize-space(.),'Guest')])[1]/ancestor::table[1]/following-sibling::table[" . $i . "]//td[2]"));
        }
        unset($s);

        //Flights
        $xpath = "//text()[starts-with(normalize-space(.),'Flight no')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[contains(.,'Booking ref')]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\d]+)#");
            $it['Passengers'] = $passenger;

            foreach ($nodes as $i => $node) {
                $it['TripSegments'][] = $this->parseFlightSeg($node, $i);
            }
            $its[] = $it;
        }

        //Hotels
        $xpath = "(//text()[starts-with(normalize-space(.),'Accommodation Name')]/ancestor::table[1])";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $i => $node) {
            $order = $i + 1;
            $it = ['Kind' => 'R'];
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[contains(.,'Booking ref')]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\d]+)#");

            $it['GuestNames'] = $passenger;
            $it['Guests'] = $passengerAdult;

            if ($kidsCount = count($passenger) - $passengerAdult) {
                $it['Kids'] = $kidsCount;
            }
            $it['HotelName'] = $this->http->FindSingleNode(".//td[starts-with(normalize-space(.),'Accommodation Name')]/following::td[1]", $node);
            $Destination = $this->http->FindSingleNode("./following-sibling::table//td[starts-with(normalize-space(.),'Destination')][" . $order . "]/following::td[1]", $node);
            $Resort = $this->http->FindSingleNode("./following-sibling::table//td[starts-with(normalize-space(.),'Resort')][" . $order . "]/following::td[1]", $node);
            $it['Address'] = implode(", ", [$it['HotelName'], $Resort, $Destination]);

            $date = $this->http->FindSingleNode("./following-sibling::table//td[contains(normalize-space(.),'Check in date')][" . $order . "]/following::td[1]", $node);

            if (preg_match("#(\d{1,2}\s*\w{3}\s*\d{4})\s*(\d{1,2}\s*\w{3}\s*\d{4})#", $date, $m)) {
                $it['CheckInDate'] = strtotime($m[1]);
                $it['CheckOutDate'] = strtotime($m[2]);
            }
            $type = [];
            $roomCount = 0;
            $k = 1;
            $s = $this->http->FindSingleNode(".//following-sibling::table[starts-with(normalize-space(.),'Room allocation and board')][" . $order . "]/following-sibling::table[" . $k . "]", $node);

            while (trim($s) != 'Transfer Options' && $k < 25) {
                if (preg_match("#Room \d+ description:\s*(.*)#", $s, $m)) {
                    $roomCount++;
                    $type[] = $m[1];
                }
                $k++;
                $s = $this->http->FindSingleNode("(.//following-sibling::table[starts-with(normalize-space(.),'Room allocation and board')])[" . $order . "]/following-sibling::table[" . $k . "]", $node);
            }
            $type = array_unique($type);

            if (!empty($type)) {
                $it['RoomType'] = implode(', ', $type);
            }
            $it['Rooms'] = $roomCount;

            $its[] = $it;
        }

        $total = $this->http->FindSingleNode("(//td[starts-with(normalize-space(.),'Total Price')])[1]/following::*[string-length()>3][1]");

        if ($total) {
            $sum = $this->normalizePrice($total);
            $cur = null;

            if (strpos($total, '£') !== false) {
                $cur = 'GBP';
            }

            if (strpos($total, '$') !== false) {
                $cur = 'USD';
            }

            if (strpos($total, '€') !== false) {
                $cur = 'EUR';
            }

            if (count($its) === 1) {
                if ($its[0]["Kind"] === "T") {
                    $its[0]['TotalCharge'] = $sum;
                    $its[0]['Currency'] = $cur;
                }

                if ($its[0]["Kind"] === "R") {
                    $its[0]['Total'] = $sum;
                    $its[0]['Currency'] = $cur;
                }
            } else {
                $data['TotalCharge'] = [
                    'Amount'   => $sum,
                    'Currency' => $cur,
                ];
            }
        }

        $data['Itineraries'] = $its;

        return $data;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
