<?php

namespace AwardWallet\Engine\copaair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PurchaseReceiptPDF extends \TAccountChecker
{
    public $mailFiles = "copaair/it-643021226.eml, copaair/it-643413270.eml";
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public $subjects = [
        '/^Recibo de compra$/',
    ];

    public static $dictionary = [
        "es" => [
        ],

        "pt" => [
            'DETALLES DEL PRODUCTO O SERVICIO' => 'DETALHES DO PRODUTO E DO SERVIÇO',
            'Vuelo'                            => 'Voo',
            'Id de Orden'                      => 'Reserva',
            'Fecha de emisión'                 => 'Data de emissão',
            'Número de Documento'              => 'Número do documento',
            //'Name' => '',
            //'Frequent Flyer #' => '',
            //'Star Alliance Status' => '',
            'Moneda'                   => 'Moeda',
            'Cantidad'                 => 'Quantidade',
            'Importe del impuesto'     => 'Valor do imposto',
            'Producto / Servicio'      => 'Produto/Serviço',
            'Fecha'                    => 'Data',
            'Desde'                    => 'De',
            'ENDOSOS / RESTRICCIONES:' => 'ENDOSSO/RESTRIÇÕES:',
        ],
    ];
    public $detectLang = [
        "es" => ["Vuelo"],
        "pt" => ["Voo"],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@css.copaair.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
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

            if (
                strpos($text, "Copa Airlines") !== false
                && (strpos($text, $this->t('DETALLES DEL PRODUCTO O SERVICIO')) !== false)
                && (strpos($text, $this->t('Vuelo')) !== false
                )
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]css\.copaair\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        if (preg_match("/[ ]*{$this->t('Id de Orden')}\s*{$this->t('Fecha de emisión')}\s*{$this->t('Número de Documento')}\n\s*(?<confNumber>[A-Z\d]{6})\s*(?<date>\d+\D*\d{4})\s*(?<ticket>[\d\s]*)\n/", $text, $m)) {
            $f->general()
                ->confirmation($m['confNumber'])
                ->date($this->normalizeDate(str_replace('º', '', $m['date'])));

            $f->addTicketNumber(str_replace(' ', '', $m['ticket']), false);
        }

        if (preg_match("/{$this->t('Name')}\s*{$this->t('Frequent Flyer #')}\s*{$this->t('Star Alliance Status')}\n+\s*(?<name>[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*(?<account>[A-Z\d]{6,})?\n*\s*{$this->t('DETALLES DEL PRODUCTO O SERVICIO')}/", $text, $m)) {
            $f->general()
                ->traveller($m['name']);

            if (isset($m['account']) && !empty($m['account'])) {
                $f->addAccountNumber($m['account'], false);
            }
        }

        if (preg_match("/{$this->t('Moneda')}\s+{$this->t('Cantidad')}\s*{$this->t('Importe del impuesto')}.*\n+\s*(?<currency>[A-Z]{3})\s*(?<cost>[\d\.\,]+)\s*[A-Z]{3}\s*(?<tax>[\d\.\,]+)/", $text, $m)) {
            $f->price()
                ->currency($m['currency'])
                ->cost(PriceHelper::parse($m['cost'], $m['currency']))
                ->tax(PriceHelper::parse($m['tax'], $m['currency']));

            $total = array_sum([$m['cost'], $m['tax']]);
            $f->price()
                ->total(PriceHelper::parse($total, $m['currency']));
        }

        $segText = $this->re("#[ ]{1,3}{$this->t('Producto / Servicio')}\s*{$this->t('Vuelo')}\s*{$this->t('Fecha')}\s*{$this->t('Desde')}.*\n((?:.+\n){1,10}){$this->t('ENDOSOS / RESTRICCIONES:')}#u", $text);
        $segments = $this->splitText($segText, "/([ ]{1,5}.+[ ]{1,4}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d{2,4}[ ]{5,}.*)/", true);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $segTable = $this->splitCols($segment, [0, 25, 40, 65, 105]);

            if (preg_match("/(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{2,4})/", $segTable[1], $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $s->setDepDay($this->normalizeDate($this->re("/\s*(\d+\s*\w+\s*\d{4})\s*$/us", $segTable[2])));

            if (preg_match("/^(?<depName>.+)\s+(?<depCode>[A-Z]{3})\s*$/us", $segTable[3], $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->name(str_replace("\n", " ", $m['depName']))
                    ->noDate();
            }

            if (preg_match("/^(?<arrName>.+)\s+(?<arrCode>[A-Z]{3})\s*$/us", $segTable[4], $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->name(str_replace("\n", " ", $m['arrName']))
                    ->noDate();
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->assignLang($text);

            $this->ParseFlightPDF($email, $text);
        }

        if (preg_match("/Total Invoice Amount\s*([\d\.]+)\s*([A-Z]{3})/u", $text, $m)) {
            $email->price()
                ->total($m[1])
                ->currency($m[2]);
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

    public function assignLang(string $text): bool
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $str = preg_replace("/([a-z])(\d{4})/", "$1 $2", $str);
        $in = [
            "#^(\d+\s*\w+\s*\d{4})$#u", //11 Febrero 2024
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
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
            $currentPos = mb_strpos($row, $word, $lastpos, 'UTF-8');

            if ($currentPos > 5) {
                $currentPos = $currentPos - 1;
            }

            $pos[] = $currentPos;
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }
}
