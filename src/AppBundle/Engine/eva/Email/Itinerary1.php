<?php

namespace AwardWallet\Engine\eva\Email;

class Itinerary1 extends \TAccountChecker
{
    public $processors = [];
    public $reFrom = "#eservice@service2\.evaair\.com#";
    public $reProvider = "#evaair\.com#";
    public $reSubject = "#Booked itinerary for booking No#";
    public $reText = null;
    public $reHtml = "#From:(<[^>]+>|\s)*EVA#";
    public $mailFiles = "eva/it-1.eml, eva/it-2.eml";

    public $xInstance = null;
    public $lastRe = null;

    public function __construct()
    {
        parent::__construct();

        // Define processors
        $this->processors = [
            // Parsed file "eva/it-1.eml"
            "#listed below is your itinerary#" => function (&$it, $parser) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation
                //$tabbed = $this->mkText($body, true); // text with tabs

                // @Handlers
                $it['Kind'] = 'T';
                $it['RecordLocator'] = $this->re("#\nBooking Reference：\s*([^\n]+)#", $text);

                $seats = [];
                $meal = [];
                $names = [];
                $numbers = [];

                $it['ReservationDate'] = $this->mkDate($this->re("#Transaction Time\s*\((\w+)\.*/(\d+)/(\d+)\s+(\d+:\d+)\)#", $text, 2) . ' ' . $this->re(1) . ' ' . $this->re(3) . ', ' . $this->re(4));

                $this->re("#\nPassenger\s*\d+\s+Passenger Name\s+([^\n]+)\s+Frequent Flyer No.\s+([^\n]+)\s+Ticket\s+(\d+)\s+Seat\s+([^\n]+)\s+(Seat Change\s+)*Meal\s+([^\n]+)#ms",
                   function ($m) use (&$it, &$seats, &$meal, &$names, &$numbers) {
                       $names[] = trim($m[1]);
                       $numbers[] = trim($m[2]);
                       $seats = $m[4];
                       $meal[] = $m[6];
                   }, $text);

                if ($names) {
                    $it['Passengers'] = array_unique($names);
                }

                if ($numbers) {
                    $it['AccountNumbers'] = array_unique($numbers);
                }

                $it['TripSegments'] = [];

                $nodes = $this->http->XPath->query("//h2[contains(text(),'Flight segment ')]/following-sibling::div[1]/table/tbody");

                for ($i = 0; $i < $nodes->length; $i++) {
                    $table = $nodes->item($i);
                    $seg = [];

                    $node = $this->http->FindSingleNode("tr/*[1][contains(text(), 'Flight Number')]/following-sibling::td[1]", $table);

                    if (preg_match("/^\s*([A-Z\d]{2})(\d{1,5})\s*\/\s*(.+)/", $node, $m)) {
                        $seg['FlightNumber'] = $m[2];
                        $seg['AirlineName'] = $m[1];
                        $seg['Aircraft'] = $m[3];
                    }

                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                    $seg['DepName'] = $this->re("#^(.*?)\s+(\w+)\.*/(\d+)/(\d+)\s+(\d+:\d+)#", $this->http->FindSingleNode("tr/*[1][contains(text(), 'From')]/following-sibling::td[1]", $table));

                    if (preg_match("/^\s*(?<name>.+?)\s+Terminal\s*(?<terminal>.+)\s*$/i", $seg['DepName'], $m)) {
                        $seg['DepName'] = trim($m['name']);
                        $seg['DepartureTerminal'] = trim($m['terminal']);
                    }
                    $seg['DepDate'] = $this->mkDate($this->re(3) . ' ' . $this->re(2) . ' ' . $this->re(4) . ', ' . $this->re(5));

                    $seg['ArrName'] = $this->re("#^(.*?)\s+(\w+)\.*/(\d+)/(\d+)\s+(\d+:\d+)#", $this->http->FindSingleNode("tr/*[1][contains(text(), 'To')]/following-sibling::td[1]", $table));

                    if (preg_match("/^\s*(?<name>.+?)\s+Terminal\s*(?<terminal>.+)\s*$/i", $seg['ArrName'], $m)) {
                        $seg['ArrName'] = trim($m['name']);
                        $seg['ArrivalTerminal'] = trim($m['terminal']);
                    }
                    $seg['ArrDate'] = $this->mkDate($this->re(3) . ' ' . $this->re(2) . ' ' . $this->re(4) . ', ' . $this->re(5));

                    $node = $this->http->FindSingleNode("tr/*[1][contains(text(), 'Booking Class')]/following-sibling::td[1]", $table);

                    if (preg_match("/^\s*(.+)\s*\/\s*(.+)/", $node, $m)) {
                        $seg['Cabin'] = trim($m[1]);
                        $seg['BookingClass'] = trim($m[2]);
                    } else {
                        $seg['Cabin'] = $node;
                    }

                    if (isset($meal[$i])) {
                        $seg['Meal'] = $meal[$i];
                    }

                    $it['TripSegments'][] = $seg;
                }
            },
        ];
    }

    public function FindSingleNodeCustom($path, $from = null)
    {
        $nodes = $this->http->XPath->query($path, $from);
        $value = [];

        for ($i = 0; $i < $nodes->length; $i++) {
            $value[] = $nodes->item($i)->nodeValue;
        }

        return implode("\n", $value);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reProvider, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return ((isset($this->reFrom) && isset($headers['from'])) ? preg_match($this->reFrom, $headers["from"]) : false)
                || ((isset($this->reSubject) && isset($headers['subject'])) ? preg_match($this->reSubject, $headers["subject"]) : false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->xBase($this->http);

        return ((isset($this->reText) && $this->reText) ? preg_match($this->reText, $this->mkText($this->http->Response['body'])) : false)
                || ((isset($this->reHtml) && $this->reHtml) ? preg_match($this->reHtml, $this->http->Response['body']) : false);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        foreach ($this->processors as $re => $processor) {
            if (preg_match($re, $parser->getHTMLBody())) {
                $processor($itineraries, $parser);

                break;
            }
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => isset($itineraries[0]) ? $itineraries : [$itineraries],
            ],
        ];
    }

    public function mkCost($value)
    {
        if (preg_match("#,#", $value) && preg_match("#\.#", $value)) { // like 1,299.99
            $value = preg_replace("#,#", '', $value);
        }

        $value = preg_replace("#,#", '.', $value);
        $value = preg_replace("#[^\d\.]#", '', $value);

        return is_numeric($value) ? (float) number_format($value, 2, '.', '') : null;
    }

    public function mkDate($date, $reltime = null)
    {
        if (!$reltime) {
            $check = strtotime($this->glue($this->mkText($date), ' '));

            return $check ? $check : null;
        }

        $unix = is_numeric($date) ? $date : strtotime($this->glue($this->mkText($date), ' '));

        if ($unix) {
            $guessunix = strtotime(date('Y-m-d', $unix) . ' ' . $reltime);

            if ($guessunix < $unix) {
                $guessunix += 60 * 60 * 24;
            } // inc day

            return $guessunix;
        }

        return null;
    }

    public function mkText($html, $preserveTabs = false, $stringifyCells = true)
    {
        $html = preg_replace("#&" . "nbsp;#uims", " ", $html);
        $html = preg_replace("#&" . "amp;#uims", "&", $html);
        $html = preg_replace("#&" . "quot;#uims", '"', $html);
        $html = preg_replace("#&" . "lt;#uims", '<', $html);
        $html = preg_replace("#&" . "gt;#uims", '>', $html);

        if ($stringifyCells && $preserveTabs) {
            $html = preg_replace_callback("#(</t(d|h)>)\s+#uims", function ($m) {return $m[1]; }, $html);

            $html = preg_replace_callback("#(<t(d|h)(\s+|\s+[^>]+|)>)(.*?)(<\/t(d|h)>)#uims", function ($m) {
                return $m[1] . preg_replace("#[\r\n\t]+#ums", ' ', $m[4]) . $m[5];
            }, $html);
        }

        $html = preg_replace("#<(td|th)(\s+|\s+[^>]+|)>#uims", "\t", $html);

        $html = preg_replace("#<(p|tr)(\s+|\s+[^>]+|)>#uims", "\n", $html);
        $html = preg_replace("#</(p|tr)>#uims", "\n", $html);

        $html = preg_replace("#\r\n#uims", "\n", $html);
        $html = preg_replace("#<br(/|)>#uims", "\n", $html);
        $html = preg_replace("#<[^>]+>#uims", ' ', $html);

        if ($preserveTabs) {
            $html = preg_replace("#[ \f\r]+#uims", ' ', $html);
        } else {
            $html = preg_replace("#[\t \f\r]+#uims", ' ', $html);
        }

        $html = preg_replace("#\n\s+#uims", "\n", $html);
        $html = preg_replace("#\s+\n#uims", "\n", $html);
        $html = preg_replace("#\n+#uims", "\n", $html);

        return trim($html);
    }

    public function xBase($newInstance)
    {
        $this->xInstance = $newInstance;
    }

    public function xHtml($path, $instance = null)
    {
        if (!$instance) {
            $instance = $this->xInstance;
        }

        return $instance->FindHTMLByXpath($path);
    }

    public function xNode($path, $instance = null)
    {
        if (!$instance) {
            $instance = $this->xInstance;
        }
        $nodes = $instance->FindNodes($path);

        return count($nodes) ? implode("\n", $nodes) : null; //$instance->FindSingleNodeCustom($path);
    }

    public function xText($path, $preserveCaret = false, $instance = null)
    {
        if ($preserveCaret) {
            return $this->mkText($this->xHtml($path, $instance));
        } else {
            return $this->xNode($path, $instance);
        }
    }

    public function mkImageUrl($imgTag)
    {
        if (preg_match("#src=(\"|'|)([^'\"]+)(\"|'|)#ims", $imgTag, $m)) {
            return $m[2];
        }

        return null;
    }

    public function glue($str, $with = ", ")
    {
        return implode($with, explode("\n", $str));
    }

    public function re($re, $text = false, $index = 1)
    {
        if (is_numeric($re) && $text == false) {
            return ($this->lastRe && isset($this->lastRe[$re])) ? $this->lastRe[$re] : null;
        }

        $this->lastRe = null;

        if (is_callable($text)) { // we have function
            // go through the text using replace function
            return preg_replace_callback($re, function ($m) use ($text) {
                return $text($m);
            }, $index); // index as text in this case
        }

        if (preg_match($re, $text, $m)) {
            $this->lastRe = $m;

            return $m[$index] ?? $m[0];
        } else {
            return null;
        }
    }

    public function mkNice($text, $glue = false)
    {
        $text = $glue ? $this->glue($text, $glue) : $text;

        $text = $this->mkText($text);
        $text = preg_replace("#,+#ms", ',', $text);
        $text = preg_replace("#\s+,\s+#ms", ', ', $text);
        $text = preg_replace_callback("#([\w\d]),([\w\d])#ms", function ($m) {return $m[1] . ', ' . $m[2]; }, $text);
        $text = preg_replace("#[,\s]+$#ms", '', $text);

        return $text;
    }

    public function mkCurrency($text)
    {
        if (preg_match("#\\$#", $text)) {
            return 'USD';
        }

        if (preg_match("#£#", $text)) {
            return 'GBP';
        }

        if (preg_match("#€#", $text)) {
            return 'EUR';
        }

        if (preg_match("#\bEUR\b#i", $text)) {
            return 'EUR';
        }

        if (preg_match("#\bUSD\b#i", $text)) {
            return 'USD';
        }

        if (preg_match("#\bBRL\b#i", $text)) {
            return 'BRL';
        }

        if (preg_match("#\bCHF\b#i", $text)) {
            return 'CHF';
        }

        if (preg_match("#\bHKD\b#i", $text)) {
            return 'HKD';
        }

        if (preg_match("#\bSEK\b#i", $text)) {
            return 'SEK';
        }

        if (preg_match("#\bZAR\b#i", $text)) {
            return 'ZAR';
        }

        if (preg_match("#\bIN(|R)\b#i", $text)) {
            return 'INR';
        }

        return null;
    }

    public function arrayTabbed($tabbed, $divRowsRe = "#\n#", $divColsRe = "#\t#")
    {
        $r = [];

        foreach (preg_split($divRowsRe, $tabbed) as $line) {
            if (!$line) {
                continue;
            }
            $arr = [];

            foreach (preg_split($divColsRe, $line) as $item) {
                $arr[] = trim($item);
            }
            $r[] = $arr;
        }

        return $r;
    }

    public function arrayColumn($array, $index)
    {
        $r = [];

        foreach ($array as $in) {
            $r[] = $in[$index] ?? null;
        }

        return $r;
    }

    public function orval()
    {
        $array = func_get_args();
        $n = sizeof($array);

        for ($i = 0; $i < $n; $i++) {
            if (((gettype($array[$i]) == 'array' || gettype($array[$i]) == 'object') && sizeof($array[$i]) > 0) || $i == $n - 1) {
                return $array[$i];
            }

            if ($array[$i]) {
                return $array[$i];
            }
        }

        return '';
    }

    public function mkClear($re, $text, $by = '')
    {
        return preg_replace($re, $by, $text);
    }

    public function grep($pattern, $input, $flags = 0)
    {
        if (gettype($flags) == 'function') {
            $r = [];

            foreach ($input as $item) {
                $res = preg_replace_callback($pattern, $flags, $item);

                if ($res !== false) {
                    $r[] = $res;
                }
            }

            return $r;
        }

        return preg_grep($pattern, $input, $flags);
    }

    public function xPDF($parser, $wildcard = null)
    {
        $pdfs = $parser->searchAttachmentByName($wildcard ? $wildcard : '.*pdf');
        $pdf = "";

        foreach ($pdfs as $pdfo) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdfo), \PDF::MODE_SIMPLE)) !== null) {
                $pdf .= $html;
            }
        }

        return $pdf;
    }

    public function correctByDate($date, $anchorDate)
    {
        // $anchorDate should be earlier than $date
        // not implemented yet
        return $date;
    }
}
