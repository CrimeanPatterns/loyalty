<?php

namespace AwardWallet\Engine\flightcentre\Email;

use AwardWallet\Schema\Parser\Email\Email;

class GroupTravelPdf extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-27817706.eml, flightcentre/it-27817791.eml, flightcentre/it-27818292.eml";

    public $reFrom = ["flightcentre.com.au"];
    public $reBody = [
        'en' => ['Passenger type', 'Ticket date'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'PNR' => ['PNR', 'Airline Booking Reference –'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $i => $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug("can't determine a language {$i}-attach");

                        continue;
                    } else {
                        if (!$this->parseEmailPdf($text, $email)) {
                            return null;
                        }
                    }
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, 'Flight Centre Group Travel') !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
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

    private function parseEmailPdf($textPDF, Email $email)
    {
        $otaConfNo = [];

        if ($email->getTravelAgency()) {
            $ocn = $email->getTravelAgency()->getConfirmationNumbers();

            foreach ($ocn as $cn) {
                $otaConfNo[] = $cn[0];
            }
        }

        $cn = $this->re("/{$this->opt($this->t('Group travel reference'))} +(\d+)/", $textPDF);

        if (!in_array($cn, $otaConfNo)) {
            $email->ota()->confirmation($cn,
                $this->t('Group travel reference'));
        }

        $r = $email->add()->flight();
        $node = $this->re("/{$this->opt($this->t('PNR'))} +(.+?) *\n/", $textPDF);

        if (preg_match_all("/\b([A-Z\d]{6})\b/", $node, $matches)) {
            if (count($matches[1]) === 1) {
                $r->general()->confirmation($matches[1][0]);
            } else {
                $confNo = array_shift($matches[1]);
                $r->general()->confirmation($confNo);

                foreach ($matches[1] as $confNo) {
                    $email->ota()->confirmation($confNo, 'one more PNR');
                }
            }
        }

        $phones = [];
        $ph = $email->getTravelAgency()->getProviderPhones();

        foreach ($ph as $p) {
            $phones[] = $p[0];
        }

        if (!empty($phonesText = $this->re("/For urgent travel assistance with this booking(.+\n.+)/", $textPDF))) {
            if (preg_match_all("/(.+?call) ([\d\+\- ]+)/s", $phonesText, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $v) {
                    if (!in_array(trim($v[2]), $phones)) {
                        $email->ota()->phone($this->nice($v[2]), $this->nice($v[1]));
                    }
                }
            } elseif (preg_match("/please call\s*(.+?)\s*on\s*([\d\+\- ]+)/s", $phonesText, $matches)) {
                if (!in_array(trim($matches[2]), $phones)) {
                    $email->ota()->phone($this->nice($matches[2]), $this->nice($matches[1]));
                }
            }
        }

        $infoText = $this->re("/{$this->opt($this->t('Ticket number'))} *\n(.+?)\n\n/s", $textPDF);

        if (preg_match_all("/^ *(.+?) +\(.+?\) +\w+ +(\d+\-\w+\-\d{4}) +(\d{3}\-\d+)/m", $infoText, $matches)) {
            $pax = array_map([$this, 'nice'], $matches[1]);
            $r->general()
                ->date($this->normalizeDate($matches[2][0]))
                ->travellers($pax);
            $r->issued()
                ->tickets($matches[3], false);
        } else {
            $this->logger->debug('other format pax-tickets');

            return false;
        }

        $itineraryText = $this->re("/^(.+?)\n\n\n/s", strstr($textPDF, 'Itinerary'));

        if (empty($itineraryText)) {
            $this->logger->debug('other format Itinerary');

            return false;
        }

        $segments = $this->splitter("/(.+\n *(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) *\d+ +(?:\D+)? {2,}\d+ *\w+ *\d+ +\d+:\d+)/",
            $itineraryText);

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            if (preg_match("/\n *(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z]) *(?<flight>\d+) +(?:\D+?)? +(?<dep>\d+ *\w+ *\d+ +\d+:\d+) +(?<dT>\w)? +(?:\D+?)? +(?<arr>\d+ *\w+ *\d+ +\d+:\d+) +(?<aT>\w)? +(?<cabin>\w{2,}) .+\s+\( *(?<depCode>[A-Z]{3}) *\)\s+\( *(?<arrCode>[A-Z]{3}) *\)\s+Equipment:\s+(?<aircraft>.+)/",
                $segment, $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flight']);
                $s->departure()
                    ->code($matches['depCode'])
                    ->date($this->normalizeDate($matches['dep']));

                if (isset($matches['dT']) && !empty($matches['dT'])) {
                    $s->departure()->terminal($matches['dT']);
                }
                $s->arrival()
                    ->code($matches['arrCode'])
                    ->date($this->normalizeDate($matches['arr']));

                if (isset($matches['aT']) && !empty($matches['aT'])) {
                    $s->arrival()->terminal($matches['aT']);
                }
                $s->extra()
                    ->aircraft($matches['aircraft'])
                    ->cabin($matches['cabin']);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //25Apr17 10:10
            '#^(\d+) *(\w+) *(\d{2}) +(\d+:\d+)$#u',
            //26-Mar-2017
            '#^(\d+)\-(\w+)\-(\d{4})$#u',
        ];
        $out = [
            '$1 $2 20$3, $4',
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
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

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function nice($str)
    {
        return trim(preg_replace("/\s+/", ' ', $str), " .,:;");
    }
}
