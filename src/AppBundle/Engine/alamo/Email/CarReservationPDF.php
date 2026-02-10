<?php

namespace AwardWallet\Engine\alamo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarReservationPDF extends \TAccountChecker
{
    public $mailFiles = "alamo/it-128896134.eml, alamo/it-129850702.eml, alamo/it-130194118.eml";

    public $subjects = [
        '/Alamo Rent A Car Reservation Confirmation\s*\d+\s*at/',
    ];
    public $prov;
    public $lang = '';
    public $pdfNamePattern = ".*pdf";

    public $detectLang = [
        'es' => ['Gracias por preferir'],
        'el' => ['Αριθμός κράτησης'],
        'en' => ['Thank you for choosing'],
    ];

    public static $dictionary = [
        "en" => [
            'Reservation Number' => 'Reservation Number',
            'Driver Name'        => 'Driver Name',
            'shown in'           => 'shown in',
            'Total Charges'      => 'Total charges',
            'Car Summary'        => 'Car Summary',
            'Pickup'             => ['Pickup Location', 'Pickup'],
            'Drop Off'           => ['Return Location', 'Drop Off'],
            'Rate Charges'       => ['Rate Charges', 'Hinnat ja vero', 'Seuraa'],
            'Pick Up Date'       => 'Pick Up Date',
            'Return Date'        => 'Return Date',
        ],
        "es" => [
            'Reservation Number' => 'NO. DE RESERVA',
            'Driver Name'        => 'Nombre del Conductor',
            'shown in'           => 'Costo Total (',
            'Total Charges'      => 'Cargos Totales',
            'Car Summary'        => 'Resumen de Auto',
            'Pickup'             => 'Entrega',
            'Drop Off'           => 'Devolución',
            'Rate Charges'       => 'Esta reserva',
        ],
        "el" => [
            'Reservation Number' => 'Αριθμός κράτησης',
            'Driver Name'        => 'Όνομα Οδηγού',
            //'shown in' => '',
            'Total Charges' => 'Συνολικές χρεώσεις',
            'Car Summary'   => 'Επισκόπιση αυτοκινήτου',
            'Pickup'        => 'Παραλαβή',
            'Drop Off'      => 'Επιστροφή',
            // 'Rate Charges' => '',
        ],
    ];

