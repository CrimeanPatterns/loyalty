<?php

namespace AwardWallet\Engine\aeromexico\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ImpotantInformation extends \TAccountChecker
{
    public $mailFiles = "aeromexico/it-281609222.eml, aeromexico/it-287266579.eml, aeromexico/it-322287136.eml, aeromexico/it-328102388.eml";
    public $subjects = [
        // es
        'Tu próximo vuelo con clave de reservación',
        'Información importante: Tu próximo vuelo a',
        'Itinerario confirmado',
        // en
        'Your upcoming flight with reservation code',
        'Important Information: Your upcoming flight to',
    ];

    public $lang = 'es';

    public static $dictionary = [
        "es" => [
            'Reservación'                 => 'Reservación',
            'Tu vuelo ha sido modificado' => ['Tu vuelo ha sido modificado', 'Tu itinerario ha sido modificado', 'Tu nuevo itinerario'],
            'Estimado'                    => ['Estimado '],
            'Tu nuevo itinerario'         => ['Tu nuevo itinerario', 'Tu itinerario anterior'],
            'Nuevo'                       => ['Nuevo', 'Cancelado', 'Vuelo confirmado'],
            'cancelledStatus'             => ['Cancelado'],
        ],
        "en" => [
            'Reservación'                 => 'Booking',
            'Tu vuelo ha sido modificado' => ['Your flight has been modified'],
            'Estimado'                    => ['Dear '],
            'Tu nuevo itinerario'         => ['Your new itinerary'],
            'Nuevo'                       => ['New', 'Cancelled', 'Canceled'],
            'cancelledStatus'             => ['Cancelled', 'Canceled'], //  to check (no examples)
        ],
    ];
    private $date;

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@itineraries.aeromexico.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.aeromexico.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Tu vuelo ha sido modificado']) && !empty($dict['Tu nuevo itinerario'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Tu vuelo ha sido modificado'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Tu nuevo itinerario'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]itineraries\.aeromexico\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservación'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*[#]([A-Z\d]{5,7})\s*$/"));
        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Estimado'))}]", null, true, "/{$this->opt($this->t('Estimado'))}\s*(\D+)\,/");

        if (!empty($traveller)) {
            $f->general()
                ->traveller($traveller);
        }

        $xpath = "//img[contains(@src, 'itineraries.aeromexico')]/ancestor::tr[1][count(*[normalize-space()]) > 1][*[normalize-space()][1][{$this->eq($this->t('Nuevo'))}]]";
        $nodes = $this->http->XPath->query($xpath);
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $status = $this->http->FindSingleNode("./descendant::td[1]", $root);

            if (!empty($status)) {
                $s->setStatus($status);

                if (preg_match("/{$this->opt($this->t('cancelledStatus'))}/", $status)) {
                    $s->setCancelled(true);
                }
            }

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+/"))
                ->number($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5})/"));

            $depText = $this->http->FindSingleNode("./descendant::td[3]", $root);

            if (preg_match("/^.+\s+(?<dCode>[A-Z]{3})\s+(?<dDate>[\d\:]+\s*\w+\.?\,?\s*\w+\s*\d+).*$/u", $depText, $m)) {
                $s->departure()
                    ->code($m['dCode'])
                    ->date($this->normalizeDate($m['dDate']));

                $dTerminal = $this->re("/Terminal\s+(.+)/", $depText);

                if (!empty($dTerminal)) {
                    $s->departure()
                        ->terminal($dTerminal);
                }
            }

            $arrText = $this->http->FindSingleNode("./descendant::td[5]", $root);

            if (preg_match("/^.+\s+(?<aCode>[A-Z]{3})\s+(?<aDate>[\d\:]+\s*\w+\.?\,?\s*\w+\s*\d+).*$/u", $arrText, $m)) {
                $s->arrival()
                    ->code($m['aCode'])
                    ->date($this->normalizeDate($m['aDate']));

                $aTerminal = $this->re("/Terminal\s+(.+)/", $arrText);

                if (!empty($aTerminal)) {
                    $s->arrival()
                        ->terminal($aTerminal);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Tu vuelo ha sido modificado'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Tu vuelo ha sido modificado'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->date = strtotime($parser->getHeader('date'));
        $this->ParseFlight($email);

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $year = date("Y", $this->date);

        $in = [
            // 11:55 vie., abril 28
            // 06:25 Sun, June 04
            "#^\s*([\d\:]+)\s*(\w+)\.?\,\s*(\w+)\s*(\d+)\s*$#iu",
        ];
        $out = [
            "$2, $4 $3 $year, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
