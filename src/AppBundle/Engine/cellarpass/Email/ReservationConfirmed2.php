<?php

namespace AwardWallet\Engine\cellarpass\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmed2 extends \TAccountChecker
{
    public $mailFiles = "cellarpass/it-442943041.eml";
    public $subjects = [
        '| Reservation Confirmed',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@reservations.cellarpass.com') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('CellarPass'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t(', Party of'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('A message from '))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]reservations\.cellarpass\.com$/', $from) > 0;
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(Event::TYPE_EVENT);

        $date = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Order Date:')]", null, true, "/{$this->opt($this->t('Order Date:'))}\s*(.+)/");

        if (!empty($date)) {
            $e->general()
                ->date(strtotime($date));
        }

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Confirmation:')]", null, true, "/^{$this->opt($this->t('Confirmation:'))}\s*([A-Z]{6,})\s*$/u"));

        $eventInfo = $this->http->FindSingleNode("//text()[contains(normalize-space(), ', Party of')]");

        if (preg_match("/^(?<traveller>\D+)\,\s*Party of\s*(?<guests>\d+)$/", $eventInfo, $m)) {
            $e->general()
                ->traveller($m['traveller']);

            $e->setGuestCount($m['guests']);
        }

        $eventName = $this->http->FindSingleNode("//text()[normalize-space()='Add to Calendar']/following::text()[normalize-space()][1]");
        $e->setName($eventName);

        $dateStart = $this->http->FindSingleNode("//text()[normalize-space()='Add to Calendar']/preceding::text()[normalize-space()][1]", null, true, "/^(.+\d{4}.*)\s*\|/");

        if (!empty($dateStart)) {
            $e->setStartDate(strtotime($dateStart))
                ->setNoEndDate(true);
        }

        $placeInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='GET DIRECTIONS']/preceding::text()[starts-with(normalize-space(), '+')]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<address>(?:.+\n){1,4})(?<phone>[+\d\.\(\)\-\s]+)$/", $placeInfo, $m)) {
            $e->setAddress(str_replace("\n", " ", $m['address']));
            $e->setPhone($m['phone']);
        }

        $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Order Total:')]", null, true, "/{$this->opt($this->t('Order Total:'))}\s*(.+)/");

        if (preg_match("/^(?<currency>\D{1,3})(?<total>[\.\d\,]+)$/", $price, $m)) {
            $e->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEvent($email);

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
        return 0;
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
