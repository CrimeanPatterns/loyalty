<?php

namespace AwardWallet\Engine\airfrance\Email;

class Itinerary1 extends \TAccountChecker
{
    public const DATE_FORMAT = 'j F Y H:i';
    public const DATE_FORMAT_PLAIN = 'Y dM, H:i';
    public const DATE_FORMAT_CONF = 'd M Y H:i';

    public $find = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'novembre', 'décembre'];
    public $replace = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && in_array($headers["from"], ['ElectronicTicketing@email.airfrance.fr', 'info@service.airfrance.com']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getBody();

        return stripos($body, 'airfrance.fr') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = $this->http->FindSingleNode('//strong[contains(text(), "de votre dossier")]/../../../font[2]');

        if ($itineraries['RecordLocator']) {
            $this->parseHtmlEmail($itineraries);
        } else {
            $this->parsePlainHtmlEmail($itineraries);
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function floatval($val)
    {
        return floatval(preg_replace('/\,/', '.', $val));
    }

    public function stringDateToUnixtime($date, $format = self::DATE_FORMAT)
    {
        $date = str_replace($this->find, $this->replace, strtolower($date));
        $date = date_parse_from_format($format, $date);

        return mktime($date['hour'], $date['minute'], 0, $date['month'], $date['day'], $date['year']);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]airfrance\.(com|fr)$/ims', $from);
    }

    /**
     * @param $itineraries
     *
     * @return mixed
     */
    public function parseHtmlEmail(&$itineraries)
    {
        $itineraries['BaseFare'] = $this->floatval($this->http->FindSingleNode('//font[contains(text(), "Tarif hors taxes")]/../following-sibling::td[1]'));
        $itineraries['Currency'] = $this->http->FindSingleNode('//font[contains(text(), "Tarif hors taxes")]/../following-sibling::td[2]');
        $itineraries['Tax'] = $this->floatval($this->http->FindSingleNode('//font[contains(text(), "Taxes et surcharges")]/../following-sibling::td[1]'));
        $itineraries['TotalCharge'] = $this->floatval($this->http->FindSingleNode('//font[contains(text(), "Montant total")]/../../following-sibling::td[1]'));

        $segments = [];
        $matches = [];

        //TODO for many segments may be following-sibling::table, but now we doesn't know separators and format between trips, code above will be common
        $tripInfoRows = $this->http->XPath->query('//strong[contains(text(), "Votre voyage")]/../following-sibling::table[1]');
        $tripRows = [];

        foreach ($tripInfoRows as $row) {
            $tripRows[] = $row;
        }

        foreach ($tripRows as $row) {
            $tripSegment = [];
            $info = $this->http->XPath->query(".//table[1]/tbody/tr/td", $row)->item(0);
            $date = $this->http->FindSingleNode('./font[3]/font/font', $info);
            $date = preg_replace('/^\w+\s+/', '', $date);
            $tripTable = $this->http->XPath->query('./table', $info);
            $flightAndClass = $this->http->FindSingleNode('.//tr[1]/td[1]', $tripTable->item(0));

            if (preg_match('/(.*)\s*\-\s*(.*)/', $flightAndClass, $matches)) {
                $tripSegment['FlightNumber'] = trim($matches[1]);
                $tripSegment['Cabin'] = trim($matches[2]);
            }
            $depTime = $this->http->FindSingleNode('.//tr[1]/td[2]', $tripTable->item(0));
            $tripSegment['DepDate'] = $this->stringDateToUnixtime($date . ' ' . $depTime);
            $depInfo = $this->http->FindSingleNode('.//tr[1]/td[3]', $tripTable->item(0));

            if (preg_match('/(.*),\s*.*\((.*)\)/', $depInfo, $matches)) {
                $tripSegment['DepName'] = trim($matches[1]);
                $tripSegment['DepCode'] = trim($matches[2]);
            }
            $arrTime = $this->http->FindSingleNode('.//tr[3]/td[2]', $tripTable->item(0));
            $tripSegment['ArrDate'] = $this->stringDateToUnixtime($date . ' ' . $arrTime);
            $arrInfo = $this->http->FindSingleNode('.//tr[3]/td[3]', $tripTable->item(0));

            if (preg_match('/(.*),\s*.*\((.*)\)/', $arrInfo, $matches)) {
                $tripSegment['ArrName'] = trim($matches[1]);
                $tripSegment['ArrCode'] = trim($matches[2]);
            }
            $tripSegment['Aircraft'] = $this->http->FindSingleNode('.//strong[contains(text(), "Appareil")]/../../following-sibling::font[1]', $info);
            $tripSegment['Duration'] = $this->http->FindSingleNode('.//strong[contains(text(), "Temps de vol")]/..', $info, true, '/Temps de vol:\s*(.*)/');

            $segments[] = $tripSegment;
        }

        $itineraries['TripSegments'] = $segments;
    }

    public function parsePlainHtmlEmail(&$itineraries)
    {
        $itineraries['RecordLocator'] = $this->http->FindPreg('/Booking Ref\. :\s*([^<]*)/');

        if ($itineraries['RecordLocator']) {
            $itineraries['Passengers'] = $this->http->FindSingleNode('//span[contains(text(), "Passager")]/../../../../following-sibling::tr[1]/td[1]');

            $segments = [];

            $tripSegment = [];
            $tripSegment['DepName'] = $this->http->FindSingleNode('//span[contains(text(), "From")]', null, true, '/From\s*\:\s*(.*)/');
            $tripSegment['ArrName'] = $this->http->FindSingleNode('//span[contains(text(), "To : ")]', null, true, '/To\s*\:\s*(.*)/');
            $tripSegment['FlightNumber'] = $this->http->FindSingleNode('//span[contains(text(), "Flight :")]', null, true, '/Flight\s*\:\s*(\w+)/');
            $depDate = trim($this->http->FindSingleNode('//span[contains(text(), "Departure :")]', null, true, '/Departure\s*\:\s*(.*)\s*Latest/'));
            $tripSegment['DepDate'] = $this->stringDateToUnixtime(date('Y') . ' ' . $depDate, $this::DATE_FORMAT_PLAIN);
            $arrDate = trim($this->http->FindSingleNode('//span[contains(text(), "Arrival :")]', null, true, '/Arrival\s*\:\s*(.*)/'));
            $tripSegment['ArrDate'] = $this->stringDateToUnixtime(date('Y') . ' ' . preg_split('/\,/', $depDate)[0] . ', ' . $arrDate, $this::DATE_FORMAT_PLAIN);
            $tripSegment['Cabin'] = trim($this->http->FindSingleNode('(//span[contains(text(), "Class")])[2]', null, true, '/Class\s*\w*\s*\:\s*(.+)/u'));

            $tripSegment['DepCode'] = $tripSegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            $segments[] = $tripSegment;
            $itineraries['TripSegments'] = $segments;
        } else {
            //confirmation type
            $itineraries['RecordLocator'] = $this->http->FindPreg('/Référence de votre dossier\s*:\s*\*\s*\*([^\*]+)/');
            $rows = preg_split('/\n/', $this->http->Response['body']);
            $trip = [];
            $matches = [];

            while (count($rows) > 1) {
                array_shift($rows);

                if (stripos($rows[0], '*Votre vol') !== false) {
                    break;
                }
            }

            $tripString = '';

            while (count($rows) > 1) {
                $tripString .= $rows[0];
                array_shift($rows);

                if ($rows[0] == '') {
                    break;
                }
            }

            if (preg_match('/(?P<date>\d+\s+\S+\s+\d+)\s*(?P<fnumber>\S+)\s*\-\s*(?P<class>\S*)\s*\*(?P<deptime>[\d\:]*)\*\s*\*(?P<depname>[^\*]+)\*\,\s*([^\(]*)\((?P<depcode>[^\)]+)\)[^\d+]*(?P<arrtime>[\d\:]+)\s*\*[\d\:]+\*\s*\*(?P<arrname>[^\*]+)\*[^\(]+\((?P<arrcode>[^\)]+)\).*\*Appareil \:\*(?P<aircraft>[^\*]*)/', $tripString, $matches)) {
                $trip['FlightNumber'] = $matches['fnumber'];
                $trip['Cabin'] = $matches['class'];
                $date = $matches['date'];
                $trip['DepDate'] = $this->stringDateToUnixtime($date . ' ' . $matches['deptime'], $this::DATE_FORMAT_CONF);
                $trip['DepName'] = trim($matches['depname']);
                $trip['DepCode'] = trim($matches['depcode']);
                $trip['ArrDate'] = $this->stringDateToUnixtime($date . ' ' . $matches['arrtime'], $this::DATE_FORMAT_CONF);
                $trip['ArrName'] = trim($matches['arrname']);
                $trip['ArrCode'] = trim($matches['arrcode']);
                $trip['Aircraft'] = trim($matches['aircraft']);
            }

            $itineraries['TripSegments'][] = $trip;

            if (preg_match('/Tarif hors taxes :\s*\*([\d+\,]+)\*\s*\*\s*([^\*]*)\*/', $this->http->Response['body'], $matches)) {
                $itineraries['BaseFare'] = $this->floatval($matches[1]);
                $itineraries['Currency'] = trim($matches[2]);
            }
            $itineraries['Tax'] = $this->floatval($this->http->FindPreg('/Taxes et surcharges :\s*\*[\n\s]*([\d+\,]+)/'));
            $itineraries['TotalCharge'] = $this->floatval($this->http->FindPreg('/Montant total payé en ligne\*\s*\*[\n\s]*([\d+\,]+)/'));
        }
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "fr"];
    }
}
