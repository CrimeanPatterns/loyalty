<?php

namespace AwardWallet\Engine\asia\Email;

class Itinerary1 extends \TAccountChecker
{
    public $processors = [];
    public $reFrom = "#[\.@]cathaypacific\.com#i";
    public $reProvider = "#cathaypacific\.com#i";
    public $reSubject = null;
    public $reText = "";
    public $reHtml = "#CATHAY\s+PACIFIC\s+AIRWAYS\s+LTD[^\w]+DATE#";

    public $xInstance = null;
    public $lastRe = null;

    public function __construct()
    {
        parent::__construct();

        // Define processors
        $this->processors = [
            // Parsed file "asia/it-12.eml"
            "#THIS IS AN INFORMATIONAL MESSAGE ONLY#" => function (&$it, $parser) {
                $this->xBase($this->http); // helper
                $text = $this->mkClear("#&\#160;#", $this->xPDF($parser), ' ');
                $text = preg_replace("#<br/*>#i", "\n", $text);

                $it = ['Kind' => 'T'];

                $it['RecordLocator'] = $this->re("#\sRECORD LOCATOR\s*:\s*([^\s]+)#", $text);
                $it['Passengers'] = $this->re("#ITINERARY FOR\s*:\s*(.*?)\s+(\d+ \w{3} \d{4})#", $text);
                $it['ReservationDate'] = $this->mkDate($this->re(2));
                $year = date('Y', $it['ReservationDate']);

                $table = $this->re("#FLIGHT RESERVATION\s+\=+\s+(.*?)\s+AIRLINES\s+\=+#ms", $text);

                $meter = [];
                $offset = 0;
                $meterStr = $this->re("#\n(((\-{2,})\s+)+)\n#ms", $text);

                foreach (preg_split("#(\-+\s)#", $meterStr, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $line) {
                    $meter[] = $offset;
                    $offset += strlen($line);
                }

                $flights = preg_split("#(\n\s+\n)#", $this->re("#\n(((\-{2,})\s+)+)\n(.*)$#ims", $table, 4));

                $segCell = function ($str, $col, $row) use ($meter) {
                    $rows = explode("\n", $str);

                    if (isset($meter[$col]) && isset($meter[$col + 1])) {
                        $from = $meter[$col];
                        $to = $meter[$col + 1];

                        return trim(substr($rows[$row], $from, $to - $from), " \n,.");
                    }
                };

                foreach ($flights as $segment) {
                    $seg = [];

                    $seg['Cabin'] = $segCell($segment, 6, 0);
                    $seg['Stops'] = $segCell($segment, 7, 0) == 'NON-STOP' ? 0 : null;

                    $seg['FlightNumber'] = $segCell($segment, 5, 0);

                    $seg['DepName'] = $segCell($segment, 1, 0);
                    $seg['ArrName'] = $segCell($segment, 1, 1);

                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                    $depDate = $segCell($segment, 2, 0);
                    $arrDate = $segCell($segment, 2, 1);

                    $seg['DepDate'] = $this->mkDate($depDate . "$year, " . $segCell($segment, 3, 0));
                    $seg['ArrDate'] = $this->mkDate(($arrDate ? $arrDate : $depDate) . "$year, " . $segCell($segment, 3, 1));

                    $it['TripSegments'][] = $seg;
                }
            },
        ];
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

        //		return ((isset($this->reText) && $this->reText)?preg_match($this->reText, $this->mkText($this->http->Response['body'])):false) ||
        //				((isset($this->reHtml) && $this->reHtml)?preg_match($this->reHtml, $this->http->Response['body']):false);
        return ((isset($this->reText) && $this->reText) ? $this->smartMatch($parser) : false)
            || ((isset($this->reHtml) && $this->reHtml) ? preg_match($this->reHtml, $this->http->Response['body']) : false);
    }

    public function smartMatch($parser)
    {
        $body = $parser->getPlainBody();

        $isRe = preg_match("#[\|\(\)\[\].\+\*\!\^]#", $this->reText) ? true : false;

        if ($isRe) {
            return preg_match($this->reText, $body);
        } else {
            $find = preg_replace("#^\#([^\#]+)\#[imxse]*$#", '\1', $this->reText);

            if (stripos($body, $find) !== false) {
                return true;
            }
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $itineraries['Kind'] = 'R';

        $htmlBody = $parser->getHTMLBody();

        foreach ($this->processors as $re => $processor) {
            if (preg_match($re, $htmlBody, $matches)) {
                $processor($itineraries, $parser);

                break;
            }
        }

        if (empty($matches)) {
            $plainBody = $parser->getPlainBody();

            foreach ($this->processors as $re => $processor) {
                if (preg_match($re, $plainBody)) {
                    $this->http->SetBody($plainBody);
                    $processor($itineraries, $parser);

                    break;
                }
            }
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 3;
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
        $html = preg_replace("#&nbsp;#uims", " ", $html);
        $html = preg_replace("#&amp;#uims", "&", $html);
        $html = preg_replace("#&quot;#uims", '"', $html);
        $html = preg_replace("#&lt;#uims", '<', $html);
        $html = preg_replace("#&gt;#uims", '>', $html);

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

        return count($nodes) ? implode("\n", $nodes) : null; //$instance->FindSingleNode($path);
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

        if (preg_match("#\bCAD\b#i", $text)) {
            return 'CAD';
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

    public function xAttachments($parser)
    {
        $all = [];

        for ($i = 0; $i < $parser->countAttachments(); $i++) {
            $a = $parser->getAttachment($i);
            $body = $parser->getAttachmentBody($i);

            $type = $a['headers']['content-disposition'];

            if (preg_match("#\.pdf$#i", $type)) {
                $all[] = \PDF::convertToHtml($body, \PDF::MODE_SIMPLE);

                continue;
            }

            if (preg_match("#\.xml$#i", $type)) {
                $xml = simplexml_load_string($body);
                $all[] = json_encode($xml);

                continue;
            }

            $all[] = $body;
        }

        $this->http->SetBody(implode("\n<!--delimeter begin-->\n<BR>\n<BR><!--delimeter end-->\n", $all));
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
