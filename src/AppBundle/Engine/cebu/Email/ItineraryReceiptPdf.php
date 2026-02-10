<?php

namespace AwardWallet\Engine\cebu\Email;

class ItineraryReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "cebu/it-1.eml, cebu/it-12091048.eml, cebu/it-12964754.eml, cebu/it-1847912.eml, cebu/it-1894539.eml, cebu/it-1898269.eml, cebu/it-2140536.eml, cebu/it-6888816.eml";

    private $reSubject = [
        'en' => ['Cebu Pacific Itinerary Receipt'],
    ];
    private $lang = '';
    private $langDetectors = [
        'en' => ['BOOKING REFERENCE NUMBER', 'Booking Reference:'],
    ];
    private $pdfText = '';

    private $pdfPattern = '.*.pdf';

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'CEB Itinerary') !== false
            || stripos($from, 'cebupacific5j.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            return false;
        }
        $textPdf = '';

        foreach ($pdfs as $pdf) {
            $textPdf .= \PDF::convertToText($parser->getAttachmentBody($pdf));
        }

        if (stripos($textPdf, 'Cebu Pacific') === false || stripos($textPdf, 'cebupacific') === false) {
            return false;
        }

        return $this->assignLang($textPdf);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            $NBSP = chr(194) . chr(160);
            $htmlPdf = str_replace($NBSP, ' ', html_entity_decode($htmlPdf));

            if ($this->assignLang($htmlPdf) === false) {
                continue;
            }
            $htmlPdf = preg_replace('#/tmp/pdftohtml-[\d_\-.]+-html\.html#', "", $htmlPdf); // /tmp/pdftohtml-1259-0.62336900_1545863497-html.html
            $this->pdfText = clone $this->http;
            $this->pdfText->SetEmailBody($htmlPdf);
        }

        return [
            'emailType'  => 'ItineraryReceiptPdf' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    private function parseEmail()
    {
        if (empty($this->pdfText)) {
            return null;
        }
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        //RecordLocator
        $it['RecordLocator'] = $this->pdfText->FindSingleNode("//p[contains(normalize-space(),'BOOKING REFERENCE NUMBER:') or contains(normalize-space(),'Booking Reference:')]/following::*[string-length(.)>2][1]", null, true, "#(\w+)#");

        //Status
        $it['Status'] = $this->pdfText->FindSingleNode("//text()[contains(normalize-space(),'Status :')]", null, true, "#:\s*([a-z ]+)\s*#i");

        if (empty($it['Status'])) {
            $it['Status'] = $this->pdfText->FindSingleNode("//text()[contains(normalize-space(),'Status')]/following::*[1]", null, true, "#:\s*([a-z ]+)\s*#i");
        }

        //ReservationDate
        $it['ReservationDate'] = strtotime($this->pdfText->FindSingleNode("//text()[contains(normalize-space(),'Booking Date :')]", null, true, "#:\s*[a-z]+\s+(\d{2}\s*[a-z]+\s*\d{4})#i"));

        if (empty($it['ReservationDate'])) {
            $it['ReservationDate'] = strtotime($this->pdfText->FindSingleNode("//p[contains(normalize-space(),'Booking Date')]/text()", null, true, "#:\s*[a-z]+\s+(\d{2}\s*[a-z]+\s*\d{4})#i"));
        }

        //Passengers
        $i = 1;
        $xpath = "//p[contains(normalize-space(),'Guest Details')]/following-sibling::p";

        while ($i < 10) {
            $p = $this->pdfText->FindSingleNode($xpath . '[' . $i . ']');

            if (preg_match("#\d{1,2}\.\s*([^(]+)\(.*#", $p, $m)) {
                $it['Passengers'][] = trim($m[1]);
            } else {
                break;
            }
            $i++;
        }

        //BaseFare
        $it['BaseFare'] = $this->cost($this->pdfText->FindSingleNode("//p[contains(normalize-space(),'Base Fare')]/following-sibling::p[1]"));

        //Currency
        $it['Currency'] = $this->currency($this->pdfText->FindSingleNode("//p[contains(normalize-space(),'Base Fare')]/following-sibling::p[1]"));

        if (empty($it['Currency'])) {
            $it['Currency'] = $this->currency($this->pdfText->FindSingleNode("//p[contains(normalize-space(),'Amount:')][2]/following-sibling::p[1]"));
        }

        //TotalCharge
        $it['TotalCharge'] = $this->cost($this->pdfText->FindSingleNode("//p[contains(normalize-space(),'Total Amount')]/following-sibling::p[1]"));

        //TripSegments
        $xpath = "//p[contains(normalize-space(),'Departure')]/following-sibling::p[contains(normalize-space(),'Arrival')]/following-sibling::p[string-length()>1]";
        $text = $this->pdfText->Response['body'];

        $segStart = strpos($text, '>Route');

        if ($segStart === false) {
            return false;
        }

        $segEnds[] = strpos($text, 'REMINDERS', $segStart);
        $segEnds[] = strpos($text, 'Additional Services', $segStart);
        $segEnds[] = strpos($text, 'Payment Details', $segStart);
        $segEnds = array_filter($segEnds);

        if (!empty($segEnds)) {
            $segEnd = min($segEnds);
        } else {
            $segEnd == false;
        }

        if ($segEnd === false) {
            $seg = substr($text, $segStart);
        } else {
            $seg = substr($text, $segStart, $segEnd - $segStart);
        }
        $seg = strip_tags($seg);
        $seg = strstr($seg, "Arrival");
        $seg = preg_replace('/[ ]{2,}/', "\n", $seg); // it-12091048.eml
        $segAr = preg_split('/^([\w)( \-]+\s+to(?:[A-Z]|\s+)[\w)( \-]+)$/mU', $seg, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($segAr as $key => $segm) {
            if ($key == 0) {
                continue;
            }

            if ($key & 1 == 1) {
                if (preg_match("#(.+)\s+to\s+(.+)#", $segm, $m) || preg_match("#(.+)\s+to([A-Z].+)#", $segm, $m)) {
                    $DepName = trim($m[1]);
                    $ArrName = trim($m[2]);
                }

                continue;
            }
            $pattern = '#\s*(?<oper>.*)\s*(?<al>[\w]{2})\s*(?<fn>\d{2,5})\s+[a-z]+'
                    . '\s+(?<dateD>\d{2}\s*[a-z]+\s*\d{4})[^(]+\((?<timeD>\d{1,2}\s*:\s*\d{2}\s*[AP]M)\)(?<nameD>[\S\n\s]*)\s+[a-z]+'
                    . '\s*(?<dateA>\d{2}\s*[a-z]+\s*\d{4})[^(]+\((?<timeA>\d{1,2}\s*:\s*\d{2}\s*[AP]M)\)(?<nameA>[\S\s]*?)(\n{3,}[\S\s]*)?\n?$#i';

            if (preg_match($pattern, $segm, $m)) {
                //Operator
                if (!empty(trim($m['oper']))) {
                    $segment['Operator'] = trim($m['oper']);
                }

                //AirlineName
                //FlightNumber
                $segment['AirlineName'] = $m['al'];
                $segment['FlightNumber'] = $m['fn'];

                //DepName
                if (isset($DepName)) {
                    $segment['DepName'] = $DepName;
                    unset($DepName);
                }

                //ArrName
                if (isset($ArrName)) {
                    $segment['ArrName'] = $ArrName;
                    unset($ArrName);
                }

                //DepCode
                //ArrCode
                if (!empty($segment['DepName']) && !empty($segment['ArrName'])) {
                    $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                //DepartureTerminal
                //DepDate
                $date = $m['dateD'];
                $time = $m['timeD'];
                $segment['DepDate'] = strtotime(str_replace("\n", ' ', $date . ' ' . $time));

                if (preg_match("#([\w\s\n]*)(terminal)\s*([\w]{1,2})\s*([,\w\s\n]*)#i", $m['nameD'], $mt)) {
                    $segment['DepartureTerminal'] = $mt[3];
                    $segment['DepName'] .= '. ' . trim($mt[1]) . ' ' . trim($mt[4]);
                    $segment['DepName'] = trim($segment['DepName'], ' .');
                } else {
                    if (!empty($m['nameD'])) {
                        $segment['DepName'] .= '. ' . trim($m['nameD']);
                    }
                }

                //ArrivalTerminal
                //ArrDate
                $date = $m['dateA'];
                $time = $m['timeA'];
                $segment['ArrDate'] = strtotime(str_replace("\n", ' ', $date . ' ' . $time));

                if (preg_match("#([\w\s\n]*)(terminal\s*)([\w]{1,2})\s*([,\w\s\n]*)#i", $m['nameA'], $mt)) {
                    $segment['ArrivalTerminal'] = $mt[3];
                    $segment['ArrName'] .= '. ' . trim($mt[1]) . ' ' . trim($mt[4]);
                    $segment['ArrName'] = trim($segment['ArrName'], ' .');
                } else {
                    if (!empty($m['nameA'])) {
                        $segment['ArrName'] .= '. ' . trim($m['nameA']);
                    }
                }

                $it['TripSegments'][] = $segment;
            }
        }

        return [$it];
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function cost($str)
    {
        //PHP 18,196.00 //P H P 3 , 8 3 3 . 0 8
        if (preg_match("#([A-Z]{3})?\s*([\d, \.]+)#", $str, $m)) {
            $m[2] = str_replace([',', ' '], '', $m[2]);

            return $m[2];
        }

        return null;
    }

    private function currency($str)
    {
        //PHP 18,196.00 //P H P 3 , 8 3 3 . 0 8
        $str = str_replace([' '], '', $str);

        if (preg_match("#([A-Z]{3})[\d,\. ]+#", $str, $m)) {
            return $m[1];
        }

        return '';
    }
}
