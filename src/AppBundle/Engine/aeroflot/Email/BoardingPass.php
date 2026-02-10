<?php

namespace AwardWallet\Engine\aeroflot\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

// pdf format for alitalia, aerolineas and  malindoair may be in malindoair/BoardingPassPdf

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-10278816.eml, aeroflot/it-11906854.eml, aeroflot/it-11973471.eml, aeroflot/it-12000013.eml, aeroflot/it-12318384.eml, aeroflot/it-194669705.eml, aeroflot/it-3002621.eml, aeroflot/it-30975153.eml, aeroflot/it-31060445.eml, aeroflot/it-3664225.eml, aeroflot/it-4195079.eml, aeroflot/it-42499155.eml, aeroflot/it-4418209.eml, aeroflot/it-4523423.eml, aeroflot/it-4535765.eml, aeroflot/it-4617845.eml, aeroflot/it-4636744.eml, aeroflot/it-4640339.eml, aeroflot/it-4670634.eml, aeroflot/it-4690420.eml, aeroflot/it-4704649.eml, aeroflot/it-4795590.eml, aeroflot/it-4868028.eml, aeroflot/it-4874921.eml, aeroflot/it-4969092.eml, aeroflot/it-4969094.eml, aeroflot/it-4969472.eml, aeroflot/it-5012461.eml, aeroflot/it-5069970.eml, aeroflot/it-5070009.eml, aeroflot/it-5070044.eml, aeroflot/it-5088506.eml, aeroflot/it-5106696.eml, aeroflot/it-5481747.eml, aeroflot/it-5591757.eml, aeroflot/it-6192531.eml, aeroflot/it-6224719.eml, aeroflot/it-6398185.eml, aeroflot/it-664185197.eml, aeroflot/it-9094632.eml, aeroflot/it-9112214.eml, aeroflot/it-9887066.eml, aeroflot/it-9941452.eml";

    public $reBody = [
        'ru' => [
            ['Благодарим Вас за то, что Вы воспользовались сервисом Онлайн-регистрация'],
            'Маршрут',
        ],
        'el' => [
            ["Σας ευχαριστούμε που χρησιμοποιείτε την υπηρεσία Mobile check-in"],
            'Αριθμός επιβεβαίωσης',
        ],
        'pt' => [
            ["Obrigado por usar os nossos serviços.", "Obrigado por usar nosso serviço de Check-in Online", "Agradecemos por usar nosso serviço de check-in móvel."],
            ["Detalhes do itinerário", "Detalhes da viagem para"],
        ],
        'fr' => [
            ["Merci d'avoir choisi nos services"],
            "Détails de l’itinéraire",
        ],
        'it' => [
            [
                "Grazie per aver scelto il nostro servizio di check-in su dispositivo mobile.",
                "Grazie per utilizzare il nostro servizio di check-in online",
            ],
            "Dettagli itinerario",
        ],
        'es' => [
            [
                "Encontrarás adjunto a este correo tu pase",
                "Gracias por usar nuestro servicio de Check-in móvil.",
                "Gracias por usar nuestro servicio de registro móvil",
                "Gracias por usar nuestro servicio Check-in Online",
            ],
            ["Detalles del itinerario", 'Detalles de itinerario para', 'Dettagli itinerario per'], // Detalles de itinerario para - gl
        ],
        'en' => [
            [
                'Thank you for using our Web Check-in Service',
                'Thank you for using our Online Check-in service',
                'Thank you for using our Mobile Check-in Service',
                'Thank you for choosing',
                'Thank you for using our Services.',
                "You've checked-in online",
                'Thank you for using our Online Check-in Service',
                'Thanks you for using our Online Check-in service.',
                'Mobile Check-in Reminders',
                'Web Check-in reminders',
            ],
            'Itinerary Details',
        ],
    ];

    public $lang;
    public $pdfName = '';
    public $date;

    public static $dict = [
        'ru' => [
            'attachedPDF'         => ['Ваш посадочный талон (формат PDF) во вложении.'],
            'mobileURL'           => ['НЕОБХОДИО ОПРЕДЕЛИТЬ'],
            'imgsrc'              => ['kulula', 'gulfair', 'sabresonicweb.com', 'alitalia.com', 'etihad.com'],
            'Confirmation Number' => 'Код бронирования',
            'Passenger'           => 'Имя пассажира',
            'Flight'              => 'Рейс',
            //            'Sold as' => '',
            //            'operated by' => '',
            'DEPARTS'             => 'ОТПРАВЛЕНИЕ',
            'ARRIVES'             => 'ПРИБЫТИЕ',
            //'SEAT'=>'',
            'TERMINAL' => 'Терминал',
        ],
        'el' => [
            'attachedPDF'         => ['NOTTRANSLATED'],
            'mobileURL'           => ['Βρείτε παρακάτω την κάρτα επιβίβασης για κινητές συσκευές'],
            'imgsrc'              => ['kulula', 'gulfair', 'sabresonicweb.com', 'alitalia.com', 'etihad.com'],
            'Confirmation Number' => 'Αριθμός επιβεβαίωσης',
            'Passenger'           => 'Όνομα/τα επιβάτη',
            'Flight'              => 'Πτήση',
            //            'Sold as' => '',
            //            'operated by' => '',
            'DEPARTS'             => 'ΑΝΑΧΩΡΗΣΗ',
            'ARRIVES'             => 'ΑΦΙΞΗ',
            'SEAT'                => 'ΘΕΣΗ',
            'TERMINAL'            => 'ΤΕΡΜΑΤΙΚΟΣ ΣΤΑΘΜΟΣ',
        ],
        'pt' => [
            'attachedPDF'         => ['Em anexo encontra o seu cartão de embarque em formato PDF'],
            'mobileURL'           => ['Abaixo do seu cartão de embarque eletrônico encontra-se o QR code', 'Sua viagem está cada vez mais perto! Caso tenha recebido seu cartão de embarque com o código QR',
                'Veja seu cartão de embarque móvel abaixo e também', ],
            'imgsrc'              => ['kulula', 'gulfair', 'sabresonicweb.com', 'alitalia.com', 'etihad.com'],
            'Confirmation Number' => ['Número de referência', 'Código de reserva:', 'Número de Confirmação'],
            'Passenger'           => ['Nomes dos passageiros', 'Nomes dos passageiros', 'Passageiros'],
            'Flight'              => ['Voo', 'Detalhes do itinerário para:', 'Detalhes da viagem para:'],
            //            'Sold as' => '',
            //            'operated by' => '',
            'DEPARTS'             => ['PARTIDA', 'Partida'],
            'ARRIVES'             => ['CHEGADA', 'Chegada'],
            'SEAT'                => ['ASSENTO', 'Assento:'],
            'TERMINAL'            => ['TERMINAL', 'Terminal'],
        ],
        'fr' => [
            'attachedPDF'         => ["Veuillez trouver ci-joint votre carte d'embarquement sous format PDF."],
            'mobileURL'           => ['NOTTRANSLATED'],
            'imgsrc'              => ['kulula', 'gulfair', 'sabresonicweb.com', 'alitalia.com', 'etihad.com'],
            'Confirmation Number' => 'Numéro de référence',
            'Passenger'           => 'Nom du ou des passagers',
            'Flight'              => 'Vol',
            //            'Sold as' => '',
            //            'operated by' => '',
            'DEPARTS'             => 'DÉPART',
            'ARRIVES'             => 'ARRIVÉE',
            'SEAT'                => 'SIÈGE',
            'TERMINAL'            => 'TERMINAL',
        ],
        'it' => [
            'attachedPDF' => ['NOTTRANSLATED'],
            'mobileURL'   => [
                'Qui sotto trovate la vostra carta d’imbarco elettronica',
                "A continuazione la carta d'imbarco elettronica con codice QR",
                'Se hai ricevuto una carta d\'imbarco con il codice QR puoi presentarla dal tuo dispositivo mobile',
            ],
            'imgsrc'              => ['kulula', 'gulfair', 'sabresonicweb.com', 'alitalia.com', 'etihad.com'],
            'Confirmation Number' => ['Numero di conferma', 'Codice di prenotazione:'],
            'Passenger'           => 'Nome/i passeggero/i',
            'Flight'              => ['Volo', 'Dettagli itinerario per:'],
            'Sold as'             => 'Venduto come',
            //            'operated by' => '',
            'DEPARTS'             => ['PARTENZA', 'In partenza'],
            'ARRIVES'             => ['ARRIVO', 'In arrivo'],
            'SEAT'                => ['POSTO', 'Posto'],
            'TERMINAL'            => 'TERMINAL',
        ],
        'es' => [
            'attachedPDF' => ['Encontrarás adjunto a este correo tu pase de Abordar'],
            'mobileURL'   => [
                'A continuación encontrará su Pase de Abordar Móvil.',
                'A continuación encontrará su tarjeta de embarque móvil.',
                'deberá presentar al llegar al aeropuerto usando su dispositivo móvil',
                'Tarjeta de embarque para',
            ],
            'imgsrc'              => ['kulula', 'gulfair', 'sabresonicweb.com', 'alitalia.com', 'etihad.com', 'sabre.com'],
            'Confirmation Number' => ['Número de confirmación:', 'Número de confirmación', 'Código de reserva:'],
            'Passenger'           => ['Nombre del pasajero', 'Pasajeros'],
            'Flight'              => ['Vuelo', 'Detalles de itinerario para:'],
            //            'Sold as' => '',
            //            'operated by' => '',
            'DEPARTS'             => ['SALIDA', 'SALE', 'Partidas'],
            'ARRIVES'             => ['LLEGADA', 'LLEGA', 'Llegadas'],
            'SEAT'                => ['ASIENTO', 'Asiento:'],
            'TERMINAL'            => ['TERMINAL', 'Terminal'],
        ],
        'en' => [
            'attachedPDF' => [
                'Please find attached your boarding pass in a PDF format',
                'Please find attached your Web Check-in boarding pass in a PDF format',
                'Please find your boarding pass attached',
                'Please find attached your Web Check-in boarding pass in PDF format',
            ],
            'mobileURL' => [
                'Please find your mobile boarding pass below',
                "You've checked-in online",
                "we have attached the Web Check-in boarding pass",
                "you must present at the airport using your mobile device",
                'Mobile Check-in',
                'reminders with regard to your online check-in:',
                'Web Check-in reminders',
                'Pick up your boarding pass at the airport',
                'Web Check-in Reminders',
            ],
            'imgsrc' => [
                'kulula',
                'gulfair',
                'sabresonicweb.com',
                'alitalia.com',
                'etihad.com',
                'philippineairlines.com',
            ],
            'Passenger'           => ['Passenger Name', 'Passengers'],
            'Flight'              => ['Flight', 'Itinerary Details for:', 'Itinerary Details for'],
            //            'Sold as' => '',
            //            'operated by' => '',
            'Confirmation Number' => [
                'Confirmation Number',
                'Booking Code',
                'Reference Number',
                'Record Locator',
                'booking code',
                'Reservation code:',
                'CONFIRMATION NUMBER',
                'PNR',
            ],
            'DEPARTS'  => ["DEPARTS", "Departs", "Departure"],
            'ARRIVES'  => ["ARRIVES", "Arrives", "Arrival"],
            'TERMINAL' => ['TERMINAL', 'Terminal'],
            'SEAT'     => ['SEAT', 'Seat'],
        ],
    ];

    private $code = '';

    private static $headers = [
        'kulula' => [
            'from' => ['kulula.com'],
            'subj' => [
                "#kulula-Boarding Pass from .+? to .+? \(?[A-Z\d]+\)?#",
                "#your boarding pass for kulula - .+? from .+? to \(?[A-Z\d]+\)?#",
            ],
        ],
        'gulfair' => [
            'from' => ['gulfair.com', 'web.checkin@gulfair.com', 'no-reply@gulfair.com'],
            'subj' => [
                '#Your Boarding Pass for Gulf Air - .+? from .+? to \(?[A-Z\d]+\)?#',
                '#Gulf Air-Boarding Pass .+? \(?[A-Z\d]+\)?#',
            ],
        ],
        'aeroflot' => [
            'from' => ['onlinecheckin@aeroflot.ru', 'noreply@aeroflot.ru'],
            'subj' => [
                '#Aeroflot Online Check-In Boarding Pass \(?[A-Z\d]+\)?#',
                '#Your Boarding Pass for Aeroflot Bonus - .+? from .+? to \(?[A-Z\d]+\)?#',
                '#Онлайн-регистрация Аэрофлота. Посадочный талон \(?[A-Z\d]+\)?#',
                '#Aeroflot Russian Airlines-Boarding Pass from .+? to .+? \(?[A-Z\d]+\)?#',
            ],
        ],
        'lionair' => [
            'from' => ['lionair.co.id'],
            'subj' => ['/Lion Air/'],
        ],
        'alitalia' => [
            'from' => ['@alitalia.com'],
            'subj' => ['/Alitalia/'],
        ],
        'aeromexico' => [
            'from' => ['@aeromexico.com'],
            'subj' => ['/Aeromexico/'],
        ],
        'airserbia' => [
            'from' => ['@airserbia.com'],
            'subj' => ['/(?:^|:\s*)JU (?i)Reservation\s*:/'],
        ],
        'cayman' => [
            'from' => ['@caymanairways.sabre.com'],
            'subj' => ['/Cayman Airways/'],
        ],
        'airmalta' => [
            'from' => ['@airmalta.com'],
            'subj' => ['/Air Malta/'],
        ],
        'aerolineas' => [
            'from' => ['@aerolineas.com'],
            'subj' => [],
        ],
        'etihad' => [
            'from' => ['@etihad.ae'],
            'subj' => ['/Etihad-Boarding\s+Pass/i'],
        ],
        'mabuhay' => [
            'from' => ['@philippineairlines.com'],
            'subj' => ['/Philippine Airlines\s*-\s*Boarding Pass from/i'],
        ],
        'oman' => [
            'from' => ['@omanair.com'],
            'subj' => ['/Oman Air .+? Confirmation from/i'],
        ],
        'belavia' => [
            'from' => ['@belavia.by'],
            'subj' => ['/Belavia-Boarding Pass from .+ to .+/i'],
        ],
        'silverairways' => [
            'from' => ['@silverairways.com>'],
            'subj' => ['/Silver Airways-(?:Boarding Pass|Check-in Confirmation) from .+ to .+/i'],
        ],
    ];

    private $bodies = [
        'kulula' => [
            "//img[@alt='Boarding pass barcode' and contains(@src,'check-in.kulula.mobi')]",
            'kulula',
        ],
        'gulfair' => [
            "//img[@alt='Boarding pass barcode' and contains(@src,'mci.gulfair.com')]",
            'Gulf Air',
        ],
        'aeroflot' => [
            "//img[@alt='Boarding pass barcode' and contains(@src,'sabresonicweb.com')]",
            'Sincerely yours, Aeroflot',
            'Искренне Ваш, Аэрофлот',
        ],
        'lionair' => [
            'Thank you for choosing Lion Air',
        ],
        'alitalia' => [
            "//img[@alt='Boarding pass barcode' and contains(@src,'alitalia.com')]",
        ],
        'aeromexico' => [
            'Gracias por elegir Aeroméxico',
        ],
        'cayman' => [
            'Thank you for choosing Cayman Airways',
            'to be boarded on any Cayman Airways',
        ],
        'airmalta' => [
            'Thank you for choosing Air Malta',
        ],
        'aerolineas' => [
            "//img[@alt='Boarding pass barcode' and contains(@src,'aerolineas.com')]",
        ],
        'etihad' => [
            "//img[contains(@src,'//mbooking.etihad.com')] | //text()[contains(.,'please visit an Etihad Airways')]",
        ],
        'mabuhay' => [
            "//text()[contains(normalize-space(.),'Philippine Airlines')]",
        ],
        'oman' => [
            "//text()[contains(normalize-space(.),'Oman ')]",
        ],
        'belavia' => [
            'Thank you for choosing BELAVIA',
            "//a[contains(@href, '.belavia.by')]",
        ],
    ];

    private $patterns = [
        'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $source = $this->http->Response['body'];

        if (!self::detectEmailByBody($parser)) {
            $this->logger->debug('can\'t determine body. wrong format. but try parse the source');
            $this->http->SetEmailBody($source);
        }

        if (!$this->assignLang()) {
            $this->logger->info("can't determinate language");

            return $email;
        }

        if ($code = $this->getProvider($parser)) {
            $email->setProviderCode($code);
        }

        /* no need include in BoardingPass print-boardingPass
        $w = $this->t('attachedPDF');
        if (!is_array($w)) $w = [$w];
        $rule = implode(' or ', array_map(function ($s) {
            return "contains(normalize-space(.),'{$s}')";
        }, $w));
        if ($this->http->XPath->query("//text()[{$rule}]")->length > 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
            if (count($pdfs) === 1) {
                $pdfAtt = $parser->getAttachment($pdfs[0]);
                if (!empty($pdfAtt['headers']) && is_array($pdfAtt['headers'])) {
                    foreach ($pdfAtt['headers'] as &$header) {
                        if (!is_array($header) && (preg_match("/name=\s*(['\"])(.*pdf)\\1/i", $header, $m) || preg_match("/name=(.*pdf)$/i", $header, $m1))) {
                            if (isset($m))
                                $this->pdfName = $m[2];
                            elseif (isset($m1)) $this->pdfName = $m1[1];;
                        }
                    }
                }
            }
        }
*/

        $f = $email->add()->flight();
        $this->parseEmail($email, $f);
        $email->setType('BoardingPass' . ucfirst($this->lang));

        $PNRs = array_column($f->getConfirmationNumbers(), 0);

        if (count($PNRs) === 0) {
            return $email;
        }

        // tickets (from PDF-attachments)
        $tickets = [];
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !preg_match("/{$this->opt($this->t('Record Locator'))}.+{$this->opt($this->t('eTicket'))}/s", $textPdf)) {
                continue;
            }

            if (preg_match_all("/{$this->opt($this->t('Record Locator'))}[ ]*[:]+[ ]*{$this->opt($PNRs)}(?:\n+.+){0,4}\n+.*{$this->opt($this->t('eTicket'))}[ ]*[:]+[ ]*({$this->patterns['eTicket']})\n/", $textPdf, $ticketMatches)) {
                foreach ($ticketMatches[1] as $tkt) {
                    if (!in_array($tkt, $tickets)) {
                        $f->issued()->ticket($tkt, false);
                        $tickets[] = $tkt;
                    }
                }
            }
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->unitDetectByBody()) {
            return true;
        }

        if ($this->http->XPath->query("//a[contains(@href,'https://www.boxbe.com/')]")->length > 0) {
            // many letters with information in html-attachments (www.boxbe.com)
            $htmls = $this->getHtmlAttachments($parser);

            foreach ($htmls as $html) {
                $this->http->SetEmailBody($html);

                if ($this->unitDetectByBody()) {
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
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'aeroflot.ru') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (preg_match($subj, $headers['subject'])) {
                    $bySubj = true;
                }
            }

            if ($byFrom || $bySubj) {
                $this->code = $code;
            }

            if ($bySubj) {
                return true;
            }
        }

        return false;
    }

    public function dateStringToEnglish(string $date): string
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code) && !empty($this->code)) {
            return $this->code;
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        && !(stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        continue 2;
                    }
                }

                return $code;
            }
        }

        return null;
    }

    private function parseEmail(Email $email, Flight $f): void
    {
        $recordLocator = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation Number'))}]/following::*[string-length(normalize-space())>3][1]", null, true, "/^[A-Z\d]{5,35}$/")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation Number'))}]", null, true, "/:\s*([A-Z\d]{5,35})$/");

        $f->general()->confirmation($recordLocator, $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation Number'))}]", null, false, "/(.+?)(?::|$)/"), true);

        $containsDigit = implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]));

        $passengers = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Passenger'))}]/ancestor::p[1]/descendant::text()[normalize-space()][not({$this->contains($this->t('Passenger'))}) and contains(normalize-space(),' ') and not({$containsDigit}) and not({$this->contains($this->t(' FF'))})]", null, "#.+[A-Z]{3}.+#"));

        if (count($passengers) === 0) {
            $passengersVal = $this->http->FindSingleNode("//div[preceding::text()[normalize-space()][1][{$this->eq($this->t('Passenger'))}] or {$this->eq('passengers', '@class')}]", null, true, '/^[[:alpha:]][-.\'[:alpha:], \\/]*[[:alpha:]]$/u');
            $passengers = $passengersVal ? [$passengersVal] : [];
        }

        if (count($passengers) === 0) {
            $passengersVal = $this->http->FindSingleNode("//node()[{$this->eq($this->t('Passenger'))}]/following-sibling::node()[normalize-space()][1]");
            $passengers = $passengersVal ? [$passengersVal] : [];
        }

        if (count($passengers) > 0) {
            $np = [];

            foreach ($passengers as $p) {
                $np = array_merge($np, array_filter(preg_split('/\s*,\s*/', $p)));
            }
            $np = preg_replace("/ (mr|mrs|ms|miss|dr|mstr)\.?\s*$/i", '', $np);
            $np = preg_replace("/^\s*([A-Z][^\\/]+?)\s*\\/\s*([A-Z][^\\/]+?)\s*$/", '$2 $1', $np);
            $f->general()->travellers(array_unique($np));
        }

        //not(contains(normalize-space(.),' ')) and
        $f->program()
            ->accounts(array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t('Passenger')) . "]/ancestor::p[1]//text()[normalize-space(.)][not(" . $this->contains($this->t('Passenger')) . ") and ( ( {$containsDigit} ) or {$this->contains($this->t(' FF'))}) and not(contains(normalize-space(.),'" . $this->t('ssciitinerary.frequentFlyer') . "'))]",
                null, "#([A-Z\d]{5,})#")), false);

        $xpath = "//table[{$this->contains($this->t('Flight'))}]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $node = $this->http->FindSingleNode(".//tr[1]", $root);
            $re = "#{$this->opt($this->t('Flight'))}\s*(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s+(?<FlightNumber>\d+)\s*(?<dopAirline>(?:{$this->opt($this->t("Sold as"))}|{$this->opt($this->t("operated by"))})[^:]+)?:\s*(?<DepName>.*?)\s*\((?<DepCode>[A-Z]{3})\)\s*\-\s+(?<ArrName>.*?)\s*\((?<ArrCode>[A-Z]{3})\)#i";
            $re3 = "#{$this->opt($this->t('Flight'))}\s*(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s+(?<FlightNumber>\d+)\s*(?<dopAirline>(?:{$this->opt($this->t("Sold as"))}|{$this->opt($this->t("operated by"))})[^:]+)?:\s*(?<DepName>.*?)\s*\((?<DepCode>[A-Z]{3})\)\s*\-?\s+(?<ArrName>.*?)\s*\((?<ArrCode>[A-Z]{3})\)#i";
            $re2 = '/Flight\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s*:\s*(.+)\s+\(([A-Z]{3})\)\s+(.+)\s+\([A-Z]{3}\)\s+(.+)\s+\(([A-Z]{3})\)\s+(.+)\s+\([A-Z]{3}\)/i';
