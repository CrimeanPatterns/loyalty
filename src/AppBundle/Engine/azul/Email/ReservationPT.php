<?php

namespace AwardWallet\Engine\azul\Email;

class ReservationPT extends \TAccountChecker
{
    public $mailFiles = "azul/it-4421385.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ReservationPT",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (stripos($parser->getHTMLBody(), 'voeazul.com.br') !== false) {
            return true;
        } else {
            return false;
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "@voeazul.com.br") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "Azul") !== false;
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//p[contains(@style,'038ACC') and contains(text(),'Seu código de reserva é')]/span");
        $it['ReservationDate'] = strtotime(str_replace('/', '-', $this->http->FindSingleNode("//td[contains(@style,'7b8284') and contains(text(),'Data da reserva:')]/span", null, "#\d{1,2}\\\d{1,2}\\\d{1,2}#")));
        $PASS_SEATS = "//td[contains(@style,'002d62') and contains(text(),'Passageiros')]/../../tr//td[contains(@style,'666666') and contains(text(),'Nome')]/";
        $it['Passengers'] = array_unique($this->http->FindNodes($PASS_SEATS . "strong"));

        $fields = [
            'TotalCharge' => 'Total:',
            'BaseFare'    => 'Tarifa:',
            'Tax'         => 'Taxa de embarque:',
        ];

        foreach ($fields as $name => $value) {
            $value = $this->http->FindSingleNode("//td[contains(.,'" . $value . "')]/following-sibling::td[contains(@style,'color')]");

            if ($value && preg_match("#(\S+)\s+([\d,\.]+)#", $value, $matches)) {
                $it[$name] = cost($matches[2]);

                if ($name == 'TotalCharge') {
                    $it['Currency'] = currency($matches[1]);
                }
            }
        }

        //Seats
        $arrSeats = $this->http->FindNodes($PASS_SEATS . "span");

        $arrDepArrCodes = $this->http->FindNodes("//img[contains(@src,'imagens/ico-')]/../..//td[contains(@style,'#666')]/span", null, "#[A-Z]{3}#");
        $arrFlightInfo = $this->http->FindNodes("//img[contains(@src,'imagens/ico-')]/../..//td[contains(.,'Voo:') and contains(@style,'02b9e5')]");
        $arrDepStruct = $this->http->FindNodes("//img[contains(@src,'imagens/ico-')]/../..//td[contains(@style,'#666')]/span/following-sibling::text()");
        $arrDepArrDate = $this->http->FindNodes("//img[contains(@src,'imagens/ico-')]/preceding::td[contains(@bgcolor,'02b9e5')]/strong/following-sibling::text()", null, "#\s+(\d{1,2}\/\d{1,2}\/\d{4})#");
        //		$arrSeats = $this->http->FindNodes("//tr[contains(@style,'#808080')]//td//img[contains(@src,'email_mkt-icon-seat.png')]/../text()[normalize-space(.)!='']");
        foreach ($arrFlightInfo as $i => $v) {
            $segs = [];

            if (preg_match("#([a-z]+?)\s+(\d+)#i", $v, $m)) {
                $segs['FlightNumber'] = trim($m[2]);
                $segs['AirlineName'] = trim($m[1]);
            }

            if (isset($arrSeats) && !empty($arrSeats)) {
                $segs['Seats'] = '';

                foreach ($it['Passengers'] as $j => $v2) {
                    $segs['Seats'] .= $arrSeats[$j * count($it['Passengers']) + $i] . ',';
                }
                $segs['Seats'] = substr($segs['Seats'], 0, -1);
            }

            $this->http->Log($arrDepStruct[$i * 3] . '  ' . $arrDepStruct[$i * 3 + 1]);

            if (preg_match("#(.+)\s*\([A-Z]{3}\)#", $arrDepStruct[$i * 3], $m)) {
                $segs['DepName'] = trim($m[1]);
            }

            if (preg_match("#(.+)\s*\([A-Z]{3}\)#", $arrDepStruct[$i * 3 + 1], $m)) {
                $segs['ArrName'] = trim($m[1]);
            }

            if (preg_match("#Saída:\s+(\d{1,2}:\d{1,2})\s+\-\s+Chegada:\s(\d{1,2}:\d{1,2})#", $arrDepStruct[$i * 3 + 2], $m)) {
                $segs['DepDate'] = strtotime(str_replace('/', '-', $arrDepArrDate[$i] . '  ' . $m[1]));
                $segs['ArrDate'] = strtotime(str_replace('/', '-', $arrDepArrDate[$i] . '  ' . $m[2]));
            }
            $segs['DepCode'] = $arrDepArrCodes[$i * count($arrFlightInfo)];
            $segs['ArrCode'] = $arrDepArrCodes[$i * count($arrFlightInfo) + 1];
            //			}

            $it['TripSegments'][] = $segs;
        }

        return [$it];
    }
}
