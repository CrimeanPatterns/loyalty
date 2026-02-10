<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CanceledRefundCode extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-150282033.eml";

    private $detectFrom = 'automated@airbnb.com';

    private $lang;
    private static $dictionary = [
        'en' => [
            'At checkout, add the code' => 'At checkout, add the code',
            'to use your' => 'to use your',
        ],
        'pt' => [
            'At checkout, add the code' => 'Ao finalizar a compra, adicione o código',
            'to use your' => 'para usar seu cupom de',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if ( isset($dict['At checkout, add the code'], $dict['to use your'])) {
                $code = $this->http->FindSingleNode("//text()[" . $this->contains($dict['At checkout, add the code']) . "]/following::text()[normalize-space()][2][" . $this->contains($dict['to use your']) . "]/ancestor::*[" . $this->contains($dict['At checkout, add the code']) . "][1]",
                    null, true, "/{$this->opt($dict['At checkout, add the code'])}\s*HCP2-([A-Z\d]{10})\s*{$this->opt($dict['to use your'])}/");
                if (!empty($code)) {
                    $h = $email->add()->hotel();

                    $h->general()
                        ->confirmation($code)
                        ->status('Cancelled')
                        ->cancelled();
                    $this->lang = $lang;
                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src, '.airbnb.com')]")->length == 0
            && $this->http->XPath->query("//a[contains(@href, '.airbnb.')]")->length == 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if ( isset($dict['At checkout, add the code'], $dict['to use your']) &&
                $this->http->XPath->query("//text()[" . $this->contains($dict['At checkout, add the code']) . "]/following::text()[normalize-space()][2][" . $this->contains($dict['to use your']) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

                return 'contains(normalize-space(' . $node . '),' . $s . ')';
            }, $field)) . ')';
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
                $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

                return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
            }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode('|', array_map(function ($s) {
                return preg_quote($s, '/');
            }, $field)) . ')';
    }
}
