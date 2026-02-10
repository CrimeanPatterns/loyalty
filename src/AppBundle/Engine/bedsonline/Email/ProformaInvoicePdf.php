<?php

namespace AwardWallet\Engine\bedsonline\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ProformaInvoicePdf extends \TAccountChecker
{
    public $mailFiles = "bedsonline/it-168662378.eml, bedsonline/it-37660533.eml, bedsonline/it-384098005-es.eml, bedsonline/it-38686760.eml, bedsonline/it-39215309.eml, bedsonline/it-44043445.eml, bedsonline/it-719688438.eml";

    public $dateFormatMDY;
    public $lang = "en";
    public static $dictionary = [
        "es" => [
            "Proforma invoice"    => ["Recibo"],
            "Booking reference:"  => ["Localizador:", "Localizador :"],
            "totalFinalPrice"     => ["Total pagado:", "Total pagado :"], // the last price in Services block
            // "statusVariants" => "",
            "Services" => "Servicios",
            // "segmentsEnd" => "",
            "ACCOMMODATION" => "ALOJAMIENTO",
            // "TICKETS AND EXCURSIONS" => "",
            // "transfer" => "",
            "from"                      => ["Entrada:", "Entrada :"],
            "to"                        => ["Salida:", "Salida :"],
            "Guest name"                => "Nombre de pasajero",
            "Booking confirmation date" => "Fecha confirmación reserva",
            "Room Type"                 => "Tipo de habitación",
            "Remarks"                   => "Observaciones",
            "Check-in hour"             => "Hora de entrada",
            "Adult"                     => ["Adulto", "Adultos"],
            "Child"                     => "Niños",
        ],
        "en" => [
            "Proforma invoice"    => ["Proforma invoice", "Receipt"],
            "Booking reference:"  => ["Booking reference:", "Booking reference :"],
            "totalFinalPrice"     => ["Total Final Price", "Total amount paid :", "Recommended Final Price :"], // the last price in Services block
            "statusVariants"      => ['CANCELLED', 'CANCELED'],
            // "Services" => "",
            "segmentsEnd" => ["Agency commission", "Payments and Refunds", "Agency discounts"],
            // "ACCOMMODATION" => "",
            // "TICKETS AND EXCURSIONS" => "",
            "transfer"   => ["ARRIVAL TRANSFER", "DEPARTURE TRANSFER"],
            "from"       => ["From:", "From :"],
            "to"         => ["To:", "To :", "Outbound:"],
            "Guest name" => ["Guest name", "Passenger name"],
            // "Booking confirmation date" => "",
            // "Room Type" => "",
            // "Remarks" => "",
            // "Check-in hour" => "",
            // "Adult" => "",
            // "Child" => "",
        ],
    ];

    private $detectFrom = ["bedsonline.com"];
    private $detectSubject = [
        "es" => "Recibo", // Bono - 207-9184852 - Recibo
        "en" => "Proforma", // Voucher - 227-605688 - Proforma
    ];

    private $detectCompany = ['Bedsonline', 'HOTELBEDS', ' TRAVELSTORE', 'LOS GATOS TRAVEL'];
    private $detectBody = [
        "es" => ["Recibo"],
        "en" => ["Proforma invoice", "Receipt"],
    ];

    private $pdfPattern = ".+\.pdf";

