<?php

namespace AwardWallet\Engine\advancedair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TripDetailsPdf extends \TAccountChecker
{
    public $mailFiles = "advancedair/it-628849582.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'        => ['Reservation Locator'],
            'departure'         => ['Departure Airport'],
            'segHeadersEnd'     => 'Local)',
            'paxHeadersEnd'     => 'Reservation Locator',
            'paymentHeadersEnd' => 'Payment Type',
            'feesHeadersEnd'    => 'Charges',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flyadvancedair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/Advanced Airlines\s*-\s*RESERVATION\s*#\s*[A-Z\d]{5,}/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if (stripos($textPdf, 'www.advancedairlines.com') === false
                && strpos($textPdf, 'with Advanced Air') === false
                && !preg_match('/©\s*\d{4}\s*Advanced Airlines/i', $textPdf)
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('TripDetailsPdf' . ucfirst($this->lang));

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
            'date'          => '\b\d{1,2}[-\s]+[[:alpha:]]{3,20}[-\s]+\d{4}\b', // 18 Jan 2024
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $segmentsText = $this->re("/\s{$this->opt($this->t('segHeadersEnd'))}(?: .+)?\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('Passenger'))}\b/", $text);

        // TODO: need multi-segments examples
        $segments = [$segmentsText];

        foreach ($segments as $segText) {
            $s = $f->addSegment();

            $tablePos = [0];

            if (preg_match("/^(.{3,40}? ){$patterns['date']}/m", $segText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(.{15,60}? ){$patterns['time']}/m", $segText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match_all("/({$patterns['time']})(?: -|[ ]{2}|\n|$)/", $segText, $timeMatches) && count($timeMatches[1]) === 2
                && preg_match("/^(.{15,80}? {$this->opt($timeMatches[1][0])})/m", $segText, $matches1)
                && preg_match("/^(.{15,80}? {$this->opt($timeMatches[1][1])})/m", $segText, $matches2)
            ) {
                $td3Pos = [mb_strlen($matches1[1]) + 1, mb_strlen($matches2[1]) + 1];
                rsort($td3Pos);
                $tablePos[] = $td3Pos[0];
            }

            $table = $this->splitCols($segText, $tablePos);

            if (count($table) !== 4) {
                $this->logger->debug('Wrong segment table!');

                break;
            }

            if (preg_match('/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s*$/', $table[0], $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $date = strtotime($table[1]);

            if (preg_match("/^\s*({$patterns['time']})\s*[-]+\s*({$patterns['time']})\s*$/", $table[2], $m)) {
                $timeDep = $m[1];
                $timeArr = $m[2];
            } else {
                $timeDep = $timeArr = null;
            }

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            $tablePos = [0];

            if (preg_match_all('/^([ ]{0,12}\S.*?[ ]{2,})\S/m', $table[3], $rowMatches)) {
                $lengthValues = array_map('mb_strlen', $rowMatches[1]);
                sort($lengthValues);
                $tablePos[] = $lengthValues[0];
            }

            $tableAirports = $this->splitCols($table[3], $tablePos);

            if (count($tableAirports) !== 2) {
                $this->logger->debug('Wrong airports sub-table!');

                break;
            }

            if (preg_match($pattern = '/^\s*(?<name>[\s\S]{2,}?)[\s(]+(?<code>[A-Z]{3})[\s)]*$/', $tableAirports[0], $m)) {
                $s->departure()->name(preg_replace('/\s+/', ' ', $m['name']))->code($m['code']);
            } else {
                $s->departure()->name(preg_replace('/\s+/', ' ', trim($tableAirports[0])));
            }

            if (preg_match($pattern, $tableAirports[1], $m)) {
                $s->arrival()->name(preg_replace('/\s+/', ' ', $m['name']))->code($m['code']);
            } else {
                $s->arrival()->name(preg_replace('/\s+/', ' ', trim($tableAirports[1])));
            }
        }

        $passengersText = $this->re("/\s{$this->opt($this->t('paxHeadersEnd'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('Charges'))}\b/", $text);
        $travellers = $reservationLocators = [];
        $tablePos = [0];

        if (preg_match_all('/^([ ]{0,20}\S.*?[ ]{2,})\S/m', $passengersText, $rowMatches)) {
            $lengthValues = array_map('mb_strlen', $rowMatches[1]);
            sort($lengthValues);
            $tablePos[] = $lengthValues[0];
        }

        $tablePassengers = $this->splitCols($passengersText, $tablePos);

        if (count($tablePassengers) === 2) {
            $passengerValues = preg_split('/[ ]*\n{2,}[ ]*/', trim($tablePassengers[0]));

            foreach ($passengerValues as $pVal) {
                $pVal = preg_replace('/\s+/', ' ', $pVal);

                if (preg_match("/^{$patterns['travellerName']}$/u", $pVal)) {
                    $travellers[] = $pVal;
                } else {
                    $travellers = [];
                    $this->logger->debug('Found wrong traveller name!');

                    break;
                }
            }

            if (preg_match_all('/(?:^| )([A-Z\d]{5,14})$/m', $tablePassengers[1], $pnrMatches)) {
                $reservationLocators = array_unique($pnrMatches[1]);
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }

        foreach ($reservationLocators as $pnr) {
            $f->general()->confirmation($pnr);
        }

        $paymentText = $this->re("/\s{$this->opt($this->t('paymentHeadersEnd'))}(?: .+)?\n+([\s\S]+?)(?:\n\n|$)/", $text);

        if (preg_match('/^.+[ ]{2}(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)(?:\n|$)/u', $paymentText, $matches)
            && !preg_match('/[ ]{2}/', $matches['currency'])
        ) {
            // $354.18
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $feesText = $this->re("/(?:\n[ ]*|[ ]{2}){$this->opt($this->t('feesHeadersEnd'))}(?: .+)?\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('Payment Type'))}\b/", $text);
            $feeChargeValues = $feeAmountValues = [];
            $tablePos = [0];

            if (preg_match_all('/^([ ]{0,20}\S.*?[ ]{2,})\S/m', $feesText, $rowMatches)) {
                $lengthValues = array_map('mb_strlen', $rowMatches[1]);
                sort($lengthValues);
                $tablePos[] = $lengthValues[0];
            }

            $tableFees = $this->splitCols($feesText, $tablePos);

            if (count($tableFees) === 2) {
                $feeChargeValues = preg_split('/[ ]*\n{2,}[ ]*/', trim($tableFees[0]));

                if (preg_match_all('/^.+[ ]{2}([^\-\d)(]+\d[,.‘\'\d ]*)$/m', $tableFees[1], $amountMatches)) {
                    $feeAmountValues = $amountMatches[1];
                }
            }

            if (count($feeChargeValues) > 0 && count($feeChargeValues) === count($feeAmountValues)) {
                foreach ($feeAmountValues as $i => $feeAmountVal) {
                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeAmountVal, $m)) {
                        $f->price()->fee($feeChargeValues[$i], PriceHelper::parse($m['amount'], $currencyCode));
                    }
                }
            }
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['departure'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumber']) !== false
                && $this->strposArray($text, $phrases['departure']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
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
