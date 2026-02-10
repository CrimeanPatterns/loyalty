<?php

namespace AwardWallet\Engine\fcmtravel\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CancelledTicket extends \TAccountChecker
{
    public $mailFiles = "fcmtravel/it-135134923.eml, fcmtravel/it-135135694.eml, fcmtravel/it-135135695.eml";
    public $subjects = [
        'Annulation billet de train',
    ];

    public $lang = 'fr';

    public static $dictionary = [
        "fr" => [
            'CancelledText' => ["Je vous confirme l'annulation de votre trajet train du", "Merci de bien vouloir annuler les billets d’avion suivants"],
            'ReturnNumber'  => ["Numero resa", "Resa"],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@fr.fcm.travel') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'www.fcmtravel.com/')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('ReturnNumber'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('CancelledText'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]fr\.fcm\.travel$/', $from) > 0;
    }

    public function ParseTrain(Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Bonjour'))}]", null, true, "/{$this->opt($this->t('Bonjour'))}\s*(.+)/"));

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('ReturnNumber'))}]", null, true, "/{$this->opt($this->t('ReturnNumber'))}\s*([A-Z\d]{6})$/u");

        if (!empty($confirmation)) {
            $t->general()
                ->confirmation($confirmation)
                ->cancelled();
        }
    }

    public function ParseFlight(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[{$this->contains($this->t('dossier'))}]/ancestor::span[1]");

        foreach ($nodes as $root) {
            $text = $this->http->FindSingleNode(".", $root);
            $f = $email->add()->flight();

            $f->general()
               ->traveller(trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('Bonjour '))}]", null, true, "/{$this->opt($this->t('Bonjour'))}\s*(.+)/"), ','))
               ->cancelled();

            $currentYear = date('Y', $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Activé')]", null, true, "/{$this->opt($this->t('Activé'))}\s*(\w+\s*\,\s*\d+\s*\w+)/")));

            if (preg_match("/(?<date>\d+\w+)\s*(?<depName>.+)\s+(?<depCode>[A-Z]{3})\s*(?<seat>\d+[A-Z])?\s*\-\s*(?<arrName>.+)\s+(?<arrCode>[A-Z]{3})\s+(?<airline>[A-Z\d]{2})\s+(?<number>\d{2,4})\s+(?<depTime>[\d\:]+)\s+(?<arrTime>[\d\:]+)\s*dossier\s*(?<confNumer>[A-Z\d]{6})/", $text, $m)) {
                $s = $f->addSegment();

                $s->departure()
                   ->name($m['depName'])
                   ->date(strtotime($m['date'] . ' ' . $currentYear . ' ' . $m['depTime']))
                   ->code($m['depCode']);

                $s->arrival()
                   ->name($m['arrName'])
                   ->date(strtotime($m['date'] . ' ' . $currentYear . ' ' . $m['arrTime']))
                   ->code($m['arrCode']);

                $s->airline()
                   ->name($m['airline'])
                   ->number($m['number']);

                $f->general()
                   ->confirmation($m['confNumer']);

                if (isset($m['seat']) && !empty($m['seat'])) {
                    $s->extra()
                       ->seat($m['seat']);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        if (stripos($parser->getSubject(), 'Annulation billet de train') !== false) {
            $this->ParseTrain($email);
        } elseif (stripos($parser->getSubject(), 'annulation billets d’avion') !== false) {
            $this->ParseFlight($email);
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
        $year = date("Y", $this->date);
        $in = [
            "#(\w+\s*\,\s*\d+\s*\w+)#u", //Mar, 18 Janv
        ];
        $out = [
            "$1 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(?<week>[\w\.]+), (?<date>\d+ \w+ .+|\d+-\d+-.+)#u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
