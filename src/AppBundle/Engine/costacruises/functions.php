<?php

class TAccountCheckerCostacruises extends TAccountChecker
{
    public function InitBrowser(): void
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('accept-encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn(): bool
    {
        if (empty($this->State['agencyId'])
            || empty($this->State['brand'])
            || empty($this->State['loyaltyNumber'])) {
            return false;
        }

        return $this->loginSuccessful();
    }

    public function LoadLoginForm(): bool
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Email, username or password invalid', ACCOUNT_INVALID_PASSWORD);
        }
        $this->http->removeCookies();
        $this->http->GetURL('https://www.costacruises.com/login.html');
        $agencyId = $this->http->FindPreg('/agencyId\'\s*:\s*"([^"]+)/');
        $brand = $this->http->FindPreg("/brand'\s*:\s*'([^']+)/");

        if ($brand === null || $agencyId === null) {
            return $this->checkErrors();
        }
        $this->State['agencyId'] = $agencyId;
        $this->State['brand'] = $brand;
        $this->http->setDefaultHeader('agencyId', $agencyId);
        $this->http->setDefaultHeader('brand', $brand);

        $url = $this->http->FindPreg("/ospeServiceUrl': '(.+&)O/");

        if ($url === null) {
            return $this->checkErrors();
        }
        $data = [
            't'  => 'load',
            'l'  => $this->http->currentUrl(),
            'ti' => $this->http->FindSingleNode('//title') ?? '',
            'p1' => '',
            'p2' => '',
            'O'  => 0,
            'V'  => 0,
            'F'  => 0,
            'S'  => 0,
            'qs' => '',
        ];
        $headers = ['Referer' => $this->http->currentUrl()];
        $this->http->RetryCount = 0;
        $this->http->GetURL($url . http_build_query($data));
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] != 503) {
            $sessID = $this->http->getCookieByName('OSPESESSID', 'ospeenterprise.costa.it', '/', false);
            $PID = $this->http->getCookieByName('bizPEUserId', 'ospeenterprise.costa.it', '/O4nfgale6wnz91lq/ospe', false);

            if ($sessID === null || $PID === null) {
                return $this->checkErrors();
            }
            $data = [
                'OSPESESSID' => $sessID,
                'PID'        => $PID,
                't'          => 'field',
                'p1'         => 'login-form__email',
                'p2'         => $this->AccountFields['Login'],
            ] + $data;
            $this->http->GetURL($url . http_build_query($data), $headers);
            $data = [
                'p1' => 'login-form__password',
                'p2' => $this->AccountFields['Pass'],
            ] + $data;
            $this->http->GetURL($url . http_build_query($data), $headers);
            $data = [
                't'  => 'form',
                'p1' => 'login-form',
                'p2' => '',
            ] + $data;
            $this->http->GetURL($url . http_build_query($data), $headers);

            $this->http->SetCookie('OSPESESSID', $sessID, 'www.costacruises.com');
        }
        $data = [
            'username'          => $this->AccountFields['Login'],
            'password'          => $this->AccountFields['Pass'],
            'profileAttributes' => ['favorite', 'campaign'],
        ];
        $headers = [
            'content-type' => 'application/json',
            'Accept'       => 'application/json',
            'country'      => 'US',
            'locale'       => 'en_US',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.costacruises.com/api/v2/costaservices/login/details', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login(): bool
    {
        $response = $this->http->JsonLog();
        $errors = $response->errors ?? [];

        if (count($errors) === 0) {
            $response = $response->data->mariner ?? null;

            if ($response === null) {
                return false;
            }
            $customer = [
                'BirthDate' => $response->dob ?? null,
                'FirstName' => $response->firstName ?? null,
                'Id'        => $response->personId ?? null,
                'LastName'  => $response->lastName ?? null,
            ];

            if (array_search(null, $customer)) {
                return false;
            }
            $this->State['Customer'] = $customer;
            $this->State['loyaltyNumber'] = $response->marinerLevel ?? '';
            $this->http->setDefaultHeader('loyaltyNumber', $response->marinerLevel ?? '');

            return $this->loginSuccessful();
        }
        $code = $errors[0]->code ?? null;
        $msg = $errors[0]->message ?? null;

        if (str_contains($msg, 'Login failed') || $code === '2_2' || str_contains($msg, 'Customer not found')) {
            throw new CheckException('Email, username or password invalid', ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse(): void
    {
        $response = $this->http->JsonLog();
        // Balance (in C|Club points)
        $balance = $response->Customer->Card->TotalScore ?? null;
        $this->SetBalance($balance);
        // Name
        $this->SetProperty('Name', beautifulName($this->State['Customer']['FirstName'] . ' ' . $this->State['Customer']['LastName']));
        // Card no. XXXXXXXX
        $number = $response->Customer->Card->Number ?? null;
        $this->SetProperty('Number', $number);
        // Club Blue/Bronze/Silver/Gold/Platinum
        $level = $response->Customer->Card->Category ?? null;
        $this->SetProperty('Level', $level);

        $this->customerCruises = $response->Products ?? null; // saving cruises for ParseItineraries method

        // PointsToNextLevel (not shown on the site statically, but calculated from the tiers info)
        $this->parsePointsToNextLevel($balance, $level);

        // ExpiringBalance & ExpirationDate
        if ($number === null) {
            return;
        }
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.costacruises.com/api/v2/costaservices/loyalty/scoreExpiration', json_encode(['CardNumber' => $number]), [
            'content-type' => 'application/json',
            'Accept'       => 'application/json',
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog()->Score ?? null;

        if ($response === null) {
            return;
        }
        $expiringBalance = $response->ExpirationScore ?? 0;

        if ($expiringBalance > 0 && !empty($response->ExpirationDate)) {
            $date = new DateTime($response->ExpirationDate);
            $this->SetProperty('ExpiringBalance', $expiringBalance);
            $this->SetExpirationDate($date->getTimestamp());
        }
    }

    public function ParseItineraries(): array
    {
        $this->logger->notice(__METHOD__);
        $cruises = $this->customerCruises ?? null;

        if (is_array($cruises)
            && empty($cruises)
        ) {
            $this->itinerariesMaster->setNoItineraries(true);

            return $cruises;
        }
        $foundUnusualIts = false;
        $closedItsCounter = 0;

        foreach ($cruises as $cruise) {
            $status = $cruise->StatusDesc ?? '';

            if ($status === 'Closed') {
                $closedItsCounter++;

                continue;
            }

            if ($status === 'Open') {
                $errorMsg = $this->parseItinerary($cruise);

                if (!empty($errorMsg)) {
                    $this->logger->error($errorMsg);
                }

                continue;
            }

            $foundUnusualIts = true;
        }

        if ($foundUnusualIts) {
            $this->sendNotification('refs #9830 found unusual cruise status // BS');
        }

        if ($closedItsCounter === count($cruises)) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return [];
    }

    public function GetConfirmationFields(): array
    {
        return [
            'ConfNo'    => [
                'Caption'  => 'Booking reference',
                'Type'     => 'string',
                'Size'     => 9,
                'Required' => true,
            ],
            'FirstName'  => [
                'Caption'  => 'First name',
                'Type'     => 'string',
                'Size'     => 40,
                'Value'    => $this->GetUserField('FirstName'),
                'Required' => false,
            ],
            'LastName'  => [
                'Caption'  => 'Surname',
                'Type'     => 'string',
                'Size'     => 40,
                'Value'    => $this->GetUserField('LastName'),
                'Required' => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields): string
    {
        return 'https://mycosta.costacruises.eu/login-page.html';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        return $this->parseItinerary(null, $arFields);
    }

    private function parsePointsToNextLevel($balance, $level): void
    {
        if ($level === 'Platinum') {
            $this->SetProperty('PointsToNextLevel', 0);

            return;
        }

        if (!is_numeric($balance)
            || empty($level)
        ) {
            return;
        }
        $this->http->GetURL('https://www.costacruises.com/c-club.html');
        $tiers = $this->http->JsonLog($this->http->FindPreg('/"tiersConfig":(\[\{.+\}\])/'));

        if (empty($tiers)) {
            return;
        }

        foreach ($tiers as $tier) {
            $id = $tier->id ?? null;

            if ($id === $level) {
                $maxPoints = $tier->maxPoints ?? null;
                $this->SetProperty('PointsToNextLevel', $maxPoints + 1 - $balance);
            }
        }
    }

    private function loginSuccessful(): bool
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $data = ['Customer' => $this->State['Customer']];
        $this->http->PostURL('https://www.costacruises.com/api/v2/costaservices/customer/productsRetrieve', json_encode($data), [
            'content-type' => 'application/json',
            'Accept'       => 'application/json',
            'Referer'      => 'https://www.costacruises.com/c-club.html',
            'country'      => 'US',
            'locale'       => 'en_US',
            'agencyId'     => $this->State['agencyId'],
            'brand'        => $this->State['brand'],
            'loyaltyNumber'=> $this->State['loyaltyNumber'],
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 0)->Customer ?? null;

        if ($response === null) {
            return false;
        }

        $this->http->setDefaultHeader('agencyId', $this->State['agencyId']);
        $this->http->setDefaultHeader('brand', $this->State['brand']);
        $this->http->setDefaultHeader('loyaltyNumber', $this->State['loyaltyNumber']);

        return true;
    }

    private function parseItinerary($cruise, $arFields = null): ?string
    {
        $this->logger->notice(__METHOD__);

        if (!is_null($arFields)) {
            $firstName = $arFields['FirstName'] ?? null;
            $lastName = $arFields['LastName'];
            $number = $arFields['ConfNo'];
        } else {
            $firstName = $this->State['Customer']['FirstName'];
            $lastName = $this->State['Customer']['LastName'];
            $number = $cruise->BookingNumber ?? null;
        }
        $this->logger->info("Parse Itinerary #$number", ['Header' => 3]);
        $this->http->GetURL('https://mycosta.costacruises.eu/login-page.html');

        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => 'Basic YWRtaW46TXlDb3N0YUFDTjAxIQ==',
            'Content-Type'  => 'application/json',
            'Origin'        => 'https://mycosta.costacruises.eu',
        ];
        $data = [
            'BookingNumber' => $number,
            'FirstName'     => $firstName,
            'LastName'      => $lastName,
            'LanguageCode'  => 'en',
            'CurrentPath'   => '/content/mycosta/international/en_us',
        ];
        $this->http->PostURL("https://mycosta.costacruises.eu/bin/proxyLogin", json_encode($data), $headers);
        $proxyLogin = $this->http->JsonLog();

        if (isset($proxyLogin->errorCode, $proxyLogin->errorDescription)) {
            return $proxyLogin->errorDescription;
        }

        $headersComb = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'Origin'       => 'https://mycosta.costacruzeiros.com',
        ];
        $data = [
            'LanguageCode' => 'pt',
            'SessionToken' => $proxyLogin->ShoppingSessionInfo->Data->SessionToken,
        ];
        $this->http->PostURL("https://mycosta.costacruzeiros.com/api/mycosta/v1/MyCostaServices/Product/PortDateCombinations?AgentId={$proxyLogin->BookingInfo->Data->Agency->Code}",
            json_encode($data), $headersComb);
        $responseComb = $this->http->JsonLog();

        $data = [
            'market'   => 'br',
            'locale'   => 'pt_BR',
            'products' => [[
                'type'      => 'ports',
                'code_list' => array_column($responseComb->PortDateCombinations, 'PortCode'),
            ]],
            'entity' => 'ports',
        ];
        $this->http->PostURL("https://mycosta.costacruzeiros.com/bin/search/contentFragmentService", json_encode($data), $headers);
        $responseService = $this->http->JsonLog();

        $c = $this->itinerariesMaster->createCruise();
        $c->general()->confirmation($proxyLogin->BookingInfo->Data->BookingNum);
        $c->general()->date2($proxyLogin->BookingInfo->Data->BookDate);

        if (count($proxyLogin->BookingInfo->Data->Sailings) > 1) {
            $this->sendNotification("BookingInfo->Data->Sailings > 1 // MI");
        }
        $c->setShip($proxyLogin->BookingInfo->Data->Sailings[0]->Cruise->Ship->Name);
        $c->setDeck($proxyLogin->BookingInfo->Data->Sailings[0]->Cabins[0]->Deck->Code ?? null, false, true);
        //$c->price()->currency($proxyLogin->BookingInfo->Data->Currency->Code);

        foreach ($proxyLogin->BookingInfo->Data->Guests as $guest) {
            $c->general()->traveller("{$guest->FirstName} {$guest->LastName}");
        }

        foreach ($responseComb->PortDateCombinations as $item) {
            $s = $c->addSegment();
            $s->parseAshore("{$item->Date} " . ($item->Arrival ?? null));
            $s->parseAboard("{$item->Date} " . ($item->Departure ?? null));

            foreach ($responseService->results as $result) {
                foreach ($result->ports as $port) {
                    if ($port->un_locode == $item->PortCode) {
                        $s->setName($port->name);
                        $s->setCode($port->ttg_code);
                    }
                }
            }
        }
        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($c->toArray(), true), ['pre' => true]);

        return null;
    }

    private function checkErrors(): bool
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
