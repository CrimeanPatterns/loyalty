<?php

namespace AwardWallet\Engine\azul\Email;

use AwardWallet\Schema\Parser\Email\Email;

class AlteradoVoo extends \TAccountChecker
{
    public $mailFiles = "azul/it-720760262.eml";

    public $lang = 'pt';
    public static $dictionary = [
        'pt' => [],
    ];

    private $detectFrom = "azul@news-voeazul.com.br";
    private $detectSubject = [
        // pt
        'foi alterado com sucesso', // Seu voo para Vitória foi alterado com sucesso
    ];
    private $detectBody = [
        'pt' => [
            'foi alterado com sucesso.',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]news-voeazul\.com.*$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['news-voeazul.'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['azul@news-voeazul.com.br '])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Código da Reserva:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Ola,'))}]",
                null, true, "/^\s*{$this->opt($this->t('Ola,'))}\s*([[:alpha:]][[:alpha:] \-]+)\s*\.\s*$/u"), false)
        ;

        // Segments
        $xpath = "//img[contains(@src, 'icon_aviao_cinza.png')]/ancestor::tr[count(*) = 3][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name('AD')
                ->number($this->http->FindSingleNode("*[2]", $root, true, "/^\s*Voo\s*(\d{1,4})\s*$/"));

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding::text()[contains(., '/')][1]", $root, true, "/^\s*(.*\d{4}.*)\s*$/"));

            // Departure
            $time = $this->http->FindSingleNode("*[1]", $root, true, "/^\s*(\d{1,2}:\d{2})\s*[A-Z]{3}\s*$/");
            $s->departure()
                ->code($this->http->FindSingleNode("*[1]", $root, true, "/^\s*\d{1,2}:\d{2}\s*([A-Z]{3})\s*$/"))
                ->date((!empty($date) && !empty($time)) ? strtotime($time, $date) : null)
            ;

            // Arrival
            $time = $this->http->FindSingleNode("*[3]", $root, true, "/^\s*(\d{1,2}:\d{2})\s*[A-Z]{3}\s*$/");
            $s->arrival()
                ->code($this->http->FindSingleNode("*[3]", $root, true, "/^\s*\d{1,2}:\d{2}\s*([A-Z]{3})\s*$/"))
                ->date((!empty($date) && !empty($time)) ? strtotime($time, $date) : null)
            ;
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // 15/10/2024
            '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$/iu',
        ];
        $out = [
            '$1.$2.$3',
        ];

        $date = preg_replace($in, $out, $date);

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
