<?php

namespace AwardWallet\Engine\budgetair\Email;

class It3327776 extends \TAccountCheckerExtended
{
    public $mailFiles = "budgetair/it-3327776.eml";
    public $reBody = 'budgetair.ru';
    public $reBody2 = "Авиаперелет";
    public $reSubject = "budgetair.ru - номер подтверждения заказа";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody => function (&$itineraries) {
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Код бронирования:']/following::text()[normalize-space(.)][1]");
                // TripNumber
                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='Пассажир']/ancestor::table[1]/tbody/tr/th[1][normalize-space(.)]", null, "#^\d+\.\s*(.+)#");
                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = cost($this->http->FindSingleNode("//*[contains(text(),'Итого')]/em"));

                // BaseFare
                // Currency
                $currency = $this->http->FindSingleNode("//*[contains(text(),'Итого')]/em", null, true, "#\D+$#");

                if ($currency == 'P') {
                    $it['Currency'] = 'RUB';
                } else {
                    $it['Currency'] = $currency;
                }

                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                $header = $this->http->XPath->query("//*[contains(text(), '№ рейса')]/ancestor::tr[1]")->item(0);
                $body = $this->http->XPath->query("//*[contains(text(), '№ рейса')]/ancestor::table[1]/tbody/tr");
                $items = [];

                $cols = [];
                $rows = [];

                foreach ($this->http->XPath->query("./*", $header) as $col) {
                    $cols[count($this->http->FindNodes("./preceding-sibling::*", $col))] = $this->http->FindSingleNode(".", $col);
                }

                foreach ($body as $row) {
                    $data = [];

                    foreach ($this->http->XPath->query("./*", $row) as $col) {
                        $data[$cols[count($this->http->FindNodes("./preceding-sibling::*", $col))]] = $this->http->FindSingleNode(".", $col);
                    }
                    $rows[] = $data;
                }

                for ($i = 0; $i < count($rows); $i = $i + 2) {
                    $row = $rows[$i];
                    $row2 = $rows[$i + 1];
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = re("#\w{2}(\d+)#", $this->getField($row, "№ рейса / время в пути"));

                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = $this->getField($row, "Маршрут");

                    // DepDate
                    $itsegment['DepDate'] = strtotime(
                        en($this->normalizeDate($this->getField($row, "Дата")))
                        . ', ' . $this->getField($row, "Вылет/Прилет")
                    );

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = $this->getField($row2, "Маршрут");

                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(
                        en($this->normalizeDate(orval(
                            $this->getField($row2, "Дата"),
                            $this->getField($row, "Дата")
                        ))) . ', ' .
                        $this->getField($row2, "Вылет/Прилет")
                    );

                    // AirlineName
                    $itsegment['AirlineName'] = re("#(\w{2})\d+#", $this->getField($row, "№ рейса / время в пути"));

                    // Aircraft
                    // TraveledMiles
                    $itsegment['TraveledMiles'] = $this->getField($row2, "Расстояние");

                    // Cabin
                    $itsegment['Cabin'] = $this->getField($row2, "Тип/класс самолета");

                    // BookingClass
                    // PendingUpgradeTo
                    // Seats
                    // Duration
                    // Meal
                    // Smoking
                    // Stops
                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["ru"];
    }

    private function getField($row, $field)
    {
        if (isset($row[$field])) {
            return $row[$field];
        }

        return null;
    }

    private function normalizeDate($dateStr)
    {
        $replaces = [
            [
                "#^\S+,\s+(\d+-\S+-\d{4})$#i",
            ],
            [
                "$1",
            ],
        ];

        return preg_replace($replaces[0], $replaces[1], $dateStr);
    }
}
