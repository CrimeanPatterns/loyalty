<?php

namespace AwardWallet\Engine\acerentacar\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1969727 extends \TAccountCheckerExtended
{
    public $mailFiles = "acerentacar/it-1969727.eml, acerentacar/it-92113756.eml";

    public $subjects = [
        '/ACE Rent A Car \- Reservation Cancelation/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'confirms the cancelation of reservation' => ['confirms the cancelation of reservation', 'confirms the cancellation of reservation'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@acerentacar.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'ACE Rent A Car')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('confirms the cancelation of reservation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Return Location:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]acerentacar\.com$/', $from) > 0;
    }

    public function ParseEmailCar(Email $email)
    {
        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('confirms the cancelation of reservation'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('confirms the cancelation of reservation'))}\s*([A-Z\d]+)/"))
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reserved For:')]/following::text()[normalize-space()][1]"))
            ->status('canceled')
            ->cancelled();

        $pickDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pickup Date:')]/following::text()[normalize-space()][1]");
        $pickTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pickup Time:')]/following::text()[normalize-space()][1]");

        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Pickup Location:')]/following::text()[normalize-space()][1]"))
            ->date($this->normalizeDate($pickDate . ', ' . $pickTime));

        $dropDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Return Date:')]/following::text()[normalize-space()][1]");
        $dropTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Return Time:')]/following::text()[normalize-space()][1]");

        $r->dropoff()
            ->location($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Return Location:')]/following::text()[normalize-space()][1]"))
            ->date($this->normalizeDate($dropDate . ', ' . $dropTime));
    }

    public function ParseEmailCar2(Email $email, $text)
    {
        $r = $email->add()->rental();
        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('confirms the cancelation of reservation'))}\s*([A-Z\d]+)/", $text))
            ->traveller($this->re("/{$this->opt($this->t('Reserved For:'))}\s*(.+)/", $text))
            ->status('canceled')
            ->cancelled();

        $pickDate = $this->re("/{$this->opt($this->t('Pickup Date:'))}\s*(.+)/", $text);
        $pickTime = $this->re("/{$this->opt($this->t('Pickup Time:'))}\s*(.+)/", $text);

        $r->pickup()
            ->location($this->re("/{$this->opt($this->t('Pickup Location:'))}\s*(.+)/", $text))
            ->date($this->normalizeDate($pickDate . ', ' . $pickTime));

        $dropDate = $this->re("/{$this->opt($this->t('Return Date:'))}\s*(.+)/", $text);
        $dropTime = $this->re("/{$this->opt($this->t('Return Time:'))}\s*(.+)/", $text);

        $r->dropoff()
            ->location($this->re("/{$this->opt($this->t('Return Location:'))}\s*(.+)/", $text))
            ->date($this->normalizeDate($dropDate . ', ' . $dropTime));
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('confirms the cancelation of reservation'))}]/ancestor::tr[1]")->length > 0) {
            $this->ParseEmailCar($email);
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('confirms the cancelation of reservation'))}]")->length > 0) {
            $text = $parser->getPlainBody();
            $this->ParseEmailCar2($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
        $in = [
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\,\s*([\d\:]+\s*A?P?M)$#", //July 04, 2021, 05:00 PM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

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
}
