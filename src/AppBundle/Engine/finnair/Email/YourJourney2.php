<?php

namespace AwardWallet\Engine\finnair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourJourney2 extends \TAccountChecker
{
    public $mailFiles = "finnair/it-300035359.eml, finnair/it-301190904.eml, finnair/it-301224521.eml";
    public $subjects = [
        //en
        ', your journey to',
        //fi
        ', matkasi kohteeseen',
        // sv
        ', din resa till',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
            //'Finnair app' => '',
            'Check in online' => ['Check in online', 'Buy travel extras'],
            //'Itinerary and important notes' => '',
            //'Ready to take off' => '',
            //'Total duration:' => '',
        ],

        "fi" => [
            'Finnair app'                   => 'Lataa Finnairin mobiilisovellus',
            'Check in online'               => ['Osta lisäpalveluita', 'Tee lähtöselvitys'],
            'Itinerary and important notes' => 'Lennon tiedot ja tärkeät lisätiedot',
            'Ready to take off'             => 'Oletko valmiina lähtöön',
            'Total duration:'               => 'Lennon kesto:',
            'Operated by'                   => 'Liikenn�i',
        ],
        "sv" => [
            'Finnair app'                   => 'Lataa Finnairin mobiilisovellus',
            'Check in online'               => ['Köp resetillval'],
            'Itinerary and important notes' => 'Ladda ner Finnair-appen',
            'Ready to take off'             => 'Redo för din resa',
            'Total duration:'               => 'Total flygtid:',
            // 'Operated by'                   => 'Liikenn�i',
        ],
    ];

    public $langDetectors = [
        "en" => ["Ready to take off"],
        "fi" => ["Oletko valmiina lähtöön"],
        "sv" => ["Redo för din resa"],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@finnair.com') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Finnair app'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Check in online'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Itinerary and important notes'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]finnair\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $nodes = $this->http->XPath->query("//img[contains(@src, 'airplane')]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $f = $email->add()->flight();

            $f->general()
                ->confirmation($this->http->FindSingleNode("./preceding::tr[1]", $root, true, "/^([A-Z\d]{6})\s+/su"))
                ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Ready to take off'))}]", null, true, "/{$this->opt($this->t('Ready to take off'))}\,?\s*(\D+)\?/"), false);

            $s = $f->addSegment();

            $airInfo = $this->http->FindSingleNode("./preceding::tr[1]", $root);

            if (preg_match("/^[A-Z\d]{6}\s+.+\s+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d{1,4})\s*(?<cabin>.+)?$/", $airInfo, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                if (isset($m['cabin']) && !empty($m['cabin'])) {
                    $s->extra()
                        ->cabin($m['cabin']);
                }
            }

            $depInfo = $this->http->FindSingleNode("./descendant::td[1]", $root);

            if (preg_match("/^(?<depCode>[A-Z]{3})\s*(?<depTime>[\d+\:]+).*\s+(?<depDate>\d+\.\d+\.\d{2,4})$/", $depInfo, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));
            }
            $arrInfo = $this->http->FindSingleNode("./descendant::td[3]", $root);

            if (preg_match("/^(?<arrTime>[\d+\:]+)\s*(?<arrCode>[A-Z]{3}).*\s+(?<arrDate>\d+\.\d+\.\d{2,4})$/", $arrInfo, $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));
            }

            $duration = $this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Total duration:'))}][1]/descendant::text()[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Total duration:'))}\s*(.+)$/");

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $operator = $this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Total duration:'))}][1]/descendant::text()[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)\.\s*{$this->opt($this->t('Total duration:'))}/");

            if (!empty($operator)) {
                $s->setCarrierAirlineName($operator);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

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

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+\.\d+)\.(\d{2})\,\s*([\d\:]+)$#u", //Miércoles, 19 de mayo de 2021
        ];
        $out = [
            "$1.20$2, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