    private $patterns = [
        'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'], $headers["subject"])) {
            return false;
        }

        if ($this->striposAll($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if ((
                    $this->striposAll($text, $this->detectCompany) === true
                    || !empty($this->http->FindSingleNode("(//text()[contains(., 'Bedsonline')])[1]"))
                    || stripos($parser->getCleanFrom(), '@bedsonline.com') !== false
                    //     Receipt                          05/05/2022
                    || preg_match("/^\s*(?:" . $this->opt($this->t("Proforma invoice")) . "\s*\n*(?:\s*PENDING PAYMENT\s*)?\s+\d{1,2}\\/\d{1,2}\\/\d{4}|\d{1,2}\\/\d{1,2}\\/\d{4}\s+" . $this->opt($this->t("Proforma invoice")) . ")\s*\n/", $text)
                ) && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            // remove watermarks
            $text = preg_replace(['/^[ ]*(PENDING|PENDIENTE)\n/m', '/\n[ ]*(PAYMENT|DE PAGO)$/m'], '', $text);

            if ((
                $this->striposAll($text, $this->detectCompany) === true
                || !empty($this->http->FindSingleNode("(//text()[contains(., 'Bedsonline')])[1]"))
                || stripos($parser->getCleanFrom(), '@bedsonline.com') !== false
                //     Receipt                          05/05/2022
                || preg_match("/^\s*(?:" . $this->opt($this->t("Proforma invoice")) . "\s*\n*(?:\s*PENDING PAYMENT\s*)?\s+\d{1,2}\\/\d{1,2}\\/\d{4}|\d{1,2}\\/\d{1,2}\\/\d{4}\s+" . $this->opt($this->t("Proforma invoice")) . ")\s*\n/", $text)
                ) && $this->assignLang($text)
            ) {
                $this->detectDateFormat($text);

                $this->parsePdf($email, $text);
            }
        }

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

    private function parsePdf(Email $email, string $text): void
    {
        // Travel Agency
        if (preg_match("/({$this->opt($this->t("Booking reference:"))})\s*([\d\-]{6,})\s+/", $text, $m)) {
            $email->ota()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $segmentsText = $this->re("/\n\s*{$this->opt($this->t("Services"))}\s*\n([\s\S]+?)\n\s*{$this->opt($this->t("segmentsEnd"))}\s*\n/", $text)
            ?? $this->re("/\n *{$this->opt($this->t("Services"))} *\n([\s\S]+?\n *{$this->opt($this->t("totalFinalPrice"))}.*)(?:\n|$)/", $text)
        ;

        // correcting hotel names
        $segmentsText = preg_replace("/(\S.+)\n{1,2}([ ]*{$this->opt($this->t("ACCOMMODATION"))})\n+([ ]*\S.{2,})\n+([ ]*{$this->opt($this->t("from"))})/", "$2\n$1\n$3\n$4", $segmentsText);

        $segments = $this->split("/(?:^|\n)\s*((?:{$this->opt($this->t("ACCOMMODATION"))}|{$this->opt($this->t("TICKETS AND EXCURSIONS"))}|{$this->opt($this->t("transfer"))})\s*\n)/", $segmentsText);

        foreach ($segments as $stext) {
            $this->logger->debug($stext);

            if (preg_match("/^{$this->opt($this->t("ACCOMMODATION"))}/", $stext)) {
                $this->parseHotel($email, $stext);
            } elseif (preg_match("/^{$this->opt($this->t("transfer"))}/", $stext)) {
                $this->parseTransfer($email, $stext);
            } elseif (preg_match("/^\s*{$this->opt($this->t("CAR RENTAL"))}/u", $stext)) {
                $this->parseRental($email, $stext);
            } elseif (preg_match("/^{$this->opt($this->t("TICKETS AND EXCURSIONS"))}/", $stext)) {
                continue;
            }
        }

        $guest = $this->re("/\n\s*{$this->opt($this->t("Guest name"))}[ ]*[:]+[ ]*([[:alpha:]][-.\'[:alpha:] ]*?[[:alpha:]])(?:[ ]{2,}|\s*\n)/u", $text);
        $date = $this->normalizeDate($this->re("/(?:\n[ ]*|[ ]{2}){$this->opt($this->t("Booking confirmation date"))}[ ]*[:]+[ ]*(.+?)(?:[ ]{2}|\n)/", $text));

        foreach ($email->getItineraries() as $i => $it) {
            $email->getItineraries()[$i]->general()
                ->traveller($guest);

            if (!empty($date)) {
                $email->getItineraries()[$i]->general()
                    ->date($date);
            }
        }

        if (preg_match("/\n\s*(?:{$this->opt($this->t("totalFinalPrice"))}|Total net amount to be paid by the agency to Bedsonline)[ ]*:?[ ]*(.+)/", $text, $total)) {
            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total[1], $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total[1], $m)
            ) {
                $email->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']));

                if (preg_match('/^[ ]*(?i)Agency discounts$[\s\S]+?^[ ]*Total discounts(?-i)[ ]*:[- ]*' . preg_quote($m['curr'], '/') . '\s*(?<amount>\d[,.\'\d]*)\s*$/m', $text, $matches)
                    || preg_match('/^[ ]*(?i)Agency discounts$[\s\S]+?^[ ]*Total discounts(?-i)[ ]*:[- ]*(?<amount>\d[,.\'\d]*)\s*' . preg_quote($m['curr'], '/') . '\s*$/m', $text, $matches)
                ) {
                    $email->price()->discount($this->amount($matches['amount']));
                }
            }
        }
    }

    private function parseHotel(Email $email, string $stext): void
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation();

        if (preg_match($pattern = "/^((?:.*\n){0,8}.*?)[ ]+(?<status>{$this->opt($this->t('statusVariants'))})(\n)/", $stext, $m)) {
            $h->general()->status($m['status']);
            $stext = preg_replace($pattern, '$1$3', $stext);
        }

        if (preg_match("/^CANCELL?ED$/i", $h->getStatus(), $m)) {
            // it-38686760.eml
            $h->general()->cancelled();
        }

        // Hotel
        $hotelName = $this->re("/^\s*{$this->opt($this->t('ACCOMMODATION'))}\s+(.{2,}?)(?:[ ]{3}|\n)/", $stext);
        $addressText = $this->re("/^\s*{$this->opt($this->t('ACCOMMODATION'))}\s+.+\n+[ ]*([\s\S]+?)[ ]*\n+[ ]*{$this->opt($this->t('from'))}/", $stext);
        $addressText = trim(preg_replace(["/^[ ]*(?:{$this->opt($this->t('Room Type'))}|PAYMENT)[ ]*$/m", "/(?:^[ ]*|[ ]{2})Total[ ]*:.*/"], '', $addressText));
        $h->hotel()->name($hotelName)->address($addressText);

        // Booked
        $fromDate = $this->re("/\n\s*{$this->opt($this->t('from'))}[ ]*(.{6,}?)[ ]*\([ ]*[-[:alpha:]]+[ ]*\)[ ]*-[ ]*{$this->opt($this->t('to'))}[ ]*.{6,}[ ]*\([ ]*[-[:alpha:]]+[ ]*\)/u", $stext);
        $toDate = $this->re("/\n\s*{$this->opt($this->t('from'))}[ ]*.{6,}[ ]*\([ ]*[-[:alpha:]]+[ ]*\)[ ]*-[ ]*{$this->opt($this->t('to'))}[ ]*(.{6,})[ ]*\([ ]*[-[:alpha:]]+[ ]*\)/u", $stext);

        if ($this->dateFormatMDY === null) {
            $this->detectDateFormatByDates($fromDate, $toDate);
        }

        $checkInDate = $this->normalizeDate($fromDate);
        $checkOutDate = $this->normalizeDate($toDate);

        if ($checkInDate && preg_match("/\n[ ]*{$this->opt($this->t('Remarks'))}\n[\s\S]*?{$this->opt($this->t('Check-in hour'), true)}\s*({$this->patterns['time']})/", $stext, $m)) {
            $checkInDate = strtotime($m[1], $checkInDate);
        }

        $h->booked()->checkIn($checkInDate)->checkOut($checkOutDate);

        // Rooms
        $roomsText = $this->re("/\n([ ]*{$this->opt($this->t('Room Type'))}(?:[ ]{2,}|\n)[\s\S]+?)\n+\s*Total[ ]*:[ ]*.+/", $stext);

        if (preg_match_all("/^[ ]*(.+?) x (\d+)[ ]{2,}.+?[ ]{2,}(\d {$this->opt($this->t('Adult'))}.+?)[ ]{2,}/im", $roomsText, $roomMatches)) {
            $guests = $kids = $rooms = 0;

            foreach ($roomMatches[0] as $key => $v) {
                $rooms += $roomMatches[2][$key];

                for ($i = 1; $i <= $roomMatches[2][$key]; $i++) {
                    $h->addRoom()->setType($roomMatches[1][$key]);
                }

                if (preg_match("/\b(\d+)[ ]*{$this->opt($this->t('Adult'))}/", $roomMatches[3][$key], $mat)) {
                    $guests += $mat[1];
                }

                if (preg_match("/\b(\d+)[ ]*{$this->opt($this->t('Child'))}/", $roomMatches[3][$key], $mat)) {
                    $kids += $mat[1];
                }
            }
            $h->booked()
                ->guests((!empty($guests)) ? $guests : null)
                ->kids((!empty($kids) || (empty($kids) && !empty($guests))) ? $kids : null)
                ->rooms((!empty($rooms)) ? $rooms : null)
            ;
        } elseif (preg_match_all("/{$this->opt($this->t('Adult'))}/", $roomsText, $adultMatches) && count($adultMatches) === 1) {
            if (preg_match("#^[ ]{0,15}(.+?) x (\d+)(?:[ ]{2,}|$)#m", $roomsText, $m)) {
                for ($i = 1; $i <= $m[2]; $i++) {
                    $h->addRoom()
                        ->setType($m[1]);
                }
                $h->booked()
                    ->rooms($m[2]);
            }

            if (preg_match("/\b(\d+)[ ]*{$this->opt($this->t('Adult'))}/", $roomsText, $m)) {
                $h->booked()
                    ->guests($m[1]);
            }

            if (preg_match("/\b(\d+)[ ]*{$this->opt($this->t('Child'))}/", $roomsText, $m)) {
                $h->booked()
                    ->kids($m[1]);
            }
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('from'))}[\s\S]+\n[ ]*Total[ ]*:[ ]*(.+)/", $stext, $total)) {
            if (preg_match('/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[,.\'\d]*)\s*$/', $total[1], $m)
                || preg_match('/^\s*(?<amount>\d[,.\'\d]*)\s*(?<curr>[^\d\s]{1,5})\s*$/', $total[1], $m)
            ) {
                $h->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']));

                if (preg_match('/^[ ]*Total Discounts[ ]{2,}' . preg_quote($m['curr'], '/') . '\s*(?<amount>\d[,.\'\d]*)\s*$/m', $stext, $matches)
                    || preg_match('/^[ ]*Total Discounts[ ]{2,}(?<amount>\d[,.\'\d]*)\s*' . preg_quote($m['curr'], '/') . '\s*$/m', $stext, $matches)
                ) {
                    $h->price()->discount($this->amount($matches['amount']));
                }
            }
        }
    }

    private function parseTransfer(Email $email, string $stext): void
    {
        $t = $email->add()->transfer();

        $t->general()->noConfirmation();

        if (preg_match($pattern = "/^((?:.*\n){0,8}.*?)[ ]+(?<status>{$this->opt($this->t('statusVariants'))})(\n)/", $stext, $m)) {
            $t->general()->status($m['status']);
            $stext = preg_replace($pattern, '$1$3', $stext);
        }

        if (preg_match("/^CANCELL?ED$/i", $t->getStatus(), $m)) {
            // it-39215309.eml
            $t->general()->cancelled();
        }

        $s = $t->addSegment();

        $datePickup = $this->normalizeDate($this->re("/\n[ ]*(?:Pick-up date|Service date)[ ]*:[ ]*(.{6,}?)(?:[ ]{2}|\n)/", $stext));
        $s->departure()->date($datePickup);
        $s->arrival()->noDate();

        $fromTo = $this->re("/(.*\bFrom[ ]*:.+[\s\S]*?)\n\n/", $stext);

        if (empty($fromTo)) {
            $fromTo = $this->re("/(.*\bFrom[ ]*:.+[\s\S]*?)$/", $stext);
        }
        $table = $this->splitCols($fromTo);

        if (preg_match("/\bFrom[ ]*:[ ]*(.{3,}?)[ ]*,[ ]*To[ ]*:[ ]*(.{3,}?)(?:[ ]{2}|\n|$)/", preg_replace('/\s+/', ' ', $table[0]), $m)) {
            // Private - Premium (Car) - From : Lima, Jorge Chavez Int. Airport , To : Costa del Sol Wyndham Lima City
            $m[2] = preg_replace("/Total\s*\:.*/", "", $m[2]);
            $s->departure()->name($m[1]);
            $s->arrival()->name($m[2]);
        }

        if (preg_match('/\n\s*Total[ ]*:[ ]*(.+)/', $stext, $total)) {
            if (preg_match('/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[,.\'\d]*)\s*$/', $total[1], $m)
                || preg_match('/^\s*(?<amount>\d[,.\'\d]*)\s*(?<curr>[^\d\s]{1,5})\s*$/', $total[1], $m)
            ) {
                $t->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']));

                if (preg_match('/^[ ]*Total Discounts[ ]{2,}' . preg_quote($m['curr'], '/') . '\s*(?<amount>\d[,.\'\d]*)\s*$/m', $stext, $matches)
                    || preg_match('/^[ ]*Total Discounts[ ]{2,}(?<amount>\d[,.\'\d]*)\s*' . preg_quote($m['curr'], '/') . '\s*$/m', $stext, $matches)
                ) {
                    $t->price()->discount($this->amount($matches['amount']));
                }
            }
        }
    }

    private function parseRental(Email $email, string $stext): void
    {
        $this->logger->error(__METHOD__);
        $r = $email->add()->rental();

        $r->general()->noConfirmation();

        $r->setCarModel($this->re("/^\s+(\S.+or similar)/m", $stext));

        if (preg_match("/Total\s*\:\s*(?<total>[\d\.]+)\s*(?<currency>\D{1,3})\s*\n/", $stext, $m)) {
            $currency = $this->currency($m['currency']);
            $r->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        if (preg_match("/Pick-up :\s*(?<pickUpDate>\d+\/\d+\/\d{4})\,.*Office :\s*(?<pickUpAddress>.+)\n/", $stext, $m)) {
            $r->pickup()
                ->date(strtotime($m['pickUpDate']))
                ->location($m['pickUpAddress']);
        }

        if (preg_match("/Drop-off :\s*(?<dropOffDate>\d+\/\d+\/\d{4})\,.*Office :\s*(?<dropOffAddress>.+)\n/", $stext, $m)) {
            $r->dropoff()
                ->date(strtotime($m['dropOffDate']))
                ->location($m['dropOffAddress']);
        }
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset($this->detectBody, $this->lang)) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($text, $dBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function detectDateFormatByDates($dateIn, $dateOut)
    {
        if (preg_match("#^\s*(\d{2})/(\d{2})/(20\d{2})\s*$#", $dateIn, $m1)
                && preg_match("#^\s*(\d{2})/(\d{2})/(20\d{2})\s*$#", $dateOut, $m2)) {
            if ($m1[1] > 31 || $m1[2] > 31 || $m2[1] > 31 || $m2[2] > 31) {
                return null;
            }

            if (($m1[1] > 12 && $m1[1] < 32 && $m1[2] < 13 && $m2[2] < 13)
                    || ($m2[1] > 12 && $m2[1] < 32 && $m2[2] < 13 && $m1[2] < 13)) {
                $this->dateFormatMDY = false;

                return null;
            }

            if (($m1[2] > 12 && $m1[2] < 32 && $m1[1] < 13 && $m2[1] < 13)
                    || ($m2[2] > 12 && $m2[2] < 32 && $m2[1] < 13 && $m1[1] < 13)) {
                $this->dateFormatMDY = true;

                return null;
            }
            $diff1 = strtotime($m1[1] . '.' . $m1[2] . '.' . $m1[3]) - strtotime($m1[1] . '.' . $m1[2] . '.' . $m1[3]);
            $diff2 = strtotime($m1[2] . '.' . $m1[1] . '.' . $m1[3]) - strtotime($m1[2] . '.' . $m1[1] . '.' . $m1[3]);

            if ($diff1 < $diff2) {
                $this->dateFormatMDY = false;
            } elseif ($diff1 < $diff2) {
                $this->dateFormatMDY = true;
            }
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{2})/(\d{2})/(\d{4})\s*$#", // 04/05/2019
            "#^\s*(\d{4})/(\d{2})/(\d{2})\s*\,\s*\D*\:\s*([\d\:]+)\.#", //2024/06/04 , Pick-up time : 09:45.
        ];

        if ($this->dateFormatMDY === false) {
            $out = [
                '$1.$2.$3',
                '$2.$3.$1, $4',
            ];
        } else {
            $out = [
                '$2.$1.$3',
                '$3.$2.$1, $4',
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
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

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
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

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'  => 'EUR',
            '$'  => 'USD',
            'US$'=> 'USD',
            '£'  => 'GBP',
            '₹'  => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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

    private function opt($field, bool $addSpaces = false): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) use ($addSpaces) {
            return $addSpaces ? $this->addSpacesWord($s) : preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function addSpacesWord(string $text): string
    {
        return preg_replace('/(\S)/u', '$1 *', preg_quote($text, '/'));
    }
}