    private $patterns = [
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subjects as $subject) {
            if (preg_match($subject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->assignLang($text);

            if ($this->assignProvider($text) && $this->detectBody($text)) {
                return true;
            }
        }

        return false;
    }

    public function ParseCarPDF(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $rental = $email->add()->rental();

        $traveller = $this->re("/{$this->opt($this->t('Driver Name'))}\D*:[ ]+([[:upper:]]{2,}[-[:upper:]\s]+)[ ]{5,}\D+/u", $text)
            ?? $this->re("/{$this->opt($this->t('Driver Name'))}\D*:[ ]+([[:upper:]]{2,}[-[:upper:]\s]+)\n/u", $text)
        ;

        $rental->general()
            ->confirmation($this->re("/{$this->opt($this->t('Reservation Number'))}\D+(\d{8,})/", $text), 'Reservation Number')
            ->traveller($traveller, true);

        $currency = $this->re("/{$this->opt($this->t('shown in'))}\s*([A-Z]{3})\)/", $text);
        $totalCharges = $this->re("/{$this->opt($this->t('Total Charges'))}\D*(\d[,.‘\'\d]*)/u", $text);

        if ($totalCharges !== null) {
            $rental->price()->currency($currency)->total(PriceHelper::parse($totalCharges, $currency));
        }

        if (preg_match("/{$this->opt($this->t('Car Summary'))}\s*\:\n.+[ ]{5,}(.+)\n.+[ ]{5,}(.+)\n/", $text, $m)) {
            $rental->car()->type($m[1]);

            if (!preg_match('/^\S+@\S+$/', $m[2])) {
                $rental->car()->model($m[2]);
            }
        }

        $rentalText = $this->re("/\b({$this->opt($this->t('Pickup'))}.+?)\n+[ ]*{$this->opt($this->t('Rate Charges'))}/su", $text);
        $rentalTable = $this->splitCols($rentalText, [0, 28]);

        if (preg_match("/{$this->opt($this->t('Pickup'))}\D*:*\n+(.+[AP]M)\n+(.+?)\n+[ ]*({$this->patterns['phone']})\n+(?:.+)?{$this->opt($this->t('Drop Off'))}/us", $rentalTable[0], $m)) {
            $rental->pickup()
                ->date($this->normalizeDate($m[1]))
                ->location(str_replace("\n", " ", $m[2]))
                ->phone($m[3]);
        }

        if (preg_match("/{$this->opt($this->t('Drop Off'))}\D*:*\n+(.+[AP]M)\n+(.+?)\n+[ ]*({$this->patterns['phone']})(?:\n|$)/us", $rentalTable[0], $m)) {
            $rental->dropoff()
                ->date($this->normalizeDate($m[1]))
                ->location(str_replace("\n", " ", $m[2]))
                ->phone($m[3]);
        }
    }

    public function ParseCarPDF2(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $rental = $email->add()->rental();

        $traveller = $this->re("/{$this->opt($this->t('Driver Name'))}\D*:[ ]+([[:upper:]]{2,}[-[:upper:]\s]+)[ ]{5,}\D+/u", $text)
            ?? $this->re("/{$this->opt($this->t('Driver Name'))}\D*:[ ]+([[:upper:]]{2,}[-[:upper:]\s]+)\n/u", $text)
        ;

        $rental->general()
            ->confirmation($this->re("/{$this->opt($this->t('Reservation Number'))}\D+(\d{8,})/", $text), 'Reservation Number')
            ->traveller($traveller, true);

        $currency = $this->re("/\({$this->opt($this->t('shown in'))}\s*([A-Z]{3})\)/", $text);
        $totalCharges = $this->re("/{$this->opt($this->t('Total Charges'))}\D*(\d[,.‘\'\d]*)/u", $text);

        if ($totalCharges !== null) {
            $rental->price()->currency($currency)->total(PriceHelper::parse($totalCharges, $currency));
        }

        if (preg_match("/{$this->opt($this->t('Car Summary'))}\s*\:\n.+[ ]{5,}(.+)\n.+[ ]{5,}(.+)\n/", $text, $m)) {
            $rental->car()
                ->type($m[1])
                ->model($m[2]);
        }

        $rentalText = $this->re("/({$this->opt($this->t('Pickup'))}.+)\n\s+{$this->opt($this->t('Rate Charges'))}/su", $text);
        $rentalTable = $this->splitCols($rentalText, [0, 45, 83]);

        if (preg_match("/{$this->opt($this->t('Pickup'))}\:*\n*(.+)\n\D+{$this->opt($this->t('Drop Off'))}/us", $rentalTable[0], $m)) {
            $rental->pickup()
                ->location(str_replace("\n", " ", $m[1]));
        }

        if (preg_match("/{$this->opt($this->t('Drop Off'))}\:?\n\n+(.+)\n\n\n{$this->opt($this->t('Rate Charges'))}/us", $rentalTable[0], $m)) {
            $rental->dropoff()
                ->location(str_replace("\n", " ", $m[1]));
        }

        if (preg_match("/{$this->opt($this->t('Pick Up Date'))}\:?\n*(\d+\/\d+\/\d{4})\n*([\d\:]+\s*A?P?M)/", $rentalTable[1], $m)) {
            $rental->pickup()
                ->date(strtotime($m[1] . ', ' . $m[2]));
        }

        if (preg_match("/{$this->opt($this->t('Return Date'))}\:?\n*(\d+\/\d+\/\d{4})\n*([\d\:]+\s*A?P?M)/", $rentalTable[1], $m)) {
            $rental->dropoff()
                ->date(strtotime($m[1] . ', ' . $m[2]));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if ($this->detectBody($text) !== true) {
                continue;
            }

            if (empty($this->prov)) {
                $this->assignProvider($text);
            }

            if (stripos($text, $this->t('Pick Up Date')) !== false) {
                $this->ParseCarPDF2($email, $text);
            } else {
                $this->ParseCarPDF($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $email->setProviderCode($this->prov);

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
        return ['alamo', 'rentacar', 'national'];
    }

    private function assignProvider($text): bool
    {
        if (strpos($text, 'Alamo') !== false) {
            $this->prov = 'alamo';

            return true;
        } elseif (strpos($text, 'Enterprise') !== false) {
            $this->prov = 'rentacar';

            return true;
        } elseif (strpos($text, 'National.') !== false) {
            $this->prov = 'national';

            return true;
        } elseif (strpos($text, 'Thank you for choosing National') !== false) {
            $this->prov = 'national';

            return true;
        }

        return false;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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

    private function normalizeDate($date)
    {
        $in = [
            '#^(\d+)\/(\d+)\/(\d+)\s*[@]\s*([\d\:]+\s*A?P?M)$#u', //01/10/2022 @ 02:00 PM
        ];
        $out = [
            '$2.$1.$3, $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function detectBody(?string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        return strpos($text, $this->t('Car Summary')) !== false
            && strpos($text, $this->t('Reservation Number')) !== false;
    }

    private function assignLang($text): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
