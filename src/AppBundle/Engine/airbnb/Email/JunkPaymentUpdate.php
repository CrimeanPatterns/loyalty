<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class JunkPaymentUpdate extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-73738520.eml";
    public static $dict = [
        'en' => [
            'refundText' => [
                ["A refund of ", "has been issued to"],
            ],
            'The Airbnb team' => 'The Airbnb team',
        ],
        'es' => [
            'refundText' => [
                ["Se realizó un reembolso de", "a tu cuenta de"],
            ],
            'The Airbnb team' => 'El equipo de Airbnb',
        ],
    ];

    private $detectFrom = "@airbnb.com";
    private $detectSubject = [
        'en' => "Airbnb payment update",
        'es' => "Actualización de pagos de Airbnb",
    ];

    private $lang = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dict as $lang => $dict) {
            if (!empty($dict["refundText"]) && !empty($dict['The Airbnb team'])) {
                foreach ($dict["refundText"] as $refundText) {
                    if ($this->http->XPath->query("//text()[" . $this->contains($refundText) . "]/following::text()[normalize-space()][position()<5][" . $this->contains($dict["The Airbnb team"]) . "]")->length > 0) {
                        $email->setIsJunk(true);
                        $this->lang = $lang;

                        break;
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
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            if (stripos($headers['from'], $this->detectFrom) === false) {
                return false;
            }

            foreach ($this->detectSubject as $reSubject) {
                if ($headers["subject"] == $reSubject) {
                    return true;
                }
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
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        if (isset(self::$dict['en'][$s])) {
            $mixed = array_unique(array_merge((array) self::$dict[$this->lang][$s], (array) self::$dict['en'][$s]));

            return $mixed;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            //1: Wed, Oct 31, 2018
            //            '#^\s*[^\d\s]+[,\s]+([^\d\s\.]+)\s+(\d+),\s*(\d{4})\s*$#u',
        ];
        $out = [
            //            '$2 $1 $3',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
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

    private function contains($field, $union = ' or ')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode($union, array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }
}