//            $this->logger->debug('$re = '.print_r( $re,true));
//            $this->logger->debug('$re3 = '.print_r( $re3,true));
//            $this->logger->debug('$re2 = '.print_r( $re2,true));

//            $this->logger->debug('$node = '.print_r( $node,true));
            if ((preg_match($re, $node, $m) || preg_match($re3, $node, $m)) && $m['DepCode'] !== $m['ArrCode']) {
                if (preg_match("/^\s*{$this->opt($this->t("Sold as"))}\s+(?<al>.+?) *(?<fn>\d{1,5})\s*$/i", $m['dopAirline'], $mat)
                    || preg_match("/\b{$this->opt($this->t("Sold as"))}\s+(?<al>.+?) *(?<fn>\d{1,5})\s*(?:\>|$)/i", $node, $mat)
                ) {
                    // Itinerary Details for: KM 514 Sold as Austrian airlines 8712: Malta International (MLA) - Schwechat International Airport (VIE)
                    // Volo AZ 1650:Brindisi (BDS) - Milano (LIN)   Venduto come Air France 5555
                    $s->airline()
                        ->carrierName($m['AirlineName'])
                        ->carrierNumber($m['FlightNumber'])
                    ;
                    $m['AirlineName'] = $mat['al'];
                    $m['FlightNumber'] = $mat['fn'];
                } elseif (
                    preg_match("/^\s*{$this->opt($this->t("operated by"))}\s+(?<al>.+?) *(?<fn>\d{1,5})\s*$/i", $m['dopAirline'], $mat)
                    || preg_match("/\b{$this->opt($this->t("operated by"))}\s+(?<al>.+?) *(?<fn>\d{1,5})\s*(?:\>|$)/i", $node, $mat)
                ) {
                    // Flight AZ 1650:Brindisi (BDS) - Milano (LIN)   operated by Air France 5555
                    $s->airline()
                        ->carrierName($mat[1])
                        ->carrierNumber($mat[2]);
                } elseif (
                    preg_match("/^\s*{$this->opt($this->t("operated by"))}\s+(?<al>.+)\s*$/i", $m['dopAirline'], $mat)
                    || preg_match("/\b{$this->opt($this->t("operated by"))}\s+(?<al>.+)\s*$/i", $node, $mat)
                ) {
                    // Itinerary Details for: OS 327 operated by Austrian airlines: Schwechat International Airport (VIE) - (KEF)
                    $s->airline()->operator($mat['al']);
                }

                if (isset($m['AirlineName']) && !empty($m['AirlineName'])) {
                    $s->airline()->name($m['AirlineName']);
                } else {
                    $s->airline()->noName();
                }
                $s->airline()
                    ->number($m['FlightNumber']);

                $s->departure()
                    ->code($m['DepCode']);

                if (!empty($m['DepName'])) {
                    $s->departure()->name($m['DepName']);
                }

                $s->arrival()
                    ->code($m['ArrCode']);

                if (!empty($m['ArrName'])) {
                    $s->arrival()->name($m['ArrName']);
                }
            } elseif (preg_match($re2, $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                $s->departure()
                    ->name($m[3] . ', ' . $m[5])
                    ->code($m[4]);

                $s->arrival()
                    ->name($m[6] . ', ' . $m[8])
                    ->code($m[7]);
            }

            $node = $this->http->FindSingleNode(".//tr[position()>1]/td[" . $this->contains($this->t('DEPARTS')) . "]", $root);

            if (preg_match("#" . $this->opt($this->t("DEPARTS")) . "\s*(?<DepDate>.+)\s+" . $this->opt($this->t("ARRIVES")) . "\s*(?<ArrDate>.+)#",
                $node, $m)) {
                $s->departure()
                    ->date(strtotime($this->normalizeDate($m['DepDate'])));
                $s->arrival()
                    ->date(strtotime($this->normalizeDate($m['ArrDate'])));
            }

            $node = $this->http->FindNodes(".//tr[position()>1]/td[{$this->contains($this->t('SEAT'))}]", $root);

            if (empty($node)) {
                $count = $this->http->XPath->query(".//text()[{$this->contains($this->t('Flight'))}]", $root)->length;
                $node = $this->http->FindNodes("ancestor::*[count(.//text()[{$this->contains($this->t('Flight'))}]) = {$count}][last()]//div[({$this->contains($this->t('SEAT'))}) and not(.//div)]", $root);
            }

            if (empty($node)) {
                $node = $this->http->FindNodes("following-sibling::div[normalize-space()][1][not({$this->contains($this->t('Flight'))})][({$this->contains($this->t('SEAT'))}) and not(.//div)]");
            }

            if (preg_match_all("#" . $this->opt($this->t('SEAT')) . ":?\s*(\d{1,3}[A-Z])\b#i", implode("\n", $node), $m)) {
                $s->extra()
                    ->seats($m[1]);
            }

            $node = $this->http->FindSingleNode("descendant::tr[position()>1]/td[{$this->contains($this->t('TERMINAL'))}]", $root);

            if ((
                preg_match("#^\s*{$this->opt($this->t('TERMINAL'))}\s*\/\s*.+?\s+([A-z\d]+)\s*\/.+#", $node, $m)
                || preg_match("#{$this->opt($this->t('TERMINAL'))}.+\/\s*(.+)$#", $node, $m)
                || preg_match("#^\s*{$this->opt($this->t('TERMINAL'))}\s*\/\s*.+?\s+([A-z\d]+)\s*#", $node, $m)
            ) && !preg_match("/^\s*(?:Unavailable|Gate)\s*$/i", $m[1])
            ) {
                // Terminal/Gate 1B/B5    |    GATE/TERMINAL 7 /1
                $s->departure()->terminal(trim($m[1], '/ '), true);
            }

            // BoardingPass info
            if (!empty($s->getDepCode()) || !empty($s->getDepDate())) {
                $bp = null;

                if ($this->http->XPath->query("//text()[{$this->contains($this->t('mobileURL'))}]")->length > 0) {
                    $bpURL = $this->http->FindSingleNode("preceding::*[self::img[@alt='Boarding pass barcode' and {$this->contains($this->t('imgsrc'), '@src')}] | self::node()[contains(normalize-space(),'Mobile Boarding Pass is not available')][1]][1]/@src", $root);

                    if (empty($bpURL)) {
                        $bpURL = $this->http->FindSingleNode("./following::img[({$this->contains($this->t('mobileURL'), '@alt')}) and ({$this->contains($this->t('imgsrc'), '@src')})]/@src", $root);
                    }

                    if (!empty($bpURL)) {
                        if (count($f->getTravellers()) > 0) {
                            foreach ($f->getTravellers() as $pax) {
                                $bp = $email->add()->bpass();
                                $bp
                                    ->setDepCode($s->getDepCode())
                                    ->setFlightNumber(trim($s->getFlightNumber()))
                                    ->setDepDate($s->getDepDate())
                                    ->setRecordLocator($f->getPrimaryConfirmationNumberKey())
                                    ->setTraveller($pax[0])
                                    ->setUrl($bpURL);
                            }
                        } else {
                            $bp = $email->add()->bpass();
                            $bp
                                ->setDepCode($s->getDepCode())
                                ->setFlightNumber($s->getFlightNumber())
                                ->setDepDate($s->getDepDate())
                                ->setRecordLocator($f->getPrimaryConfirmationNumberKey())
                                ->setUrl($bpURL);
                        }
                    }
                }
                //				elseif ( !empty($this->pdfName) ) {
//                    $bp = $email->add()->bpass();
//                    $bp->setAttachmentName($this->pdfName);
//                    //...
//				}
            }
        }
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

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function unitDetectByBody(): bool
    {
        foreach (self::$dict as $phrases) {
            if (array_key_exists('attachedPDF', $phrases) && !empty($phrases['attachedPDF']) && $this->http->XPath->query("//text()[{$this->contains($phrases['attachedPDF'])}]")->length > 0
                || array_key_exists('mobileURL', $phrases) && !empty($phrases['mobileURL']) && $this->http->XPath->query("//text()[{$this->contains($phrases['mobileURL'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    private function getHtmlAttachments(\PlancakeEmailParser $parser, $length = 6000): array
    {
        $result = [];
        $altCount = $parser->countAlternatives();

        for ($i = 0; $i < $parser->countAttachments() + $altCount; $i++) {
            $html = $parser->getAttachmentBody($i);
            $info = $parser->getAttachmentHeader($i, 'content-type');

            if (preg_match("#^text/html;#", $info) && is_string($html) && strlen($html) > $length) {
                $result[] = $html;
            }
        }

        return $result;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//node()[{$this->contains($reBody[1])}]")->length > 0) {
                    foreach ($reBody[0] as $re) {
                        if ($this->http->XPath->query("//node()[contains(normalize-space(.),\"" . $re . "\")]")->length > 0) {
                            $this->lang = $lang;

                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function normalizeDate($date): string
    {
//        $this->logger->debug($date);
        $year = date('Y', $this->date);

        $in = [
            //Fri, 07 Apr, 2017 -  21:10
            '#.*?(\d+)\s+([^\s\d]+),?\s+(\d+)\s+-?\s*(\d+:\d+)#',
            //Sat, May 21 2016 -  18:20
            //Sun, Dec 11, 2016 -  2030
            '#\S+?,?\s+([^\s\d]+)\s+(\d+),?\s+(\d+)\s+-?\s*(\d+:?\d+)#',
            //08 Aug  -  14:00
            '#(\d+)\s+([^\s\d]+)\s+-\s+(\d+:\d+)#',
            //15 Apr, 2016 - 21:05
            '#(\d+)\s+([^\s\d]+),\s+(\d{4})\s+-?\s*(\d+:\d+)#',
            //Mon, Nov 13 - 16:45
            '#^[^\s\d]+, ([^\s\d]+) (\d+) - (\d+:\d+)$#',
            // September 25th 2021 09:00 AM
            '#^\s*([^\s\d]+)\s+(\d+)th\s+(\d{4})\s*-\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$#i',
        ];
        $out = [
            '$1 $2 $3 $4',
            '$2 $1 $3 $4',
            '$1 $2 ' . $year . ' $3',
            '$1 $2 $3, $4',
            '$2 $1 ' . $year . ', $3',
            '$2 $1 $3, $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
        //		 $this->logger->info($str);
        return $str;
    }
}
