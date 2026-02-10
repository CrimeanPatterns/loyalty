<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightChanged extends \TAccountChecker
{
    public $mailFiles = "asia/it-113772146.eml, asia/it-113834056.eml";
    public $subjects = [
        '/Your flight to .+ has been changed/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@schedule.cathaypacific.com') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, 'schedule.cathaypacific.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Your new itinerary')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your original itinerary'))}]")->length > 0;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]schedule\.cathaypacific\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Your booking reference:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Your booking reference:'))}\s*([A-Z\d]+)/"), 'Your booking reference');

        $traveller = str_replace(['Mrs', 'Ms', 'Mr'], '', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*(\D+)/"));

        if (!empty($traveller) && preg_match('/^\s*passenger$/i', $traveller)) {
        } else {
            $f->general()
                ->traveller($traveller, true);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Your original itinerary']/preceding::img[contains(@src, 'itinerary')]/ancestor::table[normalize-space()][2]");

        foreach ($nodes as $root) {
            $segmentText = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));
            $this->logger->debug($segmentText);

            if (preg_match("/(?<depTime>[\d\:]+)\n(?<depCode>[A-Z]{3})\n(?<arrTime>[\d\:]+)\n(?<nextDay>[+]1\n)?(?<arrCode>([A-Z]{3}))\n(?<cabin>\D+)\nClass\s*(?<bookingCode>\D)\nOperated by\s*(?<operator>.+)\nTotal duration\n(?<duration>.+)/", $segmentText, $m)) {
                $s = $f->addSegment();

                $date = $this->http->FindSingleNode("./preceding::text()[normalize-space()][3]", $root);

                $s->airline()
                    ->name($this->http->FindSingleNode("./preceding::text()[normalize-space()][2]", $root, true, "/^([A-Z\d]{2})/"))
                    ->number($this->http->FindSingleNode("./preceding::text()[normalize-space()][2]", $root, true, "/^[A-Z\d]{2}\s*(\d{2,4})/"))
                    ->operator($m['operator']);

                $s->departure()
                    ->date(strtotime($date . ', ' . $m['depTime']))
                    ->code($m['depCode']);

                $s->arrival()
                    ->date(strtotime($date . ', ' . $m['arrTime']))
                    ->code($m['arrCode']);

                if (isset($m['nextDay']) && !empty($m['nextDay'])) {
                    $s->arrival()
                        ->date(strtotime('+1 day', strtotime($date . ', ' . $m['arrTime'])));
                } else {
                    $s->arrival()
                        ->date(strtotime($date . ', ' . $m['arrTime']));
                }

                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode'])
                    ->duration($m['duration']);
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
}
