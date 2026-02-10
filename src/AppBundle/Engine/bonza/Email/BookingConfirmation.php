<?php

namespace AwardWallet\Engine\bonza\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "bonza/it-543694058.eml";
    public $subjects = [
        'Bonza Booking Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flybonza.com') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Download the Fly Bonza App'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('All flight times are local'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('All Fees and Charges are in'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flybonza\.com$/', $from) > 0;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking Reference']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/"))
            ->travellers(array_unique($this->http->FindNodes("//text()[normalize-space()='Departs' or normalize-space()='Returns']/ancestor::div[normalize-space()][2]/descendant::text()[starts-with(normalize-space(), 'Adult') or starts-with(normalize-space(), 'Child')]/preceding::text()[normalize-space()][1]")));

        $infants = $this->http->FindNodes("//text()[normalize-space()='Departs' or normalize-space()='Returns']/ancestor::div[normalize-space()][2]/descendant::text()[starts-with(normalize-space(), 'Infant')]/preceding::text()[normalize-space()][1]");

        if (!empty($infants)) {
            $f->general()
                ->infants($infants);
        }

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::div[2]");

        if (preg_match("/Total\s+\D{1,3}(?<cost>[\d\.\,]+)\s*\D{1,3}(?<tax>[\d\.\,]+)\s*\D{1,3}(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})/", $price, $m)) {
            $f->price()
                ->currency($m['currency'])
                ->cost(PriceHelper::parse($m['cost'], $m['currency']))
                ->tax(PriceHelper::parse($m['tax'], $m['currency']))
                ->total(PriceHelper::parse($m['total'], $m['currency']));
        }

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Depart |') or starts-with(normalize-space(), 'Return |')]/ancestor::div[normalize-space()][3]");

        foreach ($nodes as $key => $root) {
            $text = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/\|\n(?<date>.*\s+\d{4})\nDeparts\n(?<depTime>[\d\:]+)\nArrives\n(?<arrTime>[\d\:]+)\nAll flight times are local\n(?<depCode>[A-Z]{3}).*\n(?<arlineName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\n(?<flightNumber>\d{2,4})\n(?<arrCode>[A-Z]{3})/su", $text, $m)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($m['arlineName'])
                    ->number($m['flightNumber']);

                $s->departure()
                    ->date(strtotime($m['date'] . ', ' . $m['depTime']))
                    ->code($m['depCode']);

                $s->arrival()
                    ->date(strtotime($m['date'] . ', ' . $m['arrTime']))
                    ->code($m['arrCode']);

                if ($key === 0) {
                    $seats = $this->http->FindNodes("//text()[normalize-space()='Departs']/ancestor::div[normalize-space()][2]/descendant::img[contains(@src, 'seat')]/following::text()[normalize-space()][1]", null, "/^(\d+[A-Z])$/");

                    if (count($seats) > 0) {
                        $s->setSeats($seats);
                    }
                }

                if ($key === 1) {
                    $seats = $this->http->FindNodes("//text()[normalize-space()='Returns']/ancestor::div[normalize-space()][2]/descendant::img[contains(@src, 'seat')]/following::text()[normalize-space()][1]", null, "/^(\d+[A-Z])$/");

                    if (count($seats) > 0) {
                        $s->setSeats($seats);
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseFlight($email);

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
