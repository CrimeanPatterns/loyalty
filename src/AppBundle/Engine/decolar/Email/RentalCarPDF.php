<?php

namespace AwardWallet\Engine\decolar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalCarPDF extends \TAccountChecker
{
    public $mailFiles = "decolar/it-106110783.eml, decolar/it-106968887.eml";
    public $subjects = [
        '/Eba\! Sua viagem está confirmada/',
    ];

    public $lang = '';
    public $pdfPattern = ".+\.pdf";

    public $detectLang = [
        'en' => ['Confirmation number:'],
        'pt' => ['Nº de reserva:'],
    ];

    public static $dictionary = [
        "en" => [
        ],
        "pt" => [
            'Confirmation number:' => 'Nº de reserva:',
            'RENTAL DAYS'          => 'DIAS DE ALUGUEL',
            'DRIVER\'S NAME'       => 'MOTORISTA',
            'Intermediary'         => 'Intermediário',
            'PICK-UP LOCATION'     => 'MODALIDADE DE RETIRADA',
            'DROP-OFF LOCATION'    => 'MODALIDADE DE RETORNO',
            'account number'       => 'N° de conta:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@decolar.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'DECOLAR.COM LTDA')]")->length > 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (!empty($text) && stripos($text, 'Your reservation') !== false) {
                    $this->assignLang($text);

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]decolar\.com$/', $from) > 0;
    }

    public function ParseRentalPDF(Email $email, $text)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation number:'))}\s*(\d+)/", $text));

        $account = $this->re("/{$this->opt($this->t('account number'))}\s*(\d+)/", $text);

        if (!empty($account)) {
            $r->program()
                ->account($account, false);
        }

        $table = $this->SplitCols($text, [0, 50]);
        /*$this->logger->debug($table[0]);
        $this->logger->warning($table[1]);*/

        $travellers = array_filter(explode("\n", $this->re("/{$this->opt($this->t('DRIVER\'S NAME'))}.+\:\n\s*(\D+){$this->opt($this->t('RENTAL DAYS'))}/smu", $table[0])));

        $r->general()
            ->travellers($travellers, true);

        $r->setCompany(trim($this->re("/COMPANY\:\n+s*(\D+)\s+\n\D+(?:Pick up)/u", $table[0])));

        if (preg_match("/Minhas Viagens\.\n+(.+)\n+(.+)\n/u", $table[1], $m)) {
            $r->car()
                ->type($m[1])
                ->model($m[2]);
        }

        $r->pickup()
            ->date($this->normalizeDate($this->re("/Pick\s*up\n\D+\:\n\s*(.+)/u", $table[0])));

        if (preg_match("/{$this->opt($this->t('PICK-UP LOCATION'))}\:\n+\s*(\D+)\n+((?:Aberta de|Aberta).+)\n+Tenho/u", $table[0], $m)
            || preg_match("/{$this->opt($this->t('PICK-UP LOCATION'))}\:\n+\s*(\D+)\n+Tenho/u", $table[0], $m)
        ) {
            $r->pickup()
                ->location(str_replace("\n", " ", $m[1]));

            if (isset($m[2])) {
                $r->pickup()
                    ->openingHours($m[2]);
            }
        }

        $r->dropoff()
            ->date($this->normalizeDate($this->re("/Drop\s*off\n\D+\:\n\s*(.+)/u", $table[0])));

        if (preg_match("/{$this->opt($this->t('DROP-OFF LOCATION'))}\:\n+\s*(\D+)\n+((?:Aberta de|Aberta).+)\n+Tenho/u", $table[0], $m)
            || preg_match("/{$this->opt($this->t('DROP-OFF LOCATION'))}\:\n+\s*(\D+)\n+Tenho/u", $table[0], $m)
        ) {
            $r->dropoff()
                ->location(str_replace("\n", " ", $m[1]));

            if (isset($m[2])) {
                $r->dropoff()
                    ->openingHours($m[2]);
            }
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL']/ancestor::tr[1]/descendant::td[2]", null, true, "/([\d\.\,]+)/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL']/ancestor::tr[1]/descendant::td[2]", null, true, "/(\D+)/");

        if (!empty($total) && !empty($currency)) {
            $r->price()
                ->total(PriceHelper::parse($total, $this->normalizeCurrency($currency)))
                ->currency($this->normalizeCurrency($currency));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!empty($text)) {
                $this->assignLang($text);
                $this->ParseRentalPDF($email, $text);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang($text)
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

    private function SplitCols($text, $pos = false)
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

    private function normalizeDate($date)
    {
        //$this->logger->warning('IN-'.$date);
        $in = [
            '#^\s*\w+\.\s*(\d+)\s*(\w+)\.\s*(\d{4})\s*([\d\:]+)\s*\D+#u', //Sáb. 14 ago. 2021 23:00 hs
        ];
        $out = [
            '$1 $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->logger->warning('OUT-'.$str);
        return strtotime($str);
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
            'BRL' => ['R$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }
}
