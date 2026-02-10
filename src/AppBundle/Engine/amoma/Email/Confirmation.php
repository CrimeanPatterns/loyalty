<?php

namespace AwardWallet\Engine\amoma\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "amoma/it-36794117.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Reference number:' => ['Reference number:'],
            'Check-out date:'   => ['Check-out date:'],
        ],
    ];

    private $subjects = [
        'en' => ['Hotel confirmation voucher to be presented at check-in'],
    ];

    private $detectors = [
        'en' => ['Hotel information', 'Hotel Information', 'HOTEL INFORMATION'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amoma.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//node()[contains(.,"@amoma.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHotel($email);
        $email->setType('Confirmation' . ucfirst($this->lang));

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

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $xpathCell = '(self::td or self::th)';
        $xpathFollow = "/following-sibling::*[normalize-space()][1]";

        $hotel = $this->http->FindSingleNode("//*[$xpathCell and {$this->eq($this->t('Hotel:'))}]" . $xpathFollow);
        $h->hotel()->name($hotel);

        $address = $this->http->FindSingleNode("//*[$xpathCell and {$this->eq($this->t('Address:'))}]" . $xpathFollow);
        $country = $this->http->FindSingleNode("//*[$xpathCell and {$this->eq($this->t('Country:'))}]" . $xpathFollow);
        $h->hotel()->address($address . ', ' . $country);

        $confirmationNumber = $this->http->FindSingleNode("//*[$xpathCell and {$this->eq($this->t('Reference number:'))}]" . $xpathFollow, null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmationNumber) {
            $confirmationNumberTitle = $this->http->FindSingleNode("//*[$xpathCell and {$this->eq($this->t('Reference number:'))}]");
            $h->general()->confirmation($confirmationNumber, rtrim($confirmationNumberTitle, ' :'));
        }

        $guestName = $this->http->FindSingleNode("//*[$xpathCell and {$this->eq($this->t('Guest name:'))}]" . $xpathFollow);
        $h->general()->traveller($guestName);

        $checkInDate = $this->http->FindSingleNode("//*[$xpathCell and {$this->eq($this->t('Check-in date:'))}]" . $xpathFollow);
        $h->booked()->checkIn2($checkInDate);

        $checkOutDate = $this->http->FindSingleNode("//*[$xpathCell and {$this->eq($this->t('Check-out date:'))}]" . $xpathFollow);
        $h->booked()->checkOut2($checkOutDate);

        $typeOfRoomHtml = $this->http->FindHTMLByXpath("//*[$xpathCell and {$this->eq($this->t('Type of room:'))}]" . $xpathFollow);
        $typeOfRoomText = $this->htmlToText($typeOfRoomHtml);

        if (preg_match_all("/^[ ]*(?<count>\d{1,3})[ ]*x[ ]*(?<type>.+?)(?:[ ]+-[ ]+(?<desc>.+))?[ ]*$/m", $typeOfRoomText, $matches, PREG_SET_ORDER)) {
            $roomsCount = 0;

            foreach ($matches as $m) {
                for ($i = 0; $i < (int) $m['count']; $i++) {
                    $room = $h->addRoom();
                    $room->setType($m['type']);

                    if (!empty($m['desc'])) {
                        $room->setDescription($m['desc']);
                    }
                    $roomsCount++;
                }
            }

            if ($roomsCount) {
                $h->booked()->rooms($roomsCount);
            }
        }

        if (preg_match("/\b(\d{1,3})[ ]*adult/i", $typeOfRoomText, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})[ ]*children/i", $typeOfRoomText, $m)) {
            $h->booked()->kids($m[1]);
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Reference number:']) || empty($phrases['Check-out date:'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Reference number:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Check-out date:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z]+\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z]+\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
