<?php

namespace AwardWallet\Engine\amextravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryETicket extends \TAccountChecker
{
    public $mailFiles = "amextravel/it-46449403.eml";

    public $reFrom = ["amxitinerary@e-savia.net"];
    public $reBody = [
        'es' => [
            ['Itinerario E-Ticket', '[ VUELO ]', '[ HOTEL ]', 'Localizador de línea aérea', 'Tipo de habitación'],
            'Estado:',
        ],
    ];
    public $reSubject = [
        '/Itinerario E-Ticket \[Billete Electronico\] [\w\- ]+? \d+ \w+ (?<pnr>[A-Z\d]{5,})$/u',
        '/Itinerario [\w\- ]+? \d+ \w+ (?<pnr>[A-Z\d]{5,})$/u',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'es' => [
            'Salida'    => 'Salida',
            'Entrada'   => 'Entrada',
            'Llegada'   => 'Llegada',
            'otaPhones' => [
                'Teléfono de Atención: [t]',
                'Servicio Emergencia [t]',
                'Teléfono contacto desde España [t]',
                'Teléfono contacto fuera España [t]',
            ],
            'detalles del Itinerario E-Ticket [Billete Electronico] para' => [
                'detalles del Itinerario E-Ticket [Billete Electronico] para',
                'detalles del Itinerario para',
            ],
        ],
    ];
    private $keywordProv = ['American Express', 'Global Business Travel España', '@gbtspain.com'];
    private $subject;
    private $traveller;
    private $otaPhones;
    private $otaConfirmation;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $type = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text) && $this->assignLang($text)) {
                        $this->parseEmailPdf($text, $email);
                        $type = 'pdf';
                    }
                }
            }
        }

        if (count($email->getItineraries()) == 0) {
            if (!$this->assignLang($this->http->Response['body'])) {
                $this->logger->debug('can\'t determine a language');

                return $email;
            }
            $type = 'html';
            $this->parseEmail($email);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->keywordProv)}] | //a[contains(@href,'.amexglobalbusinesstravel.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    if ($this->assignLang($this->http->Response['body'])) {
                        return true;
                    }
                }
            }
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectBody($text) && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && preg_match($reSubject, $headers["subject"]) > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; // flight | hotel
        $formats = 2; // pdf | html
        $cnt = $types * $formats * count(self::$dict);

        return $cnt;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $topInfo = $this->strstrArr($textPDF, $this->t('Reserva nº:'), true);

        if (empty($topInfo)) {
            $this->logger->debug('other format. not detect topInfo');

            return false;
        }
        $mainInfo = $this->strstrArr($textPDF, $this->t('COSTE DEL VIAJE:'), true);

        if (empty($mainInfo)) {
            $this->logger->debug('other format. not detect mainInfo');

            return false;
        }
        $footerInfo = $this->strstrArr($textPDF, $this->t('COSTE DEL VIAJE:'));

        $pos = array_slice($this->colsPos($topInfo, 20), 0, 2);

        if (empty($pos)) {
            $this->logger->debug('other format. not detect table with pax');

            return false;
        }
        $table = $this->splitCols($topInfo, $pos);

        $reservations = $this->splitter("/(\n.+? \d{4}[ ]*\.?\s*?\n\s*\.?\s*[\w\- ]+:[ ]*\d+\/\w+\/\d+\b)/", $mainInfo);

        foreach ($reservations as $reservation) {
            if (preg_match("/.+?[ ]{5,}.+? \d{4}\s*\.?\s*?\n[ ]*\.?\s*([\w\- ]+):[ ]*\d+\/\w+\/\d+\b/", $reservation,
                $m)) {
                if (preg_match("/^{$this->opt($this->t('Salida'))}$/", $m[1])) {
                    $flights[] = $reservation;
                } elseif (preg_match("/^{$this->opt($this->t('Entrada'))}$/", $m[1])) {
                    $hotels[] = $reservation;
                } else {
                    $this->logger->debug("unknown format for this parser");

                    return false;
                }
            }
        }

        // travel agency info
        $this->traveller = $this->re("/(.+)\s+{$this->opt($this->t('Itinerario'))}/u", $table[1]);
        $confNo = $this->re("/{$this->opt($this->t('Reserva nº:'))}[ ]*([A-Z\d]{5,})/", $mainInfo);

        if (!empty($confNo)) {
            $descr = (array) $this->t('Reserva nº:');
            $this->otaConfirmation = [trim(array_shift($descr), ":") => $confNo];
        } else {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $this->subject, $m) && isset($m['pnr'])) {
                    $this->otaConfirmation = $m['pnr'];

                    break;
                }
            }
        }
        $Phones = [];

        if (preg_match_all("/({$this->opt($this->t('otaPhones'))})[ ]*([\+\-\d \(\)]+)(?:\n|$)/iu", $table[0], $m,
            PREG_SET_ORDER)) {
            foreach ($m as $v) {
                if (!in_array(trim($v[2]), $Phones)) {
                    $this->otaPhones[] = [trim(preg_replace(['/\s*\[t\]/', '/: \[t\]/'], '', $v[1])) => trim($v[2])];
                    $Phones[] = trim($v[2]);
                }
            }
        }

        // FLIGHTS
        if (isset($flights)) {
            $this->parseFlightsPdf($flights, $email, $footerInfo);
        }

        // HOTELS
        if (isset($hotels)) {
            $this->parseHotelsPdf($hotels, $email, $footerInfo);
        }

        $sum = $this->re("/\n[ ]*{$this->opt($this->t('Total coste de viaje'))}[\*:∗ ]+(\s*(?:\s*Subtotal.+)\d+[\d,.]+\s[A-Z]{3}|.+\d+[\d,.]+\s[A-Z]{3})/u", $footerInfo);
        $sum = $this->getTotalCurrency($sum);
        $email->price()
            ->currency($sum['Currency'])
            ->total($sum['Total']);

        return true;
    }

    private function parseFlightsPdf(array $flights, Email $email, string $footerInfo)
    {
        $r = $email->add()->flight();
        $r->general()
            ->noConfirmation()
            ->traveller($this->traveller);

        if (isset($this->otaConfirmation)) {
            if (is_array($this->otaConfirmation)) {
                $cn = array_values($this->otaConfirmation);
                $descr = array_keys($this->otaConfirmation);
                $r->ota()->confirmation(array_shift($cn), array_shift($descr));
            } else {
                $r->ota()->confirmation($this->otaConfirmation);
            }
        }

        if (isset($this->otaPhones)) {
            foreach ($this->otaPhones as $otaPhone) {
                if (is_array($otaPhone)) {
                    $ph = array_values($otaPhone);
                    $descr = array_keys($otaPhone);
                    $r->ota()->phone(array_shift($ph), array_shift($descr));
                } else {
                    $r->ota()->phone($otaPhone);
                }
            }
        }

        $accounts = [];
        $tickets = [];

        foreach ($flights as $i => $root) {
            $s = $r->addSegment();

            if (preg_match("/{$this->opt($this->t('Salida'))}:[ ]*(?<depDate>.+?)[ ]{5,}(?<depInfo>.+)\n\s*{$this->opt($this->t('Llegada'))}:[ ]*(?<arrDate>.+?)[ ]{5,}(?<arrInfo>.+)\n/u",
                $root, $m)) {
                $s->departure()->date($this->normalizeDate($m['depDate']));
                $s->arrival()->date($this->normalizeDate($m['arrDate']));
                $depData = array_map("trim", explode("|", $m['depInfo']));

                if (count($depData) === 3) {
                    $s->departure()
                        ->noCode()
                        ->terminal($depData[1])
                        ->name($depData[0] . ', ' . $depData[2]);
                } else {
                    $s->departure()->noCode()->name(implode(', ', $depData));
                }
                $arrData = array_map("trim", explode("|", $m['arrInfo']));

                if (count($arrData) === 3) {
                    $s->arrival()
                        ->noCode()
                        ->terminal($arrData[1])
                        ->name($arrData[0] . ', ' . $arrData[2]);
                } else {
                    $s->arrival()->noCode()->name(implode(', ', $arrData));
                }
            }

            if (preg_match("/\n\s*{$this->opt($this->t('Llegada'))}:.+\s+.*?\b(?<iata>[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]+(?<number>\d+)[ ]{5,}{$this->t('Estado:')}[ ]*(?<status>.+)/",
                $root, $m)) {
                $s->airline()
                    ->name($m['iata'])
                    ->number($m['number']);
                $s->extra()->status($m['status']);
            }
            $s->airline()
                ->operator($this->re("/{$this->t('Operado por')}:[ ]*(.+)/", $root))
                ->confirmation($this->re("/{$this->t('Localizador de línea aérea')}[ ]*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\/([A-Z\d]{5,})(?:[ ]{3,}|\n|$)/",
                    $root));

            $s->extra()
                ->duration($this->re("/{$this->t('Duración estimada de vuelo')}[ ]*(.+?)(?:[ ]{3,}|\n|$)/", $root))
                ->cabin($this->re("/{$this->t('Clase de cabina')}[ ]*(.+?)(?:[ ]{3,}|\n|$)/", $root))
                ->aircraft($this->re("/{$this->t('Tipo de avión')}[ ]*(.+?)(?:[ ]{3,}|\n|$)/", $root))
                ->seat($this->re("/{$this->t('Número de asiento')}[ ]*(\d+[A-z])(?:[ ]{3,}|\n|$)/", $root), false,
                    true);

            $acc = $this->re("/{$this->t('Número de viajero frecuente')}[ ]*([A-Z\d]+)(?:[ ]{3,}|\n|$)/", $root);

            if (!empty($acc)) {
                $accounts[] = $acc;
            }
            $ticket = $this->re("/{$this->t('Número de billete')}[ ]*([\-\d]{7,})(?:[ ]{3,}|\n|$)/", $root);

            if (!empty($ticket)) {
                $tickets[] = $ticket;
            }
        }
        $accounts = array_unique($accounts);

        if (!empty($accounts)) {
            $r->program()->accounts($accounts, false);
        }
        $tickets = array_unique($tickets);

        if (!empty($tickets)) {
            $r->issued()->tickets($tickets, false);
        }
        $sum = $this->re("/\n[ ]*{$this->opt($this->t('Aéreo'))}[: ]+\n.+?[ ]{7,}(.+)\n\n/u", $footerInfo);
        $sum = $this->getTotalCurrency($sum);
        $r->price()
            ->currency($sum['Currency'])
            ->total($sum['Total']);

        return true;
    }

    private function parseHotelsPdf(array $hotels, Email $email, string $footerInfo)
    {
        $sum = $this->re("/\n[ ]*{$this->opt($this->t('Hotel'))}[: ]+(.+?)\n\n/us", $footerInfo);

        foreach ($hotels as $i => $root) {
            $r = $email->add()->hotel();
            $r->general()
                ->traveller($this->traveller);

            if (isset($this->otaConfirmation)) {
                if (is_array($this->otaConfirmation)) {
                    $cn = array_values($this->otaConfirmation);
                    $descr = array_keys($this->otaConfirmation);
                    $r->ota()->confirmation(array_shift($cn), array_shift($descr));
                } else {
                    $r->ota()->confirmation($this->otaConfirmation);
                }
            }

            if (isset($this->otaPhones)) {
                foreach ($this->otaPhones as $otaPhone) {
                    if (is_array($otaPhone)) {
                        $ph = array_values($otaPhone);
                        $descr = array_keys($otaPhone);
                        $r->ota()->phone(array_shift($ph), array_shift($descr));
                    } else {
                        $r->ota()->phone($otaPhone);
                    }
                }
            }

            if (preg_match("/{$this->opt($this->t('Entrada'))}:[ ]*(?<chIn>.+)\s+{$this->opt($this->t('Salida'))}:[ ]*(?<chOut>.+)/u",
                $root, $m)) {
                $r->booked()
                    ->checkIn($this->normalizeDate(trim($m['chIn'])))
                    ->checkOut($this->normalizeDate(trim($m['chOut'])));
            }

            if (preg_match("/{$this->opt($this->t('Salida'))}:[ ]*.+\s+(?<hName>.+)[ ]{3,}{$this->t('Estado:')}[ ]*(?<status>.+)/u",
                $root, $m)) {
                $hotelName = $m['hName'];
                $r->general()->status($m['status']);
            } else {
                $hotelName = $this->re('/^[\. ]+(.+?)[\. ]{7,}/u', $root);
            }
            $hotelName = trim($this->re("/(.+?)(?:,|$)/u", $hotelName));
            $r->hotel()->name($hotelName);

            $table = $this->re("/{$this->t('Estado:')}[ ]*[^\n]+\n(.+)/su", $root);
            $pos = array_slice($this->colsPos($table, 20), 0, 2);

            if (empty($pos)) {
                $this->logger->debug("other format {$i}-hotel");

                return false;
            }
            $table = $this->splitCols($table, $pos);

            if (preg_match("/{$this->t('Dirección del hotel')}:\s+(.+?)\s*(?:\[t\]\s*([\+\-\d\(\) ]{5,}))?\s*(?:{$this->t('Observaciones')}|$)/su",
                $table[0], $m)) {
                $r->hotel()->address(trim(preg_replace("/\s+/", ' ', $m[1])));

                if (isset($m[2])) {
                    $r->hotel()->phone(trim($m[2]));
                }
            }

            $r->general()->confirmation($confNo = $this->re("/{$this->t('Número de confirmación')}[ ]*([\w\-]+)/",
                $table[1]));

            if (isset($confNo)) {
                //try search cancellation
                $node = $this->http->FindSingleNode("//text()[normalize-space(.)='{$confNo}']/ancestor::*[{$this->contains($this->t('HOTEL'))}][1]/descendant::text()[{$this->starts($this->t('Política de cancelación'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

                if (!empty($node)) {
                    $r->general()->cancellation($node);
                    $this->detectDeadLine($r);
                }
            }
            $room = $r->addRoom();
            $room
                ->setType($this->re("/{$this->t('Tipo de habitación')}[ ]+(.+)/u", $table[1]))
                ->setDescription($this->re("/{$this->t('Tipo de régimen')}[ ]+(.+)/u", $table[1]));

            $sumHotel = $this->getTotalCurrency($this->re("/^[ ]*{$hotelName}.+?[ ]{7,}(.+)/miu", $sum));

            if (empty($sumHotel['Total'])) {
                $hotelNameHtml = $this->http->FindSingleNode("//text()[{$this->starts($this->t('HOTEL'))}]/following::td[1]");

                if (!empty($hotelNameHtml) && $hotelNameHtml !== $hotelName) {
                    $sumHotel = $this->getTotalCurrency($this->re("/^[ ]*{$hotelNameHtml}.+?[ ]{7,}(.+)/miu", $sum));
                }
            }

            $r->price()
                ->currency($sumHotel['Currency'])
                ->total($sumHotel['Total']);
        }

        return true;
    }

    private function parseEmail(Email $email)
    {
        // reset datas
        $this->traveller = $this->otaConfirmation = $this->otaPhones = null;

        foreach ($this->reSubject as $reSubject) {
            if (preg_match($reSubject, $this->subject, $m) && isset($m['pnr'])) {
                $this->otaConfirmation = $m['pnr'];

                break;
            }
        }
        $otaInfo = implode("\n",
            $this->http->FindNodes("//text()[{$this->eq($this->t('American Express Global Business Travel España'))}]/following::*[normalize-space()!=''][1]/descendant::text()[normalize-space()!='']"));
        $otaPhones = [];

        if (preg_match_all("/({$this->opt($this->t('otaPhones'))})[ ]*([\+\-\d \(\)]+)(?:\n|$)/iu", $otaInfo, $m,
            PREG_SET_ORDER)) {
            foreach ($m as $v) {
                if (!in_array(trim($v[2]), $otaPhones)) {
                    $this->otaPhones[] = [trim(preg_replace(['/\s*\[t\]/', '/: \[t\]/'], '', $v[1])) => trim($v[2])];
                    $otaPhones[] = trim($v[2]);
                }
            }
        }
        $this->traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('detalles del Itinerario E-Ticket [Billete Electronico] para'))}]",
            null, false,
            "/{$this->opt($this->t('detalles del Itinerario E-Ticket [Billete Electronico] para'))}\s*(.+?)\./");

        $xpath = "//text()[{$this->eq($this->t('[ VUELO ]'))}]/ancestor::*[{$this->contains($this->t('Salida'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH]: " . $xpath);
            $this->parseFlightsHtml($nodes, $email);
        }

        $xpath = "//text()[{$this->eq($this->t('[ HOTEL ]'))}]/ancestor::*[{$this->contains($this->t('Salida'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH]: " . $xpath);
            $this->parseHotelsHtml($nodes, $email);
        }

        return true;
    }

    private function parseFlightsHtml(\DOMNodeList $roots, Email $email)
    {
        $r = $email->add()->flight();

        $r->general()
            ->noConfirmation()
            ->traveller($this->traveller, true);

        if (isset($this->otaConfirmation)) {
            if (is_array($this->otaConfirmation)) {
                $cn = array_values($this->otaConfirmation);
                $descr = array_keys($this->otaConfirmation);
                $r->ota()->confirmation(array_shift($cn), array_shift($descr));
            } else {
                $r->ota()->confirmation($this->otaConfirmation);
            }
        }

        if (isset($this->otaPhones)) {
            foreach ($this->otaPhones as $otaPhone) {
                if (is_array($otaPhone)) {
                    $ph = array_values($otaPhone);
                    $descr = array_keys($otaPhone);
                    $r->ota()->phone(array_shift($ph), array_shift($descr));
                } else {
                    $r->ota()->phone($otaPhone);
                }
            }
        }

        foreach ($roots as $root) {
            $s = $r->addSegment();
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Compañía'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);
            $s->airline()->operator($node);
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Vuelo'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);

            if (preg_match("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $depDate = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Salida'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);
            $s->departure()->date($this->normalizeDate($depDate));
            $arrDate = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Llegada'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);
            $s->arrival()->date($this->normalizeDate($arrDate));
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Origen'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);
            $depData = array_map("trim", explode("|", $node));

            if (count($depData) === 3) {
                $s->departure()
                    ->noCode()
                    ->terminal($depData[1])
                    ->name($depData[0] . ', ' . $depData[2]);
            } else {
                $s->departure()->noCode()->name(implode(', ', $depData));
            }
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Destino'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);
            $arrData = array_map("trim", explode("|", $node));

            if (count($arrData) === 3) {
                $s->arrival()
                    ->noCode()
                    ->terminal($arrData[1])
                    ->name($arrData[0] . ', ' . $arrData[2]);
            } else {
                $s->arrival()->noCode()->name(implode(', ', $arrData));
            }
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Clase'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);

            if (preg_match("/^([A-Z]{1,2})\s+(.+)$/", $node, $m)) {
                $s->extra()
                    ->bookingCode($m[1])
                    ->cabin($m[2]);
            } else {
                $s->extra()->cabin($node);
            }

            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Duración'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                    $root))
                ->status($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Estado:'))}]/ancestor::td[1]",
                    $root, false, "/{$this->opt($this->t('Estado:'))}\s*(.+)/"));
        }
    }

    private function parseHotelsHtml(\DOMNodeList $roots, Email $email)
    {
        foreach ($roots as $root) {
            $r = $email->add()->hotel();
            $r->general()
                ->traveller($this->traveller, true);

            if (isset($this->otaConfirmation)) {
                if (is_array($this->otaConfirmation)) {
                    $cn = array_values($this->otaConfirmation);
                    $descr = array_keys($this->otaConfirmation);
                    $r->ota()->confirmation(array_shift($cn), array_shift($descr));
                } else {
                    $r->ota()->confirmation($this->otaConfirmation);
                }
            }

            if (isset($this->otaPhones)) {
                foreach ($this->otaPhones as $otaPhone) {
                    if (is_array($otaPhone)) {
                        $ph = array_values($otaPhone);
                        $descr = array_keys($otaPhone);
                        $r->ota()->phone(array_shift($ph), array_shift($descr));
                    } else {
                        $r->ota()->phone($otaPhone);
                    }
                }
            }

            $confNo = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Nº de confirmación:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);
            $r->general()->confirmation($confNo, trim($this->t('Nº de confirmación:'), ':'));
            $hotelName = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('HOTEL:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);
            $r->hotel()
                ->name($hotelName);
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Dirección'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);

            if (preg_match("/^(.+?)\s+\|\s*Tel\s+([\d\-\+ \(\)]+)$/", $node, $m)) {
                $r->hotel()
                    ->address($m[1])
                    ->phone($m[2]);
            }

            $checkIn = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Entrada'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);
            $checkOut = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Salida'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);

            $r->booked()
                ->checkIn($this->normalizeDate($checkIn))
                ->checkOut($this->normalizeDate($checkOut));
            $room = $r->addRoom();
            $room->setDescription($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Tipo de régimen'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root));
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Política de cancelación'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]",
                $root);

            $r->general()
                ->cancellation($node)
                ->status($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Estado:'))}]/ancestor::td[1]",
                    $root, false, "/{$this->opt($this->t('Estado:'))}\s*(.+)/"));

            $this->detectDeadLine($r);
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/EN CASO DE CANCELAR \| HACERLO ANTES DE (\d+) DIAS ANTES \| PARA EVITAR GASTOS/i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days', '00:00');
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //Lunes, 28 de Octubre de 2019 | 07:45h
            '/^\D+?\s+(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})[\|\s]+(\d+:\d+)h$/u',
            //Lunes, 28 de Octubre de 2019
            '/^\D+?\s+(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})$/u',
            //03/Dic/19 07:35h
            '/^(\d{2})\/(\D+)\/(\d{2})\s*(\d+:\d+)h$/u',
            //03/Dic/19
            '/^(\d{2})\/(\D+)\/(\d{2})$/u',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1 $2 $3, $4',
            '$1 $2 20$3, $4',
            '$1 $2 20$3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $body) > 0) {
                    if ($this->stripos($body, $reBody[0]) && $this->stripos($body, $reBody[1])) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Llegada"], $words["Salida"])) {
                if ($this->stripos($body, $words["Llegada"]) && $this->stripos($body, $words["Salida"])) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function strstrArr(string $haystack, $needle, bool $before_needle = false): ?string
    {
        $needles = (array) $needle;

        foreach ($needles as $needle) {
            if (in_array($this->lang, ['es'])) {
                $str = mb_strstr($haystack, $needle, $before_needle);
            } else {
                $str = strstr($haystack, $needle, $before_needle);
            }

            if (!empty($str)) {
                return $str;
            }
        }

        return null;
    }
}
