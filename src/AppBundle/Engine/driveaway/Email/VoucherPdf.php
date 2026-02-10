<?php

namespace AwardWallet\Engine\driveaway\Email;

use AwardWallet\Schema\Parser\Email\Email;

class VoucherPdf extends \TAccountChecker
{
    public $mailFiles = "driveaway/it-45464252.eml";

    public $lang = '';
    public $conf;

    public static $dictionary = [
        'en' => [
            'Confirmation No' => ['Confirmation No', 'Confirmation No.'],
            'Pick-up'         => ['Pick-up'],
            'Passengers'      => ['Passengers'],
            'Voucher'         => ['Voucher', 'Booking Summary'],
            'Local Costs'     => ['Local Costs', 'In Advance'],
            'Client'          => ['Client', 'Main Driver'],
        ],
    ];

    private $subjects = [
        'en' => ['- Voucher -'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Drive Away Motorhomes') !== false
            || stripos($from, '@driveawayres.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'DriveAway') === false) {
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
        $detectProvider = self::detectEmailFromProvider($parser->getHeader('from')) === true;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false
                && stripos($textPdf, 'DriveAway terms and conditions') === false
                && stripos($textPdf, '@driveawayres.com') === false
                && stripos($textPdf, 'www.driveaway.com') === false
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
        if (stripos($parser->getSubject(), 'Booking Summary') !== false) {
            $this->conf = $this->re("/{$this->opt($this->t('Booking Summary'))}[\s\-]+([A-Z\d]{5,})/u", $parser->getSubject());
        }
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parseCar($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('VoucherPdf' . ucfirst($this->lang));

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseCar(Email $email, $text)
    {
        $car = $email->add()->rental();

        $date = preg_match("/^[ ]*{$this->opt($this->t('Voucher'))}$\s+^[ ]*{$this->opt($this->t('Date'))}[ ]*:+[ ]*(.{6,})/m", $text, $m) ? $m[1] : null;
        $car->general()->date2($date);

        $client = preg_match("/^[ ]*{$this->opt($this->t('Client'))}[ ]*:+[ ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/mu", $text, $m) ? $m[1] : null;
        $car->general()->traveller(preg_replace("/^(?:Mrs|Mr|Ms)/", "", $client));

        $company = preg_match("/^[ ]*{$this->opt($this->t('Supplier'))}[ ]*:+[ ]*(.{3,})/m", $text, $m) ? $m[1] : null;
        $car->extra()->company($company);

        if (preg_match("/^[ ]*({$this->opt($this->t('Confirmation No'))})[ ]*:+[ ]*([A-Z\d\-]{5,})$/m", $text, $m)) {
            $car->general()->confirmation($m[2], $m[1]);
        } elseif (!empty($this->conf)) {
            $car->general()
                ->confirmation($this->conf);
        }

        $carType = preg_match("/^[ ]*{$this->opt($this->t('Vehicle'))}[ ]*:+[ ]*(.{2,})/m", $text, $m) ? $m[1] : null;
        $car->car()->type($carType);

        if (preg_match("/^[ ]*({$this->opt($this->t('Local Costs'))})[ ]*:+[ ]*(?<currency>[A-Z]{3}) ?(?<amount>\d[,.\'\d ]*)$/m", $text, $m)) {
            $car->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));
        }

        $address = $phone = null;

        if (preg_match("/^[ ]*{$this->opt($this->t('Address'))}[ ]*:+[ ]*([^:]{3,})$\s+^[ ]*{$this->opt($this->t('Phone'))}[ ]*:+[ ]*(.+)/m", $text, $m)
         || preg_match("/^[ ]*{$this->opt($this->t('Address'))}[ ]*:+[ ]*([^:]{3,})$\s+^[ ]*{$this->opt($this->t('Google map'))}[ ]*:/m", $text, $m)) {
            $address = preg_replace('/[ ]*[,]*\n+[ ]*/', ', ', $m[1]);
            $phones = [];

            foreach (preg_split('/\s*[,\/]\s*/', $m[2]) as $p) { // +61 (03) 6274-5500 / 1800-777-779 (toll-free)
                if (preg_match('/^([+(\d][-. \d)(]{5,}[\d)])(?:\s*\([ ]*([^)(]*[[:alpha:]][^)(]*?)[ ]*\))?$/', $p, $matches)) {
                    $phones[empty($matches[2]) ? null : strtolower($matches[2])] = $matches[1];
                }
            }
        }
        $phone = empty($phones['toll-free']) ? array_shift($phones) : $phones['toll-free'];

        if (preg_match("/^[ ]*{$this->opt($this->t('Pick-up'))}[ ]*:+[ ]*(.{6,})$\s+^[ ]*{$this->opt($this->t('Service Hours'))}[ ]*:+[ ]*(.{2,})$/m", $text, $m)) {
            if (preg_match("/\d+\.\d+\.\d{4}$/", $m[1])) {
                $time = $this->re("/^(\d+\:\d{2}) *(?![ \-])/", $m[2]);

                if (!empty($time)) {
                    $m[1] = $m[1] . ', ' . $time;
                }
            }

            $car->pickup()
                ->date(strtotime($m[1]))
                ->openingHours($m[2])
                ->location($address);

            if (!empty($phone)) {
                $car->pickup()
                    ->phone($phone);
            }
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Drop-off'))}[ ]*:+[ ]*(.{6,})$\s+^[ ]*{$this->opt($this->t('Service Hours'))}[ ]*:+[ ]*(.{2,})$/m", $text, $m)) {
            if (preg_match("/\d+\.\d+\.\d{4}$/", $m[1])) {
                $time = $this->re("/^(\d+\:\d{2}) *(?![ \-])/", $m[2]);

                if (!empty($time)) {
                    $m[1] = $m[1] . ', ' . $time;
                }
            }

            $car->dropoff()
                ->date(strtotime($m[1]))
                ->openingHours($m[2])
                ->location($address);

            if (!empty($phone)) {
                $car->dropoff()
                    ->phone($phone);
            }
        }
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Passengers']) || empty($phrases['Pick-up'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Passengers']) !== false
                && $this->strposArray($text, $phrases['Pick-up']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
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
}
