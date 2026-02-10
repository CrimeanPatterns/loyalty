<?php

namespace AwardWallet\Engine\edelweiss\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "edelweiss/it-120747911.eml";
    public $subjects = [
        'Edelweiss Itinerary',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flyedelweiss.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Edelweiss Air'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Air Itinerary Details')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flyedelweiss\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number :']/ancestor::td[1]", null, true, "/{$this->opt($this->t('Confirmation Number :'))}\s*([A-Z\d]+)/"))
            ->travellers($this->http->FindNodes("//text()[normalize-space()='Passengers']/ancestor::tr[1]/following-sibling::tr/descendant::text()[contains(normalize-space(), 'Flight')]/preceding::text()[normalize-space()][1]"), true);

        $price = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Charge to:')]/ancestor::tr[1]/descendant::td[normalize-space()][last()]");

        if (preg_match("/^\s*([A-Z]{3})\s*([\d\.\,]+)\s*$/", $price, $m)) {
            $f->price()
                ->currency($m[1])
                ->total(PriceHelper::cost($m[2], ',', '.'));
        }

        $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), 'Outbound') or contains(normalize-space(), 'Inbound')]/ancestor::tr/following-sibling::tr[contains(normalize-space(), '(')][normalize-space()][not(contains(normalize-space(), 'Inbound'))]");

        foreach ($nodes as $root) {
            $row1 = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^(?<depName>\D+)\s*\((?<depCode>[A-Z]{3})\)\s*(?<arrName>\D+)\((?<arrCode>[A-Z]{3})\)\s*(?<fName>[A-Z\d]{2})(?<fNumber>\d{2,4})\s*(?<cabin>\D+)$/", $row1, $m)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($m['fName'])
                    ->number($m['fNumber']);

                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode']);

                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode']);

                $s->extra()
                    ->cabin($m['cabin']);

                $s->departure()
                    ->date($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[1]", $root)));

                $s->arrival()
                    ->date($this->normalizeDate($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[2]", $root)));

                $s->airline()
                    ->operator($this->http->FindSingleNode("./following-sibling::tr[1]/descendant::td[3]", $root));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
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

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*\w+\,\s*(\d+)\.(\w+)\.(\d{4})\s*([\d\:]+)$#', //Thu, 16.Dec.2021   06:00
        ];
        $out = [
            '$1 $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }
}
