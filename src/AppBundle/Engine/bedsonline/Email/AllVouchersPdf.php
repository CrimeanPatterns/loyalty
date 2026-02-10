<?php

namespace AwardWallet\Engine\bedsonline\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AllVouchersPdf extends \TAccountChecker
{
    public $mailFiles = "bedsonline/it-36337741.eml, bedsonline/it-36373037.eml, bedsonline/it-36570733.eml, bedsonline/it-36805675.eml, bedsonline/it-38672681.eml, bedsonline/it-39127305.eml, bedsonline/it-42433626.eml, bedsonline/it-42433656.eml, bedsonline/it-42433732.eml, bedsonline/it-42589513.eml, bedsonline/it-43031266.eml, bedsonline/it-43124594.eml, bedsonline/it-45703346.eml, bedsonline/it-483319765.eml, bedsonline/it-483320393.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'refNumber'        => ['Reference number:', 'Reference number :', 'Reference Number:', 'Number:'],
            'paxName'          => ['Passenger name:', 'Passenger name :', 'Pax name:', 'Pax name :', 'Guest name:', 'Guest name :'],
            'Reference Number' => ['Reference Number', 'Reference Number', 'Reference'],
        ],
        'es' => [
            'Reference Number'                   => ['Número de referencia'],
            'refNumber'                          => ['Número de referencia :', 'Número de referencia:'],
            'paxName'                            => ['Nombre de los pasajeros :', 'Nombre de los pasajeros:', 'Nombre de los pasajeros'],
            'Booking date'                       => ['Fecha de reserva'],
            'Booking confirmed and guaranteed'   => ['Reserva confirmada y garantizada'],
            'Passenger name'                     => ['Nombre de los pasajeros'],
            'Agency Reference Number'            => ['Referencia de agencia'],
            'TourCMS Reference Number'           => ['Número de referencia TourCMS'],
            'From'                               => ['Desde'],
            'To'                                 => ['Hasta'],
            'Ticket type'                        => ['Tipo de entrada'],
            'Remarks'                            => ['Observaciones'],
            'Service time'                       => ['Horario de servicio'],
            'Notes'                              => ['Notas'],
            'Adults'                             => ['Adultos'],
            'Children'                           => ['Niños'],
            'Product Type'                       => ['Tipo de producto'],
            'Agency Reference'                   => ['Referencia de agencia'],
            'Service date'                       => ['Fecha de servicio'],
            'Adult'                              => ['Adulto'],
            'Child'                              => ['Niño'],
            'Pick-up point'                      => ['Punto de recogida'],
            'Pick-up time'                       => ['Horario de recogida'],
            'Voucher generated on'               => ['Bono generado el'],
        ],
    ];

    public $dateFormatMDY;

    private $subjects = [
        'en' => ['Voucher -'],
        'es' => ['Bonos  - '],
    ];

    private $formats = [
        'en' => [
            'hotel'    => ['Voucher - Hotel'],
            'ticket'   => ['Voucher - Ticket'],
            'transfer' => ['Voucher - Transfer'],
        ],
        'es' => [
            'hotel'    => ['Bono - Hotel'],
            'ticket'   => ['Bono - Entrada'],
            'transfer' => ['Voucher - Transfer'],
        ],
    ];

    /** @var \HttpBrowser */
    private $pdf;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'bedsonline.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Bedsonline') === false) {
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
        $pdfs = $parser->searchAttachmentByName('.*pd\W*f');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (strpos($textPdf, 'HOTELBEDS') === false
                && strpos($textPdf, 'Bedsonline') === false
                && strpos($textPdf, 'BUSYMOMSFAMILYTRAVEL') === false
                && strpos($textPdf, 'TRAVELCUBE') === false
                && strpos($textPdf, 'Booking confirmed and guaranteed - Voucher - Hotel') === false
                && strpos($textPdf, 'Booking confirmed and guaranteed - Voucher - Transfer') === false
                && strpos($textPdf, 'Booked and payable by Selected partners') === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf) && $this->detectFormat($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pd\W*f');

        foreach ($pdfs as $key => $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (!$this->assignLang($textPdf)) {
                continue;
            }

            $this->detectDateFormat($textPdf);

            $title = [];

            foreach ($this->formats[$this->lang] as $format => $phrases) {
                $title = array_merge($title, (array) $phrases);
            }

            $segments = $this->split("/\n( *\S.+\n{1,3}.*{$this->opt($title)})/", "\n\n" . $textPdf);

            foreach ($segments as $sText) {
                $format = $this->detectFormat($sText);

                if ($format === 'hotel') {
                    $this->parseHotelPdf($email, $sText);
                } elseif ($format === 'ticket') {
                    $this->parseTicketPdf($email, $sText);
                } elseif ($format === 'transfer') {
                    $this->pdf = clone $this->http;
                    $complex = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
                    $this->pdf->SetEmailBody($complex);
                    $this->parseTransferPdf($email, $sText);
                }
                $this->logger->debug("Format in PDF-{$key}: " . $format);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('AllVouchersPdf' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 3; // hotel, event, transfer
    }

    private function parseHotelPdf(Email $email, $text): void
    {
        // examples: it-36337741.eml, it-36373037.eml, it-36570733.eml, it-38672681.eml, it-43031266.eml

        $h = $email->add()->hotel();

        $info = $this->re("/Booking confirmed and guaranteed - Voucher - Hotel\s+(.+?)\s+From\s*:/s", $text);
        $infoTable = $this->splitCols($info, [0, strlen($this->re("/\n(.+)[ ]{5}(?:Guest name|Booking date|.*?Passenger name)(?:\\/[^:\n]*)?[ ]*:/", $info))]);

        // Travel Agency
        if (preg_match("/({$this->opt($this->t('refNumber'))})\s*([-\d]{6,})\s+/i", $infoTable[0] ?? '', $m)) {
            $h->ota()->confirmation($m[2], rtrim($m[1], ': '));
        }

        if (preg_match("/(Agency Reference Nº[ ]*:)[ ]*([-\dA-Z]{5,})\s*\n/i", $text, $m)) {
            $h->ota()->confirmation($m[2], rtrim($m[1], ': '));
        }

        // General
        $conf = $this->re("/Hotel confirmation reference[ ]*:[ ]*([-\dA-Z]{5,})\s+/", $text);

        if (empty($conf)) {
            $conf = $this->re("/Ref\. Supplier[ ]*:[ ]*([-\dA-Z]{5,})\s+/", $text);

            if (preg_match("/-H\d\s*$/", $conf)) {
                $conf = null;
            }
        }

        if (!empty($conf)) {
            $h->general()->confirmation($conf);
        } else {
            $h->general()->noConfirmation();
        }
        $paxNames = $this->re("/{$this->opt($this->t('paxName'))}[ ]*(.+)/i", $infoTable[1] ?? '');

        if ($paxNames) {
            $travellers = preg_split('/(\s*,\s*)+/', trim($paxNames, ', '));
            $travellers = array_filter($travellers, function ($v) {
                if (preg_match("/^x{5,}$/i", preg_replace('/\W+/', '', $v))) {
                    return false;
                }

                return true;
            });
            $h->general()->travellers($travellers);
        }

        // Hotel
        $hotelName = $this->re("/^(?:\s*\n)*(?:[ ]{15,}.*\n\s)?[ ]{0,10}(\S.+?)([ ]{3,}[^\w\s]+.*)?\n/", $infoTable[1] ?? '');

        if (empty($hotelName)) {
            $hotelName = $this->re("/\s*(.+)\s+[]{3,}/", $infoTable[1] ?? '');
        }
        //remove junk
        $hotelName = preg_replace("#\s*[/].*#", "", $hotelName);
        $hotelName = preg_replace("#[ ]{4,}[\s]*#", "", $hotelName); // Narita        

        $h->hotel()
            ->name($hotelName)
            ->phone($this->re("/{$this->opt($this->t('Telephone'))}[ ]*:[ ]*([+(\d][-. \d)(]{5,}[\d)])/", $text), true, true)
            ->fax($this->re("/{$this->opt($this->t('Fax'))}[ ]*:[ ]*([+(\d][-. \d)(]{5,}[\d)])/", $text), true, true);

        if (!empty($h->getHotelName())) {
            $h->hotel()->address($this->re("/\n[ ]{0,20}" . preg_quote($h->getHotelName(), '/') . "[ ]*.*\s*\n[ ]{0,20}(\S.+)/", $text));
        }

        // Booked
        $dateIn = $this->re("/From[ ]*:[ ]*(.+)[ ]+To[ ]*:[ ]*.+/", $text);
        $dateOut = $this->re("/From[ ]*:[ ]*.+[ ]+To[ ]*:[ ]*(.+)/", $text);

        if ($this->dateFormatMDY === null) {
            $this->detectDateFormatByDates($dateIn, $dateOut);
        }
        $h->booked()
            ->checkIn($this->normalizeDate($dateIn))
            ->checkOut($this->normalizeDate($dateOut));

        if (!empty($h->getCheckInDate()) && preg_match("/Check-in hour from (\d{1,2}:\d{1,2})[ ]*(?:\.|to)/", $text, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1], $h->getCheckInDate()));
        }
        // TODO: date adjustment
        $days = round(($h->getCheckOutDate() - $h->getCheckInDate()) / (60 * 60 * 24));
        $this->logger->debug('Days at the hotel ' . $days);

        if ($days >= 28) {
            $this->dateFormatMDY = false;
            $h->booked()
                ->checkIn($this->normalizeDate($dateIn))
                ->checkOut($this->normalizeDate($dateOut));

            if (!empty($h->getCheckInDate()) && preg_match("/Check-in hour from (\d{1,2}:\d{1,2})[ ]*(?:\.|to)/", $text, $m)) {
                $h->booked()->checkIn(strtotime($m[1], $h->getCheckInDate()));
            }
        }

        // general date after check in and check out date
        $bookingDate = $this->normalizeDate($this->re("/Booking date[ ]*:[ ]*(.+)/", $infoTable[1] ?? ''));

        if (!empty($bookingDate)) {
            $h->general()->date($bookingDate);
        }

        // Rooms
        $roomsText = $this->re("/Units[ ]+Room type[ ]+Adults[ ]+Children.*\n+([\s\S]+?)\n+\s*(?:Observaciones|Observations|Remarks)/", $text);

        if (preg_match_all("/^[ ]*(?<units>\d{1,3})x[ ]{2,}(?<type>.+?)[ ]{2,}(?<adults>.+?)[ ]{2,}(?<children>.+?)(?:[ ]{2,}|$)/im", $roomsText, $matches, PREG_SET_ORDER)) {
            $guests = null;
            $kids = null;
            $rooms = null;

            foreach ($matches as $m) {
                if (is_numeric($m['adults']) && is_numeric($m['children'])) {
                    $rooms += $m['units'];
                    $guests += $m['adults'];
                    $kids += $m['children'];

                    for ($i = 1; $i <= $m['units']; $i++) {
                        $h->addRoom()->setType($m['type']);
                    }
                }
            }
            $h->booked()
                ->guests($guests)
                ->kids(($kids !== null || ($kids === null && $guests !== null)) ? $kids : null)
                ->rooms($rooms);
        }
    }

    private function parseTicketPdf(Email $email, $text): void
    {
        // examples: it-42433626.eml, it-42433656.eml, it-42433732.eml, it-45703346.eml

        $r = $email->add()->event();
        $r->setEventType(EVENT_EVENT);

        $textTop = $this->re("/\n[ ]*{$this->opt($this->t('Booking confirmed and guaranteed'))}[^\n]+\n(.+?)\n[ ]*{$this->opt($this->t('From'))} \d+/s", $text);
        $pos[] = 0;
        $pos[] = mb_strlen($this->re("/\n([ ]*.*){$this->opt($this->t('Booking date'))}/", $textTop));

        if (empty($pos[1])) {
            $this->logger->debug('Other format (table top)!');

            return;
        }
        $tableTop = $this->splitCols($textTop, $pos);
        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('Reference Number'))}[\s:]+([-A-Z\d]+)/i", $tableTop[0] ?? ''))
            ->traveller($this->nice($this->re("/{$this->opt($this->t('Passenger name'))}[\s:]+(.+)\s+{$this->opt($this->t('Booking date'))}/s", $tableTop[1] ?? '')), true)
            ->date($this->normalizeDate($this->re("/{$this->opt($this->t('Booking date'))}[\s:]+(\d+\/\d+\/\d+)/", $tableTop[1] ?? '')));

        $confNo = $this->nice($this->re("/{$this->opt($this->t('Agency Reference Number'))}[\s:]+(.+(?:\n.+)?)\s+{$this->opt($this->t('TourCMS Reference Number'))}/", $tableTop[1] ?? ''));

        if (!empty($confNo)) {
            $r->ota()->confirmation(str_replace(" ", '', $confNo), 'Agency Reference Number');
        }
        $confNo = $this->nice($this->re("/{$this->opt($this->t('TourCMS Reference Number'))}[\s:]+(.+(?:\n.+)?)\s*$/", $tableTop[1] ?? ''));

        if (!empty($confNo)) {
            $r->ota()->confirmation(str_replace(" ", '', $confNo), 'TourCMS Reference Number');
        } else {
            $confNo = $this->nice($this->re("/{$this->opt($this->t('Agency Reference Number'))}[\s:]+(.+(?:\n.+)?)\s*$/", $tableTop[1] ?? ''));

            if (!empty($confNo)) {
                $r->ota()->confirmation(str_replace(" ", '', $confNo), 'Agency Reference Number');
            }
        }

        $r->place()->name($this->nice($this->re("/(.+){$this->opt($this->t('Passenger name'))}[\s:]+/s", $tableTop[1] ?? '')));

        if (preg_match("/\n[ ]*{$this->opt($this->t('From'))} (\d+\/\d+\/\d+) – {$this->opt($this->t('To'))} (\d+\/\d+\/\d+)\s+/", $text, $m)) {
            if ($m[1] !== $m[2]) {
                $this->logger->debug('Check format: event overnight(s)!');

                return;
            }
            $r->booked()->start($this->normalizeDate($m[1]));
        }
        $textMain = $this->re("/\n([ ]*{$this->opt($this->t('Ticket type'))}.+?)\n[ ]*{$this->opt($this->t('Remarks'))}/su", $text);
        $tableMain = $this->splitCols($textMain, $this->colsPos($textMain));

        if (count($tableMain) < 5 || count($tableMain) > 6) {
            $this->logger->debug('Other format main table!');

            return;
        }
        $time = $this->re("/{$this->opt($this->t('Service time'))}\s+(\d+:\d+)/", $tableMain[1]);

        if (!empty($time)) {
            $r->booked()->start(strtotime($time, $r->getStartDate()));
        } elseif (!empty($time = $this->re("/Ticket type\s+At\s+(\d+(?::\d+)?(?:\s*[ap]m)?)/i", $tableMain[0]))) {
            $r->booked()->start(strtotime($time, $r->getStartDate()));
        }

        if (isset($tableMain[5]) && preg_match("/{$this->opt($this->t('Notes'))}\s+(\d+:\d+)-(\d+:\d+)/", $tableMain[5], $m)) {
            $r->booked()->end(strtotime($m[2], $r->getStartDate()));
        } else {
            $r->booked()->noEnd();
        }
        $r->booked()
            ->guests((int) $this->re("/{$this->opt($this->t('Adults'))}\s+(\d+)/", $tableMain[2]))
            ->kids((int) $this->re("/{$this->opt($this->t('Children'))}\s+(\d+)/", $tableMain[3]));

        $remarks = $this->re("/\n[ ]*{$this->opt($this->t('Remarks'))}[ :]+(.+?)\n\n/s", $text);

        if (!empty($remarks)) {
            $remarks = preg_replace('/\s+/', ' ', trim($remarks));

            if (preg_match("/To join your tour please go to:\s*(.+?)\s*Duration of the tour:/s", $remarks, $m)
                || preg_match("/visit a\s+(.{3,70}?)\s+to enter the/", $remarks, $m)
                || preg_match("/(?:Pick-up from|From)(.+)/s", $tableMain[0], $m)
                || preg_match("/Punto de encuentro:(.{3,500}?)\\/\\//s", $remarks, $m)
            ) {
                $r->place()->address($this->nice($m[1]));
            } elseif (preg_match("/Meeting point:\s*(?<address>.{3,500}?)[\/\s]*(?:Meeting point instructions:|Start time:|Meeting time:|Pick up time:)/s", $remarks, $m)
                || preg_match("/(?<address>Departure points found at\s+.{3,500}?)\s*The tour will last/s", $remarks, $m) // it-45703346.eml
            ) {
                $type = $this->re("/Ticket type\s+In\s+(.+)/", $tableMain[0]) // it-42433626.eml
                    ?? $this->re("/Ticket type\s+(Classic)\s+Ticket/", $tableMain[0]); // it-45703346.eml

                if (!empty($type) && !empty($this->re("/(?:^|\. )(For )/", $m['address']))
                    && (preg_match("/For .*?\b{$this->opt($type)}\b[^:]*guide:\s*(.+?)\./s", $m['address'], $v) // it-42433626.eml
                        || preg_match("/For [^.]*?\b{$this->opt($type)}\b[^.]*points being\s*(.+?)\./s", $m['address'], $v) // it-45703346.eml
                    )
                ) {
                    $r->place()->address($this->nice($v[1]));
                } else {
                    $r->place()->address($this->nice($m['address']));
                }
            }
        }
    }

    private function parseTransferPdf(Email $email, $text): void
    {
        // examples: it-36805675.eml, it-39127305.eml, it-42589513.eml, it-43124594.eml
        $segments = $this->split("/((?:^|\n)\s*VOUCHER - TRANSFERS\n)/", $text);

        foreach ($segments as $stext) {
            $t = $email->add()->transfer();
            $t->obtainTravelAgency();

            $info = $this->re("/\n([ ]*{$this->opt($this->t('Reference Number'))}[ ]*\:?\s*[\s\S]+?)\n\s*{$this->opt($this->t('Product Type'))}/i", $stext);
            $infoTable = $this->splitCols($info, $this->rowColsPos($this->inOneRow($info)));

            // Travel Agency
            $conf = $this->re("/{$this->opt($this->t('Reference Number'))}[ ]*:\s*([\-\d\s]{6,})(?:\s+|\s*$)/si", $infoTable[0] ?? '');

            if (empty($conf) && preg_match("/Reference\n\s*Number\:\n+\s*(\d+\-)\s+[A-Z\d]+\n+(\d{6,})/", $infoTable[0], $m)) {
                $conf = $m[1] . $m[2];
            }

            if (!empty($t->getTravelAgency())
                && !in_array($conf, array_column($t->getTravelAgency()->getConfirmationNumbers(), 0))
            ) {
                $t->ota()->confirmation(str_replace([" ", "\n"], '', $conf), 'Reference number');
            }

            // General
            if (preg_match("/({$this->opt($this->t('Agency Reference'))}(?:\s+Nº)?)\s*:\s*(?:TO\\#|IATA[ ]+)?(.+)/is", $infoTable[1] ?? '', $m)) {
                $t->general()->confirmation(preg_replace('/\s+/', '', $m[2]), $m[1]);
            } elseif (preg_match("/Reference\n\s*Number\:\n+\s*\d+\-\s+([A-Z\d]+)\n+\d{6,}$/", $infoTable[0], $m)) {
                $t->general()->confirmation($m[1]);
            } else {
                $t->general()->noConfirmation();
            }

            $paxNames = $this->re("/{$this->opt($this->t('paxName'))}\s*(.+)/i", $infoTable[2] ?? '');

            if (empty($paxNames)) {
                $paxNames = $this->re("/{$this->opt($this->t('paxName'))}\s*\n(.+)\s*\,/sui", $infoTable[3] ?? '');
            }

            if ($paxNames) {
                $travellers = preg_split('/(\s*,\s*)+/', trim($paxNames, ', '));
                $travellers = array_filter($travellers, function ($v) {
                    if (preg_match("/^x{5,}$/i", preg_replace('/\W+/', '', $v))) {
                        return false;
                    }

                    return true;
                });
                $t->general()->travellers($travellers);
            }

            // Segment
            $s = $t->addSegment();

            // Departure, Arrival
            $points = $this->re("/\n[ ]*{$this->opt($this->t('From'))}[ ]+{$this->opt($this->t('To'))}[ ]+.*\s*\n([\s\S]+)\n\s*{$this->opt($this->t('Service date'))}/i", $stext);
            $pointsTable = array_map('trim', $this->splitCols($points, $this->rowColsPos($this->inOneRow($points))));

            if (count($pointsTable) !== 3) {
                // search arrive for delimiting colomns (it-43124594.php)
                $node = $this->pdf->FindSingleNode("//text()[normalize-space()='From']/ancestor::p[1]/following-sibling::p[position()<4][normalize-space()='To']/following-sibling::p[1]");
                $firstWordsArrive = $this->re("/^(.{10})/u", $node);
                $pos[] = 0;
                $pos[] = mb_strlen($this->re("/^(.+?){$firstWordsArrive}/m", $points));
                $pos[] = mb_strlen($this->re("/^(.+?)\b\d{1,3} {$this->opt($this->t('Adult'))}/im", $points));
                $pointsTable = array_map('trim', $this->splitCols($points, $pos));
            }

            if (count($pointsTable) === 3) {
                $addressName = ['Airport', 'Aeropuerto', 'Train Station'];
                $pointsTable = preg_replace("/^\s*([\w\s]+),\s*([\w\s+]+\s+{$this->opt($addressName)}|{$this->opt($addressName)}\s+[\w\s]+)$/", "$2, $1", $pointsTable);

                if (preg_match("/{$this->opt($addressName)}/", $pointsTable[0])) {
                    $pointsTable[0] = preg_replace("/\s*\n\s*/", ' ', $pointsTable[0]);
                }

                if (preg_match("/{$this->opt($addressName)}/", $pointsTable[1])) {
                    $pointsTable[1] = preg_replace("/\s*\n\s*/", ' ', $pointsTable[1]);
                }
                $pointsTable = preg_replace("/\s*\n\s*/", ", ", $pointsTable);

                $s->departure()->name($pointsTable[0] ?? null);
                $s->arrival()->name($pointsTable[1] ?? null);

                if (preg_match("/\b(\d{1,3}) {$this->opt($this->t('Adult'))}/i", $pointsTable[2] ?? '', $m)) {
                    $s->extra()->adults($m[1]);
                }

                if (preg_match("/\b(\d{1,3}) {$this->opt($this->t('Child'))}/i", $pointsTable[2] ?? '', $m)) {
                    $s->extra()->kids($m[1]);
                }
            }

            $date = $this->re("/\n[ ]{0,20}{$this->opt($this->t('Service date'))}[ ]{2,}{$this->opt($this->t('Pick-up point'))}(?:.*\n){1,3}?[ ]{0,20}([\d\/]{6,})\s+/i", $stext);

            if (empty($date)) {
                $date = str_replace('/', '.', $this->re("/{$this->opt($this->t('Service date'))}\n*([\d\/]+)\n*{$this->opt($this->t('Pick-up time'))}/i", $stext));
            }

            $time = $this->re("/\n[ ]{0,20}{$this->opt($this->t('Pick-up time'))}(?:[ ]{2,}.+)?\n(?:.*\n){0,3}?[ ]{0,20}(\d{1,2}:\d{2})(?:\s{2,}|\n)/i", $stext);

            if (preg_match("/{$this->opt($this->t('Voucher generated on'))} \d{4}\/\d{2}\/\d{2}/i", $stext)) {
                $this->dateFormatMDY = false;
            }

            if (!empty($date) && !empty($time)) {
                $s->departure()->date(strtotime($time, $this->normalizeDate($date)));
                $s->arrival()->noDate();
            }

            // Extra
            $s->extra()->type(preg_replace('/\s+/', ' ', $this->re("/\n\s*{$this->opt($this->t('Product Type'))}\s*\n\s*(.+)\n/i", $stext)));
        }
    }

    private function detectFormat(?string $text): string
    {
        if (empty($text) || !isset($this->formats, $this->lang) || empty($this->formats[$this->lang])) {
            return 'unknown';
        }

        foreach ($this->formats[$this->lang] as $format => $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if (strpos($text, $phrase) !== false) {
                    return $format;
                }
            }
        }

        return 'unknown';
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['refNumber']) || empty($phrases['paxName'])) {
                continue;
            }

            if ($this->striposArray($text, $phrases['refNumber']) !== false
                && $this->striposArray($text, $phrases['paxName']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function striposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strripos($text, $phrase) : stripos($text, $phrase);

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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function nice(?string $str): string
    {
        return preg_replace('/\s+/', ' ', trim($str));
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

    private function colsPos($table, $delta = 5): array
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
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $delta) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
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

    private function detectDateFormat($text)
    {
        if (preg_match_all('/\s(\d{2})\/(\d{2})\/\d{2,4}(?:\s|$)/', $text, $m)) {
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
        if (preg_match('/^\s*(\d{2})\/(\d{2})\/(\d{2,4})\s*$/', $dateIn, $m1)
            && preg_match('/^\s*(\d{2})\/(\d{2})\/(\d{2,4})\s*$/', $dateOut, $m2)
        ) {
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
            // 04/05/2019    |    04/05/19
            '/^\s*(\d{2})\/(\d{2})\/(\d{4}).*?$/',
        ];

        if ($this->dateFormatMDY === false) {
//            $this->logger->debug('dateFormatMDY: false');
            $out = [
                '$1.$2.$3',
            ];
        } else {
//            $this->logger->debug('dateFormatMDY: true');
            $out = [
                '$2.$1.$3',
            ];
        }
        $str = preg_replace($in, $out, $str);

        if (preg_match('/\d{1,2}\s+([^\d\s]{3,})\s+\d{2,4}/', $str, $m)) {
            if (($en = MonthTranslate::translate($m[1], $this->lang))) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
