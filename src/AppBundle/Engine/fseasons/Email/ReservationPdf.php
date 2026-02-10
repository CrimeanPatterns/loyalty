<?php

namespace AwardWallet\Engine\fseasons\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationPdf extends \TAccountChecker
{
    public $mailFiles = "fseasons/it-33885640.eml, fseasons/it-33885635.eml, fseasons/it-36715554.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Confirmation Number' => ['Confirmation Number', 'Confirmation number'],
            'Departure Date'      => ['Departure Date', 'Departure date'],
            'Number of Guests'    => ['Number of Guests', 'Number of guests'],
            'addressEnd'          => ['Tel:', 'Reservation:', 'Fax:', 'E-mail:', 'Web:'],
            'roomType'            => ['Accommodation', 'Room Type'],
            'rateEnd'             => ['Method of Guarantee', 'NEXT STEPS FOR PLANNING YOUR STAY'],
        ],
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, 'www.fourseasons.com') === false
                && stripos($textPdf, 'fourseasons.com') === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('ReservationPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, $textPdf)
    {
        $text = $textPdf;

        $reservationStart = $this->strposArray($text, $this->t('Reservation Confirmation'));
        $reservationEnd = $this->strposArray($text, $this->t('We look forward to welcoming'));

        if ($reservationStart !== false && $reservationEnd !== false) {
            $text = substr($text, $reservationStart, $reservationEnd - $reservationStart);
        }

        // remove colontituls
        $text = preg_replace('/\n*^https?:\/\/.+\n*/im', "\n", $text);
        $text = preg_replace('/\n*^\d+\/\d+\/\d+\b.*\n*/m', "\n", $text);

        $h = $email->add()->hotel();

        $contactsStart = $this->strposArray($text, $this->t('Reservation Confirmation'));
        $contactsEnd = $this->strposArray($text, $this->t('Confirmation Number'));
        $contactsText = $contactsStart !== false && $contactsEnd !== false
            ? substr($text, $contactsStart, $contactsEnd - $contactsStart) : '';

        if ((preg_match("/^{$this->opt($this->t('Reservation Confirmation'))}:*\s+(?:[\s\S]+?\d{4})\s+(?:[\s\S]+?\d{4})\s*\n(.{3,})\n+([\s\S]+?)\s+{$this->opt($this->t('addressEnd'))}/", $contactsText, $m)
                || preg_match("/{$this->opt($this->t('Reservation Confirmation'))}:\s*\n(.{5,})\n+([\s\S]+?)\s+{$this->opt($this->t('addressEnd'))}/iu", $contactsText, $m))
            && stripos($textPdf, $m[1]) !== false) {
            $h->hotel()
                ->name(trim($m[1]))
                ->address(preg_replace('/\s+/', ' ', trim($m[2])))
            ;
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Tel:'))}\s*([+)(\d][-.\s\d)(]{5,}[\d)(])$/m", $contactsText, $m)) {
            $h->hotel()->phone($m[1]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Fax:'))}\s*([+)(\d][-.\s\d)(]{5,}[\d)(])$/m", $contactsText, $m)) {
            $h->hotel()->fax($m[1]);
        }

        if (empty($h->getHotelName()) || empty($h->getAddress())) {
            // it-36715554.eml

            $textContacts = preg_match("/\n\n(.+[ ]{2}{$this->opt($this->t('Tel.'))}[\s\S]+?{$this->opt($this->t('Directions And Map'))}[\s\S]*?)(?:\n\n|$)/i", $text, $m) ? $m[1] : '';

            $tableContactsPos = [0];

            if (preg_match("/(.+)[ ]{2}{$this->opt($this->t('Tel.'))}/", $textContacts, $matches)) {
                $tableContactsPos[] = mb_strlen($matches[1]);
            }
            $tableContacts = $this->splitCols($textContacts, $tableContactsPos);

            if (count($tableContacts) === 2) {
                $hotelName = preg_match("/^[ ]*{$this->opt($this->t('We are delighted to confirm the following reservation at'))}[ ]+([^.!]{3,}?)[ ]*[.!]+$/m", $text, $m) ? $m[1] : '';

                if ($hotelName && preg_match('/^\s*(' . $this->spaceExpand($hotelName) . '.*)\s+([\s\S]{3,})\s*$/', $tableContacts[0], $m)) {
                    $h->hotel()
                        ->name(preg_replace('/\s+/', ' ', $m[1]))
                        ->address(preg_replace('/\s+/', ' ', $m[2]))
                    ;
                }

                if (empty($h->getPhone()) && preg_match("/\b{$this->opt($this->t('Tel.'))}[ ]*([+(\d][-. \d)(]{5,}[\d)])$/m", $tableContacts[1], $m)) {
                    $h->hotel()->phone($m[1]);
                }

                if (empty($h->getFax()) && preg_match("/\b{$this->opt($this->t('Fax.'))}[ ]*([+(\d][-. \d)(]{5,}[\d)])$/m", $tableContacts[1], $m)) {
                    $h->hotel()->fax($m[1]);
                }
            }
        }

        if (preg_match("/^[> ]*({$this->opt($this->t('Confirmation Number'))})\s+\b([,A-Z\d or]{5,})$/m", $text, $m)
            || preg_match("/^[> ]*(Confirmation)\s+\b([,A-Z\d or]{5,})\s+Number$/m", $text, $m)
        ) {
            $m[1] = rtrim($m[1], ' :');

            foreach (preg_split('/\s*(?:,+|or)\s*/i', $m[2]) as $confNumber) {
                $h->general()->confirmation($confNumber, $m[1]);
            }
        } elseif (preg_match("/^[> ]*({$this->opt($this->t('Confirmation Number'))})\s+$/m", $text, $m)) {
            $h->general()->noConfirmation();
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Guest Name'))}\s+\b([\s\S]+?)\s+^[ ]*{$this->opt($this->t('Arrival Date'))}/m", $text, $m)) {
            foreach (preg_split('/\s*[,\n&]+\s*/', $m[1]) as $name) {
                $name = preg_replace("/^{$this->opt($this->t('Sharing with'))}\s*/", '', $name);

                if (preg_match('/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/', $name)) {
                    $h->addTraveller($name);
                }
            }
        }

        // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon
        $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon)?';

        $dateCheckIn = preg_match("/^[ ]*{$this->opt($this->t('Arrival Date'))}[ ]+(.{6,})$/m", $text, $m) ? $m[1] : '';
        $h->booked()->checkIn2($dateCheckIn);
        $timeCheckIn = preg_match("/^[ ]*{$this->opt($this->t('Check-in Time'))}[ ]+({$patterns['time']})/m", $text, $m) ? $m[1] : '';

        if ($timeCheckIn && !empty($h->getCheckInDate())) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
        }

        $dateCheckOut = preg_match("/^[ ]*{$this->opt($this->t('Departure Date'))}[ ]+(.{6,})$/m", $text, $m) ? $m[1] : '';
        $h->booked()->checkOut2($dateCheckOut);
        $timeCheckOut = preg_match("/^[ ]*{$this->opt($this->t('Check-out Time'))}[ ]+({$patterns['time']})/m", $text, $m) ? $m[1] : '';

        if ($timeCheckOut && !empty($h->getCheckOutDate())) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $h->getCheckOutDate()));
        }

        $room = $h->addRoom();

        $accommodation = preg_match("/^[ ]*{$this->opt($this->t('roomType'))}[ ]+(.+)$/m", $text, $m) ? $m[1] : '';
        $room->setType($accommodation);

        $guests = preg_match("/^[ ]*{$this->opt($this->t('Number of Guests'))}[ ]+(.+)$/m", $text, $m) ? $m[1] : '';

        if (preg_match("/\b(\d{1,3})[ ]*{$this->opt($this->t('Adult'))}/", $guests, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})[ ]*{$this->opt($this->t('Child'))}/", $guests, $m)) {
            $h->booked()->kids($m[1]);
        }

        $rateText = preg_match("/^[ ]*{$this->opt($this->t('Nightly Rate'))}[ ]+([\s\S]+?)\s+^[ ]*{$this->opt($this->t('rateEnd'))}/m", $text, $m) ? $m[1] : '';
        $rateRange = $this->parseRateRange($rateText);

        if ($rateRange !== null) {
            $room->setRate($rateRange);
        }

        if (empty($room->getRate())) {
            // it-36715554.eml

            if (preg_match("/^[ ]*{$this->opt($this->t('Room Rate'))}[ ]{2,}([A-Z]{3}[ ]*\d[,.\'\d ]* (?i){$this->opt($this->t('per room, per night'))}.*)$/m", $text, $m)) {
                $room->setRate(preg_replace('/\s+/', ' ', $m[1]));
            }
        }

        $terms = preg_match("/^[ ]*{$this->opt($this->t('Terms and Conditions'))}[ ]+([\s\S]+?)\s+^[ ]*{$this->opt($this->t('Check-in Time'))}/m", $text, $m) ? $m[1] : '';

        if (preg_match("/{$this->spaceExpand('Cancellations must be received by')}\s*(?<hour>{$patterns['time']})\s*{$this->spaceExpand(', local time,')}\s*(?<prior>7\s*days?)\s*{$this->spaceExpand('prior to arrival date')}\./i", $terms, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['prior'] . ' -1 day', $m['hour']);
        } elseif (stripos($terms, 'This promotion rate is non-refundable.') !== false) {
            $h->booked()->nonRefundable();
        }

        // Price
        if (preg_match("/" . $this->opt($this->t('Estimated Total')) . "\*?[ ]*([A-Z]{3})[ ]*(\d[\d\.,]*)\s*\n/i", $text, $m)) {
            $h->price()
                ->total($this->normalizeAmount($m[2]))
                ->currency($m[1])
            ;
        }
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Departure Date']) || empty($phrases['Number of Guests'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Departure Date']) !== false
                && $this->strposArray($text, $phrases['Number of Guests']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = strpos($text, $phrase);

            if ($result !== false) {
                return $result;
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

    /**
     * Dependencies `$this->normalizeAmount()`.
     *
     * @return string|null
     */
    private function parseRateRange($string = '')
    {
        if (
            preg_match_all("/\b(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d ]*)[ ]+{$this->opt($this->t('per night'))}$/m", $string, $rateMatches) // March 21 JPY 150,000 per night
        ) {
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return (float) $this->normalizeAmount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                }
            }
        }

        return null;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function spaceExpand($text)
    {
        return preg_replace('/\s+/', '\s+', $text);
    }

    private function rowColsPos($row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
