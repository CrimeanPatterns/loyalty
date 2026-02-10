<?php

namespace AwardWallet\Engine\azul\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = 'd/m/Y';
    public const DATE_TIME_FORMAT = 'd/m/Y H:i';
    public $mailFiles = "azul/it-1.eml";
    private $itineraries = [];

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && $this->СheckMail($headers["from"]))
            || isset($headers['subject']) && preg_match('/Itinerario Azul$/i', $headers['subject']);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@voeazul.com.br') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // always returns
        if (stripos($parser->getHTMLBody(), '@voeazul.com.br') !== false) {
            return true;
        } else {
            return false;
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->itineraries['Kind'] = 'T';
        $this->itineraries['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., 'Seu código de reserva é')]/ancestor::td[1]/following-sibling::td");
        $passenger_names = $this->http->XPath->query("//text()[contains(., 'Nome:')]");

        if ($passenger_names->length > 0) {
            foreach ($passenger_names as $passenger_name) {
                $this->itineraries['Passengers'][] = $this->http->FindSingleNode("./following-sibling::strong", $passenger_name);
            }
        }
        $status = $this->http->FindSingleNode("//text()[contains(., 'Status:')]/following-sibling::span");
        $this->itineraries['Cancelled'] = ($status == 'Confirmed') ? false : true;
        $fields = [
            'TotalCharge' => 'Total:',
            'BaseFare'    => 'Tarifa:',
            'Tax'         => 'Taxa de embarque:',
        ];

        foreach ($fields as $name => $value) {
            $value = $this->http->FindSingleNode("//text()[contains(., '" . $value . "')]/ancestor::tr[1]/td[2]");

            if (preg_match("/[\.\d]*$/", $value, $matches)) {
                $this->itineraries[$name] = $matches[0];
            }

            if ($name == 'TotalCharge' && preg_match("/^[\D]+/", $value, $m)) {
                $this->itineraries['Currency'] = trim($m[0]);
            }
        }
        $this->itineraries['Status'] = $status;
        $this->itineraries['ReservationDate'] = $this->_buildDate(date_parse_from_format(self::DATE_FORMAT, $this->http->FindSingleNode("//text()[contains(., 'Data da reserva:')]/following-sibling::span")));
        $nodes = $this->http->XPath->query("//td[contains(., 'Voo:') and not(.//td)]/ancestor::table[1]");

        foreach ($nodes as $node) {
            $segment = [];

            if (preg_match("/Voo\:\s*([A-Z\d]{2})\s*(\d{1,5})\s*$/", $this->http->FindSingleNode(".//text()[contains(., 'Voo:')]", $node), $matches)) {
                $segment['AirlineName'] = $matches[1];
                $segment['FlightNumber'] = $matches[2];
            }

            if (preg_match("/([A-Z]{3})\s([^\(]*)[^\/]*\s*\/\s*([A-Z]{3})\s([^\(]*).*Saída\:\s([\d\:]*).*Chegada\:\s([\d\:]*)/", $this->http->FindSingleNode(".//text()[contains(., 'Voo:')]/ancestor::tr[1]/following-sibling::tr[2]", $node), $matches)) {
                $segment['DepCode'] = $matches[1];
                $segment['DepName'] = trim($matches[2]);

                if (preg_match("/[\/\d]*$/", $this->http->FindSingleNode(".//text()[contains(., 'Voo:')]/ancestor::tr[3]/preceding-sibling::tr[contains(., 'Ida')]", $node), $depDateMatches)) {
                    $depDate = $depDateMatches[0];
                }
                $segment['DepDate'] = $this->_buildDate(date_parse_from_format(self::DATE_TIME_FORMAT, $depDateMatches[0] . " " . $matches[5]));
                $segment['ArrCode'] = $matches[3];
                $segment['ArrName'] = trim($matches[4]);
                $segment['ArrDate'] = $this->_buildDate(date_parse_from_format(self::DATE_TIME_FORMAT, $depDateMatches[0] . " " . $matches[6]));
            }
            $segment['Seats'] = $this->http->FindSingleNode("//text()[contains(., 'Assento :')]/following-sibling::span");
            $this->itineraries['TripSegments'][] = $segment;
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$this->itineraries],
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return ["pt"];
    }

    private function СheckMail($input = '')
    {
        preg_match('/([\.@]voeazul\.com\.br)/ims', $input, $matches);

        return (isset($matches[0])) ? true : false;
    }

    private function _buildDate($parsedDate)
    {
        return mktime((isset($parsedDate['hour'])) ? $parsedDate['hour'] : 0, (isset($parsedDate['minute'])) ? $parsedDate['minute'] : 0, 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }
}
