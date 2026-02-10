<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBcanariasSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.bintercanarias.com/en/bintermas/my-points';
    private HttpBrowser $browser;
    private array $paramCookie = [];
    private int $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->useSelenium();
        $this->KeepState = true;
        $this->http->saveScreenshots = true;

//        $this->setProxyGoProxies(null, 'br');
        $this->setProxyBrightData(null, 'static', 'br');

        $this->useChromePuppeteer();
        //$this->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);
        //$this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
//        $this->seleniumOptions->addHideSeleniumExtension = false;
//        $this->seleniumOptions->userAgent = null;

    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://www.bintercanarias.com/en/bintermas/my-account');
        $this->logger->debug("currentUrl: {$this->http->currentUrl()}");
        if (strstr($this->http->currentUrl(), '/en/bintermas/login')) {
            return false;
        }
        if ($this->waitForElement(\WebDriverBy::xpath("//small[contains(text(), 'BinterMás card')]"), 5)) {
            return true;
        }
        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->logger->debug("currentUrl: {$this->http->currentUrl()}");
        if (!strstr($this->http->currentUrl(), '/en/bintermas/login')) {
            $this->http->GetURL('https://www.bintercanarias.com/en/bintermas/my-account');
        }

        $accept = $this->waitForElement(\WebDriverBy::xpath("//button[contains(text(), 'Acept all')]"), 10);

        if ($accept) {
            $accept->click();
            sleep(2);
            $this->saveResponse();
        }

        $select = $this->waitForElement(\WebDriverBy::xpath("//*[@formcontrolname = 'identityDocumentType']"), 7);
        $this->saveResponse();

        if (!$select) {
            return false;
        }

        $select->click();

        switch ($this->AccountFields['Login2']) {
            case 'userName':
                $loginType = 'Username';

                // no break
            case 'passport':
                $loginType = 'Passport';

                // no break
            case 'nonSpanichId':
                $loginType = 'Non-Spanish ID';

                break;

            case 'spanishIdNum':
                $loginType = 'DNI';

                break;

            case 'nie':
                $loginType = 'NIE';

                break;

            case 'binterMasCard':
            default:
                $loginType = 'BinterMás card';

                break;
        }

        $loginForm = $this->waitForElement(\WebDriverBy::xpath("//span[contains(text(), '{$loginType}')]"), 1);
        $this->saveResponse();

        if (!$loginForm) {
            return false;
        }
        $loginForm->click();

        $login = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="identityDocumentNumber"]'), 0);
        $pass = $this->waitForElement(\WebDriverBy::xpath('//input[@formcontrolname="password"]'), 0);
        $remember = $this->waitForElement(\WebDriverBy::xpath("//label[contains(text(), 'Remember me')]"), 0);
        $btn = $this->waitForElement(\WebDriverBy::xpath("//button[@type=\"submit\"]"), 0);
        $this->saveResponse();

        if (!$login || !$btn || !$pass || !$remember) {
            $this->logger->error("something went wrong");

            return false;
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $remember->click();
        $this->saveResponse();
        $btn->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(\WebDriverBy::xpath("//small[contains(text(), 'BinterMás card')] | //div[contains(@class, \"nt-alert-context-error\")]//span[contains(@class, 'innerHTML-text-alert')] | //*[contains(@class, 'invalid-feedback')]/div"), 10);
        $this->saveResponse();

        if ($this->http->FindSingleNode("//small[contains(text(), 'BinterMás card')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, \"nt-alert-context-error\")]//span[contains(@class, 'innerHTML-text-alert')] | //div[contains(text(), 'Enter BinteMás Card number')]")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Incorrect login data')
                || trim($message) == 'Enter BinteMás Card number'
            ) {
                throw new CheckException(trim($message), ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//input[contains(@class, "ng-invalid")]/@id')) {
            $this->logger->error("[Incorrect]: {$message}");

            throw new CheckException("Please check your credentials and try again.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

//        $serverApp = $this->http->FindSingleNode('//script[@id = "serverApp-state"]');
        $serverApp = $this->http->FindPreg('/<script id="serverApp-state" type="application\/json">(.*)<\/script/ims');
        $serverApp = str_replace('&l;', '<', $serverApp);
        $serverApp = str_replace('&g;', '>', $serverApp);
        $serverApp = str_replace('&q;', '"', $serverApp);
//        $this->logger->debug(var_export($serverApp, true), ['pre' => true]);
        $serverAppJson = $this->http->JsonLog($serverApp, 3, false, 'documentNumber');
        $userData = null;

        if (!$serverAppJson) {
            $userData = $this->http->JsonLog($this->http->FindPreg('/user_getBasic":(\{[^\}]+\})/'));
        }

        if (isset($serverAppJson)) {
            foreach ($serverAppJson as $data) {
                if (!isset($data->data->user_getBasic)) {
                    continue;
                }

                $userData = $data->data->user_getBasic;
            }// foreach ($serverAppJson as $data)
        }// if (isset($serverAppJson))

        if (empty($userData)) {
            return;
        }

        // Name
        $this->SetProperty('Name', beautifulName($userData->name . " " . $userData->surname1));
        // Status
        $this->SetProperty('Status', $userData->cardLevel);
        // Balance - BinterMás points
        $this->SetBalance($userData->points);
        // BinterMas Card Number
        $this->SetProperty('Number', $userData->frequentFlyer);
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            /*$this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                $cookie['expiry'] ?? null);*/
            $this->paramCookie[$cookie['name']] = $cookie['value'];
        }

        $this->browser->LogHeaders = true;
        $this->browser->setProxyParams($this->http->getProxyParams());
        $this->browser->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function ParseItineraries()
    {
        $this->parseWithCurl();
        $this->browser->RetryCount = 0;
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
            'Origin' => 'https://www.bintercanarias.com',
            'Referer' => 'https://www.bintercanarias.com/en/my-bookings',
            'X-Environment' => 'desktop',
            'X-Auth-Token' => $this->paramCookie['userToken'], //$this->browser->getCookieByName('userToken', 'www.bintercanarias.com'),
            'X-Hash1' => $this->paramCookie['HASH1'] ?? ' ',//$this->browser->getCookieByName('HASH1', 'www.bintercanarias.com'),
            'X-Hash2' => $this->paramCookie['HASH2'],//$this->browser->getCookieByName('HASH2', 'www.bintercanarias.com')
        ];
        $data = '{"operationName":"reservation_getMyReservations","variables":{"type":"HISTORY","ticket":"","origin":"","destination":"","flightDateMin":"","flightDateMax":"","pageNumber":1,"pageSize":10},"query":"query reservation_getMyReservations($type: MyReservationTypeEnum!, $ticket: String, $origin: String, $destination: String, $flightDateMin: DateTime, $flightDateMax: DateTime, $pageNumber: Int, $pageSize: Int) {\n  reservation_getMyReservations(\n    type: $type\n    ticket: $ticket\n    origin: $origin\n    destination: $destination\n    flightDateMin: $flightDateMin\n    flightDateMax: $flightDateMax\n    paginator: {pageNumber: $pageNumber, pageSize: $pageSize, orderType: \"DESC\", orderField: \"firstFlightDate\"}\n  ) {\n    pnr\n    surname\n    reservationDate\n    firstFlightDate\n    route\n    tickets {\n      ticket\n      name\n      surnames\n      __typename\n    }\n    __typename\n  }\n}"}';
        $this->browser->PostURL("https://services.bintercanarias.com/main/graphql", $data, $headers);
        $this->browser->RetryCount = 2;
        $response = $this->browser->JsonLog();

        foreach ($response->data->reservation_getMyReservations as $reservation) {
            $data = '{"operationName":"reservation_getByPnr","variables":{"pnr":"' . $reservation->pnr . '","surname":"' . $reservation->surname . '"},"query":"query reservation_getByPnr($pnr: String!, $surname: String!) {\n  reservation_getByPnr(pnr: $pnr, surname: $surname) {\n    token\n    source\n    pnr\n    iata\n    email\n    passengers {\n      name\n      surname1\n      surname2\n      identityDocumentType\n      identityDocumentNumber\n      selfDocument\n      birthDate\n      genre\n      accreditedResidency\n      fomentoStatus\n      tickets {\n        ticketNumber\n        date\n        fareAmount\n        taxAmount\n        serviceFeeAmount\n        managementFeeAmount\n        type\n        state\n        currency {\n          code\n          htmlCode\n          charCode\n          __typename\n        }\n        __typename\n      }\n      type {\n        code\n        largeFamilyCode\n        typeCode\n        residentCode\n        isResident\n        isSpanishResident\n        isLargeFamily\n        __typename\n      }\n      amounts {\n        fare {\n          mainAmount\n          totalAmount\n          discounts {\n            type\n            amount\n            __typename\n          }\n          discountsAmount\n          currency {\n            code\n            htmlCode\n            charCode\n            __typename\n          }\n          __typename\n        }\n        taxes {\n          mainAmount\n          totalAmount\n          discounts {\n            type\n            amount\n            __typename\n          }\n          discountsAmount\n          currency {\n            code\n            htmlCode\n            charCode\n            __typename\n          }\n          __typename\n        }\n        suplements {\n          mainAmount\n          totalAmount\n          discounts {\n            type\n            amount\n            __typename\n          }\n          discountsAmount\n          currency {\n            code\n            htmlCode\n            charCode\n            __typename\n          }\n          __typename\n        }\n        serviceFee {\n          mainAmount\n          totalAmount\n          discounts {\n            type\n            amount\n            __typename\n          }\n          discountsAmount\n          currency {\n            code\n            htmlCode\n            charCode\n            __typename\n          }\n          __typename\n        }\n        managementFee {\n          mainAmount\n          totalAmount\n          discounts {\n            type\n            amount\n            __typename\n          }\n          discountsAmount\n          currency {\n            code\n            htmlCode\n            charCode\n            __typename\n          }\n          __typename\n        }\n        mobilidadeDiscounts {\n          mainAmount\n          totalAmount\n          discounts {\n            type\n            amount\n            __typename\n          }\n          discountsAmount\n          currency {\n            code\n            htmlCode\n            charCode\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      obtainingPoints\n      canRetryFomento\n      token\n      __typename\n    }\n    flights {\n      code\n      class\n      slash\n      column\n      flight {\n        flightSegments {\n          line\n          flightNumber\n          origin {\n            iata\n            name\n            slug\n            description\n            group\n            groupTitle\n            country {\n              code2Chars\n              code3Chars\n              name\n              europeanComunitary\n              phonePrefix\n              __typename\n            }\n            info {\n              infoImage\n              infoImageAtlText\n              infoTitle\n              infoText\n              infoNotToBeMissed\n              infoTypicalFood\n              infoUsefulInformation\n              seoTitle\n              seoDesc\n              pdfTitle\n              pdfGuide\n              isVisible\n              offerImage\n              offerImageAtlText\n              offerText\n              isHighlighted\n              highlightOrder\n              homeImage\n              homeImageAltText\n              __typename\n            }\n            __typename\n          }\n          destination {\n            iata\n            name\n            slug\n            description\n            group\n            groupTitle\n            country {\n              code2Chars\n              code3Chars\n              name\n              europeanComunitary\n              phonePrefix\n              __typename\n            }\n            info {\n              infoImage\n              infoImageAtlText\n              infoTitle\n              infoText\n              infoNotToBeMissed\n              infoTypicalFood\n              infoUsefulInformation\n              seoTitle\n              seoDesc\n              pdfTitle\n              pdfGuide\n              isVisible\n              offerImage\n              offerImageAtlText\n              offerText\n              isHighlighted\n              highlightOrder\n              homeImage\n              homeImageAltText\n              __typename\n            }\n            __typename\n          }\n          departureTime\n          arrivalTime\n          departureTerminal\n          arrivalTerminal\n          stops\n          plane\n          advantages\n          token\n          __typename\n        }\n        flightTime\n        taxes\n        origin {\n          iata\n          name\n          slug\n          description\n          group\n          groupTitle\n          country {\n            code2Chars\n            code3Chars\n            name\n            europeanComunitary\n            phonePrefix\n            __typename\n          }\n          info {\n            infoImage\n            infoImageAtlText\n            infoTitle\n            infoText\n            infoNotToBeMissed\n            infoTypicalFood\n            infoUsefulInformation\n            seoTitle\n            seoDesc\n            pdfTitle\n            pdfGuide\n            isVisible\n            offerImage\n            offerImageAtlText\n            offerText\n            isHighlighted\n            highlightOrder\n            homeImage\n            homeImageAltText\n            __typename\n          }\n          __typename\n        }\n        destination {\n          iata\n          name\n          slug\n          description\n          group\n          groupTitle\n          country {\n            code2Chars\n            code3Chars\n            name\n            europeanComunitary\n            phonePrefix\n            __typename\n          }\n          info {\n            infoImage\n            infoImageAtlText\n            infoTitle\n            infoText\n            infoNotToBeMissed\n            infoTypicalFood\n            infoUsefulInformation\n            seoTitle\n            seoDesc\n            pdfTitle\n            pdfGuide\n            isVisible\n            offerImage\n            offerImageAtlText\n            offerText\n            isHighlighted\n            highlightOrder\n            homeImage\n            homeImageAltText\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      flightChangeFee {\n        mainAmount\n        totalAmount\n        discounts {\n          type\n          amount\n          __typename\n        }\n        discountsAmount\n        currency {\n          code\n          htmlCode\n          charCode\n          __typename\n        }\n        __typename\n      }\n      canChange\n      canChangeOnline\n      changeRange {\n        min\n        max\n        __typename\n      }\n      token\n      __typename\n    }\n    tickets {\n      ticketNumber\n      date\n      fareAmount\n      taxAmount\n      serviceFeeAmount\n      managementFeeAmount\n      type\n      state\n      currency {\n        code\n        htmlCode\n        charCode\n        __typename\n      }\n      __typename\n    }\n    canShowDisplay\n    hasActivity\n    canChangeFlights\n    isAirPass\n    canChangeFlightsOnline\n    showExtraBaggage\n    canExtraBaggage\n    showPriorityBoarding\n    canPriorityBoarding\n    canSelectedSeat\n    canInvoices\n    canCancel\n    canVoid\n    canRefund\n    canUpgrade\n    canCheckin\n    emissionDate\n    fomentoCheckInformation {\n      showModal\n      checkFomentoResponse {\n        passengers {\n          passenger {\n            name\n            surname1\n            surname2\n            identityDocumentType\n            identityDocumentNumber\n            accreditedResidency\n            fomentoStatus\n            tickets {\n              ticketNumber\n              date\n              fareAmount\n              taxAmount\n              serviceFeeAmount\n              managementFeeAmount\n              type\n              state\n              currency {\n                code\n                htmlCode\n                charCode\n                __typename\n              }\n              __typename\n            }\n            type {\n              code\n              largeFamilyCode\n              typeCode\n              residentCode\n              isResident\n              isSpanishResident\n              isLargeFamily\n              __typename\n            }\n            amounts {\n              fare {\n                mainAmount\n                totalAmount\n                discounts {\n                  type\n                  amount\n                  __typename\n                }\n                discountsAmount\n                currency {\n                  code\n                  htmlCode\n                  charCode\n                  __typename\n                }\n                __typename\n              }\n              taxes {\n                mainAmount\n                totalAmount\n                discounts {\n                  type\n                  amount\n                  __typename\n                }\n                discountsAmount\n                currency {\n                  code\n                  htmlCode\n                  charCode\n                  __typename\n                }\n                __typename\n              }\n              suplements {\n                mainAmount\n                totalAmount\n                discounts {\n                  type\n                  amount\n                  __typename\n                }\n                discountsAmount\n                currency {\n                  code\n                  htmlCode\n                  charCode\n                  __typename\n                }\n                __typename\n              }\n              serviceFee {\n                mainAmount\n                totalAmount\n                discounts {\n                  type\n                  amount\n                  __typename\n                }\n                discountsAmount\n                currency {\n                  code\n                  htmlCode\n                  charCode\n                  __typename\n                }\n                __typename\n              }\n              managementFee {\n                mainAmount\n                totalAmount\n                discounts {\n                  type\n                  amount\n                  __typename\n                }\n                discountsAmount\n                currency {\n                  code\n                  htmlCode\n                  charCode\n                  __typename\n                }\n                __typename\n              }\n              mobilidadeDiscounts {\n                mainAmount\n                totalAmount\n                discounts {\n                  type\n                  amount\n                  __typename\n                }\n                discountsAmount\n                currency {\n                  code\n                  htmlCode\n                  charCode\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            obtainingPoints\n            canRetryFomento\n            token\n            __typename\n          }\n          tickets\n          state\n          __typename\n        }\n        cancel\n        retries\n        __typename\n      }\n      __typename\n    }\n    isPoints\n    usedPoints\n    secondsToExpire\n    totalAmount\n    extraServices {\n      type\n      passenger {\n        name\n        surname1\n        surname2\n        identityDocumentType\n        identityDocumentNumber\n        selfDocument\n        birthDate\n        genre\n        frequentFlyer\n        accreditedResidency\n        fomentoStatus\n        tickets {\n          ticketNumber\n          date\n          fareAmount\n          taxAmount\n          serviceFeeAmount\n          managementFeeAmount\n          type\n          state\n          currency {\n            code\n            htmlCode\n            charCode\n            __typename\n          }\n          __typename\n        }\n        type {\n          code\n          largeFamilyCode\n          typeCode\n          residentCode\n          isResident\n          isSpanishResident\n          isLargeFamily\n          __typename\n        }\n        amounts {\n          fare {\n            mainAmount\n            totalAmount\n            discounts {\n              type\n              amount\n              __typename\n            }\n            discountsAmount\n            currency {\n              code\n              htmlCode\n              charCode\n              __typename\n            }\n            __typename\n          }\n          taxes {\n            mainAmount\n            totalAmount\n            discounts {\n              type\n              amount\n              __typename\n            }\n            discountsAmount\n            currency {\n              code\n              htmlCode\n              charCode\n              __typename\n            }\n            __typename\n          }\n          suplements {\n            mainAmount\n            totalAmount\n            discounts {\n              type\n              amount\n              __typename\n            }\n            discountsAmount\n            currency {\n              code\n              htmlCode\n              charCode\n              __typename\n            }\n            __typename\n          }\n          serviceFee {\n            mainAmount\n            totalAmount\n            discounts {\n              type\n              amount\n              __typename\n            }\n            discountsAmount\n            currency {\n              code\n              htmlCode\n              charCode\n              __typename\n            }\n            __typename\n          }\n          managementFee {\n            mainAmount\n            totalAmount\n            discounts {\n              type\n              amount\n              __typename\n            }\n            discountsAmount\n            currency {\n              code\n              htmlCode\n              charCode\n              __typename\n            }\n            __typename\n          }\n          mobilidadeDiscounts {\n            mainAmount\n            totalAmount\n            discounts {\n              type\n              amount\n              __typename\n            }\n            discountsAmount\n            currency {\n              code\n              htmlCode\n              charCode\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        obtainingPoints\n        canRetryFomento\n        token\n        __typename\n      }\n      flightSegment {\n        line\n        flightNumber\n        origin {\n          iata\n          name\n          slug\n          description\n          group\n          groupTitle\n          country {\n            code2Chars\n            code3Chars\n            name\n            europeanComunitary\n            phonePrefix\n            __typename\n          }\n          info {\n            infoImage\n            infoImageAtlText\n            infoTitle\n            infoText\n            infoNotToBeMissed\n            infoTypicalFood\n            infoUsefulInformation\n            seoTitle\n            seoDesc\n            pdfTitle\n            pdfGuide\n            isVisible\n            offerImage\n            offerImageAtlText\n            offerText\n            isHighlighted\n            highlightOrder\n            homeImage\n            homeImageAltText\n            __typename\n          }\n          __typename\n        }\n        destination {\n          iata\n          name\n          slug\n          description\n          group\n          groupTitle\n          country {\n            code2Chars\n            code3Chars\n            name\n            europeanComunitary\n            phonePrefix\n            __typename\n          }\n          info {\n            infoImage\n            infoImageAtlText\n            infoTitle\n            infoText\n            infoNotToBeMissed\n            infoTypicalFood\n            infoUsefulInformation\n            seoTitle\n            seoDesc\n            pdfTitle\n            pdfGuide\n            isVisible\n            offerImage\n            offerImageAtlText\n            offerText\n            isHighlighted\n            highlightOrder\n            homeImage\n            homeImageAltText\n            __typename\n          }\n          __typename\n        }\n        departureTime\n        arrivalTime\n        departureTerminal\n        arrivalTerminal\n        stops\n        plane\n        advantages\n        token\n        __typename\n      }\n      ticket {\n        ticketNumber\n        date\n        fareAmount\n        taxAmount\n        serviceFeeAmount\n        managementFeeAmount\n        type\n        state\n        currency {\n          code\n          htmlCode\n          charCode\n          __typename\n        }\n        __typename\n      }\n      freeText\n      amount\n      currency {\n        code\n        htmlCode\n        charCode\n        __typename\n      }\n      seat {\n        row\n        column\n        type\n        __typename\n      }\n      insurance {\n        contractDays\n        dueDate\n        efectiveDate\n        policy\n        product\n        reference\n        __typename\n      }\n      extraSeat {\n        row\n        column\n        type\n        __typename\n      }\n      __typename\n    }\n    refundData {\n      items {\n        ticket {\n          ticketNumber\n          date\n          fareAmount\n          taxAmount\n          serviceFeeAmount\n          managementFeeAmount\n          type\n          state\n          currency {\n            code\n            htmlCode\n            charCode\n            __typename\n          }\n          __typename\n        }\n        refundAmounts {\n          fare\n          tax\n          serviceFee\n          managementFee\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}"}';
            $this->browser->PostURL("https://services.bintercanarias.com/main/graphql", $data, $headers);
            $response = $this->browser->JsonLog();
            $this->parseItinerary($response);
        }
        $this->browser->RetryCount = 2;
    }

    public function parseItinerary($data) {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->createFlight();
        $data = $data->data->reservation_getByPnr;
        $this->logger->info(sprintf('[%s] Parse Flight #%s', $this->currentItin++, $data->pnr), ['Header' => 3]);
        $f->general()->confirmation($data->pnr, 'Booking ref.:');

        $date = $currency = null;
        foreach ($data->passengers as $passenger) {
            $this->logger->debug($passenger->name);
            $f->general()->traveller("{$passenger->name} {$passenger->surname1} {$passenger->surname2}");
            foreach ($passenger->tickets as $ticket) {
                $date = $ticket->date;
                $f->issued()->ticket($ticket->ticketNumber, false);
            }
        }
        $f->general()->date2($date);


        foreach ($data->flights as $flight) {
            $currency =
                $flight->flightChangeFee->currency->code
                ?? $data->tickets[0]->currency->code
            ;
            foreach ($flight->flight->flightSegments as $seg) {
                $s = $f->addSegment();
                $s->airline()->name($seg->line);
                $s->airline()->number($seg->flightNumber);

                $s->departure()->date2($seg->departureTime);
                $s->departure()->code($seg->origin->iata);
                $s->departure()->name($seg->origin->name);

                $s->arrival()->date2($seg->arrivalTime);
                $s->arrival()->code($seg->destination->iata);
                $s->arrival()->name($seg->destination->name);

                foreach ($data->extraServices as $extra) {
                    if ($extra->flightSegment->line == $seg->line && $extra->flightSegment->flightNumber == $seg->flightNumber) {
                        $s->extra()->seat($extra->seat->row . $extra->seat->column);
                    }
                }
            }
        }
        $f->price()->currency($currency);
        $f->price()->total($data->totalAmount);
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }
}
