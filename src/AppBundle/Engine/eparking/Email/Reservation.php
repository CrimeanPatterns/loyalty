<?php

namespace AwardWallet\Engine\eparking\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "eparking/it-142068725.eml, eparking/it-142940081.eml, eparking/it-148250320.eml, eparking/it-148759020.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Dear'                     => ['Dear'],
            'Your reservation'         => ['You have successfully booked a reservation', 'Thank you for booking your parking reservation', 'Thank you for your reservation', 'Your reservation'],
            'The estimated total is'   => ['The estimated total is', 'The estimated total for your reservation is'],
            'statusVariants'           => ['booked', 'cancelled', 'canceled'],
            'cancelledStatuses'        => ['cancelled', 'canceled'],
            'note'                     => ['Note', 'Please note'],
            'ifYouHaveAnyQuestions'    => ['If you have any questions please contact us at', 'If you have any questions, please contact us at'],
            'pleaseContact'            => ['please feel free to contact', 'please contact'],
            '2F'                       => ['Thank you', 'Sincerely'],
        ],
    ];

    private $subjects = [
        'en' => ['Reservation Confirmation', 'Cancellation Confirmation'],
    ];

    private $detectors = [
        'en' => ['Reservation Confirmation', 'Cancellation Confirmation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mail.eparking.us') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".eparking.us/") or contains(@href,"www.eparking.us") or contains(@href,"//e-park.link")]')->length === 0
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
        $email->setType('Reservation' . ucfirst($this->lang));

        $this->parseParking($email);

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

    private function parseParking(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'e-mail'        => '\S+@\S+',
        ];

        $park = $email->add()->parking();

        $mainText = $this->htmlToText($this->http->FindHTMLByXpath("descendant::tr[ descendant::text()[normalize-space()][1][{$this->starts($this->t('Dear'))}] and descendant::text()[{$this->starts($this->t('2F'))}] ][last()]"));

        $traveller = $this->re("/^[ ]*{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:[ ]*[,;:!?]|[ ]*$)/mu", $mainText);
        $park->general()->traveller($traveller);

        /*
            You have successfully booked a reservation (19104129) with Fine Airport Parking DEN for 02/24/2022 11:30 am to 02/27/2022 01:30 pm. The estimated total is $30.80, and you prepaid $30.80 of that amount.
                OR
            Your reservation (19091054) with Fine Airport Parking DEN for 12/24/2021 12:30 pm to 12/31/2021 05:00 pm has been canceled. Any prepayment has been refunded, if paid via credit card.
        */

        $confirmation = $this->re("/{$this->opt($this->t('Your reservation'))}[ ]*\([ ]*([-A-Z\d]{5,})[ ]*\)/", $mainText)
            // cid:20210805161439_qr_code_19104129    |    https://codes.eparking.us/qr?code=19104129&hash=%242y
            // https://codes.eparking.us/generate/qr/19104129/150/XdXN3FMTnB4VXJ1/code.png
            ?? $this->http->FindSingleNode("//img[{$this->contains($this->t('QR Code to Scan'), '@alt')} or contains(@src,'_qr_code_') or (contains(@src,'/generate/qr/') and contains(@src,'/code.'))]/@src", null, true, "/(?:_qr_code_|\/qr\/\?code=|\/generate\/qr\/)([-A-Z\d]{5,})(?:[\/&_]|$)/i");

        $park->general()->confirmation($confirmation);

        $placeName = $this->re("/{$this->opt($this->t('Your reservation'))}.*?[ ]+{$this->opt($this->t('with'))}[ ]+(.{2,}?)(?:[ ]+{$this->opt($this->t('for'))}[ ]+\d|[ ]*[.;!?])/", $mainText);
        $placeName = preg_replace("/^(.{2,}?)[ ]*,[ ]*{$this->opt($this->t('the only parking'))}.+/", '$1', $placeName);
        $park->place()->location($placeName);

        $dateTimeStart = $dateTimeEnd = null;

        if (preg_match("/{$this->opt($this->t('Your reservation'))}.*?[ ]+{$this->opt($this->t('for'))}[ ]+(?<dateTimeStart>.{6,}?{$patterns['time']})[ ]+{$this->opt($this->t('to'))}[ ]+(?<dateTimeEnd>.{6,}?{$patterns['time']})/", $mainText, $m)) {
            $dateTimeStart = $m['dateTimeStart'];
            $dateTimeEnd = $m['dateTimeEnd'];
        }

        if (!$dateTimeStart) {
            $dateTimeStart = $this->http->FindSingleNode("//tr[{$this->eq($this->t('START DATE'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^.*\d.*$/");
        }

        if (!$dateTimeEnd) {
            $dateTimeEnd = $this->http->FindSingleNode("//tr[{$this->eq($this->t('END DATE'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^.*\d.*$/");
        }
        $park->booked()->start2($dateTimeStart);
        $park->booked()->end2($dateTimeEnd);

        $status = $this->re("/{$this->opt($this->t('Your reservation'))}.*?[ ]+{$this->opt($this->t('has been'))}[ ]+({$this->opt($this->t('statusVariants'))})[ ]*[,.;:!?]/", $mainText);

        if ($status) {
            $park->general()->status($status);

            if (preg_match("/^{$this->opt($this->t('cancelledStatuses'))}$/i", $status)) {
                // it-142068725.eml
                $park->general()->cancelled();
            }
        }

        $totalPrice = $this->re("/{$this->opt($this->t('The estimated total is'))}[ ]+(.{2,}?)(?:[, ]+{$this->opt($this->t('and'))} |[ .:;!]$)/m", $mainText);

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $30.80
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $park->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        // remove garbage
        $mainText = preg_replace("/\n+[* ]*{$this->opt($this->t('Directions'))}[: ]*(?:\n+[ ]*[A-z\d]{1,3}\.[ ]*{$this->opt($this->t('If you are traveling on'))}.+\n.{3,}\n)+/", '', $mainText);
        $mainText = preg_replace("/\n+[* ]*{$this->opt($this->t('note'))}[: ]+.*\n+/", "\n\n", $mainText);
        $mainText = preg_replace("/\n+[ ]*{$this->opt($this->t('Please present your'))} .+\n+/", "\n\n", $mainText);

        $address = null;

        $phone = $this->re("/{$this->opt($this->t('ifYouHaveAnyQuestions'))}[: ]+(?:{$patterns['e-mail']}[ ]+{$this->opt($this->t('or'))}[ ]+)?({$patterns['phone']})[ .!]*$/im", $mainText)
            ?? $this->re("/{$this->opt($this->t('pleaseContact'))}\b[\s\S]+\n[ ]*({$patterns['phone']})[ ]*\n{2,}[ ]*{$this->opt($this->t('2F'))}/i", $mainText)
            ?? $this->re("/{$this->opt($this->t('pleaseContact'))}(?:\s+[^:\n]{2,64})?\s+{$this->opt($this->t('at'))}[: ]+(?:{$patterns['e-mail']}[ ]+{$this->opt($this->t('or'))}[ ]+)?({$patterns['phone']})[ .!]*$/im", $mainText)
        ;

        /*  Examples bad place name:
            Fasttrack/Expresso Airport Parking
            Snag A Space - Irvine Marriott
        */
        $placeNameVariants = array_filter(preg_split("/[ ]*\/[ ]*/", $placeName), function ($item) {
            return mb_strlen($item) > 1 && preg_match("/[[:alpha:]]/u", $item) > 0;
        });

        if (
            // it-142940081.eml
            count($placeNameVariants) && preg_match("/\n(?:.*{$this->opt($placeNameVariants)}.*|[ ]*{$this->opt($this->t('The'))} .{2,64} {$this->opt($this->t('Team'))}[ ]*)\n{2,}[ ]*(?<address>(?:.{2,}\n){1,2})\n[ ]*{$this->opt($this->t('ifYouHaveAnyQuestions'))}/i", $mainText, $m)
            // it-142068725.eml, it-148250320.eml, it-148759020.eml
            || preg_match("/{$this->opt($this->t('pleaseContact'))}\b.*\n[ ]*(?<address>(?:\n.{2,}){0,2}?)(?:\n+[ ]*{$patterns['e-mail']})?(?:\n+[ ]*(?<phone>{$patterns['phone']}))?(?:\n+[ ]*{$patterns['e-mail']})?\n+[ ]*{$this->opt($this->t('2F'))}/i", $mainText, $m) && !empty($m['address'])
            // it-?.eml
            || $placeName && preg_match("/\n[ ]*{$this->opt($placeName)}[, ]*(?<address>(?:\n.{2,}){1,2})(?:\n+[ ]*{$patterns['e-mail']})?(?:\n+[ ]*(?<phone>{$patterns['phone']}))?(?:\n+[ ]*{$patterns['e-mail']})?(?:\n\n|$)/i", $mainText, $m)
            // it-?.eml
            || preg_match("/Upon arrival, drive up to the .{2,64}? lot located at\s+(?<address>.{3,70}?)\s*, proceed to the self-parking gate/i", $mainText, $m)
        ) {
            $m['address'] = preg_replace("/[ ]*\n+[ ]*/", ', ', trim($m['address']));

            if (!preg_match("/^{$patterns['phone']}$/", $m['address'])) {
                $address = $m['address'];
            }

            if (!empty($m['phone'])) {
                $phone = $m['phone'];
            }
        }

        $park->place()->address($address)->phone($phone, false, true);
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

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['Dear'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Dear'])}]")->length > 0) {
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (!empty($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
