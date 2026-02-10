<?php

namespace AwardWallet\Engine\amoma\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationPDF2 extends \TAccountChecker
{
    public $mailFiles = "amoma/it-37934368.eml, amoma/it-38063065.eml, amoma/it-39766291.eml";

    public $reFrom = ["amoma.com"];
    public $reBody = [
        'en' => ['HOTEL INFORMATION:', 'YOUR BOOKING DETAILS:'],
        'pt' => ['INFORMAÇÃO DO HOTEL:', 'OS DADOS DA SUA RESERVA:'],
        'it' => ["INFORMAZIONI SULL'HOTEL:", 'INFORMAZIONI IMPORTANTI:'],
    ];
    public $reSubject = [
        'Your booking confirmation with AMOMA.com',
        'A confirmação da sua reserva com o AMOMA.com',
        'La tua conferma della prenotazione con',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'HOTEL:'   => 'HOTEL:',
            'ADDRESS:' => 'ADDRESS:',
            'adults'   => ['adults', 'adult'],
        ],
        'pt' => [
            'HOTEL:'                   => 'HOTEL:',
            'ADDRESS:'                 => 'MORADA:',
            'COUNTRY'                  => 'PAÍS:',
            'adults'                   => ['adultos'],
            'children'                 => 'crianças',
            'Reference number:'        => 'Número de referência:',
            'HOTEL INFORMATION:'       => 'INFORMAÇÃO DO HOTEL:',
            'YOUR BOOKING DETAILS:'    => 'OS DADOS DA SUA RESERVA:',
            'IMPORTANT INFORMATION:'   => 'INFORMAÇÃO IMPORTANTE:',
            'PREFERENCES:'             => 'PREFERÊNCIAS:',
            'CHECK-IN DATE:'           => 'DATA DE CHEGADA:',
            'GUEST NAME:'              => 'NOME DO ACOMPANHANTE:',
            'CHECK-OUT DATE:'          => 'DATA DE SAÍDA:',
            'TYPE OF ROOM:'            => 'TIPO DE QUARTO:',
            'Hotel booking reference:' => 'Nº referência da reserva hotel:',
            'Your Booking ID:'         => 'A Identificação da sua Reserva:',
            'Your Customer Number:'    => 'O seu Número de Cliente:',
        ],
        'it' => [
            'HOTEL:'                   => 'HOTEL:',
            'ADDRESS:'                 => 'INDIRIZZO:',
            'COUNTRY'                  => 'PAESE:',
            'adults'                   => ['adulti'],
            'children'                 => 'bambini',
            'Reference number:'        => 'Numero di prenotazione:',
            'HOTEL INFORMATION:'       => "INFORMAZIONI SULL'HOTEL:",
            'YOUR BOOKING DETAILS:'    => 'DETTAGLI DELLA TUA PRENOTAZIONE:',
            'IMPORTANT INFORMATION:'   => 'INFORMAZIONI IMPORTANTI:',
            'PREFERENCES:'             => 'PREFERENZE:',
            'CHECK-IN DATE:'           => 'DATA DI ARRIVO:',
            'GUEST NAME:'              => 'NOME OSPITE:',
            'CHECK-OUT DATE:'          => 'DATA DI PARTENZA:',
            'TYPE OF ROOM:'            => 'TIPO DI CAMERA:',
            'Hotel booking reference:' => "Nº di rif. della prenotazione d'hotel:",
            'Your Booking ID:'         => 'ID della tua prenotazione:',
            'Your Customer Number:'    => 'Il tuo numero di cliente:',
        ],
    ];
    private $keywordProv = 'AMOMA';
    private $attachment;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text) && $this->assignLang($text)) {
                        $this->attachment = $parser->getAttachmentBody($pdf);
                        $this->parseEmailPdf($text, $email);
                    }
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectBody($text) && $this->assignLang($text)) {
                return true;
            }
        }

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

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
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

    private function parseEmailPdf($textPDF, Email $email)
    {
        $r = $email->add()->hotel();

        $confNoPdf = $this->re("#^\s*{$this->opt($this->t('Reference number:'))}\s+(\w+)#iu", $textPDF);

        $hotelBlock = $this->findCutSection($textPDF, $this->t('HOTEL INFORMATION:'),
            $this->t('IMPORTANT INFORMATION:'));
        $address[] = $this->nice($this->re("#{$this->t('ADDRESS:')}\s+(.+?)\s+{$this->t('COUNTRY')}#su", $hotelBlock));
        $address[] = $this->re("#{$this->t('COUNTRY:')}\s+(.+)#u", $hotelBlock);
        $r->hotel()
            ->name($this->nice($this->re("#{$this->t('HOTEL:')}\s+(.+?)\s+{$this->t('ADDRESS:')}#su", $hotelBlock)))
            ->address(implode(", ", array_filter($address)));

        $info = $this->findCutSection($textPDF, $this->t('YOUR BOOKING DETAILS:'),
            null);
        $str = strstr($info, $this->t('PREFERENCES:'), true);

        if (!empty($str)) {
            $info = $str;
        }

        if (preg_match("#(.+)\n([ ]*{$this->opt($this->t('CHECK-IN DATE:'))}.+)#su", $info, $m)) {
            // general info
            $table = $this->splitCols($m[1],
                $this->colsPos($this->re("#([ ]*{$this->t('Reference number:')}.+)#iu", $m[1])));

            if (count($table) !== 2) {
                $this->logger->debug('other format (REFERENCE NUMBER:)');

                return false;
            }

            if (empty($confNoPdf)) {
                $confNoPdf = $this->re("#{$this->t('Reference number:')}\s+(\w+)#u", $table[0]);
            }
            $r->general()
                ->confirmation($confNoPdf)
                ->traveller($this->nice($this->re("#{$this->t('GUEST NAME:')}\s+(.+)$#su", $table[1])));

            // booked info
            $table = $this->splitCols($m[2], $this->colsPos($m[2], 10));

            if (count($table) !== 4) {
                // try convert to complex  (it-39766291.eml)
                $complex = \PDF::convertToHtml($this->attachment, \PDF::MODE_COMPLEX);
                $NBSP = chr(194) . chr(160);
                $complex = str_replace($NBSP, ' ', html_entity_decode($complex));
                $pdf = clone $this->http;
                $pdf->SetEmailBody($complex);

                if ($pdf->XPath->query("//text()[contains(.,'DATA DE CHEGADA:')]/ancestor::p[1]/following-sibling::p[normalize-space()!=''][5][translate(normalize-space(),'0123456789','dddddddddd')='dddd']")->length == 1) {
                    $pos1 = 0;
                    $col2 = preg_quote($pdf->FindSingleNode("//text()[contains(.,'DATA DE CHEGADA:')]/ancestor::p[1]/following-sibling::p[normalize-space()!=''][6]"),
                        "#");
                    $pos2 = mb_strlen($this->re("#^(.+?){$col2}#um", $m[2]));
                    $col3_1 = preg_quote($pdf->FindSingleNode("//text()[contains(.,'DATA DE CHEGADA:')]/ancestor::p[1]/following-sibling::p[normalize-space()!=''][2]"),
                        "#");
                    $col3_2 = preg_quote($pdf->FindSingleNode("//text()[contains(.,'DATA DE CHEGADA:')]/ancestor::p[1]/following-sibling::p[normalize-space()!=''][8]"),
                        "#");
                    $pos3_1 = mb_strlen($this->re("#^(.+?){$col3_1}#um", $m[2]));
                    $pos3_2 = mb_strlen($this->re("#^(.+?){$col3_2}#um", $m[2]));
                    $pos3 = min($pos3_1, $pos3_2);
                    $col4 = preg_quote($pdf->FindSingleNode("//text()[contains(.,'DATA DE CHEGADA:')]/ancestor::p[1]/following-sibling::p[normalize-space()!=''][9]"),
                        "#");
                    $pos4 = mb_strlen($this->re("#^(.+?){$col4}#um", $m[2]));
                    $pos = [$pos1, $pos2, $pos3, $pos4];
                    $table = $this->splitCols($m[2], $pos);
                } else {
                    $this->logger->debug('other format (CHECK-IN-table)');

                    return false;
                }
            }

            $r->booked()
                ->checkIn($this->normalizeDate($this->re("#{$this->opt($this->t('CHECK-IN DATE:'))}\s+(.+)#su", $table[0])))
                ->checkOut($this->normalizeDate($this->re("#{$this->opt($this->t('CHECK-OUT DATE:'))}\s+(.+)#su", $table[1])));

            if (preg_match("#{$this->opt($this->t('TYPE OF ROOM:'))}\s+(?<rooms>\d+)\s*x\s*(?<type>.+)\s+(?<guest>\d+)\s+{$this->opt($this->t('adults'))}\s*\-\s*(?<kids>\d+)\s+{$this->opt($this->t('children'))}#su",
                $table[3], $m)) {
                $r->booked()
                    ->rooms($m['rooms'])
                    ->guests($m['guest'])
                    ->kids($m['kids']);
                $room = $r->addRoom();
                $room->setType($this->nice($m['type']));
            }
        }

        // try to get from body ota-info
        $confNoBody = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hotel booking reference:'))}]",
            null, false, "#{$this->opt($this->t('Hotel booking reference:'))}\s+(\w+)#");

        if (!empty($confNoBody) && $confNoPdf === $confNoBody) {
            $confNoOta = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Booking ID:'))}]/following::text()[normalize-space()!=''][1]");

            if (!empty($confNoOta)) {
                $r->ota()
                    ->confirmation($confNoOta, trim($this->t('Your Booking ID:'), ":"));
            }
            $account = $this->http->FindSingleNode("//text()[normalize-space()='{$this->t('Your Customer Number:')}']/following::text()[normalize-space()!=''][1]");

            if (!empty($account)) {
                $r->ota()
                    ->account($account, false);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Wednesday, June 7, 2017
            '#^\s*\w+,\s+(\w+)\s+(\d+),(?:[ ]*\w+)?\s+(\d{4})\s*$#u',
            //lundi 10 juil et 2017
            '#^\s*\w+\s+(\d+)\s+(\w+)\s+(?:et\s+)?(\d{4})\s*$#u',
            // Sonntag, 17. Juni 2018
            '#^\s*\w+,\s*(\d+)[\s.]+(\w+)\s+(\d{4})\s*$#u',
            // martes, 11 de junio de 2019
            '#^\s*[\w\-]+,\s*(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
            '$1 $2 $3',
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['HOTEL:'], $words['ADDRESS:'])) {
                if (stripos($body, $words['HOTEL:']) !== false && stripos($body, $words['ADDRESS:']) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function findCutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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
        return trim(preg_replace("#\s+#", ' ', $str));
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
}
