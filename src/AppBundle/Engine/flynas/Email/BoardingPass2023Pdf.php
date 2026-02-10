<?php

namespace AwardWallet\Engine\flynas\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPass2023Pdf extends \TAccountChecker
{
    public $mailFiles = "flynas/it-586973990.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'   => ['Booking Ref.'],
            'flightNumber' => ['Flight No.'],
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flynas.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/Boarding Pass for PNR\s+(?-i)[A-Z\d]{5,}/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = $this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> '));

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider !== true
                && stripos($textPdf, 'flynas reserves the right') === false
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
        $textPdfFull = '';
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $header = $parser->getAttachmentHeader($pdf, 'Content-Type');
                $pdfName = $this->re('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header) ?? '{{unknown}}';
                $textPdfFull .= preg_replace('/^([ ]*[A-Z]{3}[ ]+[A-Z]{3}(?:[ ]{2}.+)?)$/m', "%Attachment_Name%: {$pdfName}\n\n$1", $textPdf) . "\n\n";
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('BoardingPass2023Pdf' . ucfirst($this->lang));

        $this->parsePDFs($email, $textPdfFull);

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

    private function parsePDFs(Email $email, string $text): void
    {
        $flights = [];

        $segments = $this->splitText($text, '/^(%Attachment_Name%: .+)/m', true);

        foreach ($segments as $sText) {
            $pnr = $this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('confNumber'))}[ :]+([A-Z\d]{5,})$/m", $sText) ?? '{{unknown}}';

            if (array_key_exists($pnr, $flights)) {
                $flights[$pnr][] = $sText;
            } else {
                $flights[$pnr] = [$sText];
            }
        }

        foreach ($flights as $pnr => $segments) {
            $f = $email->add()->flight();

            if ($pnr !== '{{unknown}}') {
                $f->general()->confirmation($pnr);
            }

            $travellers = [];

            foreach ($segments as $sText) {
                $fileName = $this->re('/^%Attachment_Name%: (.+)/', $sText) ?? '{{unknown}}';

                if (preg_match("/\n[ ]*(?<codeDep>[A-Z]{3})[ ]+(?<codeArr>[A-Z]{3})(?:\n|[ ]+.+\n)\n*.*\b(?<timeDep>{$this->patterns['time']}) .*\b(?<timeArr>{$this->patterns['time']})/", $sText, $m)) {
                    $codeDep = $m['codeDep'];
                    $codeArr = $m['codeArr'];
                    $timeDep = $m['timeDep'];
                    $timeArr = $m['timeArr'];
                } else {
                    $this->logger->debug('Wrong airport codes!');
                    $email->add()->event(); // for 100% fail

                    return;
                }

                $traveller = $this->re("/^[ ]*{$this->opt($this->t('Passenger'))}\n{1,3}[ ]*({$this->patterns['travellerName']})$/mu", $sText);

                if (!in_array($traveller, $travellers)) {
                    $travellers[] = $traveller;
                }

                if (preg_match("/^(?<head>[ ]*{$this->opt($this->t('flightNumber'))}[ ]+{$this->opt($this->t('Seat'))} .+)(?<body>(?:\n.*){1,3})\n+[ ]*{$this->opt($this->t('Class'))}[ ]+{$this->opt($this->t('Gate'))} /m", $sText, $m)) {
                    $row1Head = $m['head'];
                    $row1Body = trim($m['body'], "\n");
                } else {
                    $row1Head = $row1Body = '';
                }

                $tablePos = $this->rowColsPos($row1Head);
                $tablePos[0] = 0;
                $table = $this->splitCols($row1Body, $tablePos);

                if (count($table) === 5) {
                    $flight = $table[0];
                    $seat = $table[1];
                    $date = strtotime($table[2]);
                    $terminal = $table[4];
                } else {
                    $this->logger->debug('Wrong table-1!');
                    $email->add()->event(); // for 100% fail

                    return;
                }

                if (preg_match("/^(?<head>[ ]*{$this->opt($this->t('Class'))}[ ]+{$this->opt($this->t('Gate'))} .+)(?<body>(?:\n.*){1,3})\n+[ ]*{$this->opt($this->t('Extras'))}$/m", $sText, $m)) {
                    $row2Head = $m['head'];
                    $row2Body = trim($m['body'], "\n");
                } else {
                    $row2Head = $row2Body = '';
                }

                $tablePos = $this->rowColsPos($row2Head);
                $tablePos[0] = 0;
                $table = $this->splitCols($row2Body, $tablePos);

                if (count($table) === 4) {
                    $class = $table[0];
                } else {
                    $this->logger->debug('Wrong table-2!');
                    $email->add()->event(); // for 100% fail

                    return;
                }

                if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                    $airline = $m['name'];
                    $flightNumber = $m['number'];
                } else {
                    $this->logger->debug('Wrong flight number!');
                    $email->add()->event(); // for 100% fail

                    return;
                }

                $dateDep = strtotime($timeDep, $date);

                /** @var \AwardWallet\Schema\Parser\Common\FlightSegment $existingSeg */
                $existingSeg = null;

                foreach ($f->getSegments() as $seg) {
                    if ($seg->getAirlineName() === $airline && $seg->getFlightNumber() === $flightNumber
                        && $seg->getDepCode() === $codeDep && $seg->getDepDate() === $dateDep
                    ) {
                        $existingSeg = $seg;

                        break;
                    }
                }

                if ($existingSeg === null) {
                    $s = $f->addSegment();
                    $s->airline()->name($airline)->number($flightNumber);
                    $s->departure()->code($codeDep)->date($dateDep)->terminal(ReminderHtml2022::normalizeTerminal($terminal));
                    $s->arrival()->code($codeArr)->date(strtotime($timeArr, $date));
                    $s->extra()->seat($seat)->cabin(preg_replace('/\s+/', ' ', $class));
                } else {
                    $existingSeg->extra()->seats(array_merge($existingSeg->getSeats(), [$seat]));
                }

                if ($fileName !== '{{unknown}}') {
                    $bp = $email->add()->bpass();
                    $bp
                        ->setRecordLocator($pnr)
                        ->setTraveller($traveller)
                        ->setDepCode($codeDep)
                        ->setDepDate($dateDep)
                        ->setFlightNumber($flight)
                        ->setAttachmentName($fileName)
                    ;
                }
            }

            $f->general()->travellers($travellers, true);
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['flightNumber'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumber']) !== false
                && $this->strposArray($text, $phrases['flightNumber']) !== false
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
}
