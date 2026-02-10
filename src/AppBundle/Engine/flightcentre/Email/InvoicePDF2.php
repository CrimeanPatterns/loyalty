<?php

namespace AwardWallet\Engine\flightcentre\Email;

use AwardWallet\Engine\MonthTranslate;

class InvoicePDF2 extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-10782231.eml, flightcentre/it-10782241.eml, flightcentre/it-10958681.eml, flightcentre/it-11010130.eml, flightcentre/it-6815259.eml, flightcentre/it-8778989.eml, flightcentre/it-8795914.eml, flightcentre/it-8796984.eml";

    public $reFrom = "flightcentre.com.au";
    public $reBody = [
        'en' => ['Invoice', 'Flight Centre Travel'],
    ];
    public $reSubject = [
        'Invoice',
    ];
    public $lang = '';
    public $date;
    /** @var \HttpBrowser */
    public $pdf;
    public $pdfNamePattern = "(?:Invoice.*|[\w ]+Itinerary\.|Quote.*)pdf";
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $NBSP = chr(194) . chr(160);
                    $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));

                    if ($this->pdf->XPath->query("//text()[normalize-space(.)='FLIGHTS']/following::p[normalize-space(.)!=''][position()<4][normalize-space(.)='Airline']")->length > 0) {
                        $subIts = $this->parseEmailAir();

                        foreach ($subIts as $it) {
                            $its[] = $it;
                        }
                    }
                    $subIts = $this->parseEmailTransfer();

                    foreach ($subIts as $it) {
                        $its[] = $it;
                    }
                    $subIts = $this->parseEmailHotel();

                    foreach ($subIts as $it) {
                        $its[] = $it;
                    }
                    $subIts = $this->parseEmailTour();

                    foreach ($subIts as $it) {
                        $its[] = $it;
                    }
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
        $its = $this->mergeItineraries($its);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'InvoicePDF2' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 4;
        $cnt = count(self::$dict) * $types;

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    protected function mergeItineraries($its)
    {
        $its2 = $its;

        foreach ($its2 as $i => $it) {
            if (isset($it['RecordLocator']) && $i && ($j = $this->findRL($i, $it['RecordLocator'], $its)) != -1) {
                if (isset($its[$j]['TripSegments'])) {
                    foreach ($its[$j]['TripSegments'] as $flJ => $tsJ) {
                        foreach ($its[$i]['TripSegments'] as $flI => $tsI) {
                            if (isset($tsI['FlightNumber']) && isset($tsJ['FlightNumber']) && ((int) $tsJ['FlightNumber'] === (int) $tsI['FlightNumber'])
                                && (isset($tsJ['Seats']) || isset($tsI['Seats']))
                            ) {
                                $new = "";

                                if (isset($tsJ['Seats'])) {
                                    $new .= "," . $tsJ['Seats'];
                                }

                                if (isset($tsI['Seats'])) {
                                    $new .= "," . $tsI['Seats'];
                                }
                                $new = implode(",", array_filter(array_unique(array_map("trim", explode(",", $new)))));
                                $its[$j]['TripSegments'][$flJ]['Seats'] = $new;
                                $its[$i]['TripSegments'][$flI]['Seats'] = $new;
                            }
                        }
                    }

                    $its[$j]['TripSegments'] = array_merge($its[$j]['TripSegments'], $its[$i]['TripSegments']);
                    $its[$j]['TripSegments'] = array_map('unserialize', array_unique(array_map('serialize', $its[$j]['TripSegments'])));
                }

                if (isset($its[$j]['Passengers']) || isset($its[$i]['Passengers'])) {
                    $new = "";

                    if (isset($its[$j]['Passengers'])) {
                        $new .= ";" . implode(";", $its[$j]['Passengers']);
                    }

                    if (isset($its[$i]['Passengers'])) {
                        $new .= ";" . implode(";", $its[$i]['Passengers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(";", $new)))));
                    $its[$j]['Passengers'] = $new;
                }

                if (isset($its[$j]['AccountNumbers']) || isset($its[$i]['AccountNumbers'])) {
                    $new = "";

                    if (isset($its[$j]['AccountNumbers'])) {
                        $new .= "," . implode(",", $its[$j]['AccountNumbers']);
                    }

                    if (isset($its[$i]['AccountNumbers'])) {
                        $new .= "," . implode(",", $its[$i]['AccountNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(",", $new)))));
                    $its[$j]['AccountNumbers'] = $new;
                }

                if (isset($its[$j]['TicketNumbers']) || isset($its[$i]['TicketNumbers'])) {
                    $new = "";

                    if (isset($its[$j]['TicketNumbers'])) {
                        $new .= "," . implode(",", $its[$j]['TicketNumbers']);
                    }

                    if (isset($its[$i]['TicketNumbers'])) {
                        $new .= "," . implode(",", $its[$i]['TicketNumbers']);
                    }
                    $new = array_values(array_filter(array_unique(array_map("trim", explode(",", $new)))));
                    $its[$j]['TicketNumbers'] = $new;
                }

                unset($its[$i]);
            }
        }

        return array_values($its);
    }

    protected function findRL($g_i, $rl, $its)
    {
        foreach ($its as $i => $it) {
            if (isset($it['RecordLocator']) && $g_i != $i && $it['RecordLocator'] === $rl) {
                return $i;
            }
        }

        return -1;
    }

    private function inLine($str)
    {
        return str_replace("\n", " ", $str);
    }

    private function parseEmailAir()
    {
        //position()<4 - need for things when itinerary from page to page
        $nodes = $this->pdf->XPath->query("//text()[normalize-space(.)='FLIGHTS'][following::p[normalize-space(.)!=''][position()<4][starts-with(normalize-space(.),'Airline')]]/ancestor::p[1]");
        $its = [];

        foreach ($nodes as $i => $node) {
            $num = $i + 1;
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Reference:')]", null, true, "#:\s+([A-Z\d]+)#");

            if (empty($it['TripNumber'])) {
                $it['TripNumber'] = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'QUOTE')]", null, true, "#\#\s+([A-Z\d]+)#");
            }
            //$it['TripNumber'] = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'INVOICE')]", null, true, "#\#\s+([A-Z\d]+)#");
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//text()[starts-with(normalize-space(.),'Issue date:')])[1]", null, true, "#:\s+(.+?)\s*(?:\(|$)#")));

            if (empty($it['ReservationDate'])) {
                $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//text()[starts-with(normalize-space(.),'Printed date:')])[1]", null, true, "#:\s+(.+?)\s*(?:\(|$)#")));
            }
            $it['Passengers'] = $this->pdf->FindNodes("//text()[starts-with(normalize-space(.),'Passenger ')]/ancestor::p[1]/following::p[normalize-space(.)!=''][1]");

            $c = $this->countPBetween(['FLIGHTS', 'Airline'], 'Travellers:', $num);
            $n = $c + 50;
            $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("(//text()[normalize-space(.)='FLIGHTS'][following::p[normalize-space(.)!=''][position()<4][starts-with(normalize-space(.),'Airline')]]/ancestor::p[1])[{$num}]/following::p[position()<{$n}][contains(.,'Total Flight Price:')]"));

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $segstr = implode("\n", $this->pdf->FindNodes("(//text()[normalize-space(.)='FLIGHTS'][following::p[normalize-space(.)!=''][position()<4][starts-with(normalize-space(.),'Airline')]]/ancestor::p[1])[{$num}]/following::p[position()<{$c}]"));
            $segments = $this->splitter("#^\s*(?:Status|Confirmed)\n((?:[^\n]+\n){5})#m", $segstr);

            foreach ($segments as $segment) {
                $seg = [];

                if (preg_match("#(.+?)\n(\d+)\n(.+)\s+Cabin\s+Class:(.+?)\n(\d+\/\d+\/\d+\s+\d+:\d+.*?)\n(\d+\/\d+\/\d+\s+\d+:\d+.*?)\n(\d+\s*h[rs]*\s*\d+\s*m[in]*)#s", $segment, $m)) {
                    if (preg_match("#(.+?)\s+operated\s+by:\s+(.+)#is", $m[1], $v)) {
                        $seg['AirlineName'] = $this->inLine($v[1]);
                        $seg['Operator'] = $this->inLine($v[2]);
                    } else {
                        $seg['AirlineName'] = $this->inLine($m[1]);
                    }
                    $seg['FlightNumber'] = $m[2];
                    $seg['Aircraft'] = $this->inLine($m[3]);
                    $seg['Cabin'] = $this->inLine($m[4]);
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[5]));
                    $seg['ArrDate'] = strtotime($this->normalizeDate($m[6]));
                    $seg['Duration'] = $this->inLine($m[7]);
                }

                if (preg_match("#\(([A-Z]{3})\)[\s\-]+(.+?)\(([A-Z]{3})\)[\s\-]+(.+?)(?:Confirmed|$)#s", $segment, $m)) {
                    $seg['DepCode'] = $m[1];
                    $seg['DepName'] = $this->inLine($m[2]);
                    $seg['ArrCode'] = $m[3];
                    $seg['ArrName'] = $this->inLine($m[4]);
                }

                if (isset($seg['FlightNumber'])) {//exclude header on new page
                    $it['TripSegments'][] = $seg;
                }
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailTransfer()
    {
        $nodes = $this->pdf->XPath->query("//text()[normalize-space(.)='TRANSFER'][following::p[normalize-space(.)!=''][1][starts-with(normalize-space(.),'Company')]]/ancestor::p[1]");
        $its = [];

        foreach ($nodes as $i => $node) {
            $num = $i + 1;
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Reference:')]", null, true, "#:\s+([A-Z\d]+)#");
            //$it['TripNumber'] = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'INVOICE')]", null, true, "#\#\s+([A-Z\d]+)#");
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//text()[starts-with(normalize-space(.),'Issue date:')])[1]", null, true, "#:\s+(.+?)\s*(?:\(|$)#")));
            $it['Passengers'] = $this->pdf->FindNodes("//text()[starts-with(normalize-space(.),'Passenger ')]/ancestor::p[1]/following::p[normalize-space(.)!=''][1]");
            $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

            $c = $this->countPBetween(['TRANSFER', 'Company'], 'Total Transfer Price', $num);
            $n = $c + 5;
            $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("(//text()[normalize-space(.)='TRANSFER'][following::p[normalize-space(.)!=''][1][starts-with(normalize-space(.),'Company')]]/ancestor::p[1])[{$num}]/following::p[position()<{$n}][contains(.,'Total Transfer Price')]"));

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $segment = implode("\n", $this->pdf->FindNodes("(//text()[normalize-space(.)='TRANSFER'][following::p[normalize-space(.)!=''][1][starts-with(normalize-space(.),'Company')]]/ancestor::p[1])[{$num}]/following::p[position()<{$n}]"));
            $seg = [];
            $seg['Type'] = $this->re("#Type:\s+(.+)#", $segment);
            $seg['ArrDate'] = MISSING_DATE;

            if (preg_match("#Pick-up:\s+([A-Z]{3})\s+on\s+(\d+\/\d+\/\d+)#", $segment, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[2]));
            } elseif (preg_match("#Pick-up:\s+(.*?)\s*\(([A-Z]{3})\)\s+on\s+(\d+\/\d+\/\d+.*)\s*Drop-off#", $segment, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[3]));
            } elseif (preg_match("#Pick-up[\s:]+(.+)\s+on\s+(\d+\/\d+\/\d+.*?)\s*Drop-off#", $segment, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepDate'] = strtotime($this->normalizeDate($m[2]));
            }

            if (preg_match("#Drop-off:\s+([A-Z]{3})#", $segment, $m)) {
                $seg['ArrCode'] = $m[1];
            } elseif (preg_match("#Drop-off:\s+(.+)\s+\(([A-Z]{3})\)#", $segment, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
            } else {
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                if (preg_match("#Drop-off[\s:]+(.+)#", $segment, $m)) {
                    $seg['ArrName'] = $m[1];
                }
            }

            if (preg_match("#Details:\s+(.+?)(?:\-|to)([^\n]+?)\s*(?:\-(?:OW|RT)|$)#", $segment, $m)) {
                if (!isset($seg['DepName'])) {
                    $seg['DepName'] = $m[1];
                }

                if (!isset($seg['ArrName'])) {
                    $seg['ArrName'] = $m[2];
                }
            }

            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailHotel()
    {
        $nodes = $this->pdf->XPath->query("//text()[normalize-space(.)='ACCOMMODATION']/ancestor::p[1]");
        $its = [];

        foreach ($nodes as $i => $node) {
            $num = $i + 1;
            $it = ['Kind' => 'R'];
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Reference:')]", null, true, "#:\s+([A-Z\d]+)#");
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//text()[starts-with(normalize-space(.),'Issue date:')])[1]", null, true, "#:\s+(.+?)\s*(?:\(|$)#")));
            $it['GuestNames'] = $this->pdf->FindNodes("//text()[starts-with(normalize-space(.),'Passenger ')]/ancestor::p[1]/following::p[normalize-space(.)!=''][1]");

            $c = $this->countPBetween('ACCOMMODATION', 'Total Accommodation Price', $num);
            $n = $c + 5;
            $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("(//text()[normalize-space(.)='ACCOMMODATION']/ancestor::p[1])[{$num}]/following::p[position()<{$n}][contains(.,'Total Accommodation Price')]"));

            if (!empty($tot['Total'])) {
                $it['Total'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $segment = implode("\n", $this->pdf->FindNodes("(//text()[normalize-space(.)='ACCOMMODATION']/ancestor::p[1])[{$num}]/following::p[position()<{$c}]"));
            $it['HotelName'] = $this->re("#Staying at:\s+(.+)#", $segment);
            $it['Address'] = $this->re("#Address:\s+(.+)#", $segment);

            if (empty($it['Address'])) {
                $it['Address'] = $it['HotelName'] . ' ' . $this->re("#City:\s+(.+)#", $segment);
            }
            //$it['Address'] = $this->re("#City:\s+(.+)#",$segment);
            $it['RoomType'] = $this->re("#Room type:\s+(.+)#", $segment);

            if (!empty($bed = $this->re("#Bedding Configuration:\s+(.+)#", $segment))) {
                $it['RoomTypeDescription'] = $bed . (stripos($bed, 'bed') === false ? ' bed' : '');
            }
            $it['Rooms'] = $this->re("#Number of Rooms:\s+(\d+)#", $segment);
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Check[\-\s]*in:\s+(.+)#i", $segment)));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Check[\-\s]*out:\s+(.+)#i", $segment)));

            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailTour()
    {
        $nodes = $this->pdf->XPath->query("//text()[normalize-space(.)='TOURING']/ancestor::p[1]");
        $its = [];
        $flights = [];

        foreach ($nodes as $i => $nnode) {
            $num = $i + 1;
            $it = ['Kind' => 'E'];
            $it['ConfNo'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Reference:')]", null, true, "#:\s+([A-Z\d]+)#");
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("(//text()[starts-with(normalize-space(.),'Issue date:')])[1]", null, true, "#:\s+(.+?)\s*(?:\(|$)#")));
            $it['Name'] = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'Name of Tour')]/ancestor::p[1]/following::p[normalize-space(.)!=''][1]");

            $c = $this->countPBetween('TOURING', 'Total Touring Price', $num);
            $n = $c + 5;
            $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("(//text()[normalize-space(.)='TOURING']/ancestor::p[1])[{$num}]/following::p[position()<{$n}][contains(.,'Total Touring Price')]"));

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $segment = implode("\n", $this->pdf->FindNodes("(//text()[normalize-space(.)='TOURING']/ancestor::p[1])[{$num}]/following::p[position()<{$c}]"));
            $it['Address'] = $this->re("#Starts:\s+(.+)\s+on#", $segment);
            $it['Guests'] = $this->re("#Traveller\(s\):\s+(\d+)\s+adult#", $segment);
            $it['StartDate'] = strtotime($this->normalizeDate($this->re("#Starts:\s+.+on\s+(\d+\/\d+\/\d+)#i", $segment)));
            $this->date = $it['StartDate'];
            $it['EndDate'] = strtotime($this->normalizeDate($this->re("#Ends:\s+.+on\s+(\d+\/\d+\/\d+)#i", $segment)));
            $it['EventType'] = EVENT_EVENT;

            $its[] = $it;

            if (strpos($segment, "All passengers are booked on the following flights:") !== false) {
                $it = ['Kind' => 'T', 'TripSegments' => []];
                $it['RecordLocator'] = CONFNO_UNKNOWN;
                $it['TripNumber'] = $this->pdf->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Reference:')]", null, true, "#:\s+([A-Z\d]+)#");
                $it['Passengers'] = $this->pdf->FindNodes("//text()[starts-with(normalize-space(.),'Passenger ')]/ancestor::p[1]/following::p[normalize-space(.)!=''][1]");
                $text = preg_replace("#Reference\s+Number.+\s+Comments:#s", '', strstr($segment, 'All passengers are booked on the following flights:'));
                $nodes = $this->splitter("#\n(.+?\s+\([A-Z]{3}\)\s+\d+\s+\w+\s+\d+:\d+\s*.+\s+\([A-Z]{3}\)\s+(?:\d+\s+\w+\s+)?\d+:\d{2})#", $text);
                //$nodes = $this->splitter("#\n(.+?\s+\([A-Z]{3}\)\s+\d+\s+\w+\s+\d+:\d+\s*.+\s+\([A-Z]{3}\)\s+(?:\d+\s+\w+\s+)?\d+:\d{2})\s*[A-Z\d]{2}\s*\d+#", $text);
                foreach ($nodes as $node) {
                    $seg = [];

                    if (preg_match("#(.+?)\s+\(([A-Z]{3})\)\s+(\d+\s+\w+\s+\d+:\d+)\s*(.+)\s+\(([A-Z]{3})\)\s+(?:(\d+\s+\w+)\s+)?(\d+:\d{2})\s*([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                        $seg['DepName'] = $m[1];
                        $seg['DepCode'] = $m[2];
                        $seg['DepDate'] = strtotime($this->normalizeDate($m[3]));
                        $seg['ArrName'] = $m[4];
                        $seg['ArrCode'] = $m[5];

                        if (isset($m[6]) && !empty($m[6])) {
                            $seg['ArrDate'] = strtotime($this->normalizeDate($m[6] . ' ' . $m[7]));
                        } else {
                            $seg['ArrDate'] = strtotime($m[7], $seg['DepDate']);
                        }
                        $seg['AirlineName'] = $m[8];
                        $seg['FlightNumber'] = $m[9];
                    }
                    $it['TripSegments'][] = $seg;
                }
                $flights[] = $it;
            }
        }

        foreach ($flights as $it) {
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = date("Y", $this->date);
        $in = [
            //26/06/2017 11:15 AM
            '#^\s*(\d+)\/(\d+)\/(\d+)\s+(\d+:\d+)(?::\d+)?(\s*[ap]m)?\s*$#i',
            //26/06/2017
            '#^\s*(\d+)\/(\d+)\/(\d+)\s*$#i',
            //09 Jul 10:20
            '#^\s*(\d+\s+\w+)\s+(\d+:\d+)\s*$#',
            //17/03/2015 at 2:00pm
            '#^\s*(\d+)\/(\d+)\/(\d+)\s+at\s*(\d+:\d+)(?::\d+)?(\s*[ap]m)?\s*$#i',
        ];
        $out = [
            '$3-$2-$1 $4$5',
            '$3-$2-$1',
            '$1 ' . $year . ' $2',
            '$3-$2-$1 $4$5',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function countPBetween($first, $second, $order = 1)
    {
        if (is_array($first)) {
            if (count($first) === 2) {
                if ($pos1 = $this->pdf->XPath->query("(//text()[normalize-space(.)='{$first[0]}' and following::p[normalize-space(.)!=''][position()<4][starts-with(normalize-space(.),'{$first[1]}')]]/ancestor::p[1])[{$order}]/preceding::p")->length) {
                    if ($pos2 = $this->pdf->XPath->query("(//text()[normalize-space(.)='{$first[0]}' and following::p[normalize-space(.)!=''][position()<4][starts-with(normalize-space(.),'{$first[1]}')]]/ancestor::p[1])[{$order}]/following::p[starts-with(normalize-space(.),'{$second}')][1]/preceding::p")->length) {
                        return $pos2 - $pos1;
                    }
                }
            }
        } else {
            if ($pos1 = $this->pdf->XPath->query("(//text()[normalize-space(.)='{$first}']/ancestor::p[1])[{$order}]/preceding::p")->length) {
                if ($pos2 = $this->pdf->XPath->query("(//text()[normalize-space(.)='{$first}']/ancestor::p[1])[{$order}]/following::p[starts-with(normalize-space(.),'{$second}')][1]/preceding::p")->length) {
                    return $pos2 - $pos1;
                }
            }
        }

        return 0;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("$", "USD", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
