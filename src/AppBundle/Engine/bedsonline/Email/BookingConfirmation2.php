<?php

namespace AwardWallet\Engine\bedsonline\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "bedsonline/it-142189356.eml, bedsonline/it-311176485.eml, bedsonline/it-595311836.eml, bedsonline/it-632812684.eml, bedsonline/it-88132004.eml";

    public $dateFormatMDY;
    public $lang = "en";

    public static $dictionary = [
        "en" => [
            'Your reference number:'             => ['Your reference number:', 'Your reference number:'],
            'Thanks for booking with Bedsonline' => ['Thanks for booking with Bedsonline', 'Thanks for booking with bedsonline.'],
        ],
    ];
    private $detectFrom = ["bedsonline.com"];
    private $detectSubject = [
        "en" => "Booking confirmation Bedsonline", // Booking confirmation Bedsonline 75-1372588
    ];

    private $detectCompany = ['with Bedsonline'];
    private $detectBody = [
        "en" => ["Thanks for booking with Bedsonline", "Thanks for booking with bedsonline."],
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"])) {
            return false;
        }
//        if ($this->striposAll($headers["from"], $this->detectFrom)===false)
//            return false;

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        if (empty($body)) {
            $body = strip_tags($parser->getHTMLBody());
        }

        if ($this->striposAll($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getPlainBody();

        if (empty($body)) {
            $body = $parser->getHTMLBody();
            $body = strip_tags($body);
        }

        $body = str_replace(chr(194) . chr(160), ' ', $body);

        $this->detectDateFormat($body);

        $this->parsePlain($email, $body);

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
        return count(self::$dictionary);
    }

    private function parsePlain(Email $email, string $text): void
    {
        $text = strip_tags($text);
        $text = preg_replace('/^>+ /m', '', $text);

        // Agency Reference Nº: - is NOT confirmation number, it is company Id
        $segConf = $this->re("/{$this->opt($this->t('Your reference number:'))}\s*([-\d]{3,})\s*$/mu", $text);
        $traveller = $this->re("/{$this->opt($this->t('Lead passenger:'))}\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*{$this->opt($this->t('SERVICES:'))}/u", $text);

        $segmentText = $this->re("/{$this->opt($this->t('SERVICES:'))}(.+){$this->opt($this->t('Thanks for booking with Bedsonline'))}/s", $text);
        $segments = $this->split("/(SERVICE\s*[#]\d+[\s\-]+.+)/", $segmentText);

        $detectedjunkSegments = 0;

        foreach ($segments as $segment) {
            if (preg_match("/Accommodation/", $segment)) {
                $this->parseHotel($email, $segment);
            }

            if (preg_match("/^.* Car Rental\s*\n/", $segment)) {
                $this->parseRental($email, $segment);
            }

            if (preg_match("/^.* (?:Transfer|Ticket)\s*\n/", $segment)) {
                $detectedjunkSegments++;

                continue;
            }
        }

        if (count($segments) > 0 && count($segments) === $detectedjunkSegments) {
            $email->setIsJunk(true, 'hotel or car rental not found');

            return;
        }

        $its = $email->getItineraries();

        foreach ($its as $it) {
            if (!empty($segConf) && count($its) === 1) {
                $it->general()->confirmation($segConf);
            } else {
                $it->general()->noConfirmation();
            }

            $it->general()->traveller($traveller);
        }

        $otaConfirmation = $this->re("/{$this->opt($this->t('Booking reference:'))}\s*([-\d]{3,})\s*$/mu", $text);

        if (!empty($otaConfirmation)) {
            $email->ota()->confirmation($otaConfirmation);
        }
    }

    private function parseHotel(Email $email, $text): void
    {
        $h = $email->add()->hotel();

        $h->general()
            ->cancellation(trim(preg_replace("/\n\s*-\s*/", "; ", $this->re("/{$this->opt($this->t('Cancellation Charges:'))}\s*(.{2,})/s", $text)), '- '));

        $h->hotel()
            ->name($this->re("/{$this->opt($this->t('Accommodation:'))}\s*(.{2,}?)\s+Date/", $text))
            ->noAddress();

        $datesInfo = $this->re("/{$this->opt($this->t('Dates:'))}\s*(.+)/", $text);

        if (preg_match("/^([\d\/]{6,})[\s\-]+([\d\/]{6,})/", $datesInfo, $m)) {
            $d1Us = strtotime($m[1]);
            $d2Us = strtotime($m[2]);
            $d1Eu = strtotime(str_replace('/', '.', $m[1]));
            $d2Eu = strtotime(str_replace('/', '.', $m[2]));

            if ((empty($d1Us) || empty($d2Us)) && (!empty($d1Eu) && !empty($d2Eu))) {
                $h->booked()
                    ->checkIn($d1Eu)
                    ->checkOut($d2Eu);
            } else {
                $h->booked()
                    ->checkIn($d1Us)
                    ->checkOut($d2Us);
            }
        } elseif (preg_match("/\b(\d{4}-\d{1,2}-\d{1,2})[\-\s]+\b(\d{4}-\d{1,2}-\d{1,2})/us", $datesInfo, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1]))
                ->checkOut(strtotime($m[2]));
        } elseif (preg_match("/(\d+\/\w+\/\d{4})[\-\s]+(\d+\/\w+\/\d{4})/us", $datesInfo, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]));
        }

        if (preg_match("/{$this->opt($this->t('Occupancy:'))}\s*[\-]\s*{$this->opt($this->t('Room'))}\s*(?<rooms>\d+)\:\s*(?<type>.+)\s+\-\s+(?<guests>\d+)\s*{$this->opt($this->t('Adults'))}(?:\s*\+\s*(?<kids>\d+)\s*{$this->opt($this->t('Children'))})?\s*{$this->opt($this->t('Cancellation Charges:'))}/", $text, $m)
            || preg_match("/{$this->opt($this->t('Occupancy:'))}\s*[\-]\s*(?<rooms>\d+)x\s(?<type>.+)\s+\-\s+(?<guests>\d+)\s*{$this->opt($this->t('Adults'))}(?:\s*[\+\s]\s*(?<kids>\d+)\s*{$this->opt($this->t('Children'))})?\s*{$this->opt($this->t('Cancellation Charges:'))}/us", $text, $m)
            || preg_match("/{$this->opt($this->t('Occupancy:'))}\s*[\-]\s*(?<rooms>\d+)x\s(?<type>.+)\s+(?<guests>\d+)\s*{$this->opt($this->t('Adults'))}(?:\s*[\+\s]\s*(?<kids>\d+)\s*{$this->opt($this->t('Children'))})?\s*{$this->opt($this->t('Cancellation Charges:'))}/us", $text, $m)
        ) {
            $h->booked()
                ->guests($m['guests'])
                ->kids($m['kids'] ?? null, true, true)
                ->rooms($m['rooms']);

            $room = $h->addRoom();
            $room->setDescription($m['type']);
        }
    }

    private function parseRental(Email $email, $text): void
    {
        $r = $email->add()->rental();

        $r->general()
            ->cancellation(trim(preg_replace("/\n\s*-\s*/", "; ", $this->re("/{$this->opt($this->t('Cancellation Charges:'))}\s*(.{2,})/s", $text)), '- '));

        $r->car()
            ->model($this->re("/{$this->opt($this->t('Vehicle:'))} *(.+)/", $text));

        $r->extra()
            ->company($this->re("/{$this->opt($this->t('Provider:'))} *(.+)/", $text));

        $datePu = $this->re("/{$this->opt($this->t('Pick-up:'))}\s+{$this->opt($this->t('Date:'))}\s*(.+)/", $text);
        $dateDo = $this->re("/{$this->opt($this->t('Drop-off:'))}\s+{$this->opt($this->t('Date:'))}\s*(.+)/", $text);

        if (preg_match("/^([\d\/]+)$/", $datePu, $m) && preg_match("/^([\d\/]+)$/", $dateDo, $m)) {
            $d1Us = strtotime($datePu);
            $d2Us = strtotime($dateDo);
            $d1Eu = strtotime(str_replace('/', '.', $datePu));
            $d2Eu = strtotime(str_replace('/', '.', $dateDo));

            if ((empty($d1Us) || empty($d2Us)) && (!empty($d1Eu) && !empty($d2Eu))) {
                $r->pickup()
                    ->date($d1Eu);
                $r->dropoff()
                    ->date($d2Eu);
            } else {
                $r->pickup()
                    ->date($d1Us);
                $r->dropoff()
                    ->date($d2Us);
            }
        } elseif (preg_match("/^(\d+\/\w+\/\d{4})$/", $datePu, $m) && preg_match("/^(\d+\/\w+\/\d{4})$/", $dateDo, $m)) {
            $r->pickup()
                ->date($this->normalizeDate($datePu));
            $r->dropoff()
                ->date($this->normalizeDate($dateDo));
        }
        $r->pickup()
            ->location($this->re("/{$this->opt($this->t('Pick-up:'))}\s+{$this->opt($this->t('Date:'))}\s*.+\s+{$this->opt($this->t('Office:'))} *(.+)/", $text));
        $r->dropoff()
            ->location($this->re("/{$this->opt($this->t('Drop-off:'))}\s+{$this->opt($this->t('Date:'))}\s*.+\s+{$this->opt($this->t('Office:'))} *(.+)/", $text));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function detectDateFormat($text)
    {
        if (preg_match_all("#\s(\d{2})/(\d{2})/20\d{2}(?:\s|$)#", $text, $m)) {
            foreach ($m[1] as $key => $v) {
                if ($m[1][$key] > 31 || $m[2][$key] > 31) {
                    continue;
                }

                if ($m[1][$key] > 12 && $m[1][$key] < 32 && $m[2][$key] < 13) {
                    if ($this->dateFormatMDY === true) {
                        $this->dateFormatMDY = null;

                        return null;
                    }
                    $this->dateFormatMDY = false;
                }

                if ($m[2][$key] > 12 && $m[2][$key] < 32 && $m[1][$key] < 13) {
                    if ($this->dateFormatMDY === false) {
                        $this->dateFormatMDY = null;

                        return null;
                    }
                    $this->dateFormatMDY = true;
                }
            }
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            "#^\s*(\d{2})\/([^\W\d]+)\/(\d{4})\s*$#", // 14/Oct/2019
            "#^\s*(\d{2})\/(\d{2})\/(\d{4})\s*$#", // 04/05/2019
        ];

        if ($this->dateFormatMDY === false) {
            $out = [
                '$1 $2 $3',
                '$1.$2.$3',
            ];
        } else {
            $out = [
                '$1 $2 $3',
                '$2.$1.$3',
            ];
        }

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text): array
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
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

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
