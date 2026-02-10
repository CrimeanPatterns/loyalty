<?php

namespace AwardWallet\Engine\cheapnl\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmationWithoutTimes extends \TAccountChecker
{
    public $mailFiles = "cheapnl/it-33897599.eml, cheapnl/it-33986916.eml, cheapnl/it-34018933.eml, cheapnl/it-34434748.eml, cheapnl/it-34493799.eml";

    public static $detectCompany = [
        'budgetair'  => ['Budgetair', 'BudgetAir'],
        'vayama'     => ['Vayama'],
        'flugladen'  => ['Flugladen'],
        'cheapnl'    => ['CheapTickets'],
        'skyscanner' => ['Skyscanner'],
    ];

    private $detectFroms = [
        'budgetair'  => ['budgetair.'],
        'vayama'     => ['vayama.'],
        'flugladen'  => ['flugladen.'],
        'cheapnl'    => ['CheapTickets.'],
        'skyscanner' => ['e.skyscanner.'],
    ];

    private $detectSubject = [
        "en" => [
            " - Booking confirmation",
            " - Your e-ticket",
        ],
        "de" => [
            " - Bestätigung Ihrer Reservierung",
            " - Buchung Bestätigung",
        ],
        "nl" => [
            " - Boekingsbevestiging ",
        ],
        "fr" => [
            " - Confirmation de votre réservation",
        ],
        "es" => [
            " - Confirmación de la reserva",
        ],
        "pt" => [
            " - Descritivo da sua reserva",
        ],
    ];

    private $detectBody = [
        "en" => ['Your booking details', 'Cost overview', 'Booking Request Acknowledgement'],
        "de" => ["Ihre Buchungsdetails", "Zahlungsübersicht", "Vielen Dank für die Buchung"],
        "nl" => ["Jouw boekingsgegevens", "Betaaloverzicht", "Bedankt voor je boeking"],
        "fr" => ["Détails de votre réservation", "Détail du paiement", "Merci pour votre réservation"],
        "es" => ["Los detalles de tu reserva", "Resumen de pago", "Gracias por tu reserva"],
        "pt" => ["Os detalhes da sua reserva", "Resumo de custos", "Obrigado pela sua reserva"],
    ];

    private $pdfNamePattern = '.*\.pdf';
    private $codeProvider = '';

    private $lang = "en";
    private static $dictionary = [
        "en" => [
            // Html
            //			"Dear" => "",
            "Booking number:"   => ["Booking number:", "Booking number"],
            "%company% number:" => "number:",
            //			"Airline reference:" => "",
            //			"Total" => "",
            //			"Customer service:" => "",

            // Pdf
            "Cost overview Number:" => ["Cost overview Number:", "Payment overview number:"],
            //			"Passengers" => "",
            //			"Your booking details" => "",
            //			"Flight number:" => "",
        ],
        "de" => [
            // Html
            "Dear"               => "Sehr geehrte/r Frau/Herr",
            "Booking number:"    => ["Buchungsnummer:", "Buchungsnummer"],
            "%company% number:"  => 'Nummer:',
            "Airline reference:" => ["Fluggesellschaftsreferenz:"],
            "Total"              => ["Insgesamt", "Summe"],
            'Customer service:'  => 'Service-Center:',

            // Pdf
            "Cost overview Number:" => "Zahlungsübersichtsnummer:",
            "Passengers"            => "Passagiere",
            "Your booking details"  => "Ihre Buchungsdetails",
            "Flight number:"        => "Flugnummer: ",
        ],
        "nl" => [
            // Html
            "Dear"               => "Beste",
            "Booking number:"    => ["Boekingsnummer:", "Boekingsnummer"],
            "%company% number:"  => "number:",
            "Airline reference:" => "Airline referentie:",
            "Total"              => "Totaal",
            "Customer service:"  => "Klantenservice:",

            // Pdf
            "Cost overview Number:" => "Betaaloverzichtsnummer:",
            "Passengers"            => "Passagiers",
            "Your booking details"  => "Jouw boekingsgegevens",
            "Flight number:"        => "Vluchtnummer:",
        ],
        "fr" => [
            // Html
            "Dear"               => "Cher madame, monsieur",
            "Booking number:"    => ["Numéro de réservation:", "Numéro de réservation"],
            "%company% number:"  => 'Référence',
            "Airline reference:" => "Référence de la compagnie aérienne:",
            "Total"              => "Total",
            "Customer service:"  => "Le service clientèle:",

            // Pdf
            "Cost overview Number:" => "Numéro du détail du paiement:",
            "Passengers"            => "Passagers",
            "Your booking details"  => "Détails de votre réservation",
            "Flight number:"        => "Numéro de vol:",
        ],
        "es" => [
            // Html
            "Dear"               => "Estimado/a",
            "Booking number:"    => ["Número de la reserva:", "Número de la reserva"],
            "%company% number:"  => 'Número de',
            "Airline reference:" => ["Número de aerolínea:", "Referencia de la aerolínea:"],
            "Total"              => "Total",
            "Customer service:"  => "Atención al cliente:",

            // Pdf
            "Cost overview Number:" => "Número de resumen de pago:",
            "Passengers"            => "Pasajeros",
            "Your booking details"  => "Los detalles de tu reserva",
            "Flight number:"        => "Número de vuelo:",
        ],
        "pt" => [
            // Html
            "Dear"               => "Caro(a)",
            "Booking number:"    => ["Número da reserva:", "Número da reserva"],
            "%company% number:"  => 'Número',
            "Airline reference:" => "Número da companhia aérea:",
            "Total"              => "Total",
            "Customer service:"  => "Atendimento ao Cliente:",

            // Pdf
            "Cost overview Number:" => "Número do resumo de custos:",
            "Passengers"            => "Passageiros",
            "Your booking details"  => "Os detalhes da sua reserva",
            "Flight number:"        => "Número do voo:",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        $foundPdf = false;
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($body, "Travix Nederland") !== false || stripos($body, "Travix USA, LLC") !== false) {
                foreach ($this->detectBody as $lang => $detectBody) {
                    foreach ($detectBody as $dBody) {
                        if (stripos($body, $dBody) !== false) {
                            $this->lang = $lang;
                            $foundPdf = true;
                            $type = 'Pdf';
                            $this->parsePdf($email, $body);

                            continue 2;
                        }
                    }
                }
            }
        }

