<?php

namespace AwardWallet\Engine\eurostar\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;

class TicketsPdf extends \TAccountChecker
{
    public $mailFiles = "eurostar/it-176481232.eml, eurostar/it-233295412.eml, eurostar/it-697107365.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Train'           => ['Train'],
            'confNumber'      => ['Booking reference(s)'],
            'ticketEnd'       => ['Important information', 'How to protect yourself and others', 'BEFORE YOU TRAVEL'],
            'statusVariants'  => ['Confirmed'],
            'EUROSTAR TICKET' => ['EUROSTAR TICKET', 'Eurostar ticket'],
        ],
        'fr' => [
            'EUROSTAR TICKET' => 'BILLET EUROSTAR',
            'Train'           => ['Train'],
            'Date'            => 'Date',
            'From'            => 'De',
            'Departure'       => 'Départ',
            'To'              => 'Vers',
            'Arrival'         => 'Arrivée',
            'Class'           => 'Classe',
            'confNumber'      => ['Référence de réservation'],
            'Coach'           => 'Voiture',
            'Seat'            => 'Place',
            'Issued:'         => 'Émis le:',

            'Additional information' => 'Informations complémentaires',
            'Ticket number'          => 'Numéro de billet',
            'ticketEnd'              => ['Informations importantes', 'Avant le départ'],

            // html
            //            'Order status' => '',
            //            'statusVariants' => ['Confirmed'],
            //            'Total order amount' => '',
        ],
    ];

    private $enDatesInverted = false;

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (strpos($textPdf, 'EUROSTAR TICKET') === false
                && stripos($textPdf, 'eurostar.com') === false
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
        $textTrainPdf = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $textTrainPdf .= "\n\n" . $textPdf;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('TicketsPdf' . ucfirst($this->lang));

        $train = $email->add()->train();

        $this->parseTrain($parser, $train, $textTrainPdf);

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total order amount'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/', $totalPrice, $matches)) {
            // 172.80 USD
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $train->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);
        }

        $orderStatus = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Order status'))}] ]/*[normalize-space()][2]", null, true, "/^{$this->opt($this->t('statusVariants'))}$/");

        if ($orderStatus) {
            $train->general()->status($orderStatus);
        }

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

    private function parseTrain(\PlancakeEmailParser $parser, Train $train, $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:upper:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $confNumbers = $confNumberTitles = $travellers = $coachByTrain = [];

        $ticketsTexts = $this->splitText($text, "/^(.{15,80}[ ]{2}{$this->opt($this->t('EUROSTAR TICKET'))})/m", true);

        foreach ($ticketsTexts as $ticketText) {
            $thead = $tbody = null;

            if (preg_match("/^(?<thead>[\s\S]*{$this->opt($this->t('EUROSTAR TICKET'))}[\s\S]*)\n+(?<tbody>[ ]*{$this->opt($this->t('Train'))}[ ]*[:]+[\s\S]*?\S)\n+[ ]*{$this->opt($this->t('ticketEnd'))}/", $ticketText, $m)) {
                $thead = $m['thead'];
                $tbody = $m['tbody'];
            } else {
                $this->logger->debug('Wrong ticket structure!');

                continue;
            }

            $ticket = $this->re("/\n.{15,}[ ]{2}{$this->opt($this->t('Ticket number'))}[ ]*[:]+[ ]*({$patterns['eTicket']})(?:\n|$)/", $tbody);

            $tbody = preg_replace("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('Additional information'))}(?:[ ]{2}|\n)[\s\S]*$/", '$1', $tbody);

            $traveller = $this->re("/\n.{15,80}[ ]{2}({$patterns['travellerName']})\s*$/u", $thead);
            $travellers[] = $traveller;

            if (!in_array($ticket, array_column($train->getTicketNumbers(), 0))) {
                $train->addTicketNumber($ticket, false, $traveller);
            }

            // remove additional info

            $tablePos = [0];

            if (preg_match("/(.{15,}[ ]{2}){$this->opt($this->t('Date'))}[ ]*:/", $tbody, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($tbody, $tablePos);

            if (count($table) !== 2) {
                $this->logger->debug('Wrong main table!');

                return;
            }

            // remove barcode title
            $table[1] = preg_replace("/^(.{25,}?)[ ]{3,}\S.*$/m", '$1', $table[1]);

            $trainNumber = $this->re("/^[ ]*{$this->opt($this->t('Train'))}[ ]*[:]+[ ]*(\d+)$/m", $table[0]);

            if ($trainNumber === null) {
                $this->logger->debug('Train number not found!');
                $train->addSegment(); // for 100% fail

                continue;
            }

            $coach = $this->re("/\n[ ]*{$this->opt($this->t('Coach'))}[ ]+(\d{1,6})[ ]*.*\n/", $table[1]);

            if ($coach !== null) {
                if (array_key_exists($trainNumber, $coachByTrain)) {
                    $coachByTrain[$trainNumber][] = $coach;
                } else {
                    $coachByTrain[$trainNumber] = [$coach];
                }
            }

            if (!isset($s)
                || isset($s) && $s->getNumber() !== $trainNumber
            ) {
                $s = $train->addSegment();

                $s->extra()->number($trainNumber);

                if (preg_match("/\n[ ]*{$this->opt($this->t('From'))}\n+((?:[ ]*.+\n+){1,3})[ ]*{$this->opt($this->t('To'))}\n/", $table[0], $m)) {
                    $s->departure()->name(preg_replace('/\s+/', ' ', trim($m[1])));
                }

                if (preg_match("/\n[ ]*{$this->opt($this->t('To'))}\n+((?:[ ]*.+\n+){1,3})[ ]*{$this->opt($this->t('Class'))}[ ]*:/", $table[0], $m)) {
                    $s->arrival()->name(preg_replace('/\s+/', ' ', trim($m[1])));
                }

                if (preg_match("/\n[ ]*{$this->opt($this->t('Class'))}[ ]*[:]+[ ]*((?:.+\n+){1,2})[ ]*{$this->opt($this->t('confNumber'))}[ ]*:/", $table[0], $m)) {
                    $s->extra()->cabin(preg_replace('/\s+/', ' ', trim($m[1])));
                }

                $dateVal = $this->re("/^\s*{$this->opt($this->t('Date'))}[ ]*[:]+\s*(.*\d.*?)[ ]*\n/", $table[1]);

                if (preg_match('/^\d{1,2}\/(\d{1,2})$/', $dateVal, $m) && $m[1] > 12) {
                    $this->enDatesInverted = true;
                }
                $dateNormal = $this->normalizeDate($dateVal);

                $dateIssued = preg_replace('/^(\d{2})(\d{2})(\d{2})$/', '$1.$2.20$3',
                    $this->re("/ {5,}{$this->opt($this->t('Issued:'))} *(\d{6}) \d{4}(?:\n|$)/", $ticketText));
                // $date = EmailDateHelper::calculateDateRelative($dateNormal, $this, $parser, '%D%, %Y%');
                $date = EmailDateHelper::parseDateRelative($dateNormal, strtotime($dateIssued), true, '%D%.%Y%');

                $timeDep = $this->re("/\n[ ]*{$this->opt($this->t('Departure'))}\s*\n+[ ]*({$patterns['time']})/u", $table[1]);
                $s->departure()->date(strtotime($timeDep, $date));

                $timeArr = $this->re("/\n[ ]*{$this->opt($this->t('Arrival'))}\s*\n+[ ]*({$patterns['time']})/", $table[1]);
                $s->arrival()->date(strtotime($timeArr, $date));

                $s->extra()->car($coach);
            }

            if (array_key_exists($trainNumber, $coachByTrain)
                && count(array_unique($coachByTrain[$trainNumber])) > 1
            ) {
                $s->extra()->car(null, false, true); // remove car value
            }

            $seat = $this->re("/\n.*[ ]*{$this->opt($this->t('Seat'))}[ ]+(\d{1,6})[ ]*(?:\n|$)/", $table[1]);
            $s->extra()->seat($seat, false, false, $traveller);

            if (preg_match("/\n[ ]*({$this->opt($this->t('confNumber'))})[ ]*:[ ]*([-A-Z\d]{5,})(?:\n|$)/", $table[0], $m)) {
                $confNumbers[] = $m[2];
            }
        }

        foreach (array_unique($confNumbers) as $conf) {
            $train->general()->confirmation($conf);
        }

        if (count($travellers) > 0) {
            $train->general()->travellers(array_unique($travellers), true);
        }
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Train']) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Train']) !== false
                && $this->strposArray($text, $phrases['confNumber']) !== false
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 19/08
            '/^(\d{1,2})\/(\d{1,2})$/',
        ];
        $out[0] = $this->enDatesInverted ? '$2.$1' : '$1.$2';

        return preg_replace($in, $out, $text);
    }
}
