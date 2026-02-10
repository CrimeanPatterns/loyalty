<?php

namespace AwardWallet\Engine\airmaroc\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ImpotantFlight extends \TAccountChecker
{
    public $mailFiles = "airmaroc/it-126095026.eml, airmaroc/it-127447347.eml";
    public $subjects = [
        '/Important : Flight schedule change :/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'IMPORTANT : FLIGHT SCHEDULE'              => ['IMPORTANT : FLIGHT SCHEDULE', 'IMPORTANT : FLIGHT', 'IMPORTANT: FLIGHT'],
            'We regret to inform you that your flight' => ['We regret to inform you that your flight', 'We invite you to Check-in your flight'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@mail-royalairmaroc.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'royalairmaroc.com') or contains(normalize-space(), 'Royal Air Maroc')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('We regret to inform you that your flight'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('IMPORTANT : FLIGHT SCHEDULE'))} or contains(normalize-space(), 'IMPORTANT INFORMATION ABOUT YOUR FLIGHT')]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\-royalairmaroc\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Booking reference:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking reference:'))}\s*([A-Z\d]+)/"))
            ->travellers(explode(', ', trim($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Dear')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Dear'))}\s*(.+)/"), ',')), true);

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('IMPORTANT : FLIGHT SCHEDULE'))}]", null, true, "/{$this->opt($this->t('IMPORTANT : FLIGHT SCHEDULE'))}\s*(.+)/");

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        if ($f->getStatus() == 'CANCELLATION') {
            $f->general()
                ->cancelled();
        }

        $xpath = "//text()[normalize-space()='Itinerary Summary']/following::text()[normalize-space()='From'][1]/ancestor::table[1]/following::table[contains(normalize-space(), ':') and contains(normalize-space(), 'AT')][not(contains(normalize-space(), 'LEGAL INFORMATION:'))]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && $f->getCancelled() == true) {
            $xpath = "//text()[normalize-space()='Previous flight information']/following::text()[normalize-space()='From'][1]/ancestor::table[1]/following::table[contains(normalize-space(), ':') and contains(normalize-space(), 'AT')][not(contains(normalize-space(), 'LEGAL INFORMATION:'))]";
            $nodes = $this->http->XPath->query($xpath);
        }
        $this->logger->debug($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flightText = $this->http->FindSingleNode(".", $root);
            $reg = "/^\s*.+\s(?<depCode>[A-Z]{3}).+(?<arrCode>[A-Z]{3}).+\s+(?<depTime>\d+\:\d+)\s+(?<arrTime>\d+\:\d+)\s*(?<airlineName>[A-Z]{2})(?<flightName>\d{2,4})\s*(?<cabin>[A-Z])\s*(?<depDate>\w+\s*\d+\,\s*\d{4})\s*(?<arrDate>\w+\s*\d+\,\s*\d{4})$/";

            if (preg_match($reg, $flightText, $m)) {
                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['flightName']);

                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));

                $s->arrival()
                    ->code($m['arrCode'])
                    ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']));

                $s->extra()
                    ->cabin($m['cabin']);
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
