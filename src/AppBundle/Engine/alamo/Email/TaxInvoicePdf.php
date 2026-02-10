<?php

namespace AwardWallet\Engine\alamo\Email;

use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Email\Email;

class TaxInvoicePdf extends \TAccountChecker
{
    public $mailFiles = "alamo/it-296593997.eml, alamo/it-74374979.eml, alamo/it-75017064.eml";

    public static $detectProvider = [
        'alamo' => [
            'subject' => [
                'Alamo Rent a Car Invoice',
            ],
            'body'=> ['Thank you for choosing Alamo'],
        ],
        'rentacar' => [
            'subject' => [
                'Enterprise Rent-A-Car Invoice',
            ],
            'body'=> ['Thank you for choosing Enterprise'],
        ],
        'national' => [
            'subject' => [
                'National Car Rental Invoice',
            ],
            'body'=> ['Thank you for choosing National'],
        ],
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'RENTAL DETAIL' => ['RENTAL DETAIL'],
            ''              => '',
        ],
    ];

    private $providerCode;

    private $detectBody = [
        'en' => ['TAX INVOICE', 'RENTAL INVOICE'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alamo.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $params) {
            if (isset($params['subject']) && $this->strposAll($headers['subject'], $params['subject']) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            foreach (self::$detectProvider as $params) {
                if (!isset($params['body']) || $this->strposAll($textPdf, $params['body']) === false) {
                    continue;
                }

                foreach ($this->detectBody as $dBody) {
                    if ($this->strposAll($textPdf, $dBody) === true) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            foreach (self::$detectProvider as $code => $params) {
                if (!isset($params['body']) || $this->strposAll($textPdf, $params['body']) === false) {
                    continue;
                }
                $this->providerCode = $code;

                foreach ($this->detectBody as $lang => $dBody) {
                    if ($this->strposAll($textPdf, $dBody) === true) {
                        $this->lang = $lang;
                        $this->parseCar($email, $textPdf);
                    }
                }
            }
        }

        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $params) {
                if (isset($params['subject']) && $this->strposAll($parser->getSubject(), $params['subject']) === true) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function parseCar(Email $email, $text)
    {
//        $this->logger->debug('Text:'."\n".$text);

        $rentalInfo = $this->re("/\n[ ]{0,5}" . $this->opt($this->t("RENTAL DETAIL")) . "(?: {3,}.*)?\n+([\s\S]+?)\n[ ]{0,5}" . $this->opt($this->t("VEHICLE INFORMATION")) . "(?: {3,}.*)?\n/", $text);

        $table = $this->splitCols($rentalInfo, $this->rowColsPos($this->inOneRow($rentalInfo)));
//        $this->logger->debug('$table:'."\n".print_r($table, true));

        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->re("/(?:^|\n|[ ]{5,})" . $this->opt($this->t("Rental Agreement Number:")) . " *([A-Z\d\-]{5,})\n/", $text));

        if (!empty($table[2]) && preg_match("/" . $this->opt($this->t("Renter Name and Address")) . "\n+(.+)\n/", $table[2], $m)) {
            $r->general()
                ->traveller(trim($m[1]));
        }

        // Pick Up and Drop Off
        if (!empty($table[0]) && preg_match("/" . $this->opt($this->t("Pick Up Location:")) . "([\s\S]+)\n *" . $this->opt($this->t("Return Location:")) . "([\s\S]+)/", $table[0], $m)) {
            $regexp = "/([\s\S]+)\n([\d\-\+\(\) ]{5,})\s*$/";

            if (preg_match($regexp, $m[1], $mat) && strlen(preg_replace("/\D/", '', $mat[2])) > 5) {
                $r->pickup()
                    ->location(preg_replace("/\s+/", ' ', trim($mat[1])))
                    ->phone($mat[2])
                ;
            } else {
                $r->pickup()->location(preg_replace("/\s+/", ' ', trim($m[1])));
            }

            if (preg_match($regexp, $m[2], $mat) && strlen(preg_replace("/\D/", '', $mat[2])) > 5) {
                $r->dropoff()
                    ->location(preg_replace("/\s+/", ' ', trim($mat[1])))
                    ->phone($mat[2])
                ;
            } else {
                $r->dropoff()->location(preg_replace("/\s+/", ' ', trim($m[2])));
            }
        }

        if (!empty($table[1]) && preg_match("/" . $this->opt($this->t("Pick Up Date:")) . "([\s\S]+)\n *" . $this->opt($this->t("Return Date:")) . "([\s\S]+)/", $table[1], $m)) {
            $monthPickUp = $this->re("#^\s*\d+\/(\d+)\/#sui", $m[1]);
            $monthDropOff = $this->re("#^\s*\d+\/(\d+)\/#sui", $m[2]);

            if ($monthPickUp > 12 || $monthDropOff > 12) {
                $r->pickup()
                    ->date(strtotime(preg_replace("/\s+/", ' ', trim($m[1]))));
                $r->dropoff()
                    ->date(strtotime(preg_replace("/\s+/", ' ', trim($m[2]))));
            } else {
                $r->pickup()
                    ->date($this->normalizeDate(preg_replace("/\s+/", ' ', trim($m[1]))));
                $r->dropoff()
                    ->date($this->normalizeDate(preg_replace("/\s+/", ' ', trim($m[2]))));
            }
        }

        // Car
        if (preg_match("/\n[ ]{0,5}" . $this->opt($this->t("VEHICLE INFORMATION")) . "\n+(?<headerSpace>.*[ ]{2,})" . $this->opt($this->t("Model:")) . "\n+(?<valueSpace>.+[ ]{2,})(?<model>.+)\n/", $text, $m)
            && abs(strlen($m['headerSpace']) - strlen($m['valueSpace'])) < 5
        ) {
            $r->car()->model($m['model']);
        }

        if ($email->getItineraries() > 1) {
            foreach ($email->getItineraries() as $key => $it) {
                /** @var Rental $it/ */
                if ($r->getId() == $it->getId()) {
                    continue;
                }

                if (!empty($r->getPickUpLocation()) && !empty($r->getDropOffLocation())
                    && !empty($r->getPickUpDateTime()) && !empty($r->getDropOffDateTime())
                    && !empty($r->getCarModel())
                    && $it->getPickUpLocation() == $r->getPickUpLocation()
                    && $it->getDropOffLocation() == $r->getDropOffLocation()
                    && $it->getPickUpDateTime() == $r->getPickUpDateTime()
                    && $it->getDropOffDateTime() == $r->getDropOffDateTime()
                    && $it->getCarModel() == $r->getCarModel()
                ) {
                    $email->removeItinerary($r);

                    return;
                }
            }
        }

        // Price
        $priceInfo = $this->re("/\n[ ]{0,5}" . $this->opt($this->t("CHARGES")) . "\n+([\s\S]+?\n[ ]{0,5}" . $this->opt($this->t("Total Charges")) . " +.+)\n/", $text);

        if (!empty($priceInfo)) {
            if (preg_match("/\(Shown in ([A-Z]{3})\)/", $priceInfo, $m)) {
                $r->price()->currency($m[1]);
            }

            if (preg_match("/\n\s*" . $this->opt($this->t("Total Charges")) . " +(\d[\d., ]*)$/", $priceInfo, $m)) {
                $r->price()->total($this->normalizeAmount($m[1]));
            }
        }

        return $email;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('date before: "'.$str.'"');
        $in = [
            // 29/11/2019 10:14 AM
            "/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*(\d{1,2}:\d{2}(?: *[AP]M))\s*$/sui",
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('date after: "'.$str.'"');

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function strposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && strpos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }
}