        if ($foundPdf === false) {
            $body = $parser->getHTMLBody();

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($body, $dBody) !== false || $this->http->XPath->query('//*[contains(normalize-space(), "' . $dBody . '")]')->length > 0) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }
            $this->parseHtml($email);
            $type = 'Html';
        }

        if (!empty($this->codeProvider)) {
            $codeProvider = $this->codeProvider;
        } else {
            $body = $parser->getHTMLBody();
            $this->codeProvider = $codeProvider = $this->getProvider($body);
        }

        if (!empty($codeProvider)) {
            $email->setProviderCode($codeProvider);
            $email->ota()->code($codeProvider);
        } else {
            $email->obtainTravelAgency();
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFroms as $detectFroms) {
            foreach ($detectFroms as $dFrom) {
                if (stripos($from, $dFrom) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    $finded = true;

                    break 2;
                }
            }
        }

        if ($finded === false) {
            foreach ($this->detectFroms as $prov => $detectFroms) {
                foreach ($detectFroms as $dFrom) {
                    if (stripos($headers["from"], $dFrom) !== false || stripos($headers["subject"], $dFrom) !== false) {
                        $this->codeProvider = $prov;

                        return false;
                    }
                }
            }

            return false;
        }

        foreach ($this->detectFroms as $prov => $detectFroms) {
            foreach ($detectFroms as $dFrom) {
                if (stripos($headers["from"], $dFrom) !== false || stripos($headers["subject"], $dFrom) !== false) {
                    $this->codeProvider = $prov;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $head = false;

        foreach (self::$detectCompany as $prov => $detectCompany) {
            foreach ($detectCompany as $dCompany) {
                if ($this->http->XPath->query('//a[contains(@href, "' . $dCompany . '")]')->length > 0 || stripos($body, $dCompany) !== false) {
                    $head = true;

                    break 2;
                }
            }
        }

        if ($head === true) {
            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($body, $dBody) !== false || $this->http->XPath->query('//*[contains(normalize-space(), "' . $dBody . '")]')->length > 0) {
                        return true;
                    }
                }
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($body, "Travix Nederland") !== false) {
                foreach ($this->detectBody as $detectBody) {
                    foreach ($detectBody as $dBody) {
                        if (stripos($body, $dBody) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectCompany);
    }

    private function parseHtml(Email $email)
    {
        // Travel Agency
        $tripNumber = $this->nextText($this->t("Booking number:"), null, "#^\s*([A-Z\d\-]+-[A-Z\d\-]+)\s*$#");

        if (empty($tripNumber)) {
            if (!empty($this->codeProvider)) {
                $tripNumber = $this->http->FindSingleNode("(//text()[(" . $this->contains($this->t("%company% number:")) . ") and (" . $this->contains(self::$detectCompany[$this->codeProvider]) . ")]/following::text()[normalize-space(.)][1])[1]", null, true, "#^\s*([A-Z\d\-]{5,})\s*$#");

                if (empty($tripNumber)) {
                    $tripNumber = $this->http->FindSingleNode("(//td[not(.//td) and (" . $this->contains($this->t("%company% number:")) . ") and (" . $this->contains(self::$detectCompany[$this->codeProvider]) . ")][1])[1]", null, true, "#:\s*([A-Z\d\-]{5,})\s*$#");
                }
            }
        }

        if (!empty($tripNumber)) {
            $name = trim($this->http->FindSingleNode("(//text()[(" . $this->eq($this->t($tripNumber)) . ")][1]/ancestor::td[1])[1]", null, true, "#^\s*(.{5,40})[\s:]+" . $tripNumber . "$#u"), ' :');
            $email->ota()->confirmation($tripNumber, !empty($name) ? $name : null);
        }

        $phonesText = implode("\n", $this->http->FindNodes("//tr[" . $this->eq($this->t("Customer service:")) . "]/following-sibling::tr[1]//text()"));

        if (empty($phonesText)) {
            $phonesText = implode("\n", $this->http->FindNodes("//table[" . $this->eq($this->t("Customer service:")) . "]/following-sibling::table[1]//text()"));
        }

        if (empty($phonesText)) {
            $phonesText = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Travix Nederland B.V.']/preceding::text()[normalize-space()][1]/ancestor::tr[1]//text()"));
        }

        if (preg_match_all("#(.*?):?\s*([+.\-\(\) ]*\d+(?:[+.\-\(\) ]*\d+){6,}?)(?:|[ ]+\([^\(\n]+?\)|[ ]+[^\(\)\n]*)[ ]*(?:\n|$)#", $phonesText, $m, PREG_SET_ORDER)) {
            foreach ($m as $value) {
                if (!empty($value[2]) && !empty($value[1]) && !in_array(trim($value[2]), array_column($email->obtainTravelAgency()->getProviderPhones(), 0))) {
                    $email->ota()->phone(trim($value[2]), trim($value[1]));
                }
            }
        }

        $f = $email->add()->flight();

        //General
        $f->general()->noConfirmation();

        $passenger = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear")) . "]", null, true, "#" . $this->preg_implode($this->t("Dear")) . "\s*(.+)#");

        if (!empty($passenger)) {
            $f->general()->traveller($passenger);
        }

        // Price
        $total = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Total")) . "]/following-sibling::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5}(?:\s?\(\s?[A-Z]{3}\s?\))?)\s*(?<amount>\d[\d\., ]*)\s*$#u", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5}(?:\s?\(\s?[A-Z]{3}\s?\))?)\s*$#u", $total, $m)) {
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        // Segments
        $nodes = $this->http->XPath->query("//img[contains(@class, 'icon__name_airplane')]/ancestor::tr[position()<3][count(td[normalize-space()])=3]");

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("//img[contains(@class, 'icon__name_airplane')]/ancestor::td[position()<4][count(*[normalize-space()])=3]");
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("(preceding::text()[" . $this->contains($this->t("Airline reference:")) . "])[last()]/ancestor::td[2]/following-sibling::td[1]//img/@src", $root, true, "#/airlineLogos/([A-Z\d]{2})\.png#"))
                ->noNumber()
                ->confirmation($this->http->FindSingleNode("(preceding::text()[" . $this->contains($this->t("Airline reference:")) . "])[last()]/following::text()[normalize-space()][1]", $root, true, "#^\s*([A-Z\d]{5,7})\s*$#"))
                ;

            $date = $this->normalizeDate($this->http->FindSingleNode("*[normalize-space()][1]/descendant::text()[normalize-space()][1]/ancestor::td[1]", $root));
            // Departure
            $s->departure()
                ->noCode()
                ->name(implode(", ", $this->http->FindNodes("*[normalize-space()][2]//text()[normalize-space()]", $root)))
                ->noDate()
                ->day($date);

            // Arrival
            $s->arrival()
                ->noCode()
                ->name(implode(", ", $this->http->FindNodes("*[normalize-space()][3]//text()[normalize-space()]", $root)))
                ->noDate()
                ->day($date);
        }
    }

    private function parsePdf(Email $email, $text)
    {
        // Travel Agency
        if (preg_match("#(" . $this->preg_implode($this->t("Cost overview Number:")) . ")\n(?:.*\n){0,2}?.*[ ]{5,}([A-Z]{2,4}-[\d]{5,})\n#", $text, $m)) {
            $email->ota()->confirmation($m[2], trim($m[1], ':'));
        }

        $f = $email->add()->flight();

        //General
        $f->general()->noConfirmation();

        if (preg_match("#\n\s*" . $this->preg_implode($this->t("Passengers")) . "\s*\n((?:.*\n)+)\s*" . $this->preg_implode($this->t("Your booking details")) . "\s*\n#", $text, $m)) {
            $f->general()->travellers(array_unique(array_filter(array_map('trim', preg_split("#(?:[ ]{3,}|\n)#", $m[1])))));
        }

        // Price
        $total = $this->re("#\n\s*" . $this->preg_implode($this->t("Total")) . "[ ]{5,}(.+)#", $text);

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5}(?:\s?\(\s?[A-Z]{3}\s?\))?)\s*(?<amount>\d[\d\., ]*)\s*$#u", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5}(?:\s?\(\s?[A-Z]{3}\s?\))?)\s*$#u", $total, $m)) {
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        // Segments
        $segmentsText = $this->re("#\n\s*" . $this->preg_implode($this->t("Your booking details")) . "([\s\S]+)#", $text);
        $segmentsText = preg_replace("#(\n.*Travix Nederland .+)#", '', $segmentsText);
        $dateFormats = [
            '.{0,20}\b\d{1,2}\b.{0,20}\b\d{4}', // Wednesday 27 February 2019
            '[[:alpha:]]{2,}[ ]+\d{4}-\d{1,2}-\d{1,2}', // Sunday 2020-02-16
        ];
        $segments = $this->split('/\n[ ]{0,20}\W{0,3}((?:' . implode('|', $dateFormats) . ')\n)/u', $segmentsText);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->re("#^(.+)#", $stext));
            $stext = preg_replace("#^.+\n#", '', $stext);

            $table = $this->SplitCols($stext, $this->rowColsPos($this->inOneRow($stext)));

            if (count($table) != 3) {
                $this->logger->info("Incorrect parse table");

                return;
            }

            // Airline
            $s->airline()
                ->name(preg_replace("#\s+#", ' ', trim($this->re("#(.+)\s+" . $this->preg_implode($this->t("Flight number:")) . "#s", $table[2]))))
                ->number($this->re("#" . $this->preg_implode($this->t("Flight number:")) . "\s*(\d{1,5})#", $table[2]))
            ;

            // Departure
            $s->departure()
                ->noCode()
                ->name(preg_replace("#\s*\n\s*#", ", ", trim($table[0])))
                ->noDate()
                ->day($date);

            // Arrival
            $s->arrival()
                ->noCode()
                ->name(preg_replace("#\s*\n\s*#", ", ", trim($table[1])))
                ->noDate()
                ->day($date);
        }
    }

    private function getProvider($body)
    {
        foreach ($this->detectFroms as $prov => $detectFroms) {
            foreach (self::$dictionary as $dict) {
                if (isset($dict['Customer service:'])
                        && $this->http->XPath->query("//text()[" . $this->eq($dict['Customer service:']) . "]/following::*[" . $this->contains($detectFroms, '@href') . " or " . $this->contains($detectFroms) . "]")->length > 0) {
                    return $prov;
                }
            }
        }

        foreach (self::$detectCompany as $prov => $detectCompany) {
            foreach ($detectCompany as $dCompany) {
                if (stripos($body, $dCompany) !== false) {
                    return $prov;
                }
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*[^\d\s]+[\s,]+(\d+)\s+([^\d\s]+)\s+(\d{4})\s*$#u", //Fri, 29 sep 2017
            "#^\s*[^\d\s]+[\s,]+([^\d\s]+)\s+(\d+)[\s,]+(\d{4})\s*$#u", //lunes, abril 15, 2019
        ];
        $out = [
            "$1 $2 $3",
            "$2 $1 $3",
        ];
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

    private function amount($price)
    {
        $price = trim($price);

        if (preg_match("#^([\d,. ]+)[.,](\d{2})$#", $price, $m)) {
            $price = str_replace([' ', ',', '.'], '', $m[1]) . '.' . $m[2];
        }

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }

        if ($code = $this->re("#\(\s*([A-Z]{3})\s*\)$#", $s)) {
            return $code;
        }
        $sym = [
            '€'    => 'EUR',
            'AU$'  => 'AUD',
            '$'    => 'USD',
            '£'    => 'GBP',
            '₹'    => 'INR',
            '฿'    => 'THB',
            'zł'   => 'PLN',
            'ريال' => 'SAR',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $regex = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $regex);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(normalize-space({$text}), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
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

    private function rowColsPos($row)
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if ($pos === false) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
