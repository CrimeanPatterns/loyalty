<?php

namespace AwardWallet\Engine\europcar\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "europcar/it-33741046.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'langDetectors' => ['RESERVATION CONFIRMATION'],
            'renterName'    => ["Renter's Name", 'Renter Name', 'Renter name'],
            'reservationID' => 'Reservation I.D.',
            'vehicleGroup'  => 'Vehicle Group',
            'pickUp'        => ['Pick Up', 'Pick up'],
            'dropOff'       => ['Drop Off', 'Drop off'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@europcar.co.za') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf || !is_string($textPdf)) {
                continue;
            }

            if (!$this->detectProvider($parser, $textPdf)) {
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

            if (!$textPdf || !is_string($textPdf)) {
                continue;
            }

            $this->assignLang($textPdf);

            if (!empty($this->lang)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('ReservationConfirmationPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, string $text)
    {
        $rentals = $this->splitText($text, "/^[ ]*{$this->opt($this->t('RESERVATION CONFIRMATION'))}(?:[ ]{2}|$)/m");

        foreach ($rentals as $rText) {
            $r = $email->add()->rental();

            if (preg_match("/^[> ]*{$this->opt($this->t('renterName'))}[ ]*:+[ ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/mu", $rText, $m)) {
                $r->general()->traveller($m[1]);
            }

            if (preg_match("/^[> ]*({$this->opt($this->t('reservationID'))})[ ]*:+\s*([A-Z\d]{5,}(?:[ \-]+\d)?)$/m", $rText, $m)) {
                $r->general()->confirmation(str_replace(' ', '', $m[2]), $m[1]);
            }

            if (preg_match("/^[> ]*{$this->opt($this->t('vehicleGroup'))}[ ]*:+[ ]*([^:]+?)(?:\n{2}|\s+^[> ]*{$this->opt($this->t('pickUp'))})/m", $rText, $m)) {
                $r->car()->type(preg_replace('/\s+/', ' ', $m[1]));
            }

            /*
                CAPE TOWN INTERNATIONAL AIRPORT 20/2/19 12.30
                CAPE TOWN AIRPORT CAPE TOWN
                CAPE TOWN 7525
                021 935-8600
            */
            $pattern = '/'
                . '^[> ]*(?<location>.{3,}?)[ ]+(?<date>\d{1,2}\/\d{1,2}\/\d{2,4})[ ]+(?<time>\d{1,2}[.:]\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)$'
                . '\s+^[> ]*(?<address>[\s\S]{3,}?)$'
                . '\s+^[> ]*(?<phone>[+)(\d][-.\s\d)(]{5,}[\d)(])$'
                . '/m';

            if (preg_match("/^[> ]*{$this->opt($this->t('pickUp'))}[ ]*:+[ ]*([\s\S]+?)(?:\n{2}|\s+^[> ]*{$this->opt($this->t('dropOff'))})/m", $rText, $matches)
                && preg_match($pattern, $matches[1], $m)
            ) {
                $m['date'] = $this->normalizeDate($m['date']);
                $r->pickup()
                    ->location($m['location'] . ', ' . preg_replace('/\s+/', ' ', preg_replace('/\n+/', ', ', $m['address'])))
                    ->date2($m['date'] . ' ' . $m['time'])
                    ->phone($m['phone'])
                ;
            }

            if (preg_match("/\n[> ]*{$this->opt($this->t('dropOff'))}[ ]*:+[ ]*([\s\S]+?)(?:\n{2}|$)/", $rText, $matches)
                && preg_match($pattern, $matches[1], $m)
            ) {
                $m['date'] = $this->normalizeDate($m['date']);
                $r->dropoff()
                    ->location($m['location'] . ', ' . preg_replace('/\s+/', ' ', preg_replace('/\n+/', ', ', $m['address'])))
                    ->date2($m['date'] . ' ' . $m['time'])
                    ->phone($m['phone'])
                ;
            }
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string|bool
     */
    private function normalizeDate(string $string)
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/', $string, $matches)) {
            // 19/2/19
            $day = $matches[1];
            $month = $matches[2];
            $year = '20' . $matches[3];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $matches)) {
            // 19/2/2019
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day) && isset($month) && isset($year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    private function detectProvider(\PlancakeEmailParser $parser, string $text): bool
    {
        return self::detectEmailFromProvider($parser->getHeader('from')) === true
            || stripos($text, '@europcar.') !== false;
    }

    private function assignLang(string $text = ''): bool
    {
        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['langDetectors']) || empty($phrases['dropOff'])) {
                continue;
            }

            if (empty($text)
                && $this->http->XPath->query("//node()[{$this->contains($phrases['langDetectors'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['dropOff'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            } elseif (!empty($text)
                && preg_match("/{$this->opt($phrases['langDetectors'])}/", $text) > 0
                && preg_match("/{$this->opt($phrases['dropOff'])}/", $text) > 0
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

    private function splitText($textSource = '', string $pattern, $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
