<?php

namespace AwardWallet\Engine\azul\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ImportantInformation extends \TAccountChecker
{
    public $mailFiles = "azul/it-29274193.eml, azul/it-64371359.eml, azul/it-64378012.eml";

    public static $dictionary = [
        "pt" => [
            "Esta é sua reserva:" => ["Esta é sua reserva:", "Código da Reserva:"],
        ],
    ];

    private $detectFrom = [
        "voeazul.com.br",
    ];
    private $detectSubject = [
        "pt" => "Temos uma informação importante sobre o seu voo",
    ];
    private $detectCompany = [
        "voeazul.com",
    ];
    private $detectBody = [
        "pt" => [
            ["Novo Voo", "Esta é sua reserva:"],
            ["Voo original", "Código da Reserva:"],
        ],
    ];

    private $lang = "pt";

    public function flight(Email $email)
    {
        $f = $email->add()->flight();

        if ($this->http->FindSingleNode("//text()[{$this->eq($this->t('foi'))}]/following-sibling::b[{$this->eq($this->t('cancelado.'))}]")) {
            $f->general()->cancelled();
        }

        // General
        $f->general()->confirmation($this->nextText($this->t("Esta é sua reserva:")));

        $traveller = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Olá'))}])[1]/following-sibling::*[normalize-space()][1]", null,
            false, "/^[\s,]*([[:alpha:]\s\-]{3,})[.,]*/u");

        if (!empty($traveller)) {
            $f->general()->traveller($traveller);
        }

        // Segments
        $xpath = "//td[{$this->eq($this->t('Novo Voo'))}]/ancestor::table[count(following-sibling::table)=1][1]/following-sibling::table//table//tr[.//table]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//td[{$this->eq($this->t("Novo Voo"))}]/ancestor::table[1]/following-sibling::table/descendant::tr[1]/ancestor::*[1]/tr";
            $nodes = $this->http->XPath->query($xpath);
        }
        $this->logger->debug($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            // Airline
            $node = $this->http->FindSingleNode("td[1]", $root);

            if (preg_match("#^\s*Voo (\d{1,5})\s*$#", $node, $m)) {
                $s->airline()
                    ->noName()
                    ->number($m[1]);
            }
            $xpathTable = './td[2]/table/descendant::tr[1]/ancestor::*[1]/tr';
            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode($xpathTable . "[3]/td[normalize-space(.)!=''][1]", $root, true, "#^\s*([A-Z]{3})\s*$#"))
                ->date($this->normalizeDate(
                    $this->http->FindSingleNode($xpathTable . "[1]/td[normalize-space(.)!=''][1]", $root) . ' ' .
                    $this->http->FindSingleNode($xpathTable . "[2]/td[normalize-space(.)!=''][1]", $root)
                ));
            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode($xpathTable . "[3]/td[normalize-space(.)!=''][2]", $root, true, "#^\s*([A-Z]{3})\s*$#"))
                ->date($this->normalizeDate(
                    $this->http->FindSingleNode($xpathTable . "[1]/td[normalize-space(.)!=''][2]", $root) . ' ' .
                    $this->http->FindSingleNode($xpathTable . "[2]/td[normalize-space(.)!=''][2]", $root)
                ))
            ;
        }

        if (!$f->getCancelled()) {
            return $email;
        }
        $xpath = "//td[{$this->eq($this->t('Voo original'))}]/ancestor::table[count(following-sibling::table)=1][1]/following-sibling::table//table//tr[.//table]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//td[{$this->eq($this->t("Voo original"))}]/ancestor::table[1]/following-sibling::table/descendant::tr[1]/ancestor::*[1]/tr";
            $nodes = $this->http->XPath->query($xpath);
        }
        $this->logger->debug($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            // Airline
            $node = $this->http->FindSingleNode("td[1]", $root);

            if (preg_match("#^\s*Voo (\d{1,5})\s*$#", $node, $m)) {
                $s->airline()
                    ->noName()
                    ->number($m[1]);
            }
            $xpathTable = './td[2]/table/descendant::tr[1]/ancestor::*[1]/tr';
            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode($xpathTable . "[3]/td[normalize-space(.)!=''][1]", $root, true, "#^\s*([A-Z]{3})\s*$#"))
                ->date($this->normalizeDate(
                    $this->http->FindSingleNode($xpathTable . "[1]/td[normalize-space(.)!=''][1]", $root) . ' ' .
                    $this->http->FindSingleNode($xpathTable . "[2]/td[normalize-space(.)!=''][1]", $root)
                ));
            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode($xpathTable . "[3]/td[normalize-space(.)!=''][2]", $root, true, "#^\s*([A-Z]{3})\s*$#"))
                ->date($this->normalizeDate(
                    $this->http->FindSingleNode($xpathTable . "[1]/td[normalize-space(.)!=''][2]", $root) . ' ' .
                    $this->http->FindSingleNode($xpathTable . "[2]/td[normalize-space(.)!=''][2]", $root)
                ))
            ;
        }

        return $email;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //		$body = html_entity_decode($this->http->Response["body"]);
        //		foreach($this->detectBody as $lang => $dBody){
        //			if (stripos($body, $dBody) !== false) {
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->detectFrom as $dFrom) {
            if (strpos($headers["from"], $dFrom) !== false) {
                $finded = true;

                break;
            }
        }

        if (!$finded) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $finded = false;

        foreach ($this->detectCompany as $dCompany) {
            if (strpos($body, $dCompany) !== false || $this->http->XPath->query("//a[contains(@href, '" . $dCompany . "')]")->length > 0) {
                $finded = true;

                break;
            }
        }

        if (!$finded) {
            return false;
        }

        foreach ($this->detectBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		$this->http->log($str);
        $in = [
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*(\d+:\d+)\s*$#", //29/11/2018 05:40
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
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

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
