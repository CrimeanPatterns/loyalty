<?php

namespace AwardWallet\Engine\awc\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "awc/it-141296394.eml, awc/it-141800624.eml";
    public $subjects = [
        '/Your Booking Confirmation\: \d+/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'First Trenitalia West Coast Rail Limited trading as Avanti West Coast' => [
                'Thank you for booking with Avanti West Coast',
                'First Trenitalia West Coast Rail Limited trading as Avanti West Coast',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@avantiwestcoast.co.uk	') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('First Trenitalia West Coast Rail Limited trading as Avanti West Coast'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Outward journey'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Options & delivery'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]avantiwestcoast\.co\.uk$/', $from) > 0;
    }

    public function ParseRail(Email $email)
    {
        $r = $email->add()->train();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking reference:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking reference:'))}\s*(\d+)/"));

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Outward journey']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (preg_match("/^\s*(?<currency>\D)\s*(?<total>[\d\.]+)$/u", $total, $m)) {
            $r->price()
                ->total($m['total'])
                ->currency($m['currency']);
        }

        $pointsEarned = $this->http->FindSingleNode("//text()[normalize-space()='Loyalty Points Earned:']/following::text()[normalize-space()][1]", null, true, "/^\s*\d+\s*$/");

        if (!empty($pointsEarned)) {
            $r->setEarnedAwards($pointsEarned);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Reservations:']/following::text()[normalize-space()][1]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $date = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Outward') or starts-with(normalize-space(), 'Return journey')][1]/following::text()[normalize-space()][1]", $root);
            $info = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^(?<depName>.+)\s*\((?<depTime>[\d\:]+)\)\s*to\s*(?<arrName>.+)\s*\((?<arrTime>[\d\:]+)\)$/", $info, $m)) {
                $s->departure()
                    ->name('Europe, ' . $m['depName'])
                    ->date(strtotime($date . ', ' . $m['depTime']));

                $s->arrival()
                    ->name('Europe, ' . $m['arrName'])
                    ->date(strtotime($date . ', ' . $m['arrTime']));
            }

            $seats = $this->http->FindNodes("./ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Seat:')]/ancestor::td[1]", $root, "/{$this->opt($this->t('Seat:'))}\s*(\d+)/");

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }

            $cabin = array_unique($this->http->FindNodes("./ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Coach:')]/ancestor::td[1]", $root, "/{$this->opt($this->t('Coach:'))}\s*([A-Z])/"));

            if (count($cabin) == 1) {
                $s->extra()
                    ->cabin($cabin[0]);
            }

            $s->setNoNumber(true);
        }
    }

    public function ParseRail2(Email $email)
    {
        $r = $email->add()->train();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking reference:']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking reference:'))}\s*(\d+)/"));

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Outward journey']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

        if (preg_match("/^\s*(?<currency>\D)\s*(?<total>[\d\.]+)$/u", $total, $m)) {
            $r->price()
                ->total($m['total'])
                ->currency($m['currency']);
        }

        $pointsEarned = $this->http->FindSingleNode("//text()[normalize-space()='Loyalty Points Earned:']/following::text()[normalize-space()][1]", null, true, "/^\s*\d+\s*$/");

        if (!empty($pointsEarned)) {
            $r->setEarnedAwards($pointsEarned);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Outward journey']/ancestor::table[1]/descendant::text()[normalize-space()='Direct']/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $date = $this->http->FindSingleNode("./preceding::tr[1]/descendant::td[1]/descendant::text()[normalize-space()][last()]", $root);
            $info = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^\s*(?<depName>.+)\s+(?<depTime>[\d\:]+)\s*Direct\s+(?<duration>\d+\s*min)\s*(?<arrName>.+)\s+(?<arrTime>[\d\:]+)/", $info, $m)) {
                $s->departure()
                    ->name('Europe, ' . $m['depName'])
                    ->date(strtotime($date . ', ' . $m['depTime']));

                $s->arrival()
                    ->name('Europe, ' . $m['arrName'])
                    ->date(strtotime($date . ', ' . $m['arrTime']));

                $s->extra()
                    ->duration($m['duration']);

                $service = $this->http->FindSingleNode("//text()[normalize-space()='Train operator:']/following::text()[normalize-space()][1]");
                $s->extra()
                    ->service($service);
            }

            $seats = $this->http->FindNodes("./ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Seat:')]/ancestor::td[1]", $root, "/{$this->opt($this->t('Seat:'))}\s*(\d+)/");

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }

            $cabin = array_unique($this->http->FindNodes("./ancestor::table[1]/descendant::text()[starts-with(normalize-space(), 'Coach:')]/ancestor::td[1]", $root, "/{$this->opt($this->t('Coach:'))}\s*([A-Z])/"));

            if (count($cabin) == 1) {
                $s->extra()
                    ->cabin($cabin[0]);
            }

            $s->setNoNumber(true);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[normalize-space()='Reservations:']/following::text()[normalize-space()][1]/ancestor::tr[1]")->length > 0) {
            $this->ParseRail($email);
        } elseif ($this->http->XPath->query("//text()[normalize-space()='Outward journey']/ancestor::table[1]")->length > 0) {
            $this->ParseRail2($email);
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
}
