<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmationText extends \TAccountChecker
{
    public $mailFiles = "avis/it-92956133.eml";
    public $subjects = [
        '/Avis Real\-Time Reservation Confirmation/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@avis-europe.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Avis Rent A Car')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Rental office:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Car group:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]avis\-europe\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getPlainBody();
        $text = str_replace(">", "", $text);
        $this->logger->error($text);

        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('Your reservation number is'))}\s*([A-Z\-\d]+)/u", $text))
            ->traveller($this->re("/{$this->opt($this->t('Dear Mr'))}[s]?\s*(\w+)\,/", $text));

        $pickHours = $this->re("/{$this->opt($this->t('opening hours on day of collection:'))}(.+hours)/", $text);

        if (empty($pickHours)) {
            $pickHours = $this->re("/{$this->opt($this->t('opening hours on day of collection:'))}([\d\:\s\-]+hours)/us", $text);
        }

        $r->pickup()
            ->location($this->re("/{$this->opt($this->t('Rental office:'))}\s*(.+)/u", $text))
            ->openingHours($pickHours)
            ->phone($this->re("/{$this->opt($this->t('Telephone'))}(.+)/", $text))
            ->date(strtotime($this->re("/{$this->opt($this->t('Collect on:'))}\s*(.+)/u", $text)));

        $dropHours = $this->re("/{$this->opt($this->t('opening hours on day of return:'))}(.+hours)/", $text);

        if (empty($dropHours)) {
            $dropHours = $this->re("/{$this->opt($this->t('opening hours on day of return:'))}([\d\:\s\-]+hours)/us", $text);
        }

        $r->dropoff()
            ->location($this->re("/{$this->opt($this->t('Return office:'))}\s*(.+)/u", $text))
            ->openingHours($dropHours)
            ->phone($this->re("/{$this->opt($this->t('Telephone'))}(.+)/", $text))
            ->date(strtotime($this->re("/{$this->opt($this->t('Return on:'))}\s*(.+)/u", $text)));

        $carInfo = $this->re("/{$this->opt($this->t('Car group:'))}\s*(.+)/", $text);

        if (preg_match("/^(.+)\s\((.+)\)/", $carInfo, $m)) {
            $r->car()
                ->model($m[2])
                ->type($m[1]);
        }

        $r->price()
            ->total(str_replace(',', '', $this->re("/{$this->opt($this->t('Estimated cost:'))}\s*([\d\.\,]+)\s*[A-Z]{3}/", $text)))
            ->currency($this->re("/{$this->opt($this->t('Estimated cost:'))}\s*[\d\.\,]+\s*([A-Z]{3})/", $text));

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
