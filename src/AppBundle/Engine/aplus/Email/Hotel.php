<?php

namespace AwardWallet\Engine\aplus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "aplus/it-110135740.eml, aplus/it-32128662.eml, aplus/it-32556401.eml, aplus/it-32593314.eml, aplus/it-32668085.eml, aplus/it-36644787.eml, aplus/it-496381717.eml, aplus/it-497287318.eml, aplus/it-497548212.eml, aplus/it-56558297.eml, aplus/it-57812009.eml, aplus/it-57969994.eml, aplus/it-58051401.eml, aplus/it-58062423.eml, aplus/it-59838763.eml, aplus/it-6720894.eml";
    public static $dictionary = [
        "pt" => [
            "Número da reserva"            => ["Número da reserva", "Número da reserva:"],
            "Visualizar o mapa"            => ["Visualizar o mapa", "Ver o mapa"],
            "Data da hospedagem"           => ["Data da hospedagem", "Data da estadia:"],
            "Tel"                          => ["Tel", "Contactar o hotel (para quaisquer questões relacionadas com a sua estadia)"],
            "Reserva realizada em nome de" => ["Reserva realizada em nome de", "Reserva feita em nome de"],
            "Sua hospedagem:"              => ["Sua hospedagem:", "A sua estadia:", 'A SUA ESTADIA', 'A sua estadia'],
            //			"quarto" => "",
            "adultos" => "adultos?",
            //			"criança" => "",
            "Valor total" => ["Valor total", "Total (despesas e impostos incluídos):", "Total (taxas e impostos incluídos):", 'Total'],
            "Quarto"      => ["Quarto", "QUARTO"],
            // "por noite" => "",
            "Política de cancelamento:"                    => ["Política de cancelamento:", "Prazo de cancelamento:"],
            "cancel"                                       => ["cancel", "valor da primeira diária", "Cancel"],
            "rewards"                                      => ["pontos Rewards", "pontos Reward"],
            "O quarto está disponível a partir das"        => ["O quarto está disponível a partir das", "O quarto estará disponível a partir das"],
            "O quarto deve ser liberado no mais tardar às" => ["O quarto deve ser liberado no mais tardar às", "O quarto deverá ser desocupado no máximo até as"],
            "statusStart"                                  => ["Sua reserva foi", "Esta reserva foi", "A sua reserva está"],
            "cancelled"                                    => ["Esta reserva foi anulada", "Esta reserva foi cancelada"],
            "Cancellation number:"                         => "Número de cancelamento:",
            "Number of Reward points used:"                => "Número de pontos Reward utilizados:",
            "Amount already paid:"                         => "Montante já pago:",
            "Remaining amount to be paid at the hotel:"    => "Montante remanescente a pagar no hotel:",
        ],
        "pl" => [
            "Número da reserva"                             => "Numer rezerwacji",
            "Visualizar o mapa"                             => "Pokaż mapę",
            "Data da hospedagem"                            => "Data pobytu:",
            "Tel"                                           => ["Tel", "Zadzwoń do hotelu (aby zadać jakiekolwiek pytanie dotyczące Twojego pobytu)"],
            "Reserva realizada em nome de"                  => ["Rezerwacja dokonana w imieniu", "Rezerwacja na nazwisko"],
            "Sua hospedagem:"                               => "Twój pobyt:",
            "quarto"                                        => "pok(?:ój|oje)",
            "adultos"                                       => "(?:dorosły|dorośli)",
            "criança"                                       => "dziec(?:ko|i)",
            "Valor total"                                   => ["Całkowita kwota", "Suma (opłaty i podatki wliczone w cenę):", 'Suma'],
            "Quarto"                                        => ["Pokój", "POKÓJ"],
            "por noite"                                     => "za noc",
            "Política de cancelamento:"                     => "Anulowanie / opóźnienie:",
            "cancel"                                        => ["pobrana opł. za pierw. noc", "anulow", "cancel"],
            "rewards"                                       => ["punktów Rewards", "punktów programu Reward"],
            "O quarto está disponível a partir das"         => ["Pokój jest dostępny od", "The room is available from"],
            "O quarto deve ser liberado no mais tardar às"  => ["Pokój musi zostać zwolniony najpóźniej do", "The room must be vacated by"],
            "statusStart"                                   => ["Twoja rezerwacja została", "Rezerwacja została"],
            "cancelled"                                     => "została anulowana",
            "Cancellation number:"                          => ["Numer anulowania:", "Numer anulacji:"],
            "Number of Reward points used:"                 => "Liczba wykorzystanych punktów programu Reward:",
            "Amount already paid:"                          => "Kwota już zapłacona:",
            "Remaining amount to be paid at the hotel:"     => "Kwota pozostająca do zapłaty w hotelu:",
        ],
        "fr" => [
            "Número da reserva"                            => ["Numéro de réservation", "Numéro de réservation :"],
            "Visualizar o mapa"                            => "Voir la carte",
            "Data da hospedagem"                           => ["Date du séjour :", "Date d'arrivée :"],
            "Tel"                                          => ["Tél", "Appeler l’hôtel (pour toute question sur votre séjour)"],
            "Reserva realizada em nome de"                 => ["Réservation au nom de :", "Réservation effectuée au nom de :"],
            "Sua hospedagem:"                              => ["Votre séjour :", "Votre séjour"],
            "quarto"                                       => "(?:chambres?|hébergement)",
            "adultos"                                      => "adultes?",
            "criança"                                      => "enfant",
            "Valor total"                                  => ["MONTANT TOTAL", "Total (Frais et taxes inclus) :", "Total"],
            "Quarto"                                       => ["Chambre", "CHAMBRE", "Hébergement", "HÉBERGEMENT"],
            "por noite"                                    => "par nuit",
            "Política de cancelamento:"                    => "Délai d'annulation:",
            "cancel"                                       => ["Annulat", "annulée"],
            "rewards"                                      => "points Reward",
            "O quarto está disponível a partir das"        => "La chambre est disponible à partir de",
            "O quarto deve ser liberado no mais tardar às" => "La chambre doit être libérée au plus tard à",
            "statusStart"                                  => ["Votre réservation est", "Votre réservation a été"],
            "cancelled"                                    => "Cette réservation a été annulée",
            "Cancellation number:"                         => "Numéro d'annulation :",
            "Number of Reward points used:"                => "Nombre de points Reward utilisés :",
            "Amount already paid:"                         => "Montant déjà réglé :",
            "Remaining amount to be paid at the hotel:"    => "Montant restant à régler à l'hôtel :",
        ],
        "ru" => [
            "Número da reserva"            => "Номер бронирования:",
            "Reserva realizada em nome de" => ["Бронирование оформлено от имени:", "Бронирование сделано на имя :"],
            "Data da hospedagem"           => "Дата проживания:",
            "Sua hospedagem:"              => "Ваше пребывание:",
            "quarto"                       => "номера?",
            "adultos"                      => "взрослы(?:й|х|е)",
            "criança"                      => "(?:pебенок|дети)",
            "Tel"                          => ["Тел", "Позвонить в отель (по всем вопросам проживания)"],
            "Visualizar o mapa"            => "Открыть карту",
            "Valor total"                  => ["Общая стоимость", "Итого (включенные налоги и сборы):"],
            "Quarto"                       => ["Номер", "НОМЕР"],
            "por noite"                    => "за ночь",
            "Política de cancelamento:"    => "Отмена при задержке:",
            "cancel"                       => ["Аннулиров", "аннулиров"],
            //			"rewards" => "",
            "O quarto está disponível a partir das"        => ["Номер свободен с", "Заселение в номер возможно с"],
            "O quarto deve ser liberado no mais tardar às" => ["Номер должен быть освобожден не позднее"],
            "statusStart"                                  => ["Ваше бронирование"],
            "cancelled"                                    => "Это бронирование аннулировано.",
            "Cancellation number:"                         => "Номер отмены:",
            //            "Number of Reward points used:" => "",
            "Amount already paid:"                      => "Сумма уже заплатил:",
            "Remaining amount to be paid at the hotel:" => "Оставшаяся сумма к оплате в отеле:",
        ],
        "ko" => [
            "Número da reserva"            => "예약 번호:",
            "Reserva realizada em nome de" => "예약 상의 이름 :",
            "Data da hospedagem"           => "숙박 날짜:",
            "Sua hospedagem:"              => ["총 숙박일:", "총 숙박일"],
            "quarto"                       => "객실",
            "adultos"                      => "성인",
            "criança"                      => "어린이",
            "Tel"                          => ["전화 번호 :", "호텔에 전화(숙박 관련 모든 질문)"],
            "Visualizar o mapa"            => "지도 보기",
            "Valor total"                  => ["전체 금액", "총액 (요금과 포함된 세금):", "총액"],
            "Quarto"                       => "객실",
            // "por noite" => "",
            "Política de cancelamento:"    => "취소 규정:",
            "cancel"                       => ["취소"],
            //			"rewards" => "",
            "O quarto está disponível a partir das"        => "체크인은 ",
            "O quarto deve ser liberado no mais tardar às" => "체크아웃은 늦어도 ",
            "statusStart"                                  => ["고객님의 예약이", "이 예약이"],
            "cancelled"                                    => "취소되었습니다",
            "Cancellation number:"                         => "취소 번호:",
            //            "Number of Reward points used:" => "",
            "Amount already paid:"                      => "금액 은 이미 지급:",
            "Remaining amount to be paid at the hotel:" => "호텔에서 결제할 잔금:",
        ],
        "it" => [
            "Número da reserva"                            => "Codice prenotazione:",
            "Reserva realizada em nome de"                 => ["Prenotazione effettuata per conto di:", "Prenotazione effettuata a nome di", "Prenotazione effettuata a nome di :"],
            "Data da hospedagem"                           => "Data del soggiorno:",
            "Sua hospedagem:"                              => ["Soggiorno prescelto:", "Soggiorno prescelto"],
            "quarto"                                       => "camer(?:a|e)?",
            "adultos"                                      => "adult(?:i|o)",
            "criança"                                      => "bambin(?:o|i)",
            "Tel"                                          => ["Tel", "Chiamare l'hotel (per qualsiasi domanda sul soggiorno)"],
            "Visualizar o mapa"                            => "Visualizza la mappa",
            "Valor total"                                  => ["Importo totale", "Totale (Costi e tasse inclusi):", "Totale"],
            "Quarto"                                       => ["Camera", "CAMERA"],
            "por noite"                                    => "per notte",
            "Política de cancelamento:"                    => "Orario limite di annullamento:",
            "cancel"                                       => ["Annulla", "annulla", "cancel"],
            "rewards"                                      => ["Desidero utilizzare i miei", "punti Reward"],
            "O quarto está disponível a partir das"        => "La camera è disponibile a partire dalle ore",
            "O quarto deve ser liberado no mais tardar às" => "La camera deve essere liberata al più tardi entro le ore",
            "statusStart"                                  => "La sua prenotazione è",
            "cancelled"                                    => "Questa prenotazione è stata annullata", // do not leave empty
            "Cancellation number:"                         => "Numero di cancellazione:",
            "Number of Reward points used:"                => "Numero di punti Reward utilizzati:",
            "Amount already paid:"                         => "Importo già pagato:",
            "Remaining amount to be paid at the hotel:"    => "Importo residuo da saldare in hotel:",
        ],
        "es" => [
            "Número da reserva"            => "Número de reserva:",
            "Visualizar o mapa"            => "Ver mapa",
            "Data da hospedagem"           => "Fecha de la estancia:",
            "Tel"                          => ["Tel", "Llamar al hotel (para cualquier pregunta relacionada con su estancia)"],
            "Reserva realizada em nome de" => "Reserva realizada en nombre de",
            "Sua hospedagem:"              => "Su estancia:",
            "quarto"                       => "(?:habitación|habitaciones)",
            "adultos"                      => "adultos?",
            "criança"                      => "niño",
            "Valor total"                  => ["Cantidad total", "Total (gastos e impuestos incluidos):", 'Total'],
            "Quarto"                       => "Habitación",
            "por noite"                    => "por noche",
            "Política de cancelamento:"    => "Tiempo limite de cancelación:",
            "cancel"                       => ["anula", "cancel"],
            //			"rewards" => "",
            "O quarto está disponível a partir das"        => "Habitación disponible desde las",
            "O quarto deve ser liberado no mais tardar às" => "La habitación debe ser desalojada los mas tarde a las",
            "statusStart"                                  => ["Su reserva está", "Esta reserva se ha"],
            "cancelled"                                    => "Esta reserva se ha anulado.",
            "Cancellation number:"                         => "Número de cancelación:",
            //            "Number of Reward points used:" => "",
            "Amount already paid:"                      => "Cantidad ya pagada:",
            "Remaining amount to be paid at the hotel:" => "Cantidad restante a abonar en el hotel:",
        ],
        "nl" => [
            "Número da reserva"                            => "Reserveringsnummer:",
            "Visualizar o mapa"                            => "Plattegrond bekijken",
            "Data da hospedagem"                           => "Verblijfsdatum:",
            "Tel"                                          => ["Tel", "Bel het hotel (voor vragen over je overnachting)"],
            "Reserva realizada em nome de"                 => ["Reservering gemaakt op naam van", "Reservering gemaakt op naam van :"],
            "Sua hospedagem:"                              => ["Uw verblijf:", "Uw verblijf"],
            "quarto"                                       => "kamers?",
            "adultos"                                      => "volwassenen?",
            "criança"                                      => "kind",
            "Valor total"                                  => ["Totaalbedrag", "Totaal (kosten en belasting inbegrepen):", "Totaal"],
            "Quarto"                                       => ["Kamer", "KAMER"],
            // "por noite" => "",
            "Política de cancelamento:"                    => ["Annuleringsvoorwaarden:", "Annuleringsvoorwaarden"],
            "cancel"                                       => ["annul", "cancel"],
            "rewards"                                      => ["Rewards-punten", "Reward punten"],
            "O quarto está disponível a partir das"        => "De kamer is vanaf",
            "O quarto deve ser liberado no mais tardar às" => "De kamer dient uiterlijk om",
            "statusStart"                                  => ["Uw reservering is", "Deze reservering is"],
            "cancelled"                                    => "Deze reservering is geannuleerd",
            "Cancellation number:"                         => "Annuleringsnummer:",
            "Number of Reward points used:"                => "Aantal gebruikte Reward punten:",
            "Amount already paid:"                         => "Reeds betaalde bedrag:",
            "Remaining amount to be paid at the hotel:"    => "In het hotel te betalen resterende bedrag:",
        ],
        "de" => [
            "Número da reserva"                            => "Buchungsnummer:",
            "Visualizar o mapa"                            => "Karte ansehen",
            "Data da hospedagem"                           => "Aufenthaltsdatum:",
            "Tel"                                          => ["Tel", "Rufen Sie das Hotel an (für alle Fragen zu Ihrem Aufenthalt)"],
            "Reserva realizada em nome de"                 => ["Die Reservierung läuft auf folgenden Namen", "Die Reservierung läuft auf folgenden Namen :"],
            "Sua hospedagem:"                              => ["Ihr Aufenthalt:", "Ihr Aufenthalt"],
            "quarto"                                       => "Zimmer",
            "adultos"                                      => "Erwachsener?",
            "criança"                                      => "Kind",
            "Valor total"                                  => ["Gesamtbetrag", "Gesamt (gebühren und Steuern inbegriffen):", "Gesamt"],
            "Quarto"                                       => ["Zimmer", "ZIMMER"],
            "por noite"                                    => "pro nacht",
            "Política de cancelamento:"                    => ["Stornierungskonditionen:", "Stornierungskonditionen"],
            "cancel"                                       => ["Stornier", "stornier", 'storniert'],
            "rewards"                                      => "Reward Punkte",
            "O quarto está disponível a partir das"        => ["Check-in ab", "Das Zimmer steht ab"],
            "O quarto deve ser liberado no mais tardar às" => "Das Zimmer muss spätestens um",
            "statusStart"                                  => ["Ihre Buchung wurde", "Diese Buchung wurde", "Sie sind jetzt"],
            "cancelled"                                    => ["Buchung wurde storniert", "Bestätigung Ihrer Stornierung"],
            "Cancellation number:"                         => "Stornierungsnummer:",
            "Number of Reward points used:"                => "Anzahl eingelöster Reward Punkte:",
            //            "Amount already paid:" => "",
            "Remaining amount to be paid at the hotel:" => "Im Hotel zu bezahlender Restbetrag:",
        ],
        "en" => [
            "Número da reserva"                             => ["Reservation number:", "Booking number:"],
            "Reserva realizada em nome de"                  => "Reservation made in the name of :",
            "Data da hospedagem"                            => "Date of stay:",
            "Sua hospedagem:"                               => ["Your stay:", "Your stay"],
            "quarto"                                        => "(?:room|accommodation)s?",
            "adultos"                                       => "adults?",
            "criança"                                       => "child(?:ren)?",
            "Tel"                                           => ["Tel", 'Call the hotel (if you have any questions about your stay)'],
            "Visualizar o mapa"                             => "View the map",
            "Valor total"                                   => ["Total amount", "Total (fees and taxes included):", "Total"],
            "Quarto"                                        => ["Room", "ROOM", "Accommodation", "ACCOMMODATION"],
            "por noite"                                     => "per night",
            "Política de cancelamento:"                     => ["Cancellation Policy:", "Cancellation Policy"],
            "cancel"                                        => ["cancel"],
            "rewards"                                       => ["Rewards points", "Reward points"],
            "O quarto está disponível a partir das"         => ["The room is available from", "The room is available from", "Check-in time"],
            "O quarto deve ser liberado no mais tardar às"  => ["The room must be vacated by", "Check-out time"],
            "statusStart"                                   => ["This reservation was", "Your reservation is", "Your reservation has been"],
            "cancelled"                                     => ["This reservation was cancelled.", "this reservation has been canceled.", "Your reservation was canceled", "This reservation was cancelled"],
            "Cancellation number:"                          => "Cancellation number:",
            "Number of Reward points used:"                 => "Number of Reward points used:",
            "Amount already paid:"                          => "Amount already paid:",
            "Remaining amount to be paid at the hotel:"     => "Remaining amount to be paid at the hotel:",
        ],
        "zh" => [
            "Número da reserva"            => "预订编号：",
            "Reserva realizada em nome de" => "预订使用的姓名是：",
            "Data da hospedagem"           => "住宿日期",
            "Sua hospedagem:"              => ["您的住宿：", "您的住宿"],
            "quarto"                       => "客房",
            "adultos"                      => "成人",
            "criança"                      => "儿童",
            "Tel"                          => ["电话", "致电酒店（如对您的入住有任何疑问）"],
            "Visualizar o mapa"            => "查看地图",
            "Valor total"                  => ["总计 (含税费):", "总计"],
            "Quarto"                       => "客房",
            "por noite"                    => "每晚",
            "Política de cancelamento:"    => ["Cancellation Policy:", "延迟取消"],
            "cancel"                       => ["cancel"],
            //			"rewards" => "",
            //            "O quarto está disponível a partir das" => [""],
            "O quarto deve ser liberado no mais tardar às" => "The room must be vacated by",
            "statusStart"                                  => ["该项预订"],
            "cancelled"                                    => ["该项预订已经被取消。"],
            "Cancellation number:"                         => "取消编号：",
            //            "Number of Reward points used:" => "",
            //            "Amount already paid:" => "",
            //            "Remaining amount to be paid at the hotel:" => "",
        ],
        "ja" => [
            "Número da reserva"            => "予約番号：",
            "Visualizar o mapa"            => ["地図を見る"],
            "Data da hospedagem"           => ["宿泊日："],
            "Tel"                          => "ホテルに連絡する (滞在に関するすべてのご質問)",
            "Reserva realizada em nome de" => ["宿泊者のお名前："],
            "Sua hospedagem:"              => ["ご宿泊："],
            "quarto"                       => "客室",
            "adultos"                      => "大人",
            "criança"                      => "子供",
            "Valor total"                  => ["合計 (含まれない費用と税金):", '合計'],
            "Quarto"                       => "客室",
            // "por noite" => "",
            "Política de cancelamento:"    => ["キャンセルポリシー: "],
            "cancel"                       => ["cancel", "Cancel"],
            //            "rewards" => "",
            "O quarto está disponível a partir das"        => ["The room is available from"],
            "O quarto deve ser liberado no mais tardar às" => ["The room must be vacated by"],
            //            "statusStart" => [""],
            "cancelled" => "%NOT TRANSLATED%", // do not leave empty
            //            "Cancellation number:" => "",
            //            "Number of Reward points used:" => "",
            "Amount already paid:"                      => "金額はすでに支払わ:",
            "Remaining amount to be paid at the hotel:" => "ホテルでお支払いの残高金額:",
        ],
        "id" => [
            "Número da reserva"            => "Nomor pemesanan:",
            "Reserva realizada em nome de" => "Pemesanan dibuat atas nama :",
            "Data da hospedagem"           => "Tanggal menginap:",
            "Sua hospedagem:"              => "Penginapan Anda:",
            "quarto"                       => "(?:kamar)s?",
            "adultos"                      => "dewasa",
            "criança"                      => "anak",
            //            "Tel" => "",
            "Visualizar o mapa"         => "Lihat peta",
            "Valor total"               => ["Total (sudah termasuk biaya dan pajak):"],
            "Quarto"                    => ["Kamar", "KAMAR"],
            // "por noite" => "",
            "Política de cancelamento:" => "Kebijakan pembatalan:",
            "cancel"                    => ["pembatalan"],
            //            "rewards" => "",
            "O quarto está disponível a partir das"        => ["Kamar tersedia mulai"],
            "O quarto deve ser liberado no mais tardar às" => ["Kamar harus dikosongkan pada "],
            "statusStart"                                  => ["Pemesanan Anda telah"],
            //            "cancelled" => [""],
            //            "Cancellation number:" => ",
            //            "Number of Reward points used:" => "",
            "Amount already paid:"                      => "Jumlah yang telah dibayar:",
            "Remaining amount to be paid at the hotel:" => "Sisa jumlah yang harus dibayar di hotel:",
        ],
        "tr" => [
            "Número da reserva"            => "Rezervasyon numarası:",
            "Reserva realizada em nome de" => "Adına rezervasyon yapılan kişi :",
            "Data da hospedagem"           => "Konaklama tarihi:",
            "Sua hospedagem:"              => "Konaklama süresi:",
            "quarto"                       => "(?:oda)s?",
            "adultos"                      => "yetişkin",
            "criança"                      => "çocuk",
            //            "Tel" => "",
            "Visualizar o mapa"         => "Haritayı görüntüle",
            "Valor total"               => ["Otelde ödenecek kalan miktar:", 'Toplam (vergi ve ücretler dahildir):'],
            "Quarto"                    => ["Oda", "ODA"],
            // "por noite" => "",
            "Política de cancelamento:" => "İptal koşulları:",
            "cancel"                    => ["cancel"],
            //            "rewards" => "",
            "O quarto está disponível a partir das"        => ["The room is available from"],
            "O quarto deve ser liberado no mais tardar às" => ["The room must be vacated by"],
            "statusStart"                                  => ["Rezervasyonunuz"],
            //            "cancelled" => [""],
            //            "Cancellation number:" => ",
            //            "Number of Reward points used:" => "",
            "Amount already paid:"                      => "Jumlah yang telah dibayar:",
            "Remaining amount to be paid at the hotel:" => "Sisa jumlah yang harus dibayar di hotel:",
        ],
    ];

    private $subjects = [
        'pt' => ['Confirmação de sua reserva:', 'Cancelamento da sua reserva:', 'Cancelamento de sua reserva:'],
        'pl' => ['Potwierdzenie rezerwacji:'],
        'fr' => ['Confirmation de votre réservation', 'Modification de votre réservation :'],
        'ru' => ['Подтверждение вашего бронирования:'],
        'it' => ['Conferma della prenotazione:'],
        'es' => ['Confirmación de su reserva:', 'Cancelación de su reserva:'],
        'nl' => ['Uw reservering wijzigen:', 'De bevestiging van uw reservering:', 'Annulering van uw reservering:'],
        'de' => ['Buchungsbestätigung:', 'Buchung storniert:'],
        'ko' => ['예약 확인서:', '온라인 체크인이 확인되었습니다'],
        'en' => ['Confirmation of your reservation:', 'Cancellation of your reservation:', 'Modification of your reservation:'],
        'zh' => ['预订取消：'],
        'ja' => ['予約番号：', 'お客さまのオンラインチェックは確定されました：', '予約内容のお知らせ：'],
        'id' => ['Konfirmasi pemesanan Anda:'],
        'tr' => ['Rezervasyon onayı:'],
    ];
    private $langDetectors = [
        'pt' => ['Esperamos que você aproveite sua hospedagem', 'Esperamos que desfrute da sua estadia', "A sua estadia", 'para sua próxima estadia', 'Reserva feita em nome de :', 'A sua reserva está confirmada.'],
        'pl' => ['Twoja rezerwacja została potwierdzona', 'Numer rezerwacji:'],
        'fr' => ['Votre réservation', 'votre réservation'],
        'ru' => ['Подтверждение вашего бронирования:', 'Номер бронирования:'],
        'it' => ['Codice prenotazione:'],
        'es' => ['Reserva realizada en nombre de:', 'Su reserva está confirmada', 'Fecha de la estancia'],
        'nl' => ['Uw reservering is bevestigd', 'Uw reservering is gewijzigd', 'Deze reservering is geannuleerd.', 'Uw verblijf'],
        'de' => ['Ihre Buchung wurde bestätigt', 'Ihre Buchung wurde geändert', 'Diese Buchung wurde storniert', 'Sie sind jetzt eingecheckt', 'Stornierungsnummer'],
        'ko' => ['고객님의 예약이 완료되었습니다', "총 숙박일:", "고객님의 예약이 완료되었습니다"],
        'en' => ['Date of stay:'],
        'zh' => ['住宿日期'],
        'ja' => ['ご予約手配者 :', '予約内容は 以下のとおりです。', '宿泊日：'],
        'id' => ['Tanggal menginap:'],
        'tr' => ['Konaklama tarihi:'],
    ];

    private $lang = '';
    private $hotelEmails = ['AccorHotels.com', '@accor.com', '@accor-mail.com', '@sofitel.com', '@fairmont.', '@mamashelter.com', '@novotelgeelong.com.au', 'all.com'];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->assignLang($this->http->Response['body']);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHotel($parser, $email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'accor.com') !== false
            || stripos($from, 'accor-mail.com') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query("//a[{$this->contains(['reservation.accor-mail.com/', 'confirmation.accor-mail.com/', '/click.mail.all.com/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains($this->hotelEmails)}]")->length === 0
        ) {
            return false;
        }

        $textBody = $parser->getHTMLBody();
        $textBody = str_replace("\n", ' ', $textBody);

        return $this->assignLang() || $this->assignLang($textBody) || $this->assignLang(htmlspecialchars_decode($textBody));
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHotel(PlancakeEmailParser $parser, Email $email): void
    {
        $patterns = [
            'phone' => '[+(\d][-+. \/\d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992    |    44 2380/386460
        ];

        $h = $email->add()->hotel();

        // General
        $conf = $this->getNode($this->t('Número da reserva'))
            ?? $this->getNodeText($this->t('Número da reserva'))
            ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Número da reserva'))}][1]/following::text()[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Número da reserva'))}]", null, true, "/^{$this->opt($this->t('Número da reserva'))}[:\s]+([-A-Z\d]{4,35})$/u")
        ;

        if (empty($conf)) {
            if (empty($this->http->FindSingleNode("//td[" . $this->contains($this->t('Número da reserva')) . "]/following-sibling::td[normalize-space()][1]"))
                    && !empty($this->http->FindSingleNode("//td[" . $this->contains($this->t('Número da reserva')) . "]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "#.+[:：]\s*$#"))) {
                $h->general()
                    ->noConfirmation();
            }
        } else {
            $h->general()
                ->confirmation($conf);
        }

        $travellers = array_filter($this->http->FindNodes("//td[" . $this->contains($this->t('Reserva realizada em nome de')) . "]/following-sibling::td[1]"));

        if (empty($travellers)) {
            $travellers = array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t('Reserva realizada em nome de')) . "]/following::text()[normalize-space()][1]"));
        }

        if (!empty($travellers)) {
            $h->general()
                ->travellers(preg_replace("/^\s*(?:MRS|MS|MR|Nn\.|Господин|Sra) /iu", "", array_unique($travellers)), true);
        }

        $status = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('statusStart'))}])[1]", null, false, "#{$this->opt($this->t('statusStart'))}\s*(\w+)[.。]#u");

        if (!empty($status)) {
            $h->general()->status($status);
        }

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("cancelled")) . "])[1]"))) {
            $h->general()
                ->cancelled()
            ;

            if (empty($h->getStatus())) {
                $h->general()
                    ->status('Cancelled')
                ;
            }
            $num = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Cancellation number:")) . "][1]", null, true, "#[:：]\s*(\d+)\s*$#");

            if (empty($num)) {
                $num = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Cancellation number:")) . "][1]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d+)\s*$#");
            }

            if (empty($num)) {
                $num = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Número da reserva'))}][1]/following::text()[{$this->contains($this->t('Cancellation number:'))}][1]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d+)\s*$#");
            }

            if (!empty($num)) {
                $h->general()->cancellationNumber($num);
            }
        }

        $cancellationPolicy = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Política de cancelamento:')) . "]/ancestor::*[1]/following::*[normalize-space(.)][1][" . $this->contains($this->t("cancel")) . "]");

        if (empty($cancellationPolicy)) {
            $cancellationPolicy = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Política de cancelamento:')) . "]/following::text()[normalize-space(.)][1][" . $this->contains($this->t("cancel")) . "]");
        }

        if ($h->getCancelled() === true && empty($cancellationPolicy)) {
            $cancellationPolicies = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t('Cancellation number:')) . "]/ancestor::*[1]/following::*[normalize-space(.)][1][" . $this->contains($this->t("cancel")) . "]")));

            if (count($cancellationPolicies) == 1) {
                $cancellationPolicy = array_shift($cancellationPolicies);
            }
        }

        // Hotel

        $name = $this->http->FindSingleNode("//a[{$this->starts($this->t('Visualizar o mapa'))}]/ancestor::tr[count(preceding-sibling::tr[string-length(normalize-space())>1])=1][2]/preceding-sibling::tr[normalize-space()][1][not(.//tr)]")
            ?? $this->http->FindSingleNode("//a[{$this->contains($this->t('Visualizar o mapa'))}]/ancestor::tr[count(preceding-sibling::tr[string-length(normalize-space())>1])=1][1]/preceding-sibling::tr[normalize-space()][1]");

        if (preg_match("/{$this->opt($this->t('Sua hospedagem:'))}/", $name)) {
            $name = trim(preg_replace("/{$this->opt($this->t('Sua hospedagem:'))}/", "", $name));
        }

        if (empty($name)) {
            if (isset($this->subjects[$this->lang])) {
                foreach ($this->subjects[$this->lang] as $subject) {
                    // Modification de votre réservation : Novotel Wien City No HVDDGTJP
                    if (preg_match("/{$subject}\s*(.+?)\s*(?:No\.?|Nr\.?|n\.?º|N\.|nr\.)\s*[A-Z\d]{5,}/u", $parser->getSubject(), $m)) {
                        $name = $m[1];

                        break;
                    }
                }
            }
        }

        if ($address = implode(", ", array_filter(array_map(function ($v) {return trim(preg_replace("#^\>\s*#m", '', $v)); }, $this->http->FindNodes("//a[" . $this->contains($this->t('Visualizar o mapa')) . "]/ancestor::tr[2]/preceding-sibling::tr[2]//text()[normalize-space()]"))))) {
            $h->hotel()->address($address);
        } elseif (empty($address) && empty($name) && $this->http->XPath->query("//text()[{$this->eq($this->t('Sua hospedagem:'))}]/following::text()[string-length()>2][3][contains(normalize-space(), '@')]")->length > 0) {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Sua hospedagem:'))}]/following::text()[string-length()>2][3][contains(normalize-space(), '@')]/preceding::text()[string-length()>2][2]");
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Sua hospedagem:'))}]/following::text()[string-length()>2][3][contains(normalize-space(), '@')]/preceding::text()[string-length()>2][1]");
            $h->hotel()->address($address);
        } elseif (empty($address) && !empty($name)
            && ($address = implode(", ", $this->http->FindNodes("//text()[{$this->contains($this->t('Visualizar o mapa'))}]/preceding::text()[{$this->eq($name)}][1]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()][not({$this->contains($this->t('Visualizar o mapa'))})]")))
        ) {
            $h->hotel()->address($address);
        } elseif (empty($address) && !empty($name)
            && ($address = $this->http->FindSingleNode("//text()[ preceding::text()[normalize-space()][1][{$this->eq($name)}] and following::text()[normalize-space()][1][{$this->contains($this->hotelEmails)}] ]"))
        ) {
            $h->hotel()->address($address);
        } elseif (empty($address)
            && $this->http->XPath->query("//td[contains(@style,'5px solid #2196d9') or contains(@style,'5px solid #2196D9')]")->length === 0
        ) {
            $h->hotel()->noAddress();
        }

        //it-496381717.eml
        if (stripos($h->getAddress(), trim($name)) !== false
        && $address = implode(", ", $this->http->FindNodes("//text()[{$this->contains($this->t('Visualizar o mapa'))}]/preceding::text()[{$this->eq($name)}][1]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()][not({$this->contains($this->t('View the map'))})]"))) {
            $h->hotel()
                ->address($address);
        }

        $phone = $this->http->FindSingleNode("//a[{$this->contains($this->t('Visualizar o mapa'))}]/ancestor::tr[2]/following-sibling::tr[{$this->contains($this->t('Tel'))}]/descendant::text()[{$this->contains($this->t('Tel'))}][1]", null, true, "/{$this->preg_implode($this->t('Tel'))}\s*[:：\s]\s*({$patterns['phone']})\s*$/iu")
            ?? $this->http->FindSingleNode("//a[{$this->contains($this->t('Visualizar o mapa'))}]/following::text()[normalize-space()][2]/ancestor::table[1][count(.//text()[normalize-space()])=2 and .//text()[{$this->contains($this->hotelEmails)}]]//tr[{$this->starts($this->t('Tel'))}]", null, true, "/{$this->preg_implode($this->t('Tel'))}\s*[:：\s]\s*({$patterns['phone']})\s*$/iu")
            ?? $this->http->FindSingleNode("//a[{$this->contains($this->t('Visualizar o mapa'))}]/following::text()[normalize-space()][2]/ancestor::table[1][count(.//tr[normalize-space()])=2 and .//text()[{$this->contains($this->hotelEmails)}]]//tr[{$this->starts($this->t('Tel'))}]", null, true, "/{$this->preg_implode($this->t('Tel'))}\s*[:：\s]\s*({$patterns['phone']})\s*(?:[(（]|$)/iu")
        ;

        if (!$phone) {
            $phoneTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->hotelEmails)}]/following::text()[normalize-space()][1]", null, "/^{$patterns['phone']}$/"));

            if (count(array_unique(array_map('mb_strtolower', $phoneTexts))) === 1) {
                $phone = array_shift($phoneTexts);
            }
        }

        $h->hotel()->name($name)->phone($phone, false, true);

        // Booked
        $dates = $this->getNode($this->t('Data da hospedagem'));

        if (empty($dates)) {
            $dates = $this->getNodeText($this->t('Data da hospedagem'));
        }

        if (empty($dates)) {
            $dates = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Número da reserva'))}][1]/following::text()[{$this->contains($this->t('Data da hospedagem'))}][1]/following::text()[normalize-space()][1]");
        }

        if (!empty($dates)) {
            $this->parseDates($h, $dates);
        }

        $timeFormat = '(?:\d{1,2}:\d{1,2}(?:[ ]*[ap]m)?|\d{1,2}[ap]m)';

        if (!empty($h->getCheckInDate()) && !empty($time = $this->normalizeTime($this->http->FindSingleNode("//text()[" . $this->starts($this->t("O quarto está disponível a partir das")) . "]", null, true, "#" . $this->preg_implode($this->t("O quarto está disponível a partir das")) . "\s*(" . $timeFormat . ")#i")))) {
            $h->booked()
                ->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        if (!empty($h->getCheckOutDate()) && !empty($time = $this->normalizeTime($this->http->FindSingleNode("//text()[" . $this->starts($this->t("O quarto deve ser liberado no mais tardar às")) . "]", null, true, "#" . $this->preg_implode($this->t("O quarto deve ser liberado no mais tardar às")) . "\s*(" . $timeFormat . ")#i")))) {
            $h->booked()
                ->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        // Kids
        $roomDesc = implode(' ',
            $this->http->FindNodes("//td[{$this->contains($this->t('Sua hospedagem:'))} and not(descendant::table)]/following-sibling::td[normalize-space()][1]"));

        if (empty($roomDesc)) {
            $roomDesc = implode(' ',
                $this->http->FindNodes("//text()[{$this->contains($this->t('Sua hospedagem:'))} and not(descendant::table)]/following::text()[normalize-space()][1]"));
        }

        if (preg_match("/(\d+)\s+{$this->t('quarto')}/iu", $roomDesc, $m)) {
            $h->booked()
                ->rooms($m[1]);
        }

        $guests = implode(' ',
            $this->http->FindNodes("//td[{$this->contains($this->t('Sua hospedagem:'))} and not(descendant::table)]/ancestor::tr[1]/following-sibling::tr/td[normalize-space()][1]"));

        if (empty($guests)) {
            $guests = implode(' ',
                $this->http->FindNodes("//text()[{$this->contains($this->t('Sua hospedagem:'))} and not(descendant::table)]/following::text()[normalize-space()][1]"));
        }

        if (empty($guests)) {
            $guests = implode(' ',
                $this->http->FindNodes("//text()[{$this->starts($this->t('Número da reserva'))}]/following::text()[{$this->starts($this->t('Sua hospedagem:'))}][1]/ancestor::td[1]"));
        }

        // 1 成人， 0 儿童
        // 2 adultes, 0 enfant
        if (preg_match("/(\d+)\s+{$this->t('adultos')}\s*[,，]?\s+(\d+)\s+{$this->t('criança')}/iu", $guests, $m)
        || preg_match("/(\d+)\s+{$this->t('adultos')}/iu", $guests, $m)) {
            $h->booked()
                ->guests($m[1]);

            if (isset($m[2]) && $m[2] !== null) {
                $h->booked()
                    ->kids($m[2]);
            }
        }

        if (!empty($cancellationPolicy)) {
            $h->general()
                ->cancellation($cancellationPolicy);
            $this->detectDeadLine($h, $cancellationPolicy);
        }

        if ($h->getCancelled() === true) {
            return;
        }

        // Rooms
        $QuartoTitle = $this->eq($this->t('Quarto'))
            . ' or ' . $this->eq(preg_replace("/^(.+)$/", '$1 d', $this->t('Quarto')), "translate(normalize-space(), '0123456789', 'dddddddddd')")
            . ' or ' . $this->eq(preg_replace("/^(.+)$/", '$1d', $this->t('Quarto')), "translate(normalize-space(), '0123456789', 'dddddddddd')")
        ;
        $roomTitleXpath = "//text()[{$QuartoTitle}]";
        $types = $this->http->FindNodes($roomTitleXpath . "/following::text()[normalize-space()][not(ancestor::td[1][" . $this->contains($this->t('Reserva realizada em nome de')) . "])][not(" . $this->contains($travellers) . ")][1][not(./ancestor::td[1][" . $this->contains($this->t("Cancellation number:")) . "])]");

        /* Exsample
        Habitación
        Reserva realizada en nombre de : Srta. Ana Isabel Sosa
        Habitación Superior Cama King Size
         */
        $roomTypeDescriptions = $this->http->FindNodes($roomTitleXpath . "/following::text()[normalize-space()][1]/ancestor::tr[1][" . $this->contains($this->t('Reserva realizada em nome de')) . "]/following-sibling::tr[normalize-space(.)][3]");

        if (empty($roomTypeDescriptions)) {
            $roomTypeDescriptions = $this->http->FindNodes($roomTitleXpath . "/following::text()[normalize-space()][1]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][2]");
        }

        if (empty($roomTypeDescriptions)) {
            $roomTypeDescriptions = $this->http->FindNodes("//text()[{$this->starts($this->t('Reserva realizada em nome de'))}]/following::tr[normalize-space(.)][not({$this->contains($types)})][3]");
        }

        $roomBlockXpath = "//text()[{$QuartoTitle}]/ancestor::*[descendant::text()[normalize-space()][1][{$QuartoTitle}]][count(.//text()[{$QuartoTitle}]) = 1][last()]";
        $roomBlocks = $this->http->XPath->query($roomBlockXpath);

        $nights = 0;

        if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
            $nights = (int) date_diff(
                date_create('@' . strtotime('00:00', $h->getCheckOutDate())),
                date_create('@' . strtotime('00:00', $h->getCheckInDate()))
            )->format('%a');
        }

        if (!empty($types) && !empty($roomTypeDescriptions) && count($types) == count($roomTypeDescriptions)) {
            if (count($types) !== $roomBlocks->length) {
                $roomBlocks = null;
            }

            foreach ($types as $key => $type) {
                $room = $h->addRoom();
                $room
                    ->setType($type, true, true)
                    ->setDescription($roomTypeDescriptions[$key], true, true)
                ;

                if (!empty($roomBlocks) && $nights > 0) {
                    $ratesText = array_filter($this->http->FindNodes(".//text()[contains(., ' x ')]", $roomBlocks->item($key), "/^\s*(\d{1,2} x (?:\d[\.,'\d ]* ?[A-Z]{3}|[A-Z]{3} ?\d[\.,'\d ]*))(?: {$this->opt($this->t('por noite'))})?\s*$/"));
                    $rates = [];

                    foreach ($ratesText as $rateText) {
                        if (preg_match("/^(\d{1,2}) x (.+)\s*$/", $rateText, $m)) {
                            $rates = array_merge($rates, array_fill(0, $m[1], $m[2]));
                        }
                    }

                    if (count($rates) === $nights) {
                        $room->setRates($rates);
                    }
                }
            }
        } elseif (!empty($types) && !empty($roomTypeDescriptions)) {
            // $h->addRoom()
            //     ->setType($types, true, true)
            //     ->setDescription($roomTypeDescriptions, true, true)
            // ;
        }

        // Price
        $rewardsAmount = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Number of Reward points used:')) . "][1]/following::text()[normalize-space()][1]", null, true, "#^\s*(\d+)\s*$#u");

        if (!empty($rewardsAmount)) {
            $paidText = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Amount already paid:')) . "]/following::text()[normalize-space()][1]");

            if (preg_match("#(?<amount>[\d., ]+)\s*(?<currency>[A-Z]{3})#", $paidText, $m) || preg_match("#(?<currency>[A-Z]{3})\s*(?<amount>[\d., ]+)#", $paidText, $m)) {
                $paid = $this->normalizeAmount($m['amount']);
                $currency = $m['currency'];
            }
            $toBepaidText = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Remaining amount to be paid at the hotel:')) . "]/following::text()[normalize-space()][1]");

            if (preg_match("#(?<amount>[\d., ]+)\s*(?<currency>[A-Z]{3})#", $toBepaidText, $m) || preg_match("#(?<currency>[A-Z]{3})\s*(?<amount>[\d., ]+)#", $toBepaidText, $m)) {
                $toBepaid = $this->normalizeAmount($m['amount']);
            }

            if (isset($paid) && $paid !== null && isset($toBepaid) && $toBepaid !== null) {
                $h->price()
                    ->total($paid + $toBepaid)
                    ->currency($currency)
                    ->spentAwards($rewardsAmount . ' ' . $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Number of Reward points used:')) . "][1]", null, true, "#(" . $this->preg_implode($this->t('rewards')) . ")#u"))
                ;
            } elseif (empty($paidText)) {
                $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Valor total')) . "]/following::text()[normalize-space()][1]");

                if (preg_match("#(?<amount>[\d., ]+)\s*(?<currency>[A-Z]{3})#", $total, $m) || preg_match("#(?<currency>[A-Z]{3})\s*(?<amount>[\d., ]+)#", $total, $m)) {
                    $h->price()
                        ->total($this->normalizeAmount($m['amount']))
                        ->currency($m['currency'])
                        ->spentAwards($rewardsAmount . ' ' . $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Number of Reward points used:')) . "][1]", null, true, "#(" . $this->preg_implode($this->t('rewards')) . ")#u"))
                    ;
                }
            }
        } else {
            $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Valor total')) . "]/following::text()[normalize-space()][1]");

            if (preg_match("#(?<amount>[\d., ]+)\s*(?<currency>[A-Z]{3})#", $total, $m) || preg_match("#(?<currency>[A-Z]{3})\s*(?<amount>[\d., ]+)#", $total, $m)) {
                $h->price()
                    ->total($this->normalizeAmount($m['amount']))
                    ->currency($m['currency'])
                ;
            }
            $spentAwards = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('rewards')) . "][1]", null, true, "#\b(\d[\d ,.]*\s*" . $this->preg_implode($this->t('rewards')) . "\s*|\s*" . $this->preg_implode($this->t('rewards')) . "\s*\d[\d ,.]*)\b#u");

            if (!empty($spentAwards)) {
                $h->price()->spentAwards($spentAwards);
                $rewardsAmount = $this->normalizeAmount($this->http->FindSingleNode("//text()[" . $this->contains($this->t('rewards')) . "]/following::text()[normalize-space()][1]", null, true, '#\d[,.\'\d ]*#') ?? '');

                if (!empty($h->getPrice()->getTotal())) {
                    $h->price()->total($h->getPrice()->getTotal() - $rewardsAmount);
                }
            }
        }
    }

    private function parseDates(\AwardWallet\Schema\Parser\Common\Hotel $h, string $dates): void
    {
        $dateFormats = [
            "(?<%c%Day>\d{1,2})[/.](?<%c%Month>\d{2})[/.](?<%c%Year>\d{4})",
            "(?<%c%Year>\d{4}[/](?<%c%Month>\d{1,2})[/](?<%c%Day>\d{2})[/.])",
            "(?<%c%Year>\d{4})[/\D](?<%c%Month>\d{1,2})[/\D](?<%c%Day>\d{1,2})[/.\D]",
            // May. 09, 2020
            "(?<%c%Month>\w+)\. (?<%c%Day>\d{2}), (?<%c%Year>\d{4})",
            "(?<%c%Day>\d{2})[-\s]+(?<%c%Month>\w+)[-\s]+(?<%c%Year>\d{4})",
        ];
        $s = ' ';

        if (in_array($this->lang, ['ja'])) {
            $s = '';
        }

        foreach ($dateFormats as $format) {
            if (preg_match("#^(?:\D+" . $s . '|\s*)' . str_replace('%c%', 'ci', $format) . " \D+" . $s . str_replace('%c%', 'co', $format) . "#u", $dates, $m)) {
                $delimiter = '.';

                if (!is_numeric($m['ciMonth'])) {
                    $delimiter = ' ';
                }
                $h->booked()
                    ->checkIn(strtotime($m['ciDay'] . $delimiter . $m['ciMonth'] . $delimiter . $m['ciYear']))
                    ->checkOut(strtotime($m['coDay'] . $delimiter . $m['coMonth'] . $delimiter . $m['coYear']))
                ;

                return;
            }
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText): void
    {
        if (
               preg_match("#Não haverá cobrança de taxa antes das (?<time>\d+:\d+)\s*\(h local\) da data de chegada\.#ui", $cancellationText, $m) //pt
            || preg_match("#Cancelamento sem penalidade até o dia da cheg., (?<time>\d+:\d+)\s*\(h\. local\)\.#ui", $cancellationText, $m) //pt
            || preg_match("#Geen annuleringskosten voor (?<time>\d+:\d+)\s*\(lokale tijd\) op de dag van aankomst\.#ui", $cancellationText, $m) //nl
            || preg_match("#Tot (?<time>\d+)[.](?<time2>\d+)\s*uur \(lokale tijd\) op de dag van aankomst worden geen kosten in rekening gebracht voor annuleringen\.#ui", $cancellationText, $m) //nl
            || preg_match("#Opłata za anul. nie jest pobierana przed (?<time>\d+:\d+)\s*\(czasu lokal.\) w dniu przyj\.#ui", $cancellationText, $m) //pl
            || preg_match("#(?:Kostenfreie|Kostenlose) Stornierung bis (?<time>\d+:\d+)\s*(?:Uhr\s*)?\(Ortszeit\) am Anreisetag\.#ui", $cancellationText, $m) //de
            || preg_match("#Annullamento senza spese fino alle (?<time>\d+:\d+)\s*\(ora locale\) del giorno di arrivo\.#ui", $cancellationText, $m) //it
            || preg_match("#No cancellation charge applies prior to (?<time>\d+:\d+)\s*\(local time\) on the day of arrival\.#ui", $cancellationText, $m) //nl/en
            || preg_match("#Sin cargo por anulación antes de (?<time>\d+:\d+)\s*\(hora local\) el día de la llegada\.#ui", $cancellationText, $m) //es
            || preg_match("#Annulation sans frais jusqu'au jour de l'arrivée, (?<time>\d+:\d+)\s*\(Heure locale\)\.#ui", $cancellationText, $m) //fr
            || preg_match("#Annullamento gratuito fino alle (?<time>\d+:\d+)\s*\(ora locale\) del giorno di arrivo\.#ui", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative('0 day', $m['time'] . ((!empty($m['time2']) ? ':' . $m['time2'] : '')));

            return;
        }

        if (
               preg_match("#Cancelamento sem penalidade até (?<prior>\d{1,3}) dias? antes da cheg\., (?<hour>\d+:\d+)\s*\(h\. ?local\)\.#ui", $cancellationText, $m) //pt
            || preg_match("#Annulation sans frais jusqu'à (?<prior>\d{1,3}) jours? avant l'arrivée, (?<hour>\d+:\d+)\s*\(Heure locale\)\.#ui", $cancellationText, $m) //fr
            || preg_match("#No cancellation charge applies prior to (?<hour>\d+:\d+)\s*\(local time\), up to (?<prior>\d{1,3}) days? prior to arrival\.#ui", $cancellationText, $m) //en
            || preg_match("#Nessuna penale per cancellazioni (?<dayBefore>fino al giorno di arrivo), alle (?<hour>\d+\.\d+)\s*\(ora locale\)\.#ui", $cancellationText, $m) //it
            || preg_match("#No cancellation charge applies prior to (?<hour>\d+:\d+)\s*\(local time\), up to (?<prior>\d+)\s*days?\s*prior to arrival#ui", $cancellationText, $m) //it
        ) {
            if (!empty($m['dayBefore'])) {
                $m['prior'] = '1';
            }
            $h->booked()->deadlineRelative($m['prior'] . ' days', str_replace('.', ':', $m['hour']));

            return;
        }

        if (
               preg_match("#De kosten kunnen niet worden teruggestort, ook niet als#ui", $cancellationText) //nl
            || preg_match("#Die Reservierung kann nicht storniert oder geändert werden\.#ui", $cancellationText) //de
            || preg_match("#La cantidad debitada total es no reembolsable si la reserva#ui", $cancellationText) //es
            || preg_match("#De reservering kan niet worden geannuleerd of gewijzigd\.#ui", $cancellationText) //nl
            || preg_match("#De boeking kan niet worden geannuleerd\.#ui", $cancellationText) //nl
            || preg_match("#(?:Opłata|Zaliczka) nie jest zwracana, nawet jeśli rezerw\.#ui", $cancellationText) //pl
            || preg_match("# cannot be cancelled or modified\.#ui", $cancellationText) //en
            || preg_match("#The amount due is not refundable even if the booking is cancelled or modified.#ui", $cancellationText) //en
            || preg_match("#Em caso de cancelamento ou de modificação, o montante devido não será reembolsado\.#ui", $cancellationText) //pt
        ) {
            $h->booked()->nonRefundable();

            return;
        }

        if (
            preg_match("#No cancellation charge applies up to (?<prior>\d+) day prior to arrival. Beyond that time, the first night will be charged.#ui", $cancellationText, $m) //en
           || preg_match("#Kostenfreie Stornierung (?<prior>\d+) Tag vor Anreise. Danach wird die erste Übernacht. berechnet.#ui", $cancellationText, $m) //de
           || preg_match("#Geen annuleringskosten tot (?<prior>\d+) dag voor aankomst. Hierna de 1e nacht wordt gefactureerd.#ui", $cancellationText, $m) //de
        ) {
            $h->booked()->deadlineRelative($m['prior']);

            return;
        }
    }

    private function getNode($str): ?string
    {
        return $this->http->FindSingleNode("//td[{$this->eq($str)}]/following-sibling::td[normalize-space()][1]");
    }

    private function getNodeText($str): ?string
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($str)}]/following::text()[normalize-space()][1]");
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function assignLang($text = ''): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (empty($text) && $this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                } elseif (!empty($text) && strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeTime($strTime)
    {
        $in = [
            //9am
            '#^(\d+)\s*([ap]m)$#ui',
        ];
        $out = [
            '$1:00 $2',
        ];

        $str = preg_replace($in, $out, $strTime);

        return $str;
    }
}
