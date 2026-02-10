<?php

namespace AwardWallet\Engine\cellarpass\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Events extends \TAccountChecker
{
    public $mailFiles = "cellarpass/it-432607875.eml, cellarpass/it-441780718.eml";
    public $subjects = [
        'Reservation Confirmation | No Replies Please',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Thank you again for choosing' => [
                'Thank you again for choosing',
                'Please enjoy yourselves and thank you again for visiting',
                "We're so happy to host you for a tasting here at",
            ],
            'Event Date:'          => ['Event Date:', 'Date:'],
            'Event Start Time:'    => ['Event Start Time:', 'Time:'],
            'Event Duration Est.:' => ['Event Duration Est.:', 'Duration:'],
            'Number of Guests:'    => ['Number of Guests:', '# of Guests:'],
            'Amount Collected:'    => ['Amount Collected:', 'Reservation Total:'],
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you again for choosing'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking ID:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Number of Guests:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]reservations\.cellarpass\.com$/', $from) > 0;
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(Event::TYPE_EVENT);

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking ID:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking ID:'))}\s*([A-Z\d]{6,})/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest Name:'))}]/ancestor::p[1]", null, true, "/{$this->opt($this->t('Guest Name:'))}\s*(\D+)/"));

        $e->setName($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Event:')]", null, true, "/{$this->opt($this->t('Event:'))}\s*(.+)/"));

        $phone = $this->http->FindSingleNode("//text()[normalize-space()='Contact']/following::text()[normalize-space()][1]", null, true, "/^([\d\.]+)$/");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//img[contains(@src, 'fb.png')]/preceding::text()[normalize-space()][1]", null, true, "/^\s*([+][\d\(\)\-\s]+)$/");
        }

        if (!empty($phone)) {
            $e->setPhone($phone);
        }

        $address = $this->http->FindSingleNode("//text()[normalize-space()='Contact']/following::text()[normalize-space()][2]");

        if (empty($address)) {
            $address = implode(" ", $this->http->FindNodes("//img[contains(@src, 'fb.png')]/preceding::text()[normalize-space()][2]/ancestor::td[1]/descendant::text()[normalize-space()]"));
        }

        if (!empty($address)) {
            $e->setAddress($address);
        }

        $dateStart = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Event Date:'))}]", null, true, "/{$this->opt($this->t('Event Date:'))}\s*(.+)/");
        $timeStart = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Event Start Time:'))}]", null, true, "/{$this->opt($this->t('Event Start Time:'))}\s*(.+)/");

        $e->setStartDate(strtotime($dateStart . ', ' . $timeStart));
        $e->setGuestCount($this->http->FindSingleNode("//text()[{$this->starts($this->t('Number of Guests:'))}]", null, true, "/{$this->opt($this->t('Number of Guests:'))}\s*(\d+)/"));

        $duration = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Event Duration Est.:'))}]", null, true, "/{$this->opt($this->t('Event Duration Est.:'))}\s*((?:\d+\:\d+|\d+\s+))/");

        if (!empty($duration) && $duration < 60) {
            $e->setEndDate(strtotime('-' . $duration . ' minute', $e->getStartDate()));
        } elseif (!empty($duration)) {
            $e->setEndDate(strtotime($duration . ' minute', $e->getStartDate()));
        }

        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Amount Collected:'))}]/ancestor::p[1]", null, true, "/{$this->opt($this->t('Amount Collected:'))}\s*(.+)/");

        if (preg_match("/^(?<currency>\D{1,3})(?<total>[\.\d\,]+)(?:$|\,)/", $price, $m)) {
            $e->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
        }

        $hours = $this->http->FindSingleNode("//text()[normalize-space()='Tasting Hours']/following::text()[normalize-space()='Open Daily']/ancestor::tr[1]", null, true, "/({$this->opt($this->t('Open Daily'))}\s*.+a?p?m)/i");

        if (!empty($hours)) {
            $e->setNotes($hours);
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
