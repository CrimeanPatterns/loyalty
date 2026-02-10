<?php

namespace AwardWallet\Engine\aeroflot\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-10002520.eml, aeroflot/it-10350935.eml, aeroflot/it-11866336.eml, aeroflot/it-12051099.eml, aeroflot/it-181011934.eml, aeroflot/it-181887640.eml, aeroflot/it-27422344.eml, aeroflot/it-30351734.eml, aeroflot/it-30436599.eml, aeroflot/it-32929099.eml, aeroflot/it-33069824.eml, aeroflot/it-33091131.eml, aeroflot/it-33108509.eml, aeroflot/it-33241567.eml, aeroflot/it-33306183.eml, aeroflot/it-33633504.eml, aeroflot/it-8215753.eml, aeroflot/it-8237785.eml, aeroflot/it-8312129.eml, aeroflot/it-8324408.eml, aeroflot/it-83477551.eml, aeroflot/it-8378819.eml, aeroflot/it-8601112.eml, aeroflot/it-8779650.eml";

    private $subjects = [
        'ru' => ['Информация о вашем бронировании', 'Внесение персональных данных в бронирование', 'Открыта онлайн-регистрация на рейс', 'Добавление информации о визе'],
        'fr' => ['Informations relatives à votre réservation', 'Informations sur votre réservation'],
        'es' => ['Los detalles de su reserva', 'Detalles de su reserva', 'El tiempo para pagar su reserva se está agotando'],
        'it' => ['Dettagli della prenotazione'],
        'en' => ['Check-in is open for booking', 'Adding personal data to booking', 'Payment deadline is approaching for booking', 'Details of your booking', 'Online check-in for the flight is now available', 'The booking payment deadline is approaching', 'Payment for booking ', 'Adding visa information'],
        'ja' => ['お客様のご予約詳細'],
    ];

    private $lang = '';
    private $date;
    private $files = [];

    private $bodyDetectors = [
        'ru' => ['Искренне ваш, Аэрофлот', 'Информация о вашем бронировании', 'Открыта онлайн-регистрация на рейс', 'Оплата бронирования', 'Уважаемый клиент!', 'Вы бронировали билеты на рейсы', 'Пожалуйста, просмотрите информацию о вашем маршруте', 'Для идентификации в контакт-центре необходимо сообщить код бронирования'], // it-8378819
        'fr' => ['Cordialement, Aeroflot', 'Informations sur votre réservation'],
        'es' => ['Los detalles de su reserva', 'Detalles de su reserva', 'Ha reservado billetes para el vuelo'],
        'it' => ['Dettagli prenotazione', 'Dettagli della prenotazione'],
        'de' => ['Ihre Buchungsdetails', 'Mit freundlichen Grüßen, Aeroflot', 'Details zu Ihrer Buchung'],
        'en' => ['Yours sincerely, Aeroflot', 'Sincerely yours, Aeroflot', 'Details of your booking', 'Online check-in for the flight is now available',
            'The booking payment deadline is approaching', 'Hold this price” service', 'Please take a moment to review your itinerary', ],
        'ja' => ['お客様のご予約詳細', 'よろしくお願い申し上げます。Aeroflot'],
    ];

    private static $dict = [
        'ru' => [
            "Booking reference:" => ["Код бронирования:", "Код бронирования"],
            "Agent booking code" => "Агентский код бронирования",
            "Passenger details"  => "Данные о пассажирах",
            "Payment total"      => ["Итого по тарифу/сборам", "Итого:", "Итого к оплате"],
            "FLIGHT"             => ["РЕЙС", "Рейс"],
            "ARRIVAL"            => ["ПРИЛЕТ", "Прилет", "ПРИЛЁТ", "Прилёт"],
            "PASSENGERS"         => ["ПАССАЖИРЫ", "Пассажиры", "Пассажир"],
            "DOCUMENT"           => ["ДОКУМЕНТ", "Документ"],
            "MEALS"              => ["ПИТАНИЕ", "Питание", "питание"],
            "CLASS"              => ["КЛАСС", "Класс"],
            "MILES"              => ["МИЛИ", "Мили"],
            "flight time:"       => ["время в пути:"],
            "SEATS"              => ["Места", 'МЕСТА'],
            'Exit / Terminal'    => ['Выход / Терминал'],
            'junk'               => ['Проверка может занять до 5 суток с момента создания бронирования'],
            // PDF
            'Boarding_pass'      => 'Посадочный_талон',
            "eTicket"            => "№ эл.билета",
            "eTicket2"           => "Номер(а) билета(ов)",
            // "Aeroflot Bonus" => "",
            "E-ticket itinerary receipt"     => "Маршрутная квитанция электронного билета",
            "Prepared for"                   => "Подготовлено для",
            "Amount paid and payment method" => "Сумма платежа и форма оплаты",
            // "Payment amount" => "",
            "Special Services" => "Специальные услуги",
        ],
        'fr' => [
            "Booking reference:" => ["Code de réservation:", "Référence de la réservation :"],
            "Agent booking code" => "NOTTRANSLATED",
            "Passenger details"  => ["Informations relatives au passager", "Informations sur le passager"],
            "Payment total"      => "Total :",
            "FLIGHT"             => ["VOL", "Vol"],
            "ARRIVAL"            => ["DÉPART", "Départ", "ARRIVÉE", "Arrivée"],
            "PASSENGERS"         => ["PASSAGERS", "Passagers"],
            "DOCUMENT"           => ["DOCUMENT", "Document"],
            "MEALS"              => ["REPAS", "Repas"],
            "CLASS"              => ["CLASSE", "Classe"],
            "MILES"              => ["MILES", "Miles"],
            "flight time:"       => ["durée du voyage :"],
            "SEATS"              => ["Sièges"],
            // "Exit / Terminal" => "",
            // "Special Services" => ""
            // PDF
            // "Boarding_pass" => "",
            "eTicket" => "Billet électronique n°",
            // "eTicket2" => "",
            // "Aeroflot Bonus" => "",
            "E-ticket itinerary receipt" => "Reçu d'itinéraire du billet électronique",
            // "Prepared for" => "",
            "Amount paid and payment method" => "Montant à payer / Mode de paiement",
            // "Payment amount" => "",
        ],
        'es' => [
            "Booking reference:" => ["Código de la reserva:", "Referencia de la reserva:", "Código de reserva:"],
            "Agent booking code" => "NOTTRANSLATED",
            "Passenger details"  => ["Detalles del pasajero", "Datos del pasajero"],
            "Payment total"      => ["Total:", "Pago total"],
            "FLIGHT"             => ["VUELO", 'Vuelo'],
            "ARRIVAL"            => ['SALIDA', 'Salida', 'LLEGADA', 'Llegada'],
            "PASSENGERS"         => ['PASAJEROS', 'Pasajeros'],
            "DOCUMENT"           => ["DOCUMENTO", 'Documento'],
            "MEALS"              => ["COMIDAS", 'Comidas'],
            "CLASS"              => ["CLASE", 'Clase'],
            "MILES"              => ["MILLAS", "Millas"],
            "flight time:"       => ["duración del viaje:"],
            "SEATS"              => ["Asientos"],
            // "Exit / Terminal" => "",
            // "Special Services" => ""
            // PDF
            // "Boarding_pass" => "",
            "eTicket"  => "Número de billete electrónico",
            "eTicket2" => "Número(s) de billete(s)",
            // "Aeroflot Bonus" => "",
            // "E-ticket itinerary receipt" => "",
            "Prepared for" => "Preparado por",
            // "Amount paid and payment method" => "",
            // "Payment amount" => "",
        ],
        'it' => [
            "Booking reference:" => "Codice prenotazione:",
            "Agent booking code" => "NOTTRANSLATED",
            "Passenger details"  => ["Dettagli passeggeri", "Dettagli passeggero"],
            "Payment total"      => "Totale:",
            "FLIGHT"             => ["VOLO", 'Volo'],
            "ARRIVAL"            => ['PARTENZA', 'Partenza', 'ARRIVO', 'Arrivo'],
            "PASSENGERS"         => ['PASSEGGERI', 'Passeggeri'],
            "DOCUMENT"           => ["DOCUMENTO", 'Documento'],
            "MEALS"              => ["PASTI", 'Pasti'],
            "CLASS"              => ["CLASSE", 'Classe'],
            "MILES"              => ["MIGLIA", "Miglia"],
            "flight time:"       => ["durata del viaggio:"],
            "SEATS"              => ["Posti"],
            // "Exit / Terminal" => "",
            // "Special Services" => ""
            // PDF
            // "Boarding_pass" => "",
            "eTicket"  => "Numero e-ticket",
            "eTicket2" => "Numero/i biglietto/i",
            // "Aeroflot Bonus" => "",
            // "E-ticket itinerary receipt" => "",
            "Prepared for" => "Preparato per",
            // "Amount paid and payment method" => "",
            // "Payment amount" => "",
        ],
        'de' => [
            "Booking reference:" => ["Buchungscode:", "Buchungsreferenz:"],
            "Agent booking code" => "NOTTRANSLATED",
            "Passenger details"  => "Passagierdetails",
            "Payment total"      => "Zu zahlende Gesamtsumme",
            "FLIGHT"             => ["FLUG", 'Flug'],
            "ARRIVAL"            => ['ABFLUG', 'Abflug', 'ANKUNFT', 'Ankunft'],
            "PASSENGERS"         => ['PASSAGIERE', 'Passagiere'],
            "DOCUMENT"           => ["DOKUMENT", 'Dokument'],
            "MEALS"              => ["MAHLZEITEN", 'Mahlzeiten'],
            "CLASS"              => ["KLASSE", 'Klasse'],
            "MILES"              => ["MEILEN", "Meilen"],
            //            "flight time:" => [""],
            //            "SEATS" => [""],
            // "Exit / Terminal" => "",
            // "Special Services" => ""
            // PDF
            // "Boarding_pass" => "",
            // "eTicket" => "",
            // "eTicket2" => "",
            // "Aeroflot Bonus" => "",
            "E-ticket itinerary receipt" => "E-Ticket-Reiseroutenbeleg",
            // "Prepared for" => "",
            "Amount paid and payment method" => "Gezahlter Betrag und Zahlungsmethode",
            // "Payment amount" => "",
        ],
        'en' => [
            "Booking reference:" => ["Booking reference:", "Booking code:", "Reservation number:"],
            //            "Agent booking code" => "",
            //            "Passenger details" => [""],
            "Payment total" => ["Payment total", "Grand Total", 'Total'],
            "FLIGHT"        => ["FLIGHT", "Flight"],
            "ARRIVAL"       => ["ARRIVAL", "Arrival"],
            "PASSENGERS"    => ["PASSENGERS", "Passengers"],
            "DOCUMENT"      => ["DOCUMENT", "Document"],
            "MEALS"         => ["MEALS", "Meals"],
            "CLASS"         => ["CLASS", "Class"],
            "MILES"         => ["MILES", "Miles"],
            "flight time:"  => ["flight time:", " travel time:"],
            "SEATS"         => ["SEATS", "Seats"],
            // "Exit / Terminal" => "",
            // "Special Services" => ""
            // PDF
            // "Boarding_pass" => "",
            "eTicket"  => "E-ticket number",
            "eTicket2" => "Ticket(s) number(s)",
            // "Aeroflot Bonus" => "",
            // "E-ticket itinerary receipt" => "",
            // "Prepared for" => "",
            // "Amount paid and payment method" => "",
            // "Payment amount" => "",
        ],
        'ja' => [
            "Booking reference:" => ["予約コード:"],
            "Agent booking code" => "代理人予約コード:",
            "Passenger details"  => "搭乗者の詳細",
            //			"Payment total" => [""],
            "FLIGHT"       => ["フライト"],
            "ARRIVAL"      => ["到着"],
            "PASSENGERS"   => ["搭乗者"],
            "DOCUMENT"     => ["書類"],
            "MEALS"        => ["食事"],
            "CLASS"        => ["クラス"],
            "MILES"        => ["マイル"],
            "flight time:" => ["フライト時間:"],
            "SEATS"        => ["座席"],
            // "Exit / Terminal" => "",
            // "Special Services" => ""
            // PDF
            // "Boarding_pass" => "",
            "eTicket" => "E-チケット番号",
            // "eTicket2" => "",
            // "Aeroflot Bonus" => "",
            "E-ticket itinerary receipt" => "E-チケット旅程表受領書",
            // "Prepared for" => "",
            "Amount paid and payment method" => "お支払い金額 / お支払い形式",
            // "Payment amount" => "",
        ],
    ];

    private $patterns = [
        'travellerName' => '[A-z][-.\'A-z ]*[A-z]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    private $pdfInfo;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //it-181011934.eml
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('junk'))}]")->length > 0) {
            $email->setIsJunk(true);

            return $email;
        }

        $this->date = strtotime($parser->getHeader('date'));

        if ($this->assignLang() === false) {
            $this->lang = 'en';
        }

        $bPassPDF = $parser->searchAttachmentByName("{$this->opt($this->t('Boarding_pass'))}.*\.pdf");

        foreach ($bPassPDF as $bpPDF) {
            $file = $parser->getAttachment($bpPDF);
            $this->files[] = $this->re('/["](.+)["]/', $file['headers']['content-disposition']);
        }

        //$parser->getHeaderArray();
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                /*
                    Document:       E-ticket number:        Aeroflot Bonus:
                    756242388       5552107059457           53219412
                */
                $tablePos = [0];

                if (preg_match("/^(.{2,}?){$this->preg_implode($this->t("eTicket"))}/mu", $text, $matches)) {
                    $tablePos[] = mb_strlen($matches[1]);
                }

                if (preg_match("/^(.{2,}?){$this->preg_implode($this->t("Aeroflot Bonus"))}/m", $text, $matches)) {
                    $tablePos[] = mb_strlen($matches[1]);
                }
                $table = $this->splitCols($text, $tablePos);

                if (count($table) > 1 && count($table) < 4
                    && preg_match("/^{$this->preg_implode($this->t("eTicket"))}[:\s]+({$this->patterns['eTicket']})(?:[ ]{2}|$)/m", $table[1], $m)
                ) {
                    $this->pdfInfo['Tickets'] = empty($this->pdfInfo['Tickets']) ? [$m[1]] : array_unique(array_merge($this->pdfInfo['Tickets'], [$m[1]]));
                }

                if (count($table) === 3
                    && preg_match("/^{$this->preg_implode($this->t("Aeroflot Bonus"))}[:\s]+(\d[-\d]{6,}\d)(?:[ ]{2}|$)/m", $table[2], $m)
                ) {
                    $this->pdfInfo['Accounts'] = empty($this->pdfInfo['Accounts']) ? [$m[1]] : array_unique(array_merge($this->pdfInfo['Accounts'], [$m[1]]));
                }

                if ((empty($this->pdfInfo['Tickets']) || empty($this->pdfInfo['Accounts']))
                    && preg_match_all("/^.{3,}[: ]+{$this->preg_implode($this->t("eTicket"))}[: ]*(?:.{4,})?\n+[ ]*\d{8,}[ ]+(?<t>{$this->patterns['eTicket']})[ ]+(?<a>\d[-\d]{6,}\d)(?:[ ]{2}|$)/mu", $text, $ticketMatches)
                ) {
                    $this->pdfInfo['Tickets'] = empty($this->pdfInfo['Tickets']) ? $ticketMatches['t'] : array_unique(array_merge($this->pdfInfo['Tickets'], $ticketMatches['t']));
                    $this->pdfInfo['Accounts'] = empty($this->pdfInfo['Accounts']) ? $ticketMatches['a'] : array_unique(array_merge($this->pdfInfo['Accounts'], $ticketMatches['a']));
                }

                /*
                    E-ticket itinerary receipt
                    SHALOM DOV BER LERER
                */
                if (preg_match_all("/(?:^|\n)[ ]*{$this->preg_implode($this->t("E-ticket itinerary receipt"))}(?:.*\n){1,2}[ ]*([[:upper:]][-[:upper:]. ]+[[:upper:]])(?:[ ]{3}|\n|$)/u", $text, $passengerMatches)) {
                    $this->pdfInfo['Passenger'] = array_unique($passengerMatches[1]);
                }

                /*
                    Prepared for
                    SPORTOLETTI GABRIELE
                */
                if (preg_match_all("/(?:^|\n)[ ]*{$this->preg_implode($this->t("Prepared for"))}(?:.*\n){1,3}\s*([[:upper:]][-[:upper:]. ]+[[:upper:]])(?:[ ]{3}|\n|$)/u", $text, $passengerMatches)) {
                    $this->pdfInfo['Passenger'] = array_unique($passengerMatches[1]);
                }

                /*
                    Ticket(s) number(s)      5552140121344-45
                */
                if (preg_match_all("/{$this->preg_implode($this->t("eTicket2"))}\s+({$this->patterns['eTicket']})\s/", $text, $ticketMatches)) {
                    $this->pdfInfo['Tickets'] = isset($this->pdfInfo['Tickets']) ? array_unique(array_merge($this->pdfInfo['Tickets'], $ticketMatches[1])) : array_unique($ticketMatches[1]);
                }

                /*
                    Flight: AY 6843    22A
                */
                if (preg_match_all("/^[ ]*{$this->preg_implode($this->t("FLIGHT"))}[: ]+(?<a>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<n>\d+)[ ]+(?<s>\d+[A-Z])$/m", $text, $seatMatches, PREG_SET_ORDER)) {
                    $seats = [];

                    foreach ($seatMatches as $m) {
                        $seats[$m['a'] . $m['n']][] = $m['s'];
                        $this->pdfInfo['Seats'][$m['a'] . $m['n']][] = $m['s'];
                    }
                }

                if (preg_match("/^[ ]{0,15}{$this->preg_implode($this->t("Amount paid and payment method"))}.*\n+[ ]{0,15}(?<currency>[A-Z]{3})[ ]{0,2}(?<amount>\d[,.\'\d]*)(?:[ ]{3}|$)/m", $text, $m)
                    || preg_match("/{$this->preg_implode($this->t("Payment amount"))}.*?\s+(?<currency>[A-Z]{3})\s+(?<amount>\d[,.\'\d]*)\s+/s", $text, $m)
                ) {
                    // RUB 18474.00
                    $this->pdfInfo['Currency'] = $m['currency'];
                    $this->pdfInfo['TotalCharge'] = $m['amount'];
                }
            } else {
                continue;
            }
        }

        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aeroflot.ru') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Аэрофлот Бонус") or contains(normalize-space(.),"Аэрофлот предлагает") or contains(normalize-space(.),"АО Аэрофлот") or contains(.,"@aeroflot.ru") or contains(.,"www.aeroflot.ru")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.aeroflot.ru")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
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

    private function parseEmail(Email $email): void
    {
        // del garbage like it-32929099.eml
        if (stripos($this->http->Response['body'], 'Mail Attachment.png') !== false) {
            $this->http->SetEmailBody(str_ireplace('<Mail Attachment.png>', '',
                html_entity_decode($this->http->Response['body'])));
        }

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//tr[({$this->contains($this->t("Booking reference:"))}) and not(.//tr)]/following-sibling::tr[string-length(normalize-space(.))>5][1]/descendant::td[normalize-space()][2]"));

        $confirmationNumbers = array_values(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Agent booking code'))}]", null, "/(?:{$this->preg_implode($this->t('Agent booking code'))})[\s:]+([A-Z\d]{5,})/")));

        if (!empty($confirmationNumbers[0])) {
            $f->ota()
                ->confirmation(array_unique($confirmationNumbers)[0]);
        }

        $xpathFragmentBold = "(self::b or self::strong)";
        $xpathFragmentP = "//text()[{$this->contains($this->t('Passenger details'))}]/following::tr[not(.//tr) and contains(.,'→') and contains(.,':') and count(./../*)=3]";

        // Passengers
        $passengers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passenger details'))}]/ancestor::tr[1]/following-sibling::tr/td[1]");

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passenger details'))}]/ancestor::tr[1]/following::text()[{$this->contains($this->t('PASSENGERS'))}]/ancestor::tr[1][{$this->contains($this->t('DOCUMENT'))}]/ancestor::table[1]/descendant-or-self::table[count(descendant::table)=0][1]/descendant::tr[1]/td[1]");
            $passengers = array_filter($passengers);
        }

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("{$xpathFragmentP}/preceding-sibling::tr[1]/*[1][ ./descendant::text()[normalize-space(.)][1][./ancestor::*[{$xpathFragmentBold}]] ]", null, "/^({$this->patterns['travellerName']})$/");
            $passengers = array_filter($passengers);
        }

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//text()[{$this->contains($this->t('Passenger details'))}]/ancestor::tr[1]/following-sibling::div/tr/td[1]");
            $passengers = array_filter($passengers);
        }

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//text()[{$this->eq($this->t('DOCUMENT'))}]/preceding::text()[normalize-space()][1]", null, "/^({$this->patterns['travellerName']})$/");
            $passengers = array_filter($passengers);
        }

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//text()[{$this->eq($this->t('Special Services'))}]/following::text()[{$this->eq($this->t('PASSENGERS'))}]/following::text()[contains(normalize-space(), ' ')][1]", null, "/^({$this->patterns['travellerName']})$/");
            $passengers = array_filter($passengers);
        }

        if (empty($passengers) && !empty($this->pdfInfo['Passenger'])) {
            $passengers = $this->pdfInfo['Passenger'];
        }

        if (!empty($passengers[0])) {
            $f->general()
                ->travellers($passengers);
        }

        // TicketNumbers
        $ticketNumbers = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Passenger details'))}]/ancestor::tr[1]/following::text()[{$this->contains($this->t('PASSENGERS'))}]/ancestor::tr[1][{$this->contains($this->t('DOCUMENT'))}]/ancestor::table[1]/descendant-or-self::table[count(descendant::table)=0][1]/descendant::tr[2]/td[1]//a/descendant::text()[string-length(normalize-space(.))>2]",
            null, '/:\s*(\d[-\d]{7,})$/'));

        if (empty($ticketNumbers)) {
            $ticketNumbers = $this->http->FindNodes("{$xpathFragmentP}/*[1]", null, '/:\s*(\d[-\d]{7,})$/');
            $ticketNumbers = array_filter($ticketNumbers);
        }

        if (empty($ticketNumbers) && !empty($this->pdfInfo['Tickets'])) {
            $ticketNumbers = $this->pdfInfo['Tickets'];
        }

        if (!empty($ticketNumbers[0])) {
            $f->setTicketNumbers($ticketNumbers, false);
        }

        // AccountNumbers
        $accountNumbers = $this->http->FindNodes("{$xpathFragmentP}/following-sibling::tr[position() <3]/*[1]", null, "/{$this->preg_implode($this->t("Aeroflot Bonus"))}[:\s]*(\d[-\d]{7,})(?:\D|$)/i");

        if (empty($accountNumbers)) {
            $accountNumbers = $this->http->FindNodes("//text()[{$this->eq($this->t('PASSENGERS'))}]/ancestor::tr[1]/following::tr[normalize-space()][not(.//tr)][position()<4][starts-with(normalize-space(), 'SU')]", null, '/^SU[ ]?(\d[-\d]{5,})(?:\D|$)/');
        }
        $accountNumbers = array_filter($accountNumbers);

        if (empty($accountNumbers) && !empty($this->pdfInfo['Accounts'])) {
            $accountNumbers = $this->pdfInfo['Accounts'];
        }

        if (!empty($accountNumbers[0])) {
            $f->program()
                ->accounts($accountNumbers, false);
        }

        // TotalCharge
        // Currency
        $total = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("Payment total"))}]/following::*[normalize-space()][1])[1]");

        if (preg_match('/(\d[,\.\'\d ]*)\s*([A-Z]{3})\b/', $total, $m) // 1 036.81 USD
            || preg_match('/(\d[,\.\'\d ]*)\s*([^\d\s]{1,5})/', $total, $m) // 115985.00 ₽
        ) {
            $f->price()
                ->total($this->normalizeAmount($m[1]))
                ->currency($this->currency($m[2]));
        } elseif (isset($this->pdfInfo['TotalCharge'], $this->pdfInfo['Currency']) && !empty($f->getTravellers()) && count($f->getTravellers()) === 1) {
            $f->price()
                ->total($this->pdfInfo['TotalCharge'])
                ->currency($this->pdfInfo['Currency']);
        }

        $xpath = '//text()[' . $this->eq($this->t('FLIGHT')) . ']/ancestor::tr[' . $this->contains($this->t('ARRIVAL')) . '][1]/following-sibling::tr[contains(.,":")]';
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug("Segments not found by xpath: {$xpath}");

            return;
        }
        $this->logger->debug("Segments found by xpath: {$xpath}");

        foreach ($segments as $root) {
            if ($this->http->XPath->query("./td", $root)->length === 1) {
                //пересадка
                continue;
            }

            $s = $f->addSegment();

            $xpathFragmentDate = "/descendant::tr[count(./*[normalize-space(.)])=2 ]";
            $date = $this->http->FindSingleNode("./ancestor::*[self::table or self::div][ ./preceding-sibling::table[normalize-space(.)] ][1]/preceding-sibling::table[.{$xpathFragmentDate}][1]{$xpathFragmentDate}/td[2][normalize-space(.)]", $root);

            if (empty($date)) {
                $date = $this->http->FindSingleNode("./preceding::td[" . $this->contains($this->t("flight time:")) . "][1]", $root);
            }

            if (empty($date)) {
                $date = $this->http->FindSingleNode("./ancestor::*[self::table or self::div][ ./preceding-sibling::table[normalize-space(.)] ][2]/preceding-sibling::table[.{$xpathFragmentDate}][1]{$xpathFragmentDate}/td[2][normalize-space(.)]", $root);
            }

            if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\s*(.*)/', $this->getNode($root), $m)) {
                $s->airline()
                    ->name($m[1]);

                if ($m[2] !== '0') {
                    $s->airline()
                        ->number($m[2]);
                } else {//it-33633504.eml (let service detect flightnumber)
                    $s->airline()
                        ->noNumber();
                }
                $s->extra()
                    ->aircraft($m[3]);
            }

            if (!empty($date) && preg_match('/(?<time>\d+:\d+)\s*(?<code>[A-Z]{3})\s*(?<term>[A-Z\d]{0,3})\b\s*(?<name>.*)/', $this->getNode($root, 2), $m)) {
                $s->departure()
                    ->date(strtotime($m['time'], $this->normalizeDate($date)))
                    ->code($m['code']);

                if (!empty($m['term'])) {
                    $s->departure()
                        ->terminal($m['term']);
                }

                if (!empty($m['name'])) {
                    $s->departure()
                        ->name($m['name']);
                }
            }

            if (!empty($date) && preg_match('/(?<code>[A-Z]{3})\s*(?<term>[A-Z\d]{0,3})\b\s*(?<time>\d+:\d+)(?:\s*(?<overnight>\+1))?\s*(?<name>.*)/', $this->getNode($root, 3), $m)) {
                $s->arrival()
                    ->code($m['code']);

                if (!empty($m['term'])) {
                    $s->arrival()
                        ->terminal($m['term']);
                }
                $s->arrival()
                    ->date(strtotime($m['time'], $this->normalizeDate($date)));

                if (!empty($m['overnight'])) {
                    $s->arrival()
                        ->date(strtotime('+1 days', $s->getArrDate()));
                }

                if (!empty($m['name'])) {
                    $s->arrival()
                        ->name($m['name']);
                }
            }

            if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
                $specilalServices = $this->http->FindNodes("//text()[{$this->eq($this->t('PASSENGERS'))}]/following::text()[{$this->starts($this->t($s->getAirlineName() . ' ' . $s->getFlightNumber()))}]/ancestor::table[1]/descendant::text()[{$this->starts($this->t($s->getDepCode() . ' '))}]/preceding::img[1][contains(@src, 'accept')]/following::text()[contains(normalize-space(), ' ')][1]");
                $mealArray = [];

                foreach ($specilalServices as $value) {
                    if (preg_match("/{$this->opt($this->t('MEALS'))}/", $value)) {
                        $mealArray[] = $value;
                    }

                    if (count($mealArray) > 0) {
                        $s->extra()
                            ->meals($mealArray);
                    }
                }
            }

            $infroot = $this->http->XPath->query("./following::table[1][({$this->contains($this->t('MEALS'))}) and ({$this->contains($this->t('CLASS'))})]", $root);

            if ($infroot->length === 0) {
                $infroot = $this->http->XPath->query("./following-sibling::tr[normalize-space()][1][({$this->contains($this->t('MEALS'))}) and ({$this->contains($this->t('CLASS'))})]", $root);
            }

            if ($infroot->length === 0) {
                $infroot = $this->http->XPath->query("./following::table[({$this->contains($this->t('MEALS'))}) and ({$this->contains($this->t('CLASS'))})][1]", $root);
            }

            if ($infroot->length === 1) {
                $s->extra()
                    ->meal($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('MEALS'))}]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space())>3][1]/*[1]", $infroot->item(0)), true);
                $cabin = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('MEALS'))}]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>3][1]/td[2]", $infroot->item(0));

                if (preg_match("#^\s*(?<class>[A-Z]{1,2})\s+(?<cabin>.+)\s*$#", $cabin, $m) || preg_match("#^\s*(?<cabin>.+)\s+(?<class>[A-Z]{1,2})\s*$#", $cabin, $m)) {
                    $s->extra()
                        ->cabin(trim($m['cabin'], '/'))
                        ->bookingCode($m['class']);
                }
                $status = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('MEALS'))}]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>3][1]/td[3]", $infroot->item(0), true, '/^\s*\w+\s*$/u');

                if (empty($status)) {
                    $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Exit / Terminal'))}]/preceding::text()[normalize-space()][1]");
                }

                if (!empty($status)) {
                    $s->setStatus($status);
                }
            }

            $s->extra()
                ->duration($this->getNode($root, 4));

            $s->airline()
                ->operator($this->getNode($root, 5));

            $seats = [];

            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $seatCol = count($this->http->FindNodes("(//*[{$this->eq($this->t("SEATS"))}][preceding-sibling::*[{$this->eq($this->t("FLIGHT"))}]])[1]/preceding-sibling::*"));

                if (!empty($seatCol)) {
                    $seatCol++;
                    $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("SEATS"))}]/ancestor::tr[{$this->contains($this->t("FLIGHT"))}][1]/following-sibling::tr"
                        . "[td[1][starts-with(normalize-space(),'{$s->getDepCode()}') and contains(.,'{$s->getArrCode()}')]]/td[{$seatCol}]",
                        null, "#^\s*(\d{1,3}[A-Z])\s*$#"));
                }

                if (empty($seats)) {
                    $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('DOCUMENT'))}]/following::text()[starts-with(normalize-space(), '{$s->getDepCode()}') and contains(.,'{$s->getArrCode()}')]/ancestor::td[1]",
                        null, "/^\s*{$s->getDepCode()}\s*\W\s*{$s->getArrCode()}\s*(\d{1,2}[A-Z])\s*$/u"));
                }

                if (empty($seats) && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                    && !empty($this->pdfInfo['Seats']) && !empty($this->pdfInfo['Seats'][$s->getAirlineName() . $s->getFlightNumber()])
                ) {
                    $seats = $this->pdfInfo['Seats'][$s->getAirlineName() . $s->getFlightNumber()];
                }

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }
            // Boarding Pass
            if (count($this->files) > 0) {
                foreach ($this->files as $fileName) {
                    $bp = $email->add()->bpass();

                    $bp->setDepCode($s->getDepCode())
                        ->setDepDate($s->getDepDate())
                        ->setFlightNumber($s->getFlightNumber())
                        ->setRecordLocator($f->getConfirmationNumbers()[0][0])
                        ->setAttachmentName($fileName)
                        ->setTraveller(str_replace('_', ' ', $this->re("/{$this->t('Boarding_pass')}\_(\D+)\.pdf/", $fileName)));
                }
            }
        }

        $milesArray = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('MILES'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]/descendant::td[normalize-space()][last()]"));

        if (count($milesArray) == 1) {
            $f->setEarnedAwards($milesArray[0]);
        }
    }

    private function getNode(\DOMNode $root, $td = 1, $re = null)
    {
        return $this->http->FindSingleNode("td[string-length(normalize-space(.))>1][{$td}]", $root, true, $re);
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function preg_implode($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody(): bool
    {
        foreach ($this->bodyDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Booking reference:"], $words["ARRIVAL"])) {
                $needles = [(array) $words["Booking reference:"], (array) $words["ARRIVAL"]];

                if ($this->http->XPath->query("//*[{$this->contains($needles[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($needles[1])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₽'=> 'RUB',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "/^\s*(\w+)\,\s+(\d+)\s*(\w+)\s+(\d{4}).+$/u", //вт, 11 дек 2018
            "/^\s*(\w+)\s*(\d+)\,\s*(\d{4})\,\s*(\w+).*$/u", //December 11, 2017, Monday
            "/^\s*(\d+)\s*(\w+)\s*(\d{4})\,\s*(\w+).*$/u", //29 juin 2018, vendredi

            "/(\w+),\s+(\d{1,2}) (\w+).*$/u", // Tuesday, 05 Dec
            "/(\w+),\s+(\w+) (\d{1,2}).*$/u", //Thursday, August 24
            "/^\s*(\d{1,2}) ([^\d\s\.\,]+)[.]?, ([^\d\s\.\,]{2,5})[.]?,.*$/u", //  04 янв, пт
            "/^(\d{4})\S(\d+)\S(\d+)\S\,.*$/u", //  2018年12月16日,
        ];
        $out = [
            "$1, $2 $3 $4",
            "$4, $2 $1 $3",
            "$4, $1 $2 $3",

            "$1, $2 $3 $year",
            "$1, $3 $2 $year",
            "$3, $1 $2 $year",
            "$3.$2.$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
