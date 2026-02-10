<?php

namespace AwardWallet\Engine\dresorts\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourUpcoming extends \TAccountCheckerExtended
{
    public $mailFiles = "dresorts/it-3030673.eml, dresorts/it-44337273.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Reservation Number' => ['Reservation Number', '• Reservation Number'],
            'Check-in Date'      => ['Check-in Date', 'Arrival Date'],
            'Check-out Date'     => ['Check-out Date', 'Departure Date'],
            'Room Type'          => ['Room Type', 'Vacation Home'],
        ],
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".diamondresorts.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for choosing to stay at Diamond")'
                . ' or contains(normalize-space(),"Diamond Resorts Holdings, LLC. All rights reserved")'
                . ' or contains(.,"@diamondresorts.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@diamondresorts.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Upcoming Diamond Resorts') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHotel($email);
        $email->setType('YourUpcoming' . ucfirst($this->lang));

        return $email;
    }

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $travellerName = $this->http->FindSingleNode("//text()[{$this->starts('Dear')}]", null, true, "/Dear\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*(?:[,!?]+|$)/");
        $h->general()->travellers(explode(' and ', $travellerName));

        $xpathLi = "//text()[{$this->starts($this->t('Reservation Number'))} or {$this->starts('Number of Adults')}]/ancestor::li";
        $xpathBr = "//text()[{$this->starts($this->t('Reservation Number'))}]/ancestor::*[{$this->contains($this->t('Check-in Date'))}][1]";
//        $this->logger->debug('$xpathBr = '.print_r( $xpathBr,true));
//        $this->logger->debug('$this->http->XPath->query($xpathBr . \'/descendant::br\')->length = '.print_r( $this->http->XPath->query($xpathBr . '/descendant::br')->length,true));
        if ($this->http->XPath->query($xpathLi)->length > 0) {
            // it-3030673.eml
            $listItems = $this->http->FindNodes($xpathLi
                . ' | ' . $xpathLi . '/preceding-sibling::li[normalize-space()]'
                . ' | ' . $xpathLi . '/following-sibling::li[normalize-space()]');
            $infoText = implode("\n", $listItems);
        } elseif ($this->http->XPath->query($xpathBr . '/descendant::br')->length > 0) {
            // it-44337273.eml
            $infoLHtml = $this->http->FindHTMLByXpath($xpathBr);
            $infoLText = $this->htmlToText($infoLHtml);
            $infoRHtml = $this->http->FindHTMLByXpath("//text()[{$this->starts('Number of Adults')}]/ancestor::*[{$this->contains('Number of Children')}][1]");
            $infoRText = $this->htmlToText($infoRHtml);
            $infoText = $infoLText . "\n" . $infoRText;
        } else {
            $this->logger->debug('Wrong format!');

            return;
        }
//        $this->logger->debug('$infoText = '.print_r( $infoText,true));

        if (preg_match("/^[ •]*(" . $this->opt($this->t('Reservation Number')) . ")\s*:*\s*([-A-Z\d]{5,})[ ]*$/m", $infoText, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        if (preg_match("/^[ •]*Number of Points Used\s*:*\s*(\d+)[ ]*$/m", $infoText, $m)) {
            $h->price()->spentAwards($m[1]);
        }

        $dateCheckIn = preg_match("/^[ •]*" . $this->opt($this->t('Check-in Date')) . "\s*:*\s*(.{6,}?)[ ]*$/m", $infoText, $m) ? $m[1] : null;
        $dateCheckOut = preg_match("/^[ •]*" . $this->opt($this->t('Check-out Date')) . "\s*:*\s*(.{6,}?)[ ]*$/m", $infoText, $m) ? $m[1] : null;

        if (preg_match("/^[ •]*" . $this->opt($this->t('Room Type')) . "\s*:*\s*(.{3,}?)[ ]*$/m", $infoText, $m)) {
            $room = $h->addRoom();
            $room->setType($m[1]);
        }

        $guestCount = preg_match("/^[ •]*Number of Adults\s*:*\s*(\d{1,3})[ ]*$/m", $infoText, $m) ? $m[1] : null;
        $kidsCount = preg_match("/^[ •]*Number of Children\s*:*\s*(\d{1,3})[ ]*$/m", $infoText, $m) ? $m[1] : null;

        $h->booked()
            ->checkIn2($dateCheckIn)
            ->checkOut2($dateCheckOut)
            ->guests($guestCount)
            ->kids($kidsCount);

        $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->starts('Dear')}]/preceding::text()[normalize-space()][1]");

        if ($this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1) {
            $h->hotel()->name($hotelName_temp);

            $contactsTexts = $this->http->FindNodes("//text()[{$this->starts($this->t('Reservation Number'))}]/following::text()[{$this->eq($hotelName_temp)}][last()]/following::text()[normalize-space()][position()<7]");
            $contactsText = implode("\n", $contactsTexts);

            if (preg_match("/^(?<address>[\s\S]+?)(?:\s+Map and Directions)?\s+Phone\s*:*\s*(?<phone>[+(\d][-. \d)(]{5,}[\d)])[ ]*(?:\n|$)/i", $contactsText, $m)) {
                $h->hotel()
                    ->address(preg_replace('/\s+/', ' ', $m['address']))
                    ->phone($m['phone']);
            }
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Reservation Number']) || empty($phrases['Check-in Date'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Reservation Number'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Check-in Date'])}]")->length > 0
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

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
