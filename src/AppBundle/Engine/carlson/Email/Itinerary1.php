<?php

namespace AwardWallet\Engine\carlson\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = 'D M d Y';
    public $mailFiles = "";

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && in_array($headers["from"], ["reservations@radisson.com", "reservations@countryinns.com"]))
            || stripos($headers['subject'], "Your Radisson") !== false
            || stripos($headers['subject'], "Your Country Inns") !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // Parser toggled off as it is covered by emailReservationConfirmationChecker.php
        return null;
        $itineraries = [];
        $itineraries['Kind'] = 'R';

        if (preg_match('/Country Inns/', $parser->getSubject())) {
            $this->parseCountryInn($itineraries);
        } elseif (preg_match('/Radisson/', $parser->getSubject())) {
            $this->parseRadisson($itineraries);
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function buildDate($parsedDate)
    {
        return mktime($parsedDate['hour'], $parsedDate['minute'], 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }

    public function parseCountryInn(&$itineraries)
    {
        $itineraries['ConfirmationNumber'] = $this->http->FindSingleNode("//span[contains(text(), 'Confirmation Number')]/following-sibling::b[1]");

        if (!$itineraries['ConfirmationNumber']) {
            $itineraries['ConfirmationNumber'] = $this->http->FindSingleNode("//td[contains(text(), 'Confirmation Number')]/b");
        }

        if (!($itineraries['HotelName'] = $this->http->FindSingleNode("//body/div/table[3]//tr/td[2]/p/b/span"))) {
            $itineraries['HotelName'] = $this->http->FindSingleNode("//body/div/div[2]/div/div/table[3]/tbody/tr/td[2]/b");
        }
        $checkInDate = $this->http->FindSingleNode("//span[contains(text(), 'Arrival Date')]/../../following-sibling::td[1]");
        $itineraries['CheckInDate'] = $this->buildDate(date_parse_from_format(self::DATE_FORMAT, $checkInDate));
        $checkOutDate = $this->http->FindSingleNode("//span[contains(text(), 'Departure Date')]/../../following-sibling::td[1]");
        $itineraries['CheckOutDate'] = $this->buildDate(date_parse_from_format(self::DATE_FORMAT, $checkOutDate));
        $contactInfo = $this->http->FindSingleNode("//body/div/table[3]//tr/td[2]/p/span");
        $matches = [];

        if (preg_match('/(.*)\s([\d\-]+)/', $contactInfo, $matches)) {
            $itineraries['Address'] = $matches[1];
            $itineraries['Phone'] = $matches[2];
        }

        if (!isset($itineraries['Address'])) {
            $address = $this->http->XPath->query('//body/div/div[2]/div/div/table[3]/tbody/tr/td[2]');
            $addressRows = preg_split('/\n/', $address->item(0)->textContent);
            $itineraries['Address'] = trim($addressRows[2]) . ' ' . trim($addressRows[3]);
        }

        if (!($itineraries['GuestNames'] = $this->http->FindSingleNode("//span[contains(text(), 'Reservation for')]/../../following-sibling::td[1]"))) {
            $itineraries['GuestNames'] = $this->http->FindSingleNode("//td[contains(text(), 'Reservation for')]/following-sibling::td[1]");
        }
        $itineraries['Rate'] = $this->http->FindSingleNode('/html/body/div/table[9]//tr[1]/td[2]');

        if (!($itineraries['RateType'] = preg_replace('/Rate Type\:\s*/', '', $this->http->FindSingleNode("//strong[contains(text(), 'Rate Type')]")))) {
            $itineraries['RateType'] = $this->http->FindPreg('/Rate Type\:\S*([^\<]*)/u');
        }
        $itineraries['RoomType'] = $this->http->FindSingleNode('/html/body/div/table[8]//tr[3]/td/p/span');

        $itineraries['Cost'] = $this->floatval($this->http->FindPreg('/Subtotal\:[^\d+]+([\d\.]+)/'));
        $fee = $this->http->FindSingleNode("//b[contains(text(), 'Estimated Fees')]/../../../../td[2]//span");
        $tax = $this->http->FindSingleNode("//b[contains(text(), 'Estimated Taxes')]/../../../../td[2]//span");
        $itineraries['Taxes'] = $this->floatval($tax) + floatval($fee);
        $itineraries['Total'] = $this->floatval($this->http->FindSingleNode("//b[contains(text(), 'Estimated Total')]/../../../../td[2]//span"));
    }

    public function parseRadisson(&$itineraries)
    {
        $itineraries['ConfirmationNumber'] = $this->http->FindSingleNode("//td[contains(text(), 'Confirmation Number')]/b");

        if (!$itineraries['ConfirmationNumber']) {
            $confirmationNumber = $this->http->FindPreg('/Confirmation Number:\s*\S([^\ ]*)/u');
            $confirmationNumber = preg_replace('/.*;\s*\S\S/u', '', $confirmationNumber);
            $confirmationNumber = preg_replace('/Room/u', '', $confirmationNumber);
            $itineraries['ConfirmationNumber'] = $confirmationNumber;
        }

        if (!$itineraries['ConfirmationNumber']) {
            $itineraries['ConfirmationNumber'] = $this->http->FindSingleNode("//*[contains(text(), 'Reservation for:')]/ancestor::table[1]/preceding-sibling::b[1]");
        }

        $hotelInfo = $this->http->XPath->query('(//table[3]//tr/td[2])[1]');
        $hotelNameRow = 1;
        $hotelAddressRow = 2;
        $hotelPhone = 4;

        if ($hotelInfo->length == 0) {
            $hotelInfo = $this->http->XPath->query("//body/table[3]//tr");
            $hotelNameRow = 3;
            $hotelAddressRow = 4;
            $hotelPhone = 6;
        }
        $hotelInfoArray = preg_split("/\n/", $hotelInfo->item(0)->nodeValue);
        $itineraries['HotelName'] = CleanXMLValue($hotelInfoArray[$hotelNameRow]);
        $itineraries['Address'] = CleanXMLValue($hotelInfoArray[$hotelAddressRow] . ' ' . $hotelInfoArray[$hotelAddressRow + 1]);
        $itineraries['Phone'] = CleanXMLValue($hotelInfoArray[$hotelPhone]);
        $checkInDate = $this->http->FindSingleNode("//td[contains(text(), 'Arrival Date')]/../td[2]");
        $itineraries['CheckInDate'] = $this->buildDate(date_parse_from_format(self::DATE_FORMAT, $checkInDate));
        $checkOutDate = $this->http->FindSingleNode("//td[contains(text(), 'Departure Date')]/../td[2]");
        $itineraries['CheckOutDate'] = $this->buildDate(date_parse_from_format(self::DATE_FORMAT, $checkOutDate));
        $itineraries['GuestNames'] = $this->http->FindSingleNode("//td[contains(text(), 'Reservation for')]/../td[2]");
        $peoples = $this->http->FindSingleNode("//td[contains(text(), 'Number of people')]/../td[2]");
        $matches = [];

        if (preg_match('/(\d+)\s*adult.*(\d+)/i', $peoples, $matches)) {
            $itineraries['Guests'] = intval($matches[1]);
            $itineraries['Kids'] = intval($matches[2]);
        }

        $roomInfoTable = $this->http->XPath->query("//strong[contains(text(), 'Rate Information and Room Summary')]/../../..");
        $itineraries['Rate'] = $this->http->FindSingleNode('/html/body/div/div/table[9]//tr[1]//td[2]');

        if (!($itineraries['RateType'] = preg_replace('/Rate Type\:\s*/', '', $this->http->FindSingleNode("//strong[contains(text(), 'Rate Type')]")))) {
            $itineraries['RateType'] = $this->http->FindPreg('/Rate Type\:\S*([^\<]*)/u');
        }
        $itineraries['RoomType'] = $this->http->FindSingleNode('/html/body/div/div/table[8]//tr[3]/td');

        if (!$itineraries['Rate']) {
            //note refound correct data
            $itineraries['Rate'] = $this->http->FindSingleNode("./../following-sibling::table[2]//tr[1]//td[2]", $roomInfoTable->item(0));
            $itineraries['RoomType'] = $this->http->FindSingleNode("./../following-sibling::table[1]//tr[3]", $roomInfoTable->item(0));
        }

        $itineraries['Cost'] = $this->floatval($this->http->FindPreg('/Subtotal\:[^\d]+([\d\.\,]+)/'));
        $fee = $this->http->FindSingleNode("//b[contains(text(), 'Estimated Fees')]/../../td[2] | //strong[contains(text(), 'Estimated Fees')]/../../td[2]");
        $tax = $this->http->FindSingleNode("//b[contains(text(), 'Estimated Taxes')]/../../td[2] | //strong[contains(text(), 'Estimated Taxes')]/../../td[2]");
        $itineraries['Taxes'] = $this->floatval($tax) + floatval($fee);
        $itineraries['Total'] = $this->floatval($this->http->FindSingleNode("//b[contains(text(), 'Estimated Total')]/../../td[2] | //strong[contains(text(), 'Estimated Total')]/../../td[2]"));
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]radisson\.com$/ims', $from);
    }

    public function floatval($val)
    {
        return floatval(preg_replace('/,/', '', $val));
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }
}
