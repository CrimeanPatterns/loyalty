<?php

namespace AwardWallet\Engine\frontierairlines\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpcomingFlight extends \TAccountChecker
{
    public $mailFiles = "frontierairlines/it-153798801.eml";
    public $subjects = [
        'Important information about your upcoming flight. Confirmation Code',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@reservation.flyfrontier.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Frontier Airlines')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('FLIGHT NOTIFICATION'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Original Flight Details:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('New Flight Details:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]reservation\.flyfrontier\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('n.º de referencia'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s*(\D+)\,/"));

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('New Flight Details:'))}]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flightText = $this->http->FindSingleNode("./following::text()[normalize-space()][1]/ancestor::p[1]", $root);

            $this->logger->debug($flightText);

            if (preg_match("/^New Flight Details\:(?<airline>.+)\s+[#]\s*(?<number>\d{2,4})\s*from\s*(?<depCode>[A-Z]{3})\D+\s+to\s+(?<arrCode>[A-Z]{3})\D+On\s+(?<depDate>.+)\s+Departing\s*at\:\s*(?<depTime>.+)\s*Arriving\s*at:\s*(?<arrTime>.+M)$/", $flightText, $m)
            || preg_match("/^New Flight Details\:(?<airline>.+)\s+[#]\s*(?<number>\d{2,4})\s*from\s*(?<depCode>[A-Z]{3})\D+\s+to\s+(?<arrName>\D+)On\s+(?<depDate>.+)\s+Departing\s*at\:\s*(?<depTime>.+)\s*Arriving\s*at:\s*(?<arrTime>.+M)$/", $flightText, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['number']);

                $s->departure()
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));

                $s->arrival()
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['arrTime']));

                if (!isset($m['arrCode'])) {
                    $s->arrival()
                        ->name($m['arrName'])
                        ->noCode();
                } else {
                    $s->arrival()
                        ->code($m['arrCode']);
                }
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

    private function normalizeDate($str)
    {
        $in = [
            //May 02, 2022, 6:48 PM
            "#^(\w+)\s*(\d+)\,\s*(\d{4})\,\s*([\d\:]+\s*A?P?M)$#u",
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
}
