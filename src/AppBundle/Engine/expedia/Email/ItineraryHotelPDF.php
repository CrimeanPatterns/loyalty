<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryHotelPDF extends \TAccountChecker
{
    public $mailFiles = "expedia/it-14597044.eml, expedia/it-37390191.eml, expedia/it-50778104.eml";

    public $reFrom = "expedia.";
    public $reBody = [
        'en' => ['Itinerary #', 'Expedia'],
        'es' => ['No. de itinerario', 'Expedia'],
        'pt' => ['Nº do itinerário', 'Expedia'],
    ];
    public $lang = '';
    /** @var \HttpBrowser */
    public $pdf;
    public $pdfNamePattern = "(?:(?:(?:.* )?Itinerary|Itinerario|.*Hotel|.*\-[A-Z]{3}).*pdf|.+? (?:\d{1,2} *\- *)?\d{1,2} \w+\.pdf)";
    public static $dict = [
        'en' => [
            //			"Itinerary #" => "",
            //			"Call" => "",
            //			"For faster service" => "",
            //			"Call us at" => "",
            "BOOKED" => ["CONFIRMED", "BOOKED"],
            //			"CANCELLED" => "",
            //			"Tel:" => "",
            //			"Fax:" => "",
            "room" => "rooms?",
            //			"Check-in time starts at" => "",
            //			"Check-in time ends at" => "",
            //			"Total" => "",
            //			"Reserved for" => "",
            //			"Room" => "",
            //			"Room d" => "",
            //			"Room Price" => "",
            //			"d night" => "",
            //			"Price Summary" => "",
            //			"adult" => "",
            "child" => ["child", "infant"],
            //			"Adjusted total" => "",
            //			"All prices quoted in" => "",
            //			"Need help with your reservation" => "",
        ],
        'es' => [
            "Itinerary #"        => "No. de itinerario",
            "Call"               => "Llámanos",
            "For faster service" => "Para obtener un servicio más rápido",
            "Call us at"         => "Llámanos al",
            "BOOKED"             => "RESERVADO",
            //			"CANCELLED" => "",
            "Tel:"                    => "Tel.:",
            "Fax:"                    => "Fax:",
            "room"                    => "habitación",
            "Check-in time starts at" => "Hora inicial de check-in:",
            "Check-in time ends at"   => "Hora final de check-in:",
            "Total"                   => "Total",
            "Reserved for"            => ["Reservado para", "Reservadopara"],
            "Room"                    => "Habitación",
            "Room d"                  => "Habitación d",
            "Room Price"              => "Precio de la habitación",
            "d night"                 => "d noche",
            //			"Price Summary" => "",
            "adult" => "adulto",
            "child" => ["niño"],
            //			"Adjusted total" => "",
            "All prices quoted in"            => "Todos los precios se muestran en",
            "Need help with your reservation" => "¿Necesitas ayuda con tu reservación?",
        ],
        'pt' => [
            "This reservation is non-refundable and cannot be cancelled or changed"=> "Esta reserva não é reembolsável e não pode ser cancelada nem",
            "Itinerary #"                                                          => "Nº do itinerário",
            "Call"                                                                 => "Entre em contato",
            "For faster service"                                                   => "Para um atendimento mais rápido",
            "Call us at"                                                           => "Entre em contato conosco",
            "BOOKED"                                                               => "CONFIRMADO",
            //			"CANCELLED" => "",
            "Tel:"                    => "Tel:",
            "Fax:"                    => "Fax:",
            "room"                    => "quarto",
            "Check-in time starts at" => "Horário inicial do check-in:",
            "Check-in time ends at"   => "Idade mínima para o check-in:",
            "Total"                   => "Total",
            "Reserved for"            => ["Reservado para", "Reservadopara"],
            "Room"                    => "Quarto",
            "Room d"                  => "Quarto d",
            "Room Price"              => "Preço do quarto",
            "d night"                 => "d noite",
            //			"Price Summary" => "",
            "adult" => "adulto",
            "child" => ["crianças"],
            //			"Adjusted total" => "",
            "All prices quoted in"            => "Todos os preços foram cotados em ",
            "Need help with your reservation" => "Precisa de ajuda com sua reserva?",
        ],
    ];

    /**
     * @return array|Email|null
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (empty($pdfs)) {
            $pdfs = $parser->searchAttachmentByName('.*\.pdf');
        }
        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                } else {
                    continue;
                }
                $NBSP = chr(194) . chr(160);
                $html = str_ireplace(['&shy;', '&#173;', '­'], '', $html); // Soft hyphen
                $this->pdf->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
                $body = $this->pdf->Response['body'];

                $this->AssignLang($body);
                $this->parseEmail($email);
            }
        } else {
            return null;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);
        if (empty($pdfs)) {
            $pdf = $parser->searchAttachmentByName('.*\.pdf');
        }

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email)
    {
        $hotelname = $this->pdf->FindSingleNode("//text()[" . $this->starts($this->t("BOOKED")) . "]/preceding::text()[1]");
        $node = $this->pdf->FindSingleNode("//text()[" . $this->starts($this->t("BOOKED")) . " or " . $this->starts($this->t("CANCELLED")) . "]/preceding::text()[normalize-space(.)!=''][1]");

        if (preg_match('/\d+/', $node)) {
            $hotelname = $this->pdf->FindSingleNode("//text()[" . $this->starts($this->t("BOOKED")) . " or " . $this->starts($this->t("CANCELLED")) . "]/preceding::text()[normalize-space(.)!=''][2]");
        }

        if (empty($hotelname)) {
            $this->logger->debug('other format pdf. skip it');

            return true;
        }

        $h = $email->add()->hotel();

        $otaConfNo = $this->pdf->FindSingleNode("//text()[" . $this->contains($this->t("Itinerary #")) . "]", null,
            true, "#" . $this->preg_implode($this->t("Itinerary #")) . "\s+([A-Z\d]+)#");

        if (!empty($otaConfNo)) {
            $h->ota()
                ->confirmation($otaConfNo, $this->t("Itinerary #"));
        }

        $phones = implode(' ',
            $this->pdf->FindNodes("//text()[" . $this->starts($this->t("Call")) . "]/preceding::text()[1]/following::p/descendant::text()[normalize-space(.)!=''][position()<10]"));
        $phones = strstr($phones, $this->t('For faster service'), true);

        if (preg_match_all("#(^.*?|\.\s*.+?|\(|)\s*([\+\d][\d\-A-Z \(\)]{6,})#u", $phones, $m, PREG_SET_ORDER)) {
            $addedPhones = [];

            foreach ($m as $v) {
                $num = preg_replace(["#\s*\(\s*#", "#\s*\)\s*#"], ['(', ')'], trim($v[2], " ("));

                if (preg_match("#" . $this->preg_implode($this->t("Call us at")) . "#", $v[1]) || $v[1] == '(' || empty($v[1])) {
                    if (!in_array($num, $addedPhones)) {
                        $h->ota()->phone($num, $this->t("Call us at"));
                        $addedPhones[] = $num;
                    }
                } else {
                    if (!in_array($num, $addedPhones)) {
                        $h->ota()->phone($num, trim($v[1], " ."));
                        $addedPhones[] = $num;
                    }
                }
            }
        }

        $confs = array_values(array_filter($this->pdf->FindNodes("(//text()[normalize-space()='Confirmation #'])/following::text()[normalize-space()!=''][1]")));

        if (!empty($confs)) {
            $confs = array_filter($confs, function ($v) {return (preg_match("#^\s*([A-Z\d]{5,})\s*$#", $v)) ? true : false; });

            foreach ($confs as $conf) {
                $h->general()
                    ->confirmation($conf);
            }

            if (empty($confs)) {
                $h->general()
                    ->noConfirmation();
            }
        } else {
            $h->general()
                ->noConfirmation();
        }

        if (!empty($this->pdf->FindSingleNode("//text()[" . $this->starts($this->t("CANCELLED")) . "]"))) {
            $h->general()->cancelled();
        }

        if ($cancel = $this->pdf->FindSingleNode("//p[{$this->contains($this->t('This reservation is non-refundable and cannot be cancelled or changed'))}][1]")) {
            $h->booked()
                ->nonRefundable();
            $h->general()
                ->cancellation($cancel);
        } elseif ($cancel = implode("\n", $this->pdf->FindNodes("//text()[contains(normalize-space(.), 'Cancellations or changes made after')][1]/ancestor::p[1]/preceding-sibling::p[1]/following-sibling::p[position()<5]"))) {
            $cancel = trim(preg_replace("#\s*\n\s*#", ' ', $this->re("#^\s*([\S\s]+?\.)\s*(?:\n|$)#", $cancel)));

            if (preg_match("#Cancellations or changes made after (?<hour>\d+:\d+\s*([ap]m)?) \(.+?\)\s*on (?<date>\d+\s+\w+\s+\d+|\w+ \d+, \d{4}) or no\-?shows are subject to a property#i",
                $cancel, $m)) {
                $h->booked()
                    ->deadline(strtotime($m['date'] . ' ' . $m['hour']));
            }
            $h->general()
                ->cancellation($cancel);
        }

        $address = $this->pdf->FindSingleNode("//a[contains(@href,'maps.google.com')][1]");

        if (empty($address)) {
            $address = $this->pdf->FindSingleNode("//p[contains(normalize-space(.), 'Tel:') and contains(normalize-space(.), 'Fax')]/preceding-sibling::p[normalize-space(.)!=''][1]");
        }

        if (empty($address) && !empty($hotelname)) {
            $left = $this->pdf->FindSingleNode("//p[normalize-space() = '" . $hotelname . "'][1]/@style", null, true, "#left:\s*(\d+)#");

            if (!empty($left)) {
                $xpathA = "(//p[normalize-space() = '" . $hotelname . "'][1]/following::p[normalize-space()])";

                for ($i = 1; $i < 6; $i++) {
                    $l = $this->pdf->FindSingleNode($xpathA . "[{$i}]/@style", null, true, "#left:\s*(\d+)#");

                    if (!empty($l) && ($l > ($left + 20)) && ($l < ($left + 150))) {
                        $address = $this->pdf->FindSingleNode($xpathA . "[{$i}]");
                    }
                }
            }
        }
        $h->hotel()
            ->name($hotelname)
            ->address($address)
            ->phone($this->pdf->FindSingleNode("//text()[" . $this->starts($this->t("Tel:")) . "]", null, true,
                "#" . $this->preg_implode($this->t("Tel:")) . "\s+(.+?)(?:,\s*" . $this->preg_implode($this->t("Fax:")) . "|$)#"), true, true)
            ->fax($this->pdf->FindSingleNode("//text()[" . $this->starts($this->t("Tel:")) . "]", null, true,
                "#" . $this->preg_implode($this->t("Fax:")) . "\s+(.+)#"), true, true);

        if (preg_match("#(.+?(?:\s+|,)\d{4})[\s\-]+(.+?(?:\s+|,)\d{4})\s*,\s+(\d+)\s+" . $this->t("room") . "\s+\|#", $node, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate(str_replace(',', ' ', $m[1])))
                ->checkOut($this->normalizeDate(str_replace(',', ' ', $m[2])))
                ->rooms($m[3]);
            $time = $this->normalizeTime($this->pdf->FindSingleNode("//text()[" . $this->contains($this->t("Check-in time starts at")) . "]", null, true, "#" . $this->preg_implode($this->t("Check-in time starts at")) . "\s+(.+)#"));

            if (!empty($time) && !empty($h->getCheckInDate())) {
                $h->booked()->checkIn(strtotime($time, $h->getCheckInDate()));
            }
        }
        $tot = $this->getTotalCurrency($this->pdf->FindSingleNode("//text()[" . $this->eq($this->t("Total")) . "]/following::text()[normalize-space()][1]"));

        if (!empty($tot['Total'])) {
            $h->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);

            if (!empty($cur = $this->pdf->FindPreg("#" . $this->preg_implode($this->t("All prices quoted in")) . "\s+([A-Z]{3})#"))) {
                $h->price()
                    ->currency($cur);
            }
        }

        $rooms = $this->pdf->XPath->query("//p[" . $this->starts($this->t("Reserved for")) . "]");

        if ($rooms->length > 0) {
            if (count($confs) !== $rooms->length) {
                $confs = [];
            }

            foreach ($rooms as $i => $root) {
                $traveller = $this->pdf->FindSingleNode(".", $root, true, "#" . $this->preg_implode($this->t("Reserved for")) . "\s*(\S?.+)#i");

                if (empty($traveller)) {
                    $traveller = $this->pdf->FindSingleNode("following::text()[normalize-space(.)!=''][1]", $root, true, "#^[\w+\- ]+$#u");
                }

                if (!empty($traveller)) {
                    $h->general()
                        ->traveller($traveller);
                }

                $r = $h->addRoom();
                $r->setType($this->pdf->FindSingleNode("(./preceding::text()[" . $this->eq($this->t("Room")) . " or " . $this->starts($this->t("Room d"), "translate(normalize-space(),'123456789', 'ddddddddd')") . "]/ancestor::*[position()<3]/@style[contains(translate(., '0123456789', '**********'), 'left:**px') or (contains(translate(., '0123456789', '**********'), 'left:***px') and contains(., 'left:1'))]/ancestor::*[1])[last()]/following::text()[normalize-space()][1]", $root));

                if ($rooms->length == 1) {
                    $r->setRate($this->pdf->FindSingleNode("//text()[" . $this->eq($this->t("Room Price")) . "]/following::text()[" . $this->starts($this->t("d night"), "translate(normalize-space(),'123456789', 'ddddddddd')") . "][1]/following::text()[normalize-space()][1]"));
                } else {
                    $roomName = $this->pdf->FindSingleNode("./preceding::text()[" . $this->eq($this->t("Room")) . " or " . $this->starts($this->t("Room d"), "translate(normalize-space(),'123456789', 'ddddddddd')") . "][1]", $root);
                    $r->setRate($this->pdf->FindSingleNode("//text()[" . $this->starts($this->t("Price Summary")) . "]/following::text()[normalize-space(.)='{$roomName}'][1]/following::text()[" . $this->starts($this->t("d night"), "translate(normalize-space(),'123456789', 'ddddddddd')") . "][1]/following::text()[normalize-space()][1]"));
                }

                if (isset($confs[$i])) {
                    $r->setConfirmation($confs[$i]);
                }
            }
            $guests = array_filter($this->pdf->FindNodes("//p[" . $this->starts($this->t("Reserved for")) . "]/following::text()[" . $this->contains($this->t("adult")) . "][1]", null, "#(\d+)\s+" . $this->preg_implode($this->t("adult")) . "#"));

            if (!empty($guests)) {
                $h->booked()->guests(array_sum($guests));
            }
            $kids = array_filter($this->pdf->FindNodes("//p[" . $this->starts($this->t("Reserved for")) . "]/following::text()[" . $this->contains($this->t("child")) . "][1]", null, "#(\d+)\s+" . $this->preg_implode($this->t("child")) . "#"));

            if (!empty($kids)) {
                $h->booked()->kids(array_sum($kids));
            }
        } elseif ($h->getCancelled()) {
            $text = text($this->pdf->Response['body']);
            $tot = $this->getTotalCurrency($this->pdf->FindPreg("#" . $this->preg_implode($this->t("Adjusted total")) . "\s+(.+)#", false, $text));

            if (!empty($tot['Total'])) {
                $h->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);

                if (!empty($cur = $this->pdf->FindPreg("#" . $this->preg_implode($this->t("All prices quoted in")) . "\s+([A-Z]{3})#", false, $text))) {
                    $h->price()
                        ->currency($cur);
                }
            }

            if (preg_match("#(" . $this->preg_implode($this->t("All prices quoted in")) . "\s+[A-Z]{3}[^\n]*\n.+?)" . $this->preg_implode($this->t("Need help with your reservation")) . "#s", $text,
                $m)) {
                $arr = $this->splitter("#^ *(" . $this->preg_implode($this->t("Room")) . "\s+\d+\s+)#m", $m[1]);

                if (count($arr) === $h->getRoomsCount()) {
                    foreach ($arr as $v) {
                        $r = $h->addRoom();

                        if (preg_match("#" . $this->preg_implode($this->t("Room")) . "\s+\d+\s+(.+?),\s+(.+)#s", $v, $m)) {
                            $r->setType($m[1])
                                ->setDescription(preg_replace("#\s+#", ' ', $m[2]));
                        } else {
                            $r->setDescription(preg_replace("#\s+#", ' ', $v));
                        }
                    }
                }
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("AU$", "AUD", $node);
        $node = str_replace("MXN$", "MXN", $node);
        $node = str_replace("¥", "CNY", $node);
        $node = str_replace("R$", "BRL", $node);
        $node = str_replace("€", "EUR", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#^(?<c>\D)\s*(?<t>\d[\.\d\,\s]*\d*)$#", $node, $m)
            || preg_match("#^\s*(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>\D)\s*$#", $node, $m)
        ) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{1,2})\s+de\s+([^\s\d\,\.]+)\s+de\s+(\d{4})\s*$#", // 10 de agosto de 2018
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeTime($time)
    {
        $in = [
            "#^\s*(\d{1,2})\s*([AP]M)\s*$#", //3 PM
            "#^\s*(\d{1,2}:\d{1,2})\s*([AP]M)\s*$#", //3 PM
            "#^\s*(\d{1,2}:\d{1,2})\s*$#", //15:00
        ];
        $out = [
            "$1:00 $2",
            "$1 $2",
            "$1 $2",
        ];
        $time = preg_replace($in, $out, $time, -1, $count);

        if ($count > 0) {
            return $time;
        }

        return '';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'starts-with(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
