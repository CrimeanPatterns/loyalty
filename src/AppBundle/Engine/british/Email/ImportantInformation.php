<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ImportantInformation extends \TAccountChecker
{
    public $mailFiles = "british/it-30920847.eml, british/it-58280503.eml";

    public $reFrom = ["customerservices@messages.ba.com"];
    public $reBody = [
        'Thank you for choosing to fly with British Airways. Your flight is busy today',
        'Thank you for flying with British Airways. We wanted to share some',
        'Thank you for choosing to fly with British Airways. Your flight tomorrow is busy',
        'We are sorry for the delay to your flight to',
        'writing to let you know about some changes to flight BA',
        'British Airways',
    ];
    public $reSubject = ['/Important information about your BA\s*\d+/', '/Important information about your flight BA\d{2,4} to \D+$/'];

    public static $dictionary = [
        "en" => [
            "Cancelled" => ["your flight has been cancelled"],
        ],
    ];

    public $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $subject = $parser->getSubject();

        if ((preg_match($this->reSubject[0], $subject) === 0 && preg_match($this->reSubject[1], $subject) === 0)
            || !self::detectEmailByBody($parser)
        ) {
            return $email;
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        if (preg_match("/Important information about your (?<air>BA)\s*(?<flight>\d+) flight to (?<arr>.+?) on (?<day>\d+ \w+ \d{4})$/",
            $subject, $m)) {
            $r = $email->add()->flight();
            $r->general()
                ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Booking Reference')]",
                    null, false, "/^Booking Reference[: ]+([A-Z\d]{6})$/"))
                ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Dear ')]", null, false,
                    "/^Dear[ ]+(.+)$/"));

            $s = $r->addSegment();
            $s->airline()
                ->name($m['air'])
                ->number($m['flight']);
            $s->departure()
                ->noCode()
                ->noDate()
                ->day(strtotime($m['day']));
            $s->arrival()
                ->noCode()
                ->name($m['arr'])
                ->noDate();
        }

        if (preg_match("/Important information about your flight (?<air>BA)\s*(?<flight>\d+) to (?<arr>.\D+)$/",
            $subject, $m)) {
            $this->logger->warning('YES');
            $r = $email->add()->flight();
            $r->general()
                ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Booking Reference')]",
                    null, false, "/^Booking Reference[: ]+([A-Z\d]{6})$/"))
                ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Dear ')]", null, false,
                    "/^Dear[ ]+(.+)$/"));

            $s = $r->addSegment();
            $s->airline()
                ->name($m['air'])
                ->number($m['flight']);
            $s->departure()
                ->noCode()
                ->noDate();
            $s->arrival()
                ->noCode()
                ->noDate();

            $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference:'))}]/following::text()[{$this->contains($this->t('Cancelled'))}][1]");
            $this->logger->warning("/^\D+from\s+\D+\s+to(?<arrName>\D+)[.]\s+\D+{$this->opt($this->t('Cancelled'))}[.]$/");

            if (preg_match("/^\D+from\s+(?<depName>\D+)\s+to\s+(?<arrName>\D+)[.]\s+\D+{$this->opt($this->t('Cancelled'))}[.]$/", $node, $m)) {
                $s->departure()
                    ->name($m['depName']);
                $s->arrival()
                    ->name($m['arrName']);

                $r->general()
                    ->cancelled()
                    ->status('cancelled');
            }
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='British Airways' or @alt='BA' or contains(@src,'notification.ba.com')] | //a[contains(@href,'notification.ba.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//text()[starts-with(normalize-space(),'{$reBody}')]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        foreach ($this->reSubject as $subject) {
            if ($fromProv && preg_match($subject, $headers["subject"]) > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    protected function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
