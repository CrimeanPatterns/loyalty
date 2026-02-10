<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationCanceled extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-1938614.eml, airbnb/it-1941848.eml, airbnb/it-1973093.eml";
    public $subjects = [
        '/^Reservation\s+[A-Z\d]+\s*on\s*[\d\-]+\s*Canceled$/',
        '/^Reservation\s+[A-Z\d]+\s*on\s*\w+\s*\d+\,\s*\d{4}\s*Canceled$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'canceled reservation' => ['canceled reservation', 'cancelled reservation'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airbnb.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Airbnb')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('canceled reservation'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('starting on'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airbnb\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('canceled reservation'))}]", null, true, "/{$this->opt($this->t('canceled reservation'))}\s*([\dA-Z]+)/u");
        $this->logger->error($confirmation);

        if (!empty($confirmation)) {
            $h->general()
                ->confirmation($confirmation)
                ->cancelled()
                ->status('canceled');
        }

        $cancellation = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'cancellation policy')]", null, true, "/({$this->opt($this->t('In accordance with'))}.+)/su");

        if (!empty($cancellation)) {
            $h->general()->cancellation($cancellation);
        }

        $checkIn = $this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'starting on')]", null, true, "/{$this->opt($this->t('starting on'))}\s+(\d{4}\-\d+\-\d+)/u"));

        if (empty($checkIn)) {
            $checkIn = $this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'starting on')]", null, true, "/{$this->opt($this->t('starting on'))}\s+(\w+\s\d+\,\s+\d{4})/u"));
        }

        if (empty($checkIn)) {
            $checkIn = $this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'starting on')]", null, true, "/{$this->opt($this->t('starting on'))}\s+(\d+\s\w+\,\s+\d{4})/u"));
        }

        $h->booked()
            ->checkIn($checkIn);

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

    private function normalizeDate($date)
    {
        $in = [
            // 2014-03-03
            '/^(\d{4})\-(\d+)\-(\d+)$/u',
            //July 24, 2014
            '/^(\w+)\s(\d+)\,\s+(\d{4})$/u',
            //26 June, 2014
            '/^(\d+)\s(\w+)\,\s+(\d{4})$/u',
        ];
        $out = [
            '$3.$2.$1',
            '$2 $1 $3',
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }
}
