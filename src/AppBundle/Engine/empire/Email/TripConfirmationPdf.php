<?php

namespace AwardWallet\Engine\empire\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TripConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "empire/it-138171521.eml, empire/it-274916921.eml, empire/it-293207696.eml";

    public $lang = '';

    // use TripConfirmation::$detectProvider

    public static $dictionary = [
        'en' => [
            'Reservation#'     => ['Reservation#', 'Reservation #'],
            'Pickup Address'   => ['Pickup Address'],
            'headerTableStart' => 'TRIP CONFIRMATION',
            'headerTableEnd'   => ['Reservation Details', 'Reservation Detail'],
            'resDetailStart'   => ['Reservation Details', 'Reservation Detail'],
            'resDetailEnd'     => ['Special Instructions', 'Terms & Conditions'],
            'afterAddress'     => ['PO/Reference', 'Extra Passengers', 'Department', 'Employee', 'Event code'],
            'baseFare'         => ['Base Flat Charge', 'Flat Rate'],
            'Passenger Name'   => ['Passenger Name', 'Passenger'],
            'Vehicle Type'     => ['Vehicle Type'],
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(TripConfirmation::$detectProvider);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@empirecls.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            // $detectProvider = false;
            //
            // foreach (TripConfirmation::$detectProvider as $pDetects) {
            //     foreach ($pDetects as $pd) {
            //         if (stripos($textPdf, $pd) !== false) {
            //             $detectProvider = true;
            //
            //             break 2;
            //         }
            //     }
            // }

            if ($this->detectFormat($textPdf) && $this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        $providerCode = '';

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            foreach (TripConfirmation::$detectProvider as $pCode => $pDetects) {
                foreach ($pDetects as $pd) {
                    if (stripos($textPdf, $pd) !== false) {
                        $providerCode = $pCode;
                        $this->logger->debug('$providerCode = ' . print_r($providerCode, true));

                        break 2;
                    }
                }
            }

            if ($this->detectFormat($textPdf) && $this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        if (!empty($providerCode)) {
            $email->setProviderCode($providerCode);
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('TripConfirmationPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]',
            'travellerName' => '[[:alpha:]][-.\'’\/[:alpha:]\s]*[[:alpha:]]', // WEDDING VASTOLA/BACARDI
        ];

        $transfer = $email->add()->transfer();

        $status = $this->re("/^[ ]*{$this->opt($this->t('TRIP CONFIRMATION'))}[ ]+{$this->opt($this->t('STATUS'))}[ ]*:[ *]*(.{2,}?)[ *]*$/m", $text);
        $transfer->general()->status($status);

        $headerTableText = $this->re("/^[ ]*{$this->opt($this->t('headerTableStart'))}(?:[ ]{2}.+|$)\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('headerTableEnd'))}/m", $text);
        $tablePos = [0];

        if (preg_match("/^(.+ ){$this->opt($this->t('Vehicle Type'))}[ ]*:/m", $headerTableText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.+ ){$this->opt($this->t('Customer'))}[ ]*:/m", $headerTableText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $hTable = $this->splitCols($headerTableText, $tablePos);

        if (count($hTable) !== 3) {
            $this->logger->debug('Wrong header table!');

            return;
        }

        if (preg_match("/(?:^\s*|\n[ ]*)({$this->opt($this->t('Reservation#'))})[ ]*:[ ]*([A-Z\d]{4,})(?:[ ]*[*][ ]*\d{1,3})?(?:\n|$)/", $hTable[0], $m)) {
            $transfer->general()->confirmation($m[2], $m[1]);
        }

        if (preg_match("/(?:^\s*|\n[ ]*){$this->opt($this->t('Passenger Name'))}[ ]*:[ ]*([\s\S]*?)(?:\n+.+:|\n+{$this->opt($this->t('Passenger Mobile'))}[ ]*:|\s*$)/", $hTable[0], $m)
            && preg_match("/^{$patterns['travellerName']}$/u", $passengerName = preg_replace(['/\s+/', '/^([^)(]{2,}?)\s*\([^)(]*\)$/'], [' ', '$1'], $m[1]))
        ) {
            $transfer->general()->traveller($passengerName, true);
        }

        $lastDate = null;
        $s = $transfer->addSegment();

        $vehicle = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('Vehicle Type'))}[ ]*:[ ]*([\s\S]*?)(?:\n\n|\n.+:|$)/", $hTable[1]);
        $noOfPax = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('# of Pax'))}[ ]*:[ ]*(\d{1,3})(?:\n\n|\n.+:|$)/", $hTable[1]);
        $s->extra()->type($vehicle, false, true)->adults($noOfPax);

        $reservationDetailText = $this->re("/^[ ]*{$this->opt($this->t('resDetailStart'))}(?:[ ]{2}.+|$)\n+([\s\S]+?)\n+^[ ]*{$this->opt($this->t('resDetailEnd'))}/m", $text);
        $tablePos = [0];
        $tablePos1 = [];

        $chargeNames = [
            'baseFare',
            'Service Fee',
            'Discretionary Gratuity',
            'Total Payments',
            'Balance Due',
        ];

        foreach ($chargeNames as $chargeName) {
            if (preg_match("/^(.+ ){$this->opt($this->t($chargeName))}[ ]*:/m", $reservationDetailText, $matches)) {
                $tablePos1[] = mb_strlen($matches[1]);
            }
        }

        if (count($tablePos1) > 0) {
            sort($tablePos1);
            $tablePos[] = $tablePos1[0];
        }
        $table = $this->splitCols($reservationDetailText, $tablePos);

        if (count($table) !== 1 && count($table) !== 2) {
            $this->logger->debug('Wrong resDetail table!');

            return;
        }

        $table[0] = preg_replace("/^(.*{$this->opt($this->t('Pickup Address'))}[ ]*:.*{$this->opt($this->t('Dropoff Address'))}[ ]*:.*?)\n+[ ]*{$this->opt($this->t('afterAddress'))}[ ]*:.*$/s", '$1', $table[0]);

        $pickupAddress = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('Pickup Address'))}[ ]*:[ ]*([\s\S]*?)\n+[ ]*(?:Total\s+\d{1,3}\s+Stop|{$this->opt($this->t('Dropoff Address'))}[ ]*:)/i", $table[0]);

        $stopRows = [];
        $addressPickupParts = preg_split("/\n+[ ]*{$this->opt($this->t('Stop'))}[ ]+\d{1,3}[ ]*[:]+[ ]*/", $pickupAddress);

        if (count($addressPickupParts) > 1) {
            // it-293207696.eml
            $pickupAddress = array_shift($addressPickupParts);
            $stopRows = $addressPickupParts;
        }

        if (preg_match("/^(?<address>.{3,}?)\s*\[\s*Arrv\s*:\s*(?<time1>{$patterns['time']})\s*Dep\s*:\s*(?<time2>{$patterns['time']})\s*\]$/is", $pickupAddress, $m)) {
            $pickupAddress = preg_replace('/\s+/', ' ', $m['address']);
            $timeArr = $m['time1'];
            $timeDep = $m['time2'];
        } else {
            $timeArr = $timeDep = null;
        }

        $startTime = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('Start Time'))}[ ]*:[ ]*({$patterns['time']})(?:\n\n|\n.+:|$)/", $hTable[1]);
        $endTime = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('End Time'))}[ ]*:[ ]*({$patterns['time']})(?:\n\n|\n.+:|$)/", $hTable[1]);
        $pickupDate = strtotime($this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('Pickup Date'))}[ ]*:[ ]*(.*\d.*)(?:[ ]{2}|\n|$)/", $hTable[0]));
        $pickupTime = $timeArr ?? $startTime ?? $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('Pickup Time'))}[ ]*:[ ]*({$patterns['time']})/", $hTable[0]);

        if (preg_match("/^(?<location>.+?)\s*{$this->opt($this->t('Arriving from'))}\s+[A-Z]{3}(?:\s+{$this->opt($this->t('at'))}\s*:\s*(?<time>.{3,}))?$/s", $pickupAddress, $m)) {
            if (preg_match("/^(?<location>.{3,}?)\s*,[^,]+{$this->opt($this->t('Flight'))}\s*#\s*\d+/s", $m['location'], $m2)) {
                $pickupAddress = $m2['location'];
            } else {
                $pickupAddress = $m['location'];
            }

            if (!empty($m['time']) && preg_match("/^{$patterns['time']}$/", $m['time'])) {
                $pickupTime = $m['time'];
            }
        }

        $pickupAddress = preg_replace("/^\s*(\S[\s\S]+?)\s*\n *DIRS:[\s\S]+/", '$1', $pickupAddress);

        if (preg_match("/^\s*(?<code>[A-Z]{3})\s*\(/", $pickupAddress, $m)) {
            $s->departure()->code($m['code']);
        } elseif ($pickupAddress) {
            $s->departure()->address(preg_replace('/\s+/', ' ', $pickupAddress));
        }

        if ($pickupDate && $pickupTime) {
            $s->departure()->date(strtotime($pickupTime, $pickupDate));
            $lastDate = $s->getDepDate();
        }

        /* [2023-02-27]: Currently not relevant!
        foreach ($stopRows as $i => $sRow) {
            $stopAddress = preg_replace('/\s+/', ' ', trim($sRow));
            $s->arrival()->address($stopAddress)->noDate();

            $s = $transfer->addSegment();
            $s->extra()->type($vehicle, false, true)->adults($noOfPax);

            $s->departure()->address($stopAddress);

            if (count($stopRows) > ($i + 1) && !empty($lastDate)) {
                $s->departure()->date(strtotime('+' . ($i + 1) . ' minutes', $lastDate)); // dirty hack
            } else {
                $s->departure()->noDate();
            }
        }
        */

        $dropoffAddress = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('Dropoff Address'))}[ ]*:[ ]*([\s\S]*?)\s*$/", $table[0]);
        $dropoffTime = null;

        if (preg_match("/^(?<location>.+?)\s*(?:{$this->opt($this->t('Departing to'))}|{$this->opt($this->t('Arriving from'))})\s+[A-Z]{3}(?:\s+{$this->opt($this->t('at'))}\s*:\s*(?<time>.{3,}))?$/s", $dropoffAddress, $m)) {
            if (preg_match("/^(?<location>.{3,}?)\s*,[^,]+{$this->opt($this->t('Flight'))}\s*#\s*\d+/s", $m['location'], $m2)) {
                $dropoffAddress = preg_replace('/\s+/', ' ', $m2['location']);
            } else {
                $dropoffAddress = preg_replace('/\s+/', ' ', $m['location']);
            }

            if (!empty($m['time']) && preg_match("/^{$patterns['time']}$/", $m['time'])) {
                $dropoffTime = $m['time'];
            }
        }
        $dropoffAddress = preg_replace("/^\s*(\S[\s\S]+?)\s*\n *DIRS:[\s\S]+/", '$1', $dropoffAddress);

        if (preg_match("/^\s*(?<code>[A-Z]{3})\s*\(/", $dropoffAddress, $m)) {
            $s->arrival()->code($m['code']);
        } elseif ($dropoffAddress) {
            $s->arrival()->address(preg_replace('/\s+/', ' ', $dropoffAddress));
        }

        if (!$dropoffTime) {
            $dropoffTime = $timeDep ?? $endTime;
        }

        if (!empty($lastDate) && $dropoffTime) {
            $dropoffDate = strtotime($dropoffTime, $lastDate);

            if (!empty($dropoffDate) && $lastDate > $dropoffDate) {
                $dropoffDate = strtotime('+1 days', $dropoffDate); // dirty hack
            }

            $s->arrival()->date($dropoffDate);
        } elseif (!$dropoffTime) {
            $s->arrival()->noDate();
        }

        if (count($table) === 1) {
            // it-274916921.eml
            return;
        }

        $totalPrice = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('Total'))}[ ]*:[ ]*(.*\d.*?)(?:[ ]*\n|\s*$)/", $table[1]);

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $826.27
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $transfer->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('baseFare'))}[ ]*:[ ]*(.*\d.*?)(?:[ ]*\n|\s*$)/", $table[1]);

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseFare, $m)) {
                $transfer->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeText = $this->re("/(?:^\s*|\n[ ]*){$this->opt($this->t('baseFare'))}[ ]*:.*\n+([\s\S]*?)[ ]*\n+[ ]*{$this->opt($this->t('Total'))}[ ]*:/", $table[1]);
            preg_match_all("/^[ ]*(?<name>[^:\n]{2,}?)[ ]*:[ ]*(?<value>.+?)[ ]*$/m", $feeText, $feeMatches, PREG_SET_ORDER);

            foreach ($feeMatches as $feeM) {
                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeM['value'], $m)) {
                    $transfer->price()->fee($feeM['name'], PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }
    }

    private function detectFormat($text): bool
    {
        $text = substr($text, 0, 1200);

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['headerTableStart']) && !empty($dict['Reservation#'])
                && !empty($dict['Vehicle Type']) && !empty($dict['Passenger Name'])
                && preg_match("/\n {0,5}{$this->opt($dict['headerTableStart'])} {10,}.+"
                    . "\n+ {0,5}{$this->opt($dict['Reservation#'])} ?:.+? {2,}{$this->opt($dict['Vehicle Type'])} ?:.+"
                    . "\n {0,5}{$this->opt($dict['Passenger Name'])} ?:/", $text)
            ) {
                return true;
            }
        }

        return false;
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Reservation#']) || empty($phrases['Pickup Address'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Reservation#']) !== false
                && $this->strposArray($text, $phrases['Pickup Address']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (!empty($m[$c])) {
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
}
