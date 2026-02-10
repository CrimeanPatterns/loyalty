<?php

namespace AwardWallet\Engine\disneyresort\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ThankYouForBooking extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-2170126.eml, disneyresort/it-2503185.eml, disneyresort/it-2966796.eml";

    public $reFrom = [
        'confirmations@experience.disneydestinations.com',
        'confirmations@reservation.disneydestinations.com',
    ];
    public $reBody = [
        'en' => ['Guest', 'Description'],
    ];
    public $reSubject = [
        'Thank you for booking at',
        'Thank you for making reservations at',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Confirmation Number' => ['Confirmation Number', 'Reservation Number'],
            'Description'         => ['Description', 'DESCRIPTION'],
            'prevDescription'     => ['RESORT', 'HOTEL'],
            'CANCELLATION POLICY' => [
                'CANCELLATION POLICY',
                'Cancellation Policy',
                'CANCELLATION AND REFUNDS',
                'Cancellation and Refunds',
                'Cancellation Prior to Guest Arrival',
            ],
            'sumTotal' => ['Grand Total:', 'Total Price for this Stay:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Disney Destinations, LLC' or contains(@src,'disneydestinations.com')] | //a[contains(@href,'disneydestinations.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && stripos($headers['subject'], 'WALT DISNEY WORLD Resort') === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
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

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();

        $info = $this->http->FindNodes("//text()[{$this->eq($this->t('Date:'))}]/ancestor::tr[{$this->contains($this->t('Confirmation Number'))}][1]/td[2]/descendant::text()[normalize-space()!='']");

        if (count($info) === 4) {
            $r->general()
                ->date(strtotime($info[0]))
                ->confirmation($this->re("/^([A-Z\d]+)$/", $info[1]))
                ->travellers($this->http->FindNodes("//text()[starts-with(normalize-space(translate(.,'0123456789','dddddddddd')),'Guest d')]/following::text()[normalize-space()!=''][1]",
                    null, "/(.+?)\s*(?:\(|$)/"))
                ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('CANCELLATION POLICY'))}]/following::text()[string-length(normalize-space())>3][1]/ancestor::tr[1]",
                    null, false, "/^[ \W]*(.+)/"));
            $r->booked()
                ->checkIn(strtotime($info[2]))
                ->checkOut(strtotime($info[3]));
            $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-In after'))}]");

            if (preg_match("/{$this->opt($this->t('Check-In after'))} (\d+:\d+(?:\s*[ap]m)?)[ \/]+{$this->opt($this->t('Check-Out before'))} (\d+:\d+(?:\s*[ap]m)?)/i",
                $node, $m)) {
                $r->booked()
                    ->checkIn(strtotime($m[1], $r->getCheckInDate()))
                    ->checkOut(strtotime($m[2], $r->getCheckOutDate()));
            }
        }
        $hotel = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Description'))}]/ancestor::tr[1][./preceding::text()[normalize-space()!=''][1][{$this->eq($this->t('prevDescription'))}]]/following-sibling::tr[normalize-space()!=''][1]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][1]");
        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Description'))}]/ancestor::tr[1][./preceding::text()[normalize-space()!=''][1][{$this->eq($this->t('prevDescription'))}]]/following-sibling::tr[normalize-space()!=''][1]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][2]");
        $r->hotel()
            ->name($hotel)
            ->noAddress();

        $room = $r->addRoom();
        $room->setType($roomType);

        $sum = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('sumTotal'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));
        $r->price()
            ->total($sum['Total'])
            ->currency($sum['Currency']);

        $cancellationText = $r->getCancellation();

        if (!empty($cancellationText)) {
            $this->detectDeadLine($r, $cancellationText);
        }

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (
            preg_match("/In order to receive a refund of your deposit, including credit card deposit transactions, notification of cancellation must be received at least (?<prior>\d+) days prior to your arrival date/i",
            $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'] . ' days', '00:00');
        }

        if (preg_match("/^For cancellations made\s*\d+\s*days or more prior to Guest arrival[,\s]+amounts paid[,\s]+minus cancellation fees assessed by third party hotels or other suppliers/i",
            $cancellationText, $m)
        ) {
            $h->booked()->nonRefundable();
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ((stripos($this->http->Response['body'], $reBody[0]) !== false)
                    && (stripos($this->http->Response['body'], $reBody[1]) !== false)
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("/(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)/", $node, $m)
            || preg_match("/(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})/", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
