<?php

namespace AwardWallet\Engine\bcd\Email;

class ItPlainTXT extends \TAccountChecker
{
    public $xPath = "";
    public $mailFiles = "bcd/it-4751043.eml";
    public $emailDate;
    public $year;
    public $plainBody;

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["es"];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->emailDate = strtotime($parser->getHeader('date'));
        $this->year = date("Y", $this->emailDate);
        $this->plainBody = $parser->getPlainBody();
        $result = [];

        foreach (splitter("#PASSENGER\s+ITINERARY\s+RECEIPT(.+?)#", $this->plainBody) as $it) {
            $res = ['Kind' => 'T'];
            $res['RecordLocator'] = $this->re("#BOOKING\s+REF\.\s+\/CODIGO\s+DE\s+RESERVA\s+\:\s+.+?\/\s*([A-Z\d-]+)#", $it);
            $res['ReservationDate'] = $this->totime($this->re("#ISSUE DATE\/FECHA DE EMISION:\s*(\d+\s+\w+\s+\d+)#", $it));

            if (preg_match('#NAME\/NOMBRE:[\s\n]*(\w+)\/(\w+)#', $it, $m)) {
                $res['Passengers'][] = $m[2] . ' ' . $m[1];
            }
            //						TOTAL : USD 158.07
            if (preg_match("#TOTAL\s*:\s*(\S{1,3})\s+([\d\.\,]+)#", $it, $m)) {
                $res['Currency'] = $m[1];
                $res['TotalCharge'] = $this->cost($m[2]);
            }

            $res['Tax'] = $this->cost($this->re("#TAX\/IMPUESTOS\s*:\s*\S{1,3}\s+([\d\.\,]+)#si", $it));
            $res['BaseFare'] = $this->cost($this->re("#AIR\s+FARE\/TARIFA\s*:\s*\S{1,3}\s+([\d\.\,]+)#si", $it));

            $txt = re("#FROM\/TO.+ESTATUS(.+?)AL\s+MOMENTO\s+DEL#s", $it);
            $segs = [];

            foreach (splitter("#(\s*\w+\s+\w+\s+\w\s+\d{1,2}[A-Z]{3}\s+\d{4}\s+\w+\s+\w+\s+\w+\s+\w+)#", $txt) as $sg) {
                if (preg_match('#\s*(?<DepName>\w+)\s+(?<FlightNumber>\w+)\s+(?<HZ>\w)\s+(?<DepDate>\d{1,2}[A-Z]{3})\s+(?<DepTime>\d{4})\s+\w+\s+\w+\s+(?<Status>\w+)\s+(?<ArrName>\w+)#s', $sg, $m)) {
                    if (preg_match('#(\S{2})(\d+)#', $m['FlightNumber'], $m2)) {
                        $segs['AirlineName'] = $m2[1];
                        $segs['FlightNumber'] = $m2[2];
                    }
                    $segs['DepCode'] = $segs['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $segs['DepDate'] = strtotime($m['DepDate'] . " " . $this->year . " " . $m['DepTime']);
                    $segs['ArrDate'] = MISSING_DATE;
                    $segs['DepName'] = $m['DepName'];
                    $segs['ArrName'] = $m['ArrName'];
                }
            }
            $res['TripSegments'][] = $segs;
            $result[] = $res;
        }

        return [
            'parsedData' => ['Itineraries' => $result],
        ];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && preg_match("#\@bcdtravel\.com#i", $headers['from']) > 0)
        || (isset($headers['subject']) && (preg_match("#BCD TRAVEL#i", $headers['subject']) > 0))
        || (isset($headers['subject']) && (preg_match("#Reserva de Hotel#", $headers['subject']) > 0));
        //stripos not worked. xz
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        //  forwarded message

        $body = $parser->getHtmlBody();

        return stripos($body, 'BCD Travel') !== false && stripos($body, 'RECIBO DE ITINERARIO DE TICKET ELECTRONICO') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@bcd') !== false;
    }

    protected function re($re, $text = null, $index = 1)
    {
        if (!$text) {
            $text = $this->plainBody;
        }

        if (preg_match($re, $text, $m)) {
            return $m[$index] ?? $m[0];
        } else {
            return null;
        }
    }

    protected function totime($dateStr, $anchor = null) // usualy "anchor" is a "reservation date"
    {
        $dateStr = trim($dateStr);

        if (!$dateStr || $dateStr == ',') {
            return false;
        }

        $date = strtotime($dateStr);

        if (!$date) {
            return false;
        }

        // correct if year doesn't exist
        $yDate = strtotime($dateStr, 1);
        $noYear = ($yDate < 24 * 3600 * 365) ? true : false;

        if ($noYear) {
            if ($anchor) {
                $anchor = isUnixtime($anchor) ? $anchor : strtotime($anchor);

                if (!$anchor) {
                    return false;
                }

                // compare date to be greater than anchor
                if ($yDate < $anchor) {
                    $years = date('Y', $anchor) - date('Y', $yDate);
                    $yDate = strtotime("+$years year", $yDate);

                    // still lower? ok, add 1 year
                    if ($yDate < $anchor) {
                        $yDate = strtotime("+1 year", $yDate);
                    }

                    $date = $yDate;
                }
            }

            if (!$anchor) {
                if ($date < strtotime('-6 month')) {
                    $date = strtotime('+1 year', $date);
                }
            }
        }

        return $date;
    }

    protected function cost($value)
    {
        if (preg_match('#\d+\s*([,.])\s*\d+\s*[,.]\s*\d+#', $value, $m)) {
            $value = str_replace($m[1], '', $value);
        }

        $value = str_replace(',', '.', $value);
        $value = preg_replace("#[^\d\.]#", '', $value);

        // (float) <- potential bug
        return is_numeric($value) ? (float) number_format($value, 2, '.', '') : null;
    }

    protected function splitter($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
