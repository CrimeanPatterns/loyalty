<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "asia/it-3476134.eml, asia/it-3501222.eml, asia/it-3501238.eml, asia/it-3971968.eml, asia/it-3997890.eml, asia/it-5555421.eml, asia/it-662987762.eml, asia/it-8607540.eml, asia/it-8620565.eml, asia/it-8621068.eml";

    public $reFrom = "@asiamiles.com>";
    public $reSubject = [
        "en" => "Add third party itinerary to",
        "en2"=> "electronic ticket",
    ];

    public $reBody2 = [
        "en"=> "Itinerary",
        "it"=> "Itinerario",
    ];

    public static $dictionary = [
        "en" => [],
        "it" => [
            'Ticket number'            => 'Numero Biglietto',
            'Issuing Airline and date' => 'Compagnia Aerea Emittente e data',
            'Frequent flyer number'    => 'Numero frequent flyer',
            'Total Amount'             => 'Importo totale',
            //'Fare Equivalent' => '',
            'Fare'        => 'Tariffa',
            'Itinerary'   => 'Itinerario',
            'Operated by' => 'Operato da',
            'Terminal'    => 'Terminale',
        ],
    ];

    public $lang = "en";
    public $subject;

    public function parsePdf(Email $email)
    {
        $text = $this->text;

        $f = $email->add()->flight();
        $travellers = array_filter([$this->re("#{$this->opt($this->t('Ticket number'))}\s+(.*?)\s{2,}#ms", $text)]);
        $confNumber = $this->re("#" . $this->opt($this->t("Booking Reference:")) . "\s+([A-Z\d]+)\s#ms", $text);

        if (empty($confNumber)) {
            $confNumber = $this->re("/{$this->opt($this->t('use booking ref'))}\s+([A-Z\d]{6})\s+for/", $this->subject);
        }

        if (!empty($confNumber)) {
            $f->general()
                ->confirmation($confNumber);
        } elseif (empty($confNumber)
            && (preg_match("/{$this->opt($this->t('use booking ref  for your flight'))}/u", $this->subject)
            || preg_match("/{$this->opt($this->t('use booking ref \[RecLoc\] for your flight'))}/u", $this->subject))) {
            $f->general()
                ->noConfirmation();
        }

        $f->general()
            ->travellers(preg_replace("/(?:\sMr|\sMrs|\sMiss)$/", "", $travellers));

        $dateBooking = strtotime($this->normalizeDate($this->re("/{$this->opt($this->t('Issuing Airline and date'))}\D*(\d+\w+\d{2})/", $text)));

        if (!empty($dateBooking)) {
            $f->general()
                ->date($dateBooking);
        }

        $tickets = array_filter([$this->re("#{$this->opt($this->t('Ticket number'))}\s+.*?\s{2,}([^\n]+)#ms", $text)]);

        if (!empty($tickets)) {
            $f->setTicketNumbers($tickets, false);
        }

        // AccountNumbers
        if (preg_match_all("#{$this->opt($this->t('Frequent flyer number'))}[ ]{1,35}([A-Z\d]{7,})(?:[ ]{2}|$)#m", $text, $m)) {
            $f->setAccountNumbers(array_unique($m[1]), false);
        }

        // TotalCharge
        $total = $this->re("#{$this->opt($this->t('Total Amount'))}\s*:\s*[A-Z]{3}\s+([\d\,\.]+)#", $text);
        // Currency
        $currency = $this->re("#{$this->opt($this->t('Total Amount'))}\s*:\s*([A-Z]{3})\s+[\d\,\.]+#", $text);

        if (!empty($total) && !empty($currency)) {
            $f->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);
        }

        // BaseFare
        if (!$baseFare = $this->re("#{$this->opt($this->t('Fare Equivalent'))}\s*:\s*[A-Z]{3}\s+([\d\,\.]+)#", $text)) {
            $cost = $this->re("#{$this->opt($this->t('Fare'))}\s*:\s*[A-Z]{3}\s+([\d\,\.]+)#", $text);
            $f->price()
                ->cost(PriceHelper::parse($cost, $currency));
        }

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $table = $this->re("#{$this->opt($this->t('Itinerary'))}(.*?)(?:Tour Code|\n\n\n|Form of payment|Baggage Policy\n\n|BAGGAGE PROHIBITED\:)#ms", $text);
        $table = preg_replace("#^\s*\n#s", "", $table);
        $table = preg_replace("#(\s+one\n)#u", "", $table);

        if (preg_match("/\sINTL\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]\d{2,4})/", $table)) {
            $table = preg_replace("#(\sINTL\s*)((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{2,4})([ ]{10})#", "$1     $2     ", $table);
        } elseif (preg_match("/\s((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{2,4})[ ]{10,}([A-Z])/", $table)) {
            $table = preg_replace("/\s((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{2,4})[ ]{10,}([A-Z])/", "        $1         $2", $table);
        }

        $rows = explode("\n", $table);
        $pos = $this->TableHeadPos($rows[0]);

        // fix for 8607540
        $positions = [
            'Date'     => strpos($rows[0], 'Date'),
            'Departure'=> strpos($rows[0], 'Departure'),
        ];

        unset($rows[0]);
        $body = preg_replace("#^\s*\n#s", "", implode("\n", $rows));
        $this->logger->debug($body);
        $segments = array_filter(
            array_map(
                function ($s) {
                    return preg_replace("#^[\s_]*\n#s", "", $s);
                }, explode("\n\n", $body)
            ),
            function ($s) {
                return !empty(trim($s, ' _'));
            }
        );

        foreach ($segments as $stext) {
            $spos = $pos;
            // fix arr col by name
            $names = ["HONG KONG"];

            if (($len = strlen($this->re("#^(\s*\S.*\s+)(?:" . implode("|", $names) . ")#", $stext))) > 0) {
                $spos[1] = $len;
            }

            // fix airline col by arr name
            $names = ["SYDNEY KINGSFORD"];

            if (($len = strlen($this->re("#^(\s*\S.*\s+(?:" . implode("|", $names) . "))#", $stext))) > 0) {
                $spos[2] = $len;
            }

            // fix for 8607540
            if (preg_match("#(?<Departure>(?<Date>.*?\s+)\d+\s+[^\s\d]+\s+)\d+:\d+\s+\d+:\d+#", $stext, $m)) {
                $keys = ['Date', 'Departure'];

                foreach ($keys as $k) {
                    if (($p = array_search($positions[$k], $spos)) !== null) {
                        $spos[$p] = strlen($m[$k]);
                    }
                }
            }

            $table = $this->SplitCols($stext, $spos);

            if (count($table) < 7) {
                $this->http->log("incorrect table parse");

                return;
            }

            $date = strtotime($this->normalizeDate($this->re("#(.+)#", $table[4])));

            $s = $f->addSegment();

            $s->airline()
                ->name($this->re("#\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\d{1,5}\b#", $table[2]))
                ->number($this->re("#\b(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,5})\b#", $table[2]));

            $operator = $this->re("#{$this->opt($this->t('Operated by'))}\s+(.+)#", $stext);

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $s->departure()
                ->name(trim(str_replace("\n", " ", $this->re("#(.*?)(?:Terminal|\n\n|$)#s", $table[0]))))
                ->noCode();

            $s->arrival()
                ->name(trim(str_replace("\n", " ", $this->re("#(.*?)(?:Terminal|\n\n|$)#s", $table[1]))))
                ->noCode();

            // DepartureTerminal
            $depTerminal = $this->re("#{$this->opt($this->t('Terminal'))}\s+(\w+)#", $table[0]);

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            // DepDate
            $depDate = strtotime($this->re("#(.+)#", $table[5]), $date);

            if ($depDate < $dateBooking) {
                $s->departure()
                    ->date(strtotime('+1 year', $depDate));
            } else {
                $s->departure()
                    ->date($depDate);
            }

            // ArrivalTerminal
            $arrTerminal = $this->re("#{$this->opt($this->t('Terminal'))}\s+(\w+)#", $table[1]);

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            // ArrDate
            $arrDate = strtotime($this->re("#(.+)#", $table[6]), $date);

            if ($arrDate < $dateBooking) {
                $s->arrival()
                    ->date(strtotime('+1 year', $arrDate));
            } else {
                $s->arrival()
                    ->date($arrDate);
            }

            if (!empty($itsegment['ArrDate'])
                && preg_match("/[ ]{2}{$this->opt($this->t('Arrival Day'))} ?[+] ?(\d{1,3})(?:[ ]{2}|$)/m", $stext, $m)
            ) {
                // Arrival Day+1
                $s->arrival()
                    ->date(strtotime("+{$m[1]} days", $s->getArrDate()));
            }

            $cabin = $this->re("#(?:^|\n)([A-Z])(?:$|\n)#", $table[3]);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            // Seats
            if (preg_match("/^\s*(\d{1,5}[A-Z])\s*$/", $table[count($table) - 1], $m)) {
                $s->extra()
                    ->seats([$m[1]]);
            }

            // Stops
            $stops = $this->re("#Number of stops[ ]+(\d+)#", $stext);

            if ($stops !== null) {
                $s->extra()
                    ->stops((int) $stops);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*\s+\d+[A-Z]+\s+[A-Z]{3}\.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, 'CATHAY PACIFIC AIRWAYS') === false
            && stripos($text, 'www.cathaypacific.com') === false
            && stripos($text, '@cathaypacific.com') === false
        ) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->subject = $parser->getSubject();

        $this->http->FilterHTML = false;
        $pdfs = $parser->searchAttachmentByName('.*\s+\d+[A-Z]+\s+[A-Z]{3}\.pdf');

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)\s+([^\d\s]+)$#", //20 MAY
            "#^(\d+)([^\d\s]+)$#", //23Oct
            "#^(\d+)([^\d\s]+)(\d{2})$#", //23Oct
        ];
        $out = [
            "$1 $2 $year",
            "$1 $2 $year",
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // $this->http->log($str);
        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, 0, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
