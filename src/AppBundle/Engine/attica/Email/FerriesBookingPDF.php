<?php

namespace AwardWallet\Engine\attica\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FerriesBookingPDF extends \TAccountChecker
{
    public $mailFiles = "attica/it-478074216.eml";
    public $pdfNamePattern = ".*pdf";
    public $lang = 'en';
    public $subject;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        if (empty($text)) {
            return false;
        }

        if (strpos($text, 'BLUE STAR FERRIES MARITIME') !== false
            && stripos($text, 'Booking Confirmation') !== false
            && stripos($text, 'First Journey') !== false
            && stripos($text, 'Depart. Date/Time') !== false
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]attica-group\.com$/', $from) > 0;
    }

    public function ParseFerryPDF(Email $email, $text)
    {
        $this->logger->error(__METHOD__);

        $f = $email->add()->ferry();

        $f->general()
            ->confirmation($this->re("/Booking Confirmation\s*\((\d+)\)/", $this->subject))
            ->date($this->normalizeDate($this->re("/Booking Confirmation printed on\s*\:\w+\s*([\d\/]+\d{4}\s*[\d\:]+)/", $text)));

        $paxText = $this->re("/\n+(\s*Lead Pax Name.+\n+\s*.+)\n+/", $text);
        $paxTable = $this->splitCols($paxText, [0, 25, 45, 60]);
        $f->general()
            ->traveller($this->re("/Lead Pax Name\s*(.+)/us", $paxTable[0]));

        if (preg_match_all("/Ticket Nr.+\n+.*\/\s*A\s*[A-Z]\s*[A-Z]{2,3}\s*(A\d{7,})/", $text, $match)) {
            $f->setTicketNumbers(array_filter(array_unique($match[1])), false);
        }

        if (preg_match_all("/\n+(\s*From.+\n+\s*(?:.+\n){1,})\s*Lead Pax Name/u", $text, $match)) {
            foreach ($match[1] as $segText) {
                /*From                      To               Depart. Date/Time                       Vessel                  Arrival Date/Time               Check-in Date/Time
                AG. KIRYKOS              PIRAEUS            Tu 22/08/2023 - 18:15 BLUE STAR MYCONOS We 23/08/2023 - 00:20 Tu 22/08/2023 - 17:45*/
                if (preg_match("/\s*From\s*To.*\n+\s*(?<depName>\D+)[ ]{10,}(?<arrName>.+)[ ]{10,}\s+[A-Z][a-z]{1,2}\s+(?<depDate>\d+\/\d+\/\d{4}\s*\-\s*[\d\:]+)\s*(?<vessel>.+)\s*[A-Z][a-z]{1,2}\s+(?<arrDate>\d+\/\d+\/\d{4}\s*-\s*[\d\:]+)\s*[A-Z][a-z]{1,2}\s+\d+\/\d+\/\d{4}\s*\-\s*[\d\:]+/", $segText, $m)
                /*From                      To               Depart. Date/Time                       Vessel                  Arrival Date/Time               Check-in Date/Time
                PIRAEUS         AG.MARINA AEGINASu 20/08/2023 - 10:40                         AERO HIGHSPEED              Su 20/08/2023 - 11:10           Su 20/08/2023 - 10:10*/
                || preg_match("/\s*From\s*To.*\n+\s*(?<depName>\D+)[ ]{5,}(?<arrName>.+)\s*[A-Z][a-z]{1,2}\s+(?<depDate>\d+\/\d+\/\d{4}\s*\-\s*[\d\:]+)\s*(?<vessel>.+)\s*[A-Z][a-z]{1,2}\s+(?<arrDate>\d+\/\d+\/\d{4}\s*-\s*[\d\:]+)\s*[A-Z][a-z]{1,2}\s+\d+\/\d+\/\d{4}\s*\-\s*[\d\:]+/", $segText, $m)) {
                    $s = $f->addSegment();

                    $s->departure()
                        ->name($m['depName'])
                        ->date($this->normalizeDate($m['depDate']));

                    $s->arrival()
                        ->name($m['arrName'])
                        ->date($this->normalizeDate($m['arrDate']));

                    $s->setVessel($m['vessel']);
                }
            }
        }

        $price = $this->re("/Grand Total[\:\s]+([\d\.\,]+\s*[A-Z]{3})/", $text);

        if (preg_match("/(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})/", $price, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->re("/Passenger & Vehicle Fare[\:\s]+([\d\.\,]+)\s*[A-Z]{3}/", $text);

            if (!empty($cost)) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->re("/Taxes and Fees[\:\s]+([\d\.\,]+)\s*[A-Z]{3}/", $text);

            if (!empty($tax)) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $feeSum = $this->re("/Extras and Supplement Fees :[\:\s]+([\d\.\,]+)\s*[A-Z]{3}/", $text);

            if ($feeSum !== null) {
                $f->price()
                    ->fee('Extras and Supplement Fees', PriceHelper::parse($feeSum, $m['currency']));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                $this->ParseFerryPDF($email, $text);
            }
        }

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
            'PLN' => ['zł'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            "#^(\d+)\/(\d+)\/(\d{4})\s\-*\s*([\d\:]+\s*A?P?M?)$#u", //22/08/2023 - 18:15
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
