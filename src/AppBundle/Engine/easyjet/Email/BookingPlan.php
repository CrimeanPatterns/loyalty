<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingPlan extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-695503869.eml";
    public $subjects = [
        '/^Your booking\s+[A-Z\d]+\:\s+.*is just days away$/',
    ];

    public $lang = 'en';
    public $conf = '';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.easyjet.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'easyJet Airline')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('PLAN YOUR ARRIVAL'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your flight departs at'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.easyjet\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/^Your booking\s+([A-Z\d]+)\:/", $parser->getSubject(), $m)) {
            $this->conf = $m[1];
        }

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->conf);

        $segText = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Depart:']/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^Depart\:\n*(?<depName>.+\)?)\s+(?<depDate>\w+\s*\d+\s*\w+)\s+(?<depTime>[\d\:]+)\n*Flight time\:\n*(?<duration>.+)\n*Arrive:\n*(?<arrName>.+\)?)\s+(?<arrDate>\w+\s*\d+\s*\w+)\s+(?<arrTime>[\d\:]+)/", $segText, $m)) {
            $s = $f->addSegment();

            $s->airline()
                ->name('U2')
                ->noNumber();

            $s->departure()
                ->date(strtotime($m['depDate'] . ', ' . $m['depTime']))
                ->name($m['depName'])
                ->noCode();

            $s->arrival()
                ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']))
                ->name($m['arrName'])
                ->noCode();

            $s->extra()
                ->duration($m['duration']);
        }
        $this->logger->debug($segText);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
