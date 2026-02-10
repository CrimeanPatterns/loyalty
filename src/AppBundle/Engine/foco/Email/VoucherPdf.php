<?php

namespace AwardWallet\Engine\foco\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class VoucherPdf extends \TAccountChecker
{
    public $mailFiles = "foco/it-598284311-pt.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'confNumber' => ['Código da reserva'],
            'pickUp'     => ['Hora de Retirada'],
            'dropOff'    => ['Hora de Devolução'],
            'totalValue' => ['Valor total', 'Valor mensal'],
        ],
    ];

    private $subjects = [
        'pt' => ['Confirmação de Reserva'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aluguefoco.com.br') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true
            && strpos($headers['subject'], 'Foco Aluguel de Carros') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (strpos($textPdf, 'Endereço da loja Foco') === false
                && strpos($textPdf, 'através do site da Foco') === false
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

            if (!$textPdf) {
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

        $email->setType('VoucherPdf' . ucfirst($this->lang));

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
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $text = preg_replace("/^(.+?)\n+[ ]*{$this->opt($this->t('Siga essas instruções em sua chegada'))}.*$/s", '$1', $text);

        $car = $email->add()->rental();

        if (preg_match("/^[ ]*({$this->opt($this->t('confNumber'))})[: ]+([-A-Z\d]{5,})$/m", $text, $m)) {
            $car->general()->confirmation($m[2], $m[1]);
        }

        $traveller = $this->re("/^[ ]*{$this->opt($this->t('Locatário'))}[: ]+({$patterns['travellerName']})$/mu", $text);
        $car->general()->traveller($traveller, true);

        if (preg_match("/^(?<head>.*{$this->opt($this->t('pickUp'))}[ ]+.*{$this->opt($this->t('dropOff'))})\n+(?<body>[\s\S]+?)\n+(?:.+ {$this->opt($this->t('ou similar'))}$|[ ]*{$this->opt($this->t('Seu veículo'))}\s)/m", $text, $m)) {
            $pointsHeaders = $m['head'];
            $pointsText = $m['body'];
        } else {
            $pointsHeaders = $pointsText = null;
        }

        $tablePos = $this->rowColsPos($pointsHeaders);
        $tablePos[0] = 0;
        $table = $this->splitCols($pointsText, $tablePos);

        if (count($table) === 2) {
            /*
                24/11/2023 às 9:00
                Jardins SP: Aeroporto SAO12
                Avenida Nove de Julho, 3597
                - 05511959200109
                01407000
            */

            $pattern = "/^\s*"
            . "(?<date>.+\b\d{4})[ ]+{$this->opt($this->t('às'))}[ ]+(?<time>{$patterns['time']}).*\n+"
            . "[ ]*(?<location>\S[\s\S]{2,}?)\n+"
            . "[ ]*-[ ]*(?<phone>{$patterns['phone']})(?:\n|$)"
            . "/";

            if (preg_match($pattern, $table[0], $m)) {
                $car->pickup()
                    ->date(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))))
                    ->location(preg_replace('/([ ]*\n+[ ]*)+/', ', ', $m['location']))
                    ->phone($m['phone'])
                ;
            }

            if (preg_match($pattern, $table[1], $m)) {
                $car->dropoff()
                    ->date(strtotime($m['time'], strtotime($this->normalizeDate($m['date']))))
                    ->location(preg_replace('/([ ]*\n+[ ]*)+/', ', ', $m['location']))
                    ->phone($m['phone'])
                ;
            }
        }

        $yourVehicleText = $this->re("/\n((?:.+ {$this->opt($this->t('ou similar'))}\n|[ ]*{$this->opt($this->t('Seu veículo'))}\s)[\s\S]+?)\n+[ ]*{$this->opt($this->t('Preços de locação'))}\n/", $text);
        $carModel = $this->re("/(?:^[ ]*|[ ]{2})(\S.+ {$this->opt($this->t('ou similar'))})$/m", $yourVehicleText);
        $car->car()->model($carModel, false, true);

        $priceList = [];

        if (preg_match("/^(?<head>[ ]*{$this->opt($this->t('Item'))}[ ]+{$this->opt($this->t('Valor unitário'))}[ ]+{$this->opt($this->t('Quantidade'))}[ ]+{$this->opt($this->t('totalValue'))})\n+(?<body>[\s\S]+?\n[ ]*{$this->opt($this->t('Total'))}[ ]{2}.*\d.*)$/m", $text, $m)) {
            $priceHeaders = $m['head'];
            $priceText = $m['body'];
        } else {
            $priceHeaders = $priceText = null;
        }

        $tablePos = $this->rowColsPos($priceHeaders);
        $tablePos[0] = 0;

        $priceRows = $this->splitText($priceText, "/^([ ]{0,20}\S.*)$/m", true);

        foreach ($priceRows as $pRow) {
            $table = $this->splitCols($pRow, $tablePos);

            if (count($table) !== 4) {
                $this->logger->debug('Wrong price table!');

                continue;
            }

            $priceList[] = $table;
        }

        if (count($priceList) === 0) {
            return;
        }

        $totalPrice = array_pop($priceList);

        if (preg_match("/^\s*{$this->opt($this->t('Total'))}\s*$/", $totalPrice[0])
            && preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice[3], $matches)
        ) {
            // R$ 3,300.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $car->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $cost = array_shift($priceList);

            if (preg_match("/^\s*{$this->opt($this->t('Diária'))}\s*$/", $cost[0])
                && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $cost[3], $m)
            ) {
                $car->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            foreach ($priceList as $priceItem) {
                $charge = preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $priceItem[3], $m)
                        || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $priceItem[1], $m) && preg_match('/^1?$/', trim($priceItem[2]))
                    ? PriceHelper::parse($m['amount'], $currencyCode) : null
                ;

                if ($charge === null) {
                    continue;
                }

                if (preg_match("/^\s*{$this->opt($this->t('Desconto'))}\s*$/", $priceItem[0])) {
                    $car->price()->discount($charge);

                    continue;
                }

                $car->price()->fee(trim($priceItem[0]), $charge);
            }
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['dropOff'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumber']) !== false
                && $this->strposArray($text, $phrases['dropOff']) !== false
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 25/11/2023
            '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/',
        ];
        $out = [
            '$2/$1/$3',
        ];

        return preg_replace($in, $out, $text);
    }
}
