<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerCheaptickets extends TAccountChecker
{
    use ProxyList;

    public const REWARDS_PAGE_URL = "https://www.cheaptickets.com/user/account";

    private $expedia;
    /** @var CaptchaRecognizer */
    private $recognizer;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerCheapticketsSelenium.php";

        return new TAccountCheckerCheapticketsSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->UseSSLv3();

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            if (rand(1, 3) > 1) {
                $this->http->SetProxy($this->proxyReCaptcha());
            /*
            $this->http->SetProxy($this->proxyDOP());
            $proxy = $this->http->getLiveProxy("https://www.cheaptickets.com/login", 20);
            $this->http->SetProxy($proxy);
            */
            } else {
                $this->logger->notice(">>> no proxy");
            }
        }
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;
        $this->delay();

        if ($this->http->Response['code'] == 200 && $this->http->FindNodes("//a[contains(@href, 'logout')]/@href") && !strstr($this->http->currentUrl(), 'https://www.cheaptickets.com/user/signin')) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.cheaptickets.com/login");
        $pointOfSaleId = $this->http->FindPreg("/\"pointOfSaleId.\":.\"([^\\\]+)/");
        $uiBrand = $this->http->FindPreg("/\"uiBrand.\":.\"([^\\\]+)/");
        $deviceId = $this->http->FindPreg("/\"deviceId.\":.\"([^\\\]+)/");
        $remoteAddress = $this->http->FindPreg("/\"remoteAddress.\":.\"([^\\\]+)/");
        $guid = $this->http->FindPreg("/\"guid.\":.\"([^\\\]+)/");
        $traceId = $this->http->FindPreg("/\"traceId.\":.\"([^\\\]+)/");
        $tpid = $this->http->FindPreg("/.\"tpid.\":(\d+)/");
        $site_id = $this->http->FindPreg("/_site_id.\":(\d+)/");
        $eapid = $this->http->FindPreg("/.\"eapid.\":(\d+)/");
        $csrf = $this->http->FindPreg("/\,.\"login.\":.\"([^\\\]+).\",.\"mf/");
        $xB3Traceid = $this->http->FindPreg("/\"x-b3-traceid.\":.\"([^\\\]+)/");

        if (!$this->http->ParseForm("loginForm") || !$csrf || !$deviceId || !$uiBrand || !$pointOfSaleId) {
            if ($this->http->Response['code'] == 429) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(2);
            }

            return $this->checkErrors();
        }

        $captcha = $this->parseFunCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "username"      => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "rememberMe"    => true,
            "channelType"   => "WEB",
            "devices"       => [
                [
                    "payload" => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"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;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","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":1064,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":143110,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"70301,www.cheaptickets.com,Cheaptickets,ULX","requestURL":"https://www.cheaptickets.com/login","userAgent":"' . $this->http->userAgent . '","placement":"LOGIN","placementPage":"70301","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                    "type"    => "TRUST_WIDGET",
                ],
            ],
            "atoShieldData" => [
                "atoTokens" => [
                    "fc-token"   => $captcha,
                    "email"      => $this->AccountFields['Login'],
                ],
                "placement" => "login",
            ],
            "csrfData"      => [
                "csrfToken" => $csrf,
                "placement" => "login",
            ],
        ];

        $headers = [
            "Accept"               => "application/json",
            "accept-encoding"      => "gzip, deflate, br",
            "brand"                => $uiBrand,
            "content-type"         => "application/json",
            "device-type"          => "DESKTOP",
            "device-user-agent-id" => $deviceId,
            "eapid"                => $eapid,
            "pointofsaleid"        => $pointOfSaleId,
            "siteid"               => $site_id,
            "tpid"                 => $tpid,
            "trace-id"             => $traceId,
            "x-mc1-guid"           => $guid,
            "x-remote-addr"        => $remoteAddress,
            "X-USER-AGENT"         => $this->http->userAgent,
            "X-B3-Traceid:"        => $xB3Traceid,
            "X-Xss-Protection"     => "1; mode=block",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.cheaptickets.com/eg-auth-svcs/authenticate/password", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = [
            'https://www.cheaptickets.com/account/logout',
            'https://www.cheaptickets.com/account/login?destinationUrl=%2F',
        ];

        return $arg;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status === true) {
            $this->delay();

            return true;
        }
        $failure = $response->failure ?? null;
        $requestId = $response->requestId ?? null;
        $message = $response->message ?? null;

        if ($status === false && $failure === null && $requestId === null) {
            throw new CheckException("Email and password don't match. Try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message === 'Email constraint not met') {
            throw new CheckException("Enter a valid email.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        /*
         * this method like a travelocity
         */
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
            $this->delay();
        }

        // Name
        $name = $this->http->FindSingleNode("//li[@id = 'fullname']");

        if (!isset($name)) {
            $name = $this->http->FindPreg('/>([^\'>]*)\'s information/ims');
            $name = preg_replace('/&nbsp;/ims', ' ', $name);
        }

        if (empty($name)) {
            if ($prop11 = $this->http->FindPreg("/\"prop11\":\"([^\"]+)/ims")) {
                $this->http->GetURL("https://www.cheaptickets.com/users/{$prop11}/profile?_=" . time() . date("B"));
            }
            $response = $this->http->JsonLog();

            if (isset($response->firstname, $response->middlename, $response->lastname)) {
                $name = Html::cleanXMLValue($response->firstname . " " . $response->middlename . " " . $response->lastname);
            }
        }
        $this->SetProperty("Name", beautifulName($name));

        // CheapCash balance
        $this->http->GetURL("https://www.cheaptickets.com/account/myclub");
        $this->delay();

        if (!$this->SetBalance($this->http->FindSingleNode("//h2[contains(text(), 'CheapCash balance:')]/span"))) {
            if (!empty($this->Properties['Name']) || $this->http->FindSingleNode("//span[@class = 'userName']")) {
                $this->SetBalanceNA();
            }
        }
        // Expiration Date
        $expNodes = $this->http->XPath->query("//td[@data-table-category = 'expiring-date']");
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");

        for ($i = 0; $i < $expNodes->length; $i++) {
            $date = Html::cleanXMLValue($expNodes->item($i)->nodeValue);
            $date = strtotime($date);

            if ($date && (!isset($exp) || $date < $exp) && $date > time()) {
                $exp = $date;
                $this->SetExpirationDate($exp);
            }// if ($date && (!isset($exp) || $date < $exp) && $date > time())
        }// for ($i = 0; $i < $expNodes->length; $i++)

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['Name']) || isset($response->id)) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function ParseItineraries()
    {
        $this->http->GetURL("https://www.cheaptickets.com/trips");
        $this->delay();
        $expedia = $this->getExpedia();

        return $expedia->ParseItineraries('www.cheaptickets.com', $this->ParsePastIts);
    }

    public function GetConfirmationFields()
    {
        return [
            "Email" => [
                "Caption"  => "Email Address",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
            "ConfNo" => [
                "Caption"  => "Itinerary Number",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.cheaptickets.com/trips/booking-search?view=SEARCH_BY_ITINERARY_NUMBER_AND_EMAIL";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $this->sendNotification('check confirmation // MI');
        $duaid = $this->http->FindPreg('#\\\\"duaid\\\\":\\\\"([\w\-]+)\\\\",\\\\"#');

        if (!isset($duaid)) {
            return [];
        }

        $headers = [
            'Accept'               => '*/*',
            'Content-Type'         => 'application/json',
            'client-info'          => 'trips-pwa,4484d3137c7eb9c7e64b788b606f32fdb276b64b,us-west-2',
            'Origin'               => 'https://www.cheaptickets.com',
        ];
        $data = '[{"operationName":"TripSearchBookingQuery","variables":{"viewType":"SEARCH_RESULT","context":{"siteId":69,"locale":"en_US","eapid":0,"currency":"BRL","device":{"type":"DESKTOP"},"identity":{"duaid":"' . $duaid . '","expUserId":"-1","tuid":"-1","authState":"ANONYMOUS"},"privacyTrackingState":"CAN_NOT_TRACK","debugContext":{"abacusOverrides":[],"alterMode":"RELEASED"}},"searchInput":[{"key":"EMAIL_ADDRESS","value":"' . $arFields['Email'] . '"},{"key":"ITINERARY_NUMBER","value":"' . $arFields['ConfNo'] . '"}]},"query":"query TripSearchBookingQuery($context: ContextInput!, $searchInput: [GraphQLPairInput!], $viewType: TripsSearchBookingView!) {\n  trips(context: $context) {\n    searchBooking(searchInput: $searchInput, viewType: $viewType) {\n      ...TripsViewFragment\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsViewFragment on TripsView {\n  __typename\n  ...TripsViewContentFragment\n  floatingActionButton {\n    ...TripsFloatingActionButtonFragment\n    __typename\n  }\n  ...TripsDynamicMapFragment\n  pageTitle\n  contentType\n  tripsSideEffects {\n    ...TripsNavigateToViewActionFragment\n    __typename\n  }\n}\n\nfragment TripsViewContentFragment on TripsView {\n  __typename\n  header {\n    ...ViewHeaderFragment\n    __typename\n  }\n  elements {\n    ...TripsCarouselContainerFragment\n    ...TripsSectionContainerFragment\n    ...TripsFormContainerFragment\n    ...TripsListFlexContainerFragment\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsSlimCardFragment\n    ...TripsMapCardFragment\n    ...TripsIllustrationCardFragment\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    ...TripsPageBreakFragment\n    ...TripsContainerDividerFragment\n    ...TripsLodgingUpgradesPrimerFragment\n    ...TripItemContextualCardsPrimerFragment\n    __typename\n  }\n  notifications: customerNotifications {\n    ...TripsCustomerNotificationsFragment\n    __typename\n  }\n  toast {\n    ...TripsToastFragment\n    __typename\n  }\n  contentType\n}\n\nfragment ViewHeaderFragment on TripsViewHeader {\n  __typename\n  primary\n  secondaries\n  toolbar {\n    ...ToolbarFragment\n    __typename\n  }\n  signal {\n    type\n    reference\n    __typename\n  }\n}\n\nfragment TripsTertiaryButtonFragment on TripsTertiaryButton {\n  __typename\n  primary\n  accessibility {\n    label\n    __typename\n  }\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsLinkActionFragment\n    ...MapDirectionsActionFragment\n    ...TripsWriteToClipboardActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenInviteDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerForItemActionFragment\n    ...TripsInviteActionFragment\n    ...TripsSaveNewTripActionFragment\n    ...TripsFormActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    __typename\n  }\n}\n\nfragment TripsLinkActionFragment on TripsLinkAction {\n  __typename\n  resource {\n    value\n    __typename\n  }\n  target\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  clickstreamAnalytics {\n    ...ClickstreamAnalyticsFragment\n    __typename\n  }\n}\n\nfragment ClickstreamAnalyticsFragment on ClickstreamAnalytics {\n  event {\n    clickstreamTraceId\n    eventCategory\n    eventName\n    eventType\n    eventVersion\n    __typename\n  }\n  payload {\n    ... on TripRecommendationModule {\n      title\n      responseId\n      recommendations {\n        id\n        position\n        priceDisplayed\n        currencyCode\n        name\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment MapDirectionsActionFragment on TripsMapDirectionsAction {\n  __typename\n  data {\n    center {\n      latitude\n      longitude\n      __typename\n    }\n    zoom\n    __typename\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  url\n}\n\nfragment TripsWriteToClipboardActionFragment on CopyToClipboardAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  value\n}\n\nfragment TripsVirtualAgentInitActionFragment on TripsVirtualAgentInitAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  applicationName\n  pageName\n  clientOverrides {\n    enableAutoOpenChatWidget\n    enableProactiveConversation\n    subscribedEvents\n    conversationProperties {\n      launchPoint\n      pageName\n      skipWelcome\n      __typename\n    }\n    intentMessage {\n      ... on VirtualAgentCancelIntentMessage {\n        action\n        intent\n        emailAddress\n        orderLineId\n        orderNumber\n        product\n        __typename\n      }\n      __typename\n    }\n    intentArguments {\n      id\n      value\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsOpenDialogActionFragment on TripsOpenDialogAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  modalDialog {\n    ...TripsModalDialogFragment\n    __typename\n  }\n}\n\nfragment TripsModalDialogFragment on TripsModalDialog {\n  __typename\n  heading\n  buttonLayout\n  buttons {\n    ...TripsDialogPrimaryButtonFragment\n    ...TripsDialogSecondaryButtonFragment\n    ...TripsDialogTertiaryButtonFragment\n    __typename\n  }\n  content {\n    ...TripsEmbeddedContentListFragment\n    __typename\n  }\n}\n\nfragment TripsDialogPrimaryButtonFragment on TripsPrimaryButton {\n  __typename\n  primary\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsCancelCarActionFragment\n    ...TripsCancelInsuranceActionFragment\n    ...TripsCancelActivityActionFragment\n    ...TripsCancellationActionFragment\n    ...TripsCloseDialogActionFragment\n    ...TripsDeleteTripActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    ...TripsUnsaveItemFromTripActionFragment\n    ...TripsLinkActionFragment\n    __typename\n  }\n}\n\nfragment TripsDialogTertiaryButtonFragment on TripsTertiaryButton {\n  __typename\n  primary\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsCancelCarActionFragment\n    ...TripsCancelInsuranceActionFragment\n    ...TripsCancelActivityActionFragment\n    ...TripsCancellationActionFragment\n    ...TripsCloseDialogActionFragment\n    __typename\n  }\n}\n\nfragment TripsDialogSecondaryButtonFragment on TripsSecondaryButton {\n  __typename\n  primary\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n  action {\n    ...TripsCancelCarActionFragment\n    ...TripsCancelInsuranceActionFragment\n    ...TripsCancelActivityActionFragment\n    ...TripsCancellationActionFragment\n    ...TripsCloseDialogActionFragment\n    __typename\n  }\n}\n\nfragment TripsCloseDialogActionFragment on TripsCloseDialogAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsDeleteTripActionFragment on TripsDeleteTripAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  overview {\n    tripViewId\n    filter\n    __typename\n  }\n}\n\nfragment TripsCancelCarActionFragment on TripsCancelCarAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  orderLineNumbers\n}\n\nfragment TripsCancelInsuranceActionFragment on TripsCancelInsuranceAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  orderLineNumber\n}\n\nfragment TripsCancelActivityActionFragment on TripsCancelActivityAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  activityOrderLineNumbers: orderLineNumbers\n  orderNumber\n}\n\nfragment TripsCancellationActionFragment on TripsCancellationAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  itemToCancel: item {\n    filter\n    tripItemId\n    tripViewId\n    __typename\n  }\n  cancellationType\n  cancellationAttributes {\n    orderNumber\n    orderLineNumbers\n    refundAmount\n    penaltyAmount\n    __typename\n  }\n}\n\nfragment TripsUnsaveItemFromTripActionFragment on TripsUnsaveItemFromTripAction {\n  __typename\n  tripEntity\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  tripItem {\n    tripItemId\n    tripViewId\n    filter\n    __typename\n  }\n  emitSignals {\n    ...TripsEmitSignalFragment\n    __typename\n  }\n}\n\nfragment TripsEmitSignalFragment on TripsEmitSignal {\n  signal {\n    type\n    reference\n    __typename\n  }\n  values {\n    key\n    value {\n      ...TripsSignalFieldIdValueFragment\n      ...TripsSignalFieldIdExistingValuesFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsSignalFieldIdExistingValuesFragment on TripsSignalFieldIdExistingValues {\n  ids\n  prefixes\n  __typename\n}\n\nfragment TripsSignalFieldIdValueFragment on TripsSignalFieldIdValue {\n  id\n  __typename\n}\n\nfragment TripsEmbeddedContentListFragment on TripsEmbeddedContentList {\n  __typename\n  primary\n  secondaries\n  listTheme: theme\n  items {\n    ...TripsEmbeddedContentLineItemFragment\n    __typename\n  }\n}\n\nfragment TripsEmbeddedContentLineItemFragment on TripsEmbeddedContentLineItem {\n  __typename\n  items {\n    __typename\n    ...TripsEmbeddedContentItemFragment\n  }\n}\n\nfragment TripsEmbeddedContentItemFragment on TripsEmbeddedContentItem {\n  __typename\n  primary\n  icon {\n    id\n    description\n    title\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    __typename\n  }\n}\n\nfragment TripsOpenFullScreenDialogActionFragment on TripsOpenFullScreenDialogAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  dialog {\n    ...TripsFullScreenDialogFragment\n    __typename\n  }\n}\n\nfragment TripsFullScreenDialogFragment on TripsFullScreenDialog {\n  __typename\n  heading\n  closeButton {\n    ...TripsCloseDialogButtonFragment\n    __typename\n  }\n  content {\n    ...TripsEmbeddedContentCardFragment\n    __typename\n  }\n}\n\nfragment TripsCloseDialogButtonFragment on TripsCloseDialogButton {\n  __typename\n  primary\n  icon {\n    __typename\n    id\n    description\n    title\n  }\n  action {\n    __typename\n    analytics {\n      __typename\n      referrerId\n      linkName\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n    }\n  }\n}\n\nfragment TripsEmbeddedContentCardFragment on TripsEmbeddedContentCard {\n  __typename\n  primary\n  items {\n    ...TripsEmbeddedContentListFragment\n    __typename\n  }\n}\n\nfragment TripsOpenMenuActionFragment on TripsOpenMenuAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  floatingMenu {\n    ...TripsFloatingMenuFragment\n    __typename\n  }\n}\n\nfragment TripsFloatingMenuFragment on TripsFloatingMenu {\n  items {\n    ...TripsMenuTitleFragment\n    ...TripsMenuListItemFragment\n    ...TripsMenuListTitleFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsMenuListItemFragment on TripsMenuListItem {\n  __typename\n  primary\n  icon {\n    id\n    description\n    title\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenChangeDatesDatePickerActionFragment\n    ...TripsOpenEmailDrawerActionFragment\n    ...TripsOpenEditTripDrawerActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    ...TripsOpenSaveToTripDrawerActionFragment\n    ...TripsCustomerNotificationOpenInAppActionFragment\n    __typename\n  }\n}\n\nfragment TripsNavigateToViewActionFragment on TripsNavigateToViewAction {\n  __typename\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  tripItemId\n  tripViewId\n  viewFilter {\n    filter\n    __typename\n  }\n  viewType\n  viewUrl\n}\n\nfragment TripsOpenChangeDatesDatePickerActionFragment on TripsOpenChangeDatesDatePickerAction {\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  attributes {\n    ...TripsListDatePickerAttributesFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsListDatePickerAttributesFragment on TripsDatePickerAttributes {\n  analytics {\n    closeAnalytics {\n      eventType\n      linkName\n      referrerId\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  buttonText\n  changeDatesAction {\n    analytics {\n      eventType\n      linkName\n      referrerId\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n      __typename\n    }\n    item {\n      filter\n      tripItemId\n      tripViewId\n      __typename\n    }\n    tripEntity\n    __typename\n  }\n  maxDateRange\n  maxDateRangeMessage\n  calendarSelectionType\n  daysBookableInAdvance\n  itemDates {\n    end {\n      ...TripsListDateFragment\n      __typename\n    }\n    start {\n      ...TripsListDateFragment\n      __typename\n    }\n    __typename\n  }\n  productId\n  __typename\n}\n\nfragment TripsListDateFragment on Date {\n  day\n  month\n  year\n  __typename\n}\n\nfragment TripsOpenEmailDrawerActionFragment on TripsOpenEmailDrawerAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  item {\n    __typename\n    filter\n    tripItemId\n    tripViewId\n  }\n}\n\nfragment TripsOpenEditTripDrawerActionFragment on TripsOpenEditTripDrawerAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  overview {\n    __typename\n    filter\n    tripViewId\n  }\n}\n\nfragment TripsOpenMoveTripItemDrawerActionFragment on TripsOpenMoveTripItemDrawerAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  item {\n    filter\n    tripEntity\n    tripItemId\n    tripViewId\n    __typename\n  }\n}\n\nfragment TripsOpenSaveToTripDrawerActionFragment on TripsOpenSaveToTripDrawerAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  input {\n    itemId\n    source\n    pageLocation\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsListSaveItemAttributesFragment on TripsSaveItemAttributes {\n  ...TripsListSaveStayAttributesFragment\n  ...TripsListSaveActivityAttributesFragment\n  ...TripsSaveFlightSearchAttributesFragment\n  __typename\n}\n\nfragment TripsListSaveActivityAttributesFragment on TripsSaveActivityAttributes {\n  regionId\n  dateRange {\n    start {\n      ...TripsListDateFragment\n      __typename\n    }\n    end {\n      ...TripsListDateFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsListSaveStayAttributesFragment on TripsSaveStayAttributes {\n  checkInDate {\n    ...TripsListDateFragment\n    __typename\n  }\n  checkoutDate {\n    ...TripsListDateFragment\n    __typename\n  }\n  regionId\n  roomConfiguration {\n    numberOfAdults\n    childAges\n    __typename\n  }\n  __typename\n}\n\nfragment TripsSaveFlightSearchAttributesFragment on TripsSaveFlightSearchAttributes {\n  searchCriteria {\n    primary {\n      journeyCriterias {\n        arrivalDate {\n          ...TripsListDateFragment\n          __typename\n        }\n        departureDate {\n          ...TripsListDateFragment\n          __typename\n        }\n        destination\n        destinationAirportLocationType\n        origin\n        originAirportLocationType\n        __typename\n      }\n      searchPreferences {\n        advancedFilters\n        airline\n        cabinClass\n        __typename\n      }\n      travelers {\n        age\n        type\n        __typename\n      }\n      tripType\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsCustomerNotificationOpenInAppActionFragment on TripsCustomerNotificationOpenInAppAction {\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  notificationAttributes {\n    notificationLocation\n    xPageID\n    optionalContext {\n      tripItemId\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsMenuListTitleFragment on TripsMenuListTitle {\n  __typename\n  primary\n}\n\nfragment TripsMenuTitleFragment on TripsMenuTitle {\n  __typename\n  primary\n}\n\nfragment ClientSideImpressionAnalyticsFragment on ClientSideImpressionAnalytics {\n  uisPrimeAnalytics {\n    ...ClientSideAnalyticsFragment\n    __typename\n  }\n  clickstreamAnalytics {\n    ...ClickstreamAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment ClientSideAnalyticsFragment on ClientSideAnalytics {\n  eventType\n  linkName\n  referrerId\n  uisPrimeMessages {\n    messageContent\n    schemaName\n    __typename\n  }\n  __typename\n}\n\nfragment TripsOpenInviteDrawerActionFragment on TripsOpenInviteDrawerAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  overview {\n    __typename\n    filter\n    tripViewId\n  }\n}\n\nfragment TripsOpenCreateNewTripDrawerActionFragment on TripsOpenCreateNewTripDrawerAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsOpenCreateNewTripDrawerForItemActionFragment on TripsOpenCreateNewTripDrawerForItemAction {\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  createTripMetadata {\n    moveItem {\n      filter\n      tripEntity\n      tripItemId\n      tripViewId\n      __typename\n    }\n    saveItemInput {\n      itemId\n      pageLocation\n      attributes {\n        ...TripsListSaveItemAttributesFragment\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInviteActionFragment on TripsInviteAction {\n  __typename\n  inputIds\n  overview {\n    filter\n    tripViewId\n    __typename\n  }\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsSaveNewTripActionFragment on TripsSaveNewTripAction {\n  __typename\n  inputIds\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsFormActionFragment on TripsFormAction {\n  __typename\n  validatedInputIds\n  type\n  formData {\n    ...TripsFormDataFragment\n    __typename\n  }\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsFormDataFragment on TripsFormData {\n  __typename\n  ...TripsCreateTripFromMovedItemFragment\n  ...TripsInviteFragment\n  ...TripsSendItineraryEmailFragment\n  ...TripsUpdateTripFragment\n  ...TripsCreateTripFromItemFragment\n}\n\nfragment TripsCreateTripFromMovedItemFragment on TripsCreateTripFromMovedItem {\n  __typename\n  item {\n    filter\n    tripEntity\n    tripItemId\n    tripViewId\n    __typename\n  }\n}\n\nfragment TripsInviteFragment on TripsInvite {\n  __typename\n  overview {\n    filter\n    tripViewId\n    __typename\n  }\n}\n\nfragment TripsSendItineraryEmailFragment on TripsSendItineraryEmail {\n  __typename\n  item {\n    tripViewId\n    tripItemId\n    filter\n    __typename\n  }\n}\n\nfragment TripsUpdateTripFragment on TripsUpdateTrip {\n  __typename\n  overview {\n    filter\n    tripViewId\n    __typename\n  }\n  emitSignals {\n    ...TripsEmitSignalFragment\n    __typename\n  }\n}\n\nfragment TripsCreateTripFromItemFragment on TripsCreateTripFromItem {\n  __typename\n  input {\n    itemId\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    pageLocation\n    __typename\n  }\n}\n\nfragment ToolbarFragment on TripsToolbar {\n  __typename\n  primary\n  secondaries\n  accessibility {\n    label\n    __typename\n  }\n  actions {\n    primary {\n      ...TripsTertiaryButtonFragment\n      __typename\n    }\n    secondaries {\n      ...TripsTertiaryButtonFragment\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsCarouselContainerFragment on TripsCarouselContainer {\n  __typename\n  heading\n  subheading {\n    ...TripsCarouselSubHeaderFragment\n    __typename\n  }\n  elements {\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsSlimCardFragment\n    __typename\n  }\n  accessibility {\n    ... on TripsCarouselAccessibilityData {\n      nextButton\n      prevButton\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsContentCardFragment on TripsContentCard {\n  __typename\n  primary\n  secondaries\n  rows {\n    __typename\n    ...ContentColumnsFragment\n    ...TripsViewContentListFragment\n    ...EmblemsInlineContentFragment\n  }\n}\n\nfragment ContentColumnsFragment on TripsContentColumns {\n  __typename\n  primary\n  columns {\n    __typename\n    ...TripsViewContentListFragment\n  }\n}\n\nfragment TripsViewContentListFragment on TripsContentList {\n  __typename\n  primary\n  secondaries\n  listTheme: theme\n  items {\n    ...TripsViewContentLineItemFragment\n    __typename\n  }\n}\n\nfragment TripsViewContentLineItemFragment on TripsViewContentLineItem {\n  __typename\n  items {\n    __typename\n    ...TripsViewContentItemFragment\n  }\n}\n\nfragment TripsViewContentItemFragment on TripsViewContentItem {\n  __typename\n  primary\n  icon {\n    id\n    description\n    title\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    __typename\n  }\n}\n\nfragment EmblemsInlineContentFragment on TripsEmblemsInlineContent {\n  __typename\n  primary\n  secondaries\n  emblems {\n    ...TripsEmblemFragment\n    __typename\n  }\n}\n\nfragment TripsEmblemFragment on TripsEmblem {\n  ...BadgeFragment\n  ...EGDSMarkFragment\n  ...EGDSStandardBadgeFragment\n  ...EGDSLoyaltyBadgeFragment\n  ...EGDSProgramBadgeFragment\n  __typename\n}\n\nfragment BadgeFragment on TripsBadge {\n  accessibility\n  text\n  tripsBadgeTheme: theme\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n  __typename\n}\n\nfragment UIGraphicFragment on UIGraphic {\n  ...EGDSIconFragment\n  ...EGDSMarkFragment\n  ...EGDSIllustrationFragment\n  __typename\n}\n\nfragment EGDSIconFragment on Icon {\n  description\n  id\n  size\n  theme\n  title\n  withBackground\n  __typename\n}\n\nfragment EGDSMarkFragment on Mark {\n  description\n  id\n  markSize: size\n  url {\n    ... on HttpURI {\n      __typename\n      relativePath\n      value\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment EGDSIllustrationFragment on Illustration {\n  id\n  description\n  link: url\n  __typename\n}\n\nfragment EGDSStandardBadgeFragment on EGDSStandardBadge {\n  accessibility\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n  text\n  size\n  theme\n  __typename\n}\n\nfragment EGDSLoyaltyBadgeFragment on EGDSLoyaltyBadge {\n  accessibility\n  text\n  size\n  theme\n  __typename\n}\n\nfragment EGDSProgramBadgeFragment on EGDSProgramBadge {\n  accessibility\n  text\n  theme\n  __typename\n}\n\nfragment TripsFullBleedImageCardFragment on TripsFullBleedImageCard {\n  primary\n  secondaries\n  background {\n    url\n    description\n    __typename\n  }\n  badgeList {\n    ...EGDSBadgeFragment\n    __typename\n  }\n  icons {\n    id\n    description\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment EGDSBadgeFragment on EGDSBadge {\n  ...EGDSStandardBadgeFragment\n  ...EGDSLoyaltyBadgeFragment\n  ...EGDSProgramBadgeFragment\n  __typename\n}\n\nfragment TripsImageTopCardFragment on TripsImageTopCard {\n  primary\n  secondaries\n  background {\n    url\n    description\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...MapDirectionsActionFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment TripsSlimCardFragment on TripsSlimCard {\n  graphic {\n    ...EGDSIconFragment\n    ...EGDSMarkFragment\n    ...EGDSIllustrationFragment\n    __typename\n  }\n  primary\n  secondaries\n  subTexts {\n    ...TripsTextFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  action {\n    ...TripsLinkActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsTextFragment on TripsText {\n  ...EGDSGraphicTextFragment\n  ...EGDSPlainTextFragment\n  __typename\n}\n\nfragment EGDSGraphicTextFragment on EGDSGraphicText {\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n  text\n  __typename\n}\n\nfragment EGDSPlainTextFragment on EGDSPlainText {\n  text\n  __typename\n}\n\nfragment NavigateToManageBookingActionFragment on TripsNavigateToManageBookingAction {\n  __typename\n  item {\n    __typename\n    tripItemId\n    tripViewId\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  url\n}\n\nfragment TripsCarouselSubHeaderFragment on TripsCarouselSubHeader {\n  ...EGDSGraphicTextFragment\n  ...EGDSPlainTextFragment\n  __typename\n}\n\nfragment TripsSectionContainerFragment on TripsSectionContainer {\n  ...TripsInternalSectionContainerFragment\n  elements {\n    ...TripsCarouselContainerFragment\n    ...TripsMediaGalleryFragment\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsFittedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsMapCardFragment\n    ...TripsImageSlimCardFragment\n    ...TripsSlimCardFragment\n    ...TripsMapCardFragment\n    ...TripsIllustrationCardFragment\n    ...TripsFlightMapCardFragment\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    ...TripsPricePresentationFragment\n    ...TripsSubSectionContainerFragment\n    ...TripsListFlexContainerFragment\n    ...TripsServiceRequestsButtonPrimerFragment\n    ...TripsSlimCardContainerFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsFittedImageCardFragment on TripsFittedImageCard {\n  primary\n  secondaries\n  img: image {\n    url\n    description\n    aspectRatio\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  imageType\n  __typename\n}\n\nfragment TripsMapCardFragment on TripsMapCard {\n  primary\n  secondaries\n  data {\n    center {\n      latitude\n      longitude\n      __typename\n    }\n    zoom\n    __typename\n  }\n  image {\n    url\n    description\n    __typename\n  }\n  action {\n    ...MapActionFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment MapActionFragment on TripsMapAction {\n  __typename\n  data {\n    center {\n      latitude\n      longitude\n      __typename\n    }\n    zoom\n    __typename\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsImageSlimCardFragment on TripsImageSlimCard {\n  ...TripsInternalImageSlimCardFragment\n  signal {\n    type\n    reference\n    __typename\n  }\n  cardIcon {\n    ...TripsIconFragment\n    __typename\n  }\n  primaryAction {\n    ...TripsLinkActionFragment\n    ...TripsMoveItemToTripActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsSaveItemToTripActionFragment\n    ...TripsUnsaveItemFromTripActionFragment\n    __typename\n  }\n  itemPricePrimer {\n    ... on TripsSavedItemPricePrimer {\n      ...TripsSavedItemPricePrimerFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInternalImageSlimCardFragment on TripsImageSlimCard {\n  primary\n  secondaries\n  badgeList {\n    ...EGDSBadgeFragment\n    __typename\n  }\n  thumbnail {\n    aspectRatio\n    description\n    url\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  accessibility {\n    hint\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment TripsIconFragment on TripsIcon {\n  action {\n    ...TripsOpenMenuActionFragment\n    ...TripsSaveItemToTripActionFragment\n    ...TripsUnsaveItemFromTripActionFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    title\n    theme\n    __typename\n  }\n  label\n  __typename\n}\n\nfragment TripsSaveItemToTripActionFragment on TripsSaveItemToTripAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  saveItemInput {\n    itemId\n    source\n    pageLocation\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    __typename\n  }\n  tripId\n}\n\nfragment TripsSavedItemPricePrimerFragment on TripsSavedItemPricePrimer {\n  tripItem {\n    ...TripItemFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripItemFragment on TripItem {\n  filter\n  tripItemId\n  tripViewId\n  __typename\n}\n\nfragment TripsMoveItemToTripActionFragment on TripsMoveItemToTripAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  data {\n    item {\n      filter\n      tripEntity\n      tripItemId\n      tripViewId\n      __typename\n    }\n    toTripId\n    toTripName\n    __typename\n  }\n}\n\nfragment TripsIllustrationCardFragment on TripsIllustrationCard {\n  primary\n  secondaries\n  illustration {\n    url\n    description\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsPrimaryButtonFragment on TripsPrimaryButton {\n  ...TripsInternalPrimaryButtonFragment\n  action {\n    ...TripsLinkActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...MapDirectionsActionFragment\n    ...TripsWriteToClipboardActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsSendItineraryEmailActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsUpdateTripActionFragment\n    ...TripsOpenInviteDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerForItemActionFragment\n    ...TripsInviteActionFragment\n    ...TripsSaveNewTripActionFragment\n    ...TripsFormActionFragment\n    ...TripsDeleteTripActionFragment\n    ...TripsInviteAcceptActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    ...TripsAcceptInviteAndNavigateToOverviewActionFragment\n    ...TripsCreateTripFromItemActionFragment\n    __typename\n  }\n  width\n  __typename\n}\n\nfragment TripsInternalPrimaryButtonFragment on TripsPrimaryButton {\n  __typename\n  primary\n  accessibility {\n    label\n    __typename\n  }\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  width\n}\n\nfragment TripsSendItineraryEmailActionFragment on TripsSendItineraryEmailAction {\n  __typename\n  inputIds\n  item {\n    __typename\n    tripViewId\n    tripItemId\n    filter\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsUpdateTripActionFragment on TripsUpdateTripAction {\n  __typename\n  inputIds\n  overview {\n    __typename\n    filter\n    tripViewId\n  }\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  emitSignals {\n    ...TripsEmitSignalFragment\n    __typename\n  }\n}\n\nfragment TripsInviteAcceptActionFragment on TripsInviteAcceptAction {\n  __typename\n  inviteId\n  analytics {\n    __typename\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n}\n\nfragment TripsAcceptInviteAndNavigateToOverviewActionFragment on TripsAcceptInviteAndNavigateToOverviewAction {\n  __typename\n  analytics {\n    __typename\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n  }\n  overview {\n    tripViewId\n    filter\n    inviteId\n    __typename\n  }\n  overviewUrl\n}\n\nfragment TripsCreateTripFromItemActionFragment on TripsCreateTripFromItemAction {\n  __typename\n  analytics {\n    linkName\n    referrerId\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n  saveItemInput {\n    itemId\n    pageLocation\n    attributes {\n      ...TripsListSaveItemAttributesFragment\n      __typename\n    }\n    __typename\n  }\n  inputIds\n}\n\nfragment TripsSecondaryButtonFragment on TripsSecondaryButton {\n  __typename\n  primary\n  accessibility {\n    label\n    __typename\n  }\n  icon {\n    description\n    id\n    __typename\n  }\n  disabled\n  action {\n    ...TripsLinkActionFragment\n    ...NavigateToManageBookingActionFragment\n    ...MapDirectionsActionFragment\n    ...TripsWriteToClipboardActionFragment\n    ...TripsVirtualAgentInitActionFragment\n    ...TripsOpenDialogActionFragment\n    ...TripsOpenFullScreenDialogActionFragment\n    ...TripsOpenMenuActionFragment\n    ...TripsNavigateToViewActionFragment\n    ...TripsOpenInviteDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerActionFragment\n    ...TripsOpenCreateNewTripDrawerForItemActionFragment\n    ...TripsInviteActionFragment\n    ...TripsSaveNewTripActionFragment\n    ...TripsFormActionFragment\n    ...TripsOpenMoveTripItemDrawerActionFragment\n    __typename\n  }\n}\n\nfragment TripsFlightMapCardFragment on TripsFlightPathMapCard {\n  primary\n  secondaries\n  image {\n    url\n    description\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsPricePresentationFragment on TripsPricePresentation {\n  __typename\n  pricePresentation {\n    __typename\n    ...PricePresentationFragment\n  }\n}\n\nfragment PricePresentationFragment on PricePresentation {\n  title {\n    primary\n    __typename\n  }\n  sections {\n    ...PricePresentationSectionFragment\n    __typename\n  }\n  footer {\n    header\n    messages {\n      ...PriceLineElementFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationSectionFragment on PricePresentationSection {\n  header {\n    name {\n      ...PricePresentationLineItemEntryFragment\n      __typename\n    }\n    enrichedValue {\n      ...PricePresentationLineItemEntryFragment\n      __typename\n    }\n    __typename\n  }\n  subSections {\n    ...PricePresentationSubSectionFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationSubSectionFragment on PricePresentationSubSection {\n  header {\n    name {\n      primaryMessage {\n        __typename\n        ... on PriceLineText {\n          primary\n          __typename\n        }\n        ... on PriceLineHeading {\n          primary\n          __typename\n        }\n      }\n      __typename\n    }\n    enrichedValue {\n      ...PricePresentationLineItemEntryFragment\n      __typename\n    }\n    __typename\n  }\n  items {\n    ...PricePresentationLineItemFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationLineItemFragment on PricePresentationLineItem {\n  enrichedValue {\n    ...PricePresentationLineItemEntryFragment\n    __typename\n  }\n  name {\n    ...PricePresentationLineItemEntryFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationLineItemEntryFragment on PricePresentationLineItemEntry {\n  primaryMessage {\n    ...PriceLineElementFragment\n    __typename\n  }\n  secondaryMessages {\n    ...PriceLineElementFragment\n    __typename\n  }\n  __typename\n}\n\nfragment PriceLineElementFragment on PricePresentationLineItemMessage {\n  __typename\n  ...PriceLineTextFragment\n  ...PriceLineHeadingFragment\n  ...PriceLineBadgeFragment\n  ...InlinePriceLineTextFragment\n}\n\nfragment PriceLineTextFragment on PriceLineText {\n  __typename\n  theme\n  primary\n  weight\n  additionalInfo {\n    ...AdditionalInformationPopoverFragment\n    __typename\n  }\n  additionalInformation {\n    ...PricePresentationAdditionalInformationFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  graphic {\n    ...UIGraphicFragment\n    __typename\n  }\n}\n\nfragment PricePresentationAdditionalInformationFragment on PricePresentationAdditionalInformation {\n  ...PricePresentationAdditionalInformationDialogFragment\n  ...PricePresentationAdditionalInformationPopoverFragment\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationDialogFragment on PricePresentationAdditionalInformationDialog {\n  closeAnalytics {\n    linkName\n    referrerId\n    __typename\n  }\n  enrichedSecondaries {\n    ...AdditionalInformationPopoverSectionFragment\n    __typename\n  }\n  footer {\n    ...PricePresentationAdditionalInformationDialogFooterFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  openAnalytics {\n    linkName\n    referrerId\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationDialogFooterFragment on EGDSDialogFooter {\n  ... on EGDSInlineDialogFooter {\n    buttons {\n      ...PricePresentationAdditionalInformationDialogFooterButtonsFragment\n      __typename\n    }\n    __typename\n  }\n  ... on EGDSStackedDialogFooter {\n    buttons {\n      ...PricePresentationAdditionalInformationDialogFooterButtonsFragment\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationDialogFooterButtonsFragment on EGDSButton {\n  accessibility\n  disabled\n  primary\n  __typename\n}\n\nfragment PricePresentationAdditionalInformationPopoverFragment on PricePresentationAdditionalInformationPopover {\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  enrichedSecondaries {\n    ...AdditionalInformationPopoverSectionFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  __typename\n}\n\nfragment AdditionalInformationPopoverFragment on AdditionalInformationPopover {\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n  enrichedSecondaries {\n    ...AdditionalInformationPopoverSectionFragment\n    __typename\n  }\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  __typename\n}\n\nfragment AdditionalInformationPopoverSectionFragment on AdditionalInformationPopoverSection {\n  __typename\n  ... on AdditionalInformationPopoverTextSection {\n    ...AdditionalInformationPopoverTextSectionFragment\n    __typename\n  }\n  ... on AdditionalInformationPopoverListSection {\n    ...AdditionalInformationPopoverListSectionFragment\n    __typename\n  }\n  ... on AdditionalInformationPopoverGridSection {\n    ...AdditionalInformationPopoverGridSectionFragment\n    __typename\n  }\n}\n\nfragment AdditionalInformationPopoverTextSectionFragment on AdditionalInformationPopoverTextSection {\n  __typename\n  text {\n    text\n    ...EGDSStandardLinkFragment\n    __typename\n  }\n}\n\nfragment AdditionalInformationPopoverListSectionFragment on AdditionalInformationPopoverListSection {\n  __typename\n  content {\n    __typename\n    items {\n      text\n      __typename\n    }\n  }\n}\n\nfragment AdditionalInformationPopoverGridSectionFragment on AdditionalInformationPopoverGridSection {\n  __typename\n  subSections {\n    header {\n      name {\n        primaryMessage {\n          ...AdditionalInformationPopoverGridLineItemMessageFragment\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    items {\n      name {\n        ...AdditionalInformationPopoverGridLineItemEntryFragment\n        __typename\n      }\n      enrichedValue {\n        ...AdditionalInformationPopoverGridLineItemEntryFragment\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment AdditionalInformationPopoverGridLineItemEntryFragment on PricePresentationLineItemEntry {\n  primaryMessage {\n    ...AdditionalInformationPopoverGridLineItemMessageFragment\n    __typename\n  }\n  secondaryMessages {\n    ...AdditionalInformationPopoverGridLineItemMessageFragment\n    __typename\n  }\n  __typename\n}\n\nfragment AdditionalInformationPopoverGridLineItemMessageFragment on PricePresentationLineItemMessage {\n  ... on PriceLineText {\n    __typename\n    primary\n  }\n  ... on PriceLineHeading {\n    __typename\n    tag\n    size\n    primary\n  }\n  __typename\n}\n\nfragment PriceLineHeadingFragment on PriceLineHeading {\n  __typename\n  primary\n  tag\n  size\n  additionalInfo {\n    ...AdditionalInformationPopoverFragment\n    __typename\n  }\n  additionalInformation {\n    ...PricePresentationAdditionalInformationFragment\n    __typename\n  }\n  icon {\n    id\n    description\n    size\n    __typename\n  }\n}\n\nfragment PriceLineBadgeFragment on PriceLineBadge {\n  __typename\n  badge {\n    accessibility\n    text\n    theme\n    __typename\n  }\n}\n\nfragment InlinePriceLineTextFragment on InlinePriceLineText {\n  __typename\n  inlineItems {\n    ...PriceLineTextFragment\n    __typename\n  }\n}\n\nfragment EGDSStandardLinkFragment on EGDSStandardLink {\n  action {\n    ...ActionFragment\n    __typename\n  }\n  standardLinkIcon: icon {\n    ...EGDSIconFragment\n    __typename\n  }\n  iconPosition\n  size\n  text\n  __typename\n}\n\nfragment ActionFragment on UILinkAction {\n  accessibility\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  resource {\n    value\n    __typename\n  }\n  target\n  __typename\n}\n\nfragment TripsSubSectionContainerFragment on TripsSectionContainer {\n  __typename\n  heading\n  elements {\n    ...TripsCarouselContainerFragment\n    ...TripsContentCardFragment\n    ...TripsFullBleedImageCardFragment\n    ...TripsFittedImageCardFragment\n    ...TripsImageTopCardFragment\n    ...TripsMapCardFragment\n    ...TripsSlimCardFragment\n    ...TripsIllustrationCardFragment\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    __typename\n  }\n}\n\nfragment TripsInternalSectionContainerFragment on TripsSectionContainer {\n  __typename\n  heading\n  subheadings\n  tripsListSubTexts: subTexts\n  theme\n}\n\nfragment TripsSlimCardContainerFragment on TripsSlimCardContainer {\n  heading\n  subHeaders {\n    ...TripsTextFragment\n    __typename\n  }\n  slimCards {\n    ...TripsSlimCardFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsListFlexContainerFragment on TripsFlexContainer {\n  ...TripsInternalFlexContainerFragment\n  elements {\n    ...TripsPrimaryButtonFragment\n    ...TripsSecondaryButtonFragment\n    ...TripsTertiaryButtonFragment\n    ...TripsAvatarGroupFragment\n    ...TripsEmbeddedContentCardFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInternalFlexContainerFragment on TripsFlexContainer {\n  __typename\n  alignItems\n  direction\n  justifyContent\n  wrap\n  elements {\n    ...TripsInternalFlexContainerItemFragment\n    __typename\n  }\n}\n\nfragment TripsInternalFlexContainerItemFragment on TripsFlexContainerItem {\n  grow\n  __typename\n}\n\nfragment TripsAvatarGroupFragment on TripsAvatarGroup {\n  avatars {\n    ...TripsAvatarFragment\n    __typename\n  }\n  avatarSize\n  showBorder\n  action {\n    ...TripsNavigateToViewActionFragment\n    __typename\n  }\n  accessibility {\n    label\n    __typename\n  }\n  __typename\n}\n\nfragment TripsAvatarFragment on TripsAvatar {\n  name\n  url\n  __typename\n}\n\nfragment TripsServiceRequestsButtonPrimerFragment on TripsServiceRequestsButtonPrimer {\n  buttonStyle\n  itineraryNumber\n  lineOfBusiness\n  orderLineId\n  __typename\n}\n\nfragment TripsMediaGalleryFragment on TripsMediaGallery {\n  __typename\n  accessibilityHeadingText\n  analytics {\n    linkName\n    referrerId\n    __typename\n  }\n  nextButtonText\n  previousButtonText\n  mediaGalleryId: egdsElementId\n  media {\n    ...TripsMediaFragment\n    __typename\n  }\n  mediaGalleryDialogToolbar {\n    ...TripsMediaGalleryDialogFragment\n    __typename\n  }\n}\n\nfragment TripsMediaGalleryDialogFragment on TripsToolbar {\n  primary\n  secondaries\n  actions {\n    primary {\n      icon {\n        description\n        id\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsMediaFragment on TripsMedia {\n  media {\n    ... on Image {\n      url\n      description\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsFormContainerFragment on TripsFormContainer {\n  __typename\n  formTheme\n  elements {\n    ...TripsPrimaryButtonFragment\n    ...TripsContentCardFragment\n    ...TripsValidatedInputFragment\n    __typename\n  }\n}\n\nfragment TripsValidatedInputFragment on TripsValidatedInput {\n  egdsElementId\n  instructions\n  label\n  placeholder\n  required\n  value\n  inputType\n  leftIcon {\n    __typename\n    leftIconId: id\n    title\n    description\n  }\n  rightIcon {\n    __typename\n    rightIconId: id\n    title\n    description\n  }\n  validations {\n    ...EGDSMaxLengthInputValidationFragment\n    ...EGDSMinLengthInputValidationFragment\n    ...EGDSRegexInputValidationFragment\n    ...EGDSRequiredInputValidationFragment\n    ...EGDSTravelersInputValidationFragment\n    ...MultiEmailValidationFragment\n    ...SingleEmailValidationFragment\n    __typename\n  }\n  __typename\n}\n\nfragment EGDSMaxLengthInputValidationFragment on EGDSMaxLengthInputValidation {\n  __typename\n  errorMessage\n  maxLength\n}\n\nfragment EGDSMinLengthInputValidationFragment on EGDSMinLengthInputValidation {\n  __typename\n  errorMessage\n  minLength\n}\n\nfragment EGDSRegexInputValidationFragment on EGDSRegexInputValidation {\n  __typename\n  errorMessage\n  pattern\n}\n\nfragment EGDSRequiredInputValidationFragment on EGDSRequiredInputValidation {\n  __typename\n  errorMessage\n}\n\nfragment EGDSTravelersInputValidationFragment on EGDSTravelersInputValidation {\n  __typename\n  errorMessage\n}\n\nfragment MultiEmailValidationFragment on MultiEmailValidation {\n  __typename\n  errorMessage\n}\n\nfragment SingleEmailValidationFragment on SingleEmailValidation {\n  __typename\n  errorMessage\n}\n\nfragment TripsPageBreakFragment on TripsPageBreak {\n  __typename\n  _empty\n}\n\nfragment TripsContainerDividerFragment on TripsContainerDivider {\n  divider\n  __typename\n}\n\nfragment TripsLodgingUpgradesPrimerFragment on TripsLodgingUpgradesPrimer {\n  itineraryNumber\n  __typename\n}\n\nfragment TripItemContextualCardsPrimerFragment on TripItemContextualCardsPrimer {\n  tripViewId\n  tripItemId\n  placeHolder {\n    url\n    description\n    __typename\n  }\n  __typename\n}\n\nfragment TripsCustomerNotificationsFragment on TripsCustomerNotificationQueryParameters {\n  funnelLocation\n  notificationLocation\n  optionalContext {\n    itineraryNumber\n    journeyCriterias {\n      dateRange {\n        start {\n          day\n          month\n          year\n          __typename\n        }\n        end {\n          day\n          month\n          year\n          __typename\n        }\n        __typename\n      }\n      destination {\n        airportTLA\n        propertyId\n        regionId\n        __typename\n      }\n      origin {\n        airportTLA\n        propertyId\n        regionId\n        __typename\n      }\n      tripScheduleChangeStatus\n      __typename\n    }\n    tripId\n    tripItemId\n    __typename\n  }\n  xPageID\n  __typename\n}\n\nfragment TripsToastFragment on TripsToast {\n  ...TripsInfoToastFragment\n  ...TripsInlineActionToastFragment\n  ...TripsStackedActionToastFragment\n  __typename\n}\n\nfragment TripsInfoToastFragment on TripsInfoToast {\n  primary\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsInlineActionToastFragment on TripsInlineActionToast {\n  primary\n  button {\n    ...TripsToastButtonFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsToastButtonFragment on TripsButton {\n  __typename\n  primary\n  action {\n    ...TripsNavigateToViewActionFragment\n    ...TripsDismissActionFragment\n    __typename\n  }\n}\n\nfragment TripsDismissActionFragment on TripsDismissAction {\n  __typename\n  analytics {\n    referrerId\n    linkName\n    uisPrimeMessages {\n      messageContent\n      schemaName\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment TripsStackedActionToastFragment on TripsStackedActionToast {\n  primary\n  button {\n    ...TripsToastButtonFragment\n    __typename\n  }\n  impressionTracking {\n    ...ClientSideImpressionAnalyticsFragment\n    __typename\n  }\n  __typename\n}\n\nfragment TripsFloatingActionButtonFragment on TripsFloatingActionButton {\n  __typename\n  action {\n    ...TripsVirtualAgentInitActionFragment\n    __typename\n  }\n}\n\nfragment TripsDynamicMapFragment on TripsView {\n  egTripsMap {\n    ...DynamicMapFragment\n    __typename\n  }\n  egTripsCards {\n    ...TripsDynamicMapCardContentFragment\n    __typename\n  }\n  __typename\n}\n\nfragment DynamicMapFragment on EGDSBasicMap {\n  label\n  initialViewport\n  center {\n    latitude\n    longitude\n    __typename\n  }\n  zoom\n  bounds {\n    northeast {\n      latitude\n      longitude\n      __typename\n    }\n    southwest {\n      latitude\n      longitude\n      __typename\n    }\n    __typename\n  }\n  computedBoundsOptions {\n    coordinates {\n      latitude\n      longitude\n      __typename\n    }\n    gaiaId\n    lowerQuantile\n    upperQuantile\n    marginMultiplier\n    minMargin\n    minimumPins\n    interpolationRatio\n    __typename\n  }\n  config {\n    ... on EGDSDynamicMapConfig {\n      accessToken\n      egdsMapProvider\n      externalConfigEndpoint {\n        value\n        __typename\n      }\n      mapId\n      __typename\n    }\n    __typename\n  }\n  markers {\n    ... on EGDSMapFeature {\n      id\n      description\n      markerPosition {\n        latitude\n        longitude\n        __typename\n      }\n      type\n      markerStatus\n      qualifiers\n      text\n      clientSideAnalytics {\n        linkName\n        referrerId\n        __typename\n      }\n      onSelectAccessibilityMessage\n      onEnterAccessibilityMessage\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment TripsDynamicMapCardContentFragment on EGDSImageCard {\n  id\n  description\n  image {\n    aspectRatio\n    description\n    thumbnailClickAnalytics {\n      eventType\n      linkName\n      referrerId\n      uisPrimeMessages {\n        messageContent\n        schemaName\n        __typename\n      }\n      __typename\n    }\n    url\n    __typename\n  }\n  title\n  __typename\n}\n"}]';

        // Check auth
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.cheaptickets.com/graphql", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        foreach ($response[0]->data->trips->searchBooking->elements as $elements) {
            foreach ($elements->elements as $element) {
                if (isset($element->action->viewType) && $element->action->viewType == 'OVERVIEW') {
                    $viewUrl = $element->action->viewUrl;

                    break 2;
                } elseif (isset($element->heading)) {
                    $this->logger->debug($element->heading);
                }
            }
        }

        if (!isset($viewUrl)) {
            return null;
        }
        $this->http->GetURL($viewUrl);
        $providerHost = "https://www.cheaptickets.com";
        $this->delay();
        $its = $this->http->FindNodes('//div[@role="main"]//a[contains(@href, "/trips/egti-") and contains(.,"View booking")]/@href');
        $expedia = $this->getExpedia();

        foreach ($its as $it) {
            $this->http->GetURL($it);

            if ($expedia->ParseItineraryDetectType($providerHost, $arFields) === false) {
                //$this->delay();
                $this->http->GetURL($it);
                $this->increaseTimeLimit();
                $expedia->ParseItineraryDetectType($providerHost, $arFields);
            }
        }

        $this->logger->debug("Parsed data: " . var_export($this->itinerariesMaster->toArray(), true));

        return null;
    }

    private function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode('//iframe[contains(@src, "-api.arkoselabs.com")]/@src', null, true, "/pkey=([^&]+)/")
            ?? $this->http->FindPreg('/pkey=([^&\\\]+)/')
        ;

        if (!$key) {
            return false;
        }

        if ($this->attempt == 1) {
            $postData = array_merge(
                [
                    "type"                     => "FunCaptchaTaskProxyless",
                    "websiteURL"               => $this->http->currentUrl(),
                    "funcaptchaApiJSSubdomain" => 'expedia-api.arkoselabs.com',
                    "websitePublicKey"         => $key,
                ],
                []
//            $this->getCaptchaProxy()
            );
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($recognizer, $postData, $retry);
        }

        // RUCAPTCHA version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    private function delay()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->verify()) {
            $delay = rand(1, 10);
            $this->logger->debug("Delay -> {$delay}");
            sleep($delay);

            return true;
        }

        return false;
    }

    private function verify()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] != 429 || !$this->http->ParseForm("verifyButton")) {
            return false;
        }
        $currentUrl = $this->http->currentUrl();
        // captcha
        $key = $this->http->FindSingleNode("//form[@id = 'verifyButton']//div[@class = 'g-recaptcha']/@data-sitekey");
        $captcha = $this->parseCaptcha($key);

        if ($captcha === false) {
            return false;
        }
        $this->http->GetURL("https://www.cheaptickets.com/botOrNot/verify?g-recaptcha-response={$captcha}&destination={$this->http->Form['destination']}");

        if ($this->http->Response['code'] == 302) {
            $this->http->GetURL($currentUrl);
        }

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "We\'re currently working to improve the site.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our service is temporarily down and it appears we’ve been delayed for take off.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our service is temporarily down and it appears we’ve been delayed for take off.')]")) {
            throw new CheckException("We’re Sorry! Our service is temporarily down and it appears we’ve been delayed for take off.", ACCOUNT_PROVIDER_ERROR);
        }
        // We apologize for our system failure. Please try again.
        if ($message = $this->http->FindSingleNode("//div[@id = 'system-error-div']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/^Site temporarily unavailable\.$/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindPreg("/recaptchaSiteKey = \"([^\"]+)/");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
        // watchdog workaround
        $this->increaseTimeLimit(180);

        return $captcha;
    }

    /** @return TAccountCheckerExpedia */
    private function getExpedia()
    {
        if (!isset($this->expedia)) {
            $this->expedia = new TAccountCheckerExpedia();
            $this->expedia->AccountFields = $this->AccountFields;
            $this->expedia->http = $this->http;
            $this->expedia->itinerariesMaster = $this->itinerariesMaster;
            $this->expedia->globalLogger = $this->globalLogger; // fixed notifications
        }

        return $this->expedia;
    }
}
