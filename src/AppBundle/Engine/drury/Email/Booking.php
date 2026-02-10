<?php

namespace AwardWallet\Engine\drury\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "drury/it-78676964.eml";
    public $subjects = [
        "/We're looking forward to seeing you at Drury/i",
        '/Your reservation confirmation [-A-z\d]{5,} at Drury/i',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.druryhotels.com') !== false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".druryhotels.com/") or contains(@href,"email.druryhotels.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"This email was sent by: Drury Hotels")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->contains('CONFIRMATION #')}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains('YOUR ROOM')}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.druryhotels\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        ];

        $h = $email->add()->hotel();
        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='RESERVED FOR']/ancestor::tr[1]/descendant::td[2]");
        $cancellation = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation Policy:']/following::text()[normalize-space()][1]");
        $h->general()
            ->traveller($traveller)
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='CONFIRMATION #']/ancestor::tr[1]", null, true, "/{$this->opt($this->t('CONFIRMATION #'))}\s*(\d+)/"))
            ->cancellation($cancellation);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='CONFIRMATION #']/preceding::text()[normalize-space()][2]"))
            ->address($this->http->FindSingleNode("//text()[normalize-space()='CONFIRMATION #']/preceding::text()[normalize-space()][1]"));

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[normalize-space()='ARRIVAL']/ancestor::td[1]", null, true, "/{$this->opt($this->t('ARRIVAL'))}\s*(\d+\/\d+\/\d{4})\s*$/")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[normalize-space()='DEPARTURE']/ancestor::td[1]", null, true, "/{$this->opt($this->t('DEPARTURE'))}\s*(\d+\/\d+\/\d{4})\s*$/")));

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='YOUR ROOM']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setDescription($roomType);
        }

        $totalPrice = $this->http->FindSingleNode("//*[normalize-space()='Total for stay, including tax:']/following-sibling::*[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $1,955.57
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $account = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'DRURY REWARDS')]/ancestor::td[1]", null, true, "/{$this->opt($this->t('DRURY REWARDS'))}\s*[#]\s*(\d+)/");

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        $balance = str_replace(',', '', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'POINTS')]/ancestor::td[1]", null, true, "/{$this->opt($this->t('POINTS'))}\s*([\d\,]+)/"));

        if (!empty($account) && $balance !== null) {
            $st = $email->add()->statement();
            $st->setNumber($account);
            $st->setBalance($balance);
            $st->addProperty('Name', $traveller);
        }

        if (!empty($cancellation)) {
            if (preg_match("/If (?i)your plans change and you need to cancell?, please do so by (?<hour>{$patterns['time']}) the day prior to arrival to avoid being charged one night's room and tax\./", $cancellation, $m)
            ) {
                $h->booked()->deadlineRelative('1 days', $m['hour']);
            } elseif (preg_match("/Reservations (?i)cancell?ed after (?<hour>{$patterns['time']}) on day of arrival will incur a one night room and tax penalty\./i", $cancellation, $m)
            ) {
                $h->booked()->deadlineRelative('0 days', $m['hour']);
            }
        }

        $email->setType('Booking' . ucfirst($this->lang));

        return $email;
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
