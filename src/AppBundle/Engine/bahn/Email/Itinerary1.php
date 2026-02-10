<?php

namespace AwardWallet\Engine\bahn\Email;

class Itinerary1 extends \TAccountChecker
{
    public $processors = [];

    public $xInstance = null;
    public $lastRe = null;

    public function __construct()
    {
        parent::__construct();

        // Define processors
        $this->processors = [
            // Parsed file "bahn/it-1.eml"
            "#Booking confirmation#" => function (&$it, $parser) {
                $this->xBase($this->http); // helper

                $text = $this->mkText($this->xPDF($parser, '.')); // text representation
                echo $text;
                // @Handlers
                $it['Kind'] = 'L';
                $it['Number'] = $this->re("#\nAuftragsnummer:\s*([^\n]+)#", $text);

                $it['ReservationDate'] = $this->mkDate($this->re("#\nDatum\s*([^\n]+)#", $text));
                $year = date('Y', $it['ReservationDate']);

                //if ($this->re("#\nHalt\s+Datum\s+Zeit\s+Gleis\s+Fahrt\s+Reservierung\s+([^\n]+)\s+(\d+\.\d+)\.\s+\w*\s+(\d+:\d+)\s*\d*\s+[^\n]+\s+(.*?)\s+(\d+\.\d+)\.\s+\w*\s+(\d+:\d+)#ms", $text)){
                if ($this->re("#\nHalt\s+Datum\s+Zeit\s+Gleis\s+Fahrt\s+Reservierung\s+([^\n]+)\s+(\d+\.\d+)\.\s+\w*\s+(\d+:\d+)[^\n]+\n[^\n]+\n(.*?)\s+(\d+\.\d+)\.\s+\w*\s+(\d+:\d+)#ms", $text)) {
                    $it['PickupDatetime'] = $this->mkDate($this->re(2) . ".$year, " . $this->re(3));
                    $it['PickupLocation'] = $this->re(1);

                    $it['DropoffDatetime'] = $this->mkDate($this->re(5) . ".$year, " . $this->re(6));
                    $it['DropoffLocation'] = $this->re(4);
                }

                $it['RenterName'] = beautifulName($this->mkClear("#&\#[^;]+;#", $this->re("#\n([^\n]+)\nKreditkartenzahlung\n#", $text)));
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@bahn\.de/i', $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match('/@bahn\.de/i', $headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        return !empty($body) && stripos($body, 'bahn.de') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $body = $parser->getHTMLBody();

        if (!$body) {
            $body = $parser->getPlainBody();
        }

        foreach ($this->processors as $re => $processor) {
            if (preg_match($re, $body)) {
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

    public static function getEmailLanguages()
    {
        return ["de"];
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
