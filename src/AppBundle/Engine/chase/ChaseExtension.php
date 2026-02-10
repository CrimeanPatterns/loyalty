<?php

namespace AwardWallet\Engine\chase;

use AwardWallet\ExtensionWorker\AbstractParser;
use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ActiveTabInterface;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\EvaluateOptions;
use AwardWallet\ExtensionWorker\ExtensionCommunicationError;
use AwardWallet\ExtensionWorker\FindTextOptions;
use AwardWallet\ExtensionWorker\LoginResult;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseAllInterface;
use AwardWallet\ExtensionWorker\ParseHistoryOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\ExtensionWorker\SelectFrameOptions;
use AwardWallet\ExtensionWorker\SelectorException;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Common\Statement;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\ParserTraits\TextTrait;

class ChaseExtension extends AbstractParser implements LoginWithIdInterface, ParseAllInterface, ActiveTabInterface
{
    use TextTrait;
    private array $headers = [];
    private int $maxHistoryRows = 150;
    private int $maxActivityInfoPage = 5;
    private ?string $profileId = null;

    public function getStartingUrl(AccountOptions $options): string
    {
        /*if ($options->isMobile) {
            return 'https://chaseonline.chase.com/secure/LogOff.aspx';
        }*/

        return 'https://secure.chase.com/web/auth/dashboard';
    }

    public function isLoggedIn(Tab $tab): bool
    {
        $login = $tab->evaluate('//iframe[@id="logonbox"] | //div[@data-testid="cutomer-greeting-message"]', EvaluateOptions::new()->timeout(40));
        return $login->getNodeName() === 'DIV';
    }

    public function getLoginId(Tab $tab): string
    {
        $options = [
            'method' => 'post',
            'headers' => [
                'Accept' => '*/*',
                'x-jpmc-csrf-token' => 'NONE',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ];
        $list = $tab->fetch('/svc/rr/profile/l4/v1/overview/list', $options)->body;
        //$this->logger->info($list);
        return json_decode($list)->userId ?? '';
    }

    public function logout(Tab $tab): void
    {
        $barShadowRoot = $tab->querySelector("#globalBrandBar")->shadowRoot();
        $logOutShadowRoot = $barShadowRoot->querySelector("#brand_bar_sign_in_out")->shadowRoot();
        $logOutShadowRoot->querySelector("button")->click();
        $tab->evaluate('//button[@data-pt-name="hd_bb_sign-in-panel"] | //main[@id="logon-content"]');
    }

    public function login(Tab $tab, Credentials $credentials): LoginResult
    {
        $frame = $tab->selectFrameContainingSelector("//input[@name='username']",
            SelectFrameOptions::new()->method("evaluate")->visible(false));

        $loginInputShadowRoot = $frame->querySelector("mds-text-input")->shadowRoot();
        $loginLabel = $loginInputShadowRoot->querySelector("label");
        $loginLabel->click();
        $login = $loginInputShadowRoot->querySelector('input#userId-input');
        $login->setValue($credentials->getLogin());

        $passwordInputShadowRoot = $frame->querySelector("mds-text-input-secure")->shadowRoot();
        $passwordLabel = $passwordInputShadowRoot->querySelector('label');
        $passwordLabel->click();
        $password = $passwordInputShadowRoot->querySelector('input');
        $password->setValue($credentials->getPassword());

        $rememberMeShadowRoot = $frame->querySelector("#rememberMe")->shadowRoot();
        $rememberMe = $rememberMeShadowRoot->querySelector('input#input-rememberMe');
        if (!$rememberMe->checked()) {
            $rememberMeShadowRoot->querySelector('label[for="input-rememberMe"]')->click();
        }

        $frame->querySelector("mds-button")->shadowRoot()->querySelector('button')->click();

        $i = 0;
        $stop = false;
        do {
            $this->logger->debug("Attempt: $i");
            // Error login message or 2fa
            try {
                $frame = $tab->selectFrameContainingSelector($xpath = '//h2[@id="inner-logon-error"] | //p[contains(text(),"How should we get in touch?")]',
                    SelectFrameOptions::new()->method("evaluate")->visible(false)->timeout(0));
                if ($frame->evaluate($xpath, EvaluateOptions::new()->timeout(0)->allowNull(true))) {
                    $stop = true;
                }
            } catch (SelectorException $e) {
                $this->logger->info($e->getMessage());
            }
            // Success login
            if ($tab->evaluate('//mds-navigation-bar[@id="primaryNavigationBar"]',
                EvaluateOptions::new()->timeout(0)->allowNull(true))) {
                $stop = true;
            }
            $i++;
            sleep(1);
        } while (!$stop && $i < 15);

        try {
            $errorOrTitle = $frame->evaluate('//h2[@id="inner-logon-error"]/text() | //p[contains(text(),"How should we get in touch?")]',
                EvaluateOptions::new()->timeout(0)->allowNull(true));
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }
        if (isset($errorOrTitle)) {
            $this->logger->debug('[errorOrTitle]:' . $errorOrTitle->getInnerText());
            if (str_contains($errorOrTitle->getInnerText(), "How should we get in touch?")) {
                $this->logger->notice('Waiting for 2fa to pass for 90 seconds');
                $errorOrTitle = $tab->evaluate('//mds-navigation-bar[@id="primaryNavigationBar"]',
                    EvaluateOptions::new()->timeout(90)->allowNull(true));
                if ($errorOrTitle) {
                    return new LoginResult(true);
                } else {
                    return LoginResult::providerError("It seems that Chase needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your Chase authentication options) to get this computer authorized and then please try to auto-login again.");
                }
            }
            // We can't find that username and password. Try again.
            if (str_contains($errorOrTitle->getInnerText(), "We can't find that username and password. Try again.")) {
                return LoginResult::invalidPassword($errorOrTitle->getInnerText());
            }
        } elseif ($tab->evaluate('//div[@data-testid="cutomer-greeting-message"]',
            EvaluateOptions::new()->timeout(0)->allowNull(true))) {
            return new LoginResult(true);
        }

        return new LoginResult(false);
    }

    private function sendAnswer(Tab $tab, ?string $answer): ?string
    {
        $this->logger->info("sending answer: ${answer}");
        $input = $tab->querySelector("input[name='otp']");
        $input->setValue($answer);
        $button = $tab->querySelector("button#OTPDetails");
        $button->click();
        $error = $tab->evaluate('//p[@data-ng-bind-html="errHold"]',
            EvaluateOptions::new()->visible(false)->nonEmptyString());

        return $error->getInnerText();
    }

    private string $baseURL = 'https://secure.chase.com';
    public function parseAll(
        Tab $tab,
        Master $master,
        AccountOptions $accountOptions,
        ?ParseHistoryOptions $historyOptions,
        ?ParseItinerariesOptions $itinerariesOptions
    ): void {
        $tab->showMessage('Message from AwardWallet: we are currently updating your account; this process may take up to 8 minutes on some accounts. Please don’t close this tab until we are done updating. In some cases, you may see an empty gray screen, which is normal.');
        $st = $master->createStatement();
        $tab->evaluate('//div[@id="ovd-layout-container"]');
        $tab->saveScreenshot();
        //$tab->evaluate('//*[@id="awFader"]');
        $this->profileId = $tab->findText('//script[contains(text(),"profileId = ")]',
            FindTextOptions::new()->preg($preg = '/profileId\s*=\s*(\d+),/')->visible(false)->allowNull(true));

        if (!$this->profileId) {
            $this->logger->error(">>>> retry parse profileId");
            $tab->gotoUrl('https://secure.chase.com/web/auth/dashboard');
            $this->profileId = $tab->findText('//script[contains(text(),"profileId = ")]',
                FindTextOptions::new()->preg($preg)->visible(false));
        }
        if (!$this->profileId) {
            $this->logger->error(">>>> profileId not found");
        }
        $token = $tab->getCookies()["authcsrftoken"] ?? null;
        $this->headers['x-jpmc-csrf-token'] = $token ?? 'NONE';
        $this->logger->info("Parse (New design)", ['Header' => 2]);
         $options = [
            'method' => 'post',
            'headers' => $this->headers + [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                "context" => "CBO_DASHBOARD",
            ])
        ];
        $listResp = $tab->fetch('/svc/rl/accounts/secure/v1/dashboard/data/list', $options);
        $this->logger->info($listResp->body);
        $cache = json_decode($listResp->body)->cache ??[];

        foreach ($cache as $node) {
            if (in_array($node->url, [
                '/svc/rr/accounts/secure/v1/dashboard/overview/accounts/list',
                '/svc/rr/accounts/secure/v2/dashboard/tiles/list',
                '/svc/rr/accounts/secure/v3/dashboard/tiles/list',
                '/svc/rr/accounts/secure/v4/dashboard/tiles/list',
            ])
            ) {
                $this->logger->info("Personal account", ['Header' => 2]);

                if (in_array($node->url, [
                    '/svc/rr/accounts/secure/v2/dashboard/tiles/list',
                    '/svc/rr/accounts/secure/v3/dashboard/tiles/list',
                ])
                ) {
                    $this->logger->notice("refs #17984 url: {$node->url}");
                }

                $version = 2;
                if (in_array($node->url, [
                    '/svc/rr/accounts/secure/v2/dashboard/tiles/list',
                    '/svc/rr/accounts/secure/v1/dashboard/overview/accounts/list'
                ])) {
                    $accounts = $node->response->accounts;
                } else {
                    $accounts = $node->response->accountTiles;
                    $version = 3;
                }
                $subAccountBalance = $this->parseCardDetails($tab, $st, $accountOptions, $historyOptions, $accounts, $version, $accountWithoutCards);
                // You don't have any accounts to show.
                if (empty($accounts)) {
                    $this->logger->error("You don't have any accounts to show.");
                    return;
                    //throw new CheckException("You don't have any accounts to show.", ACCOUNT_PROVIDER_ERROR);
                }

                break;
            }
            // Business account
            if ($node->url == '/svc/rl/accounts/secure/v1/user/metadata/list'
                && $node->response->defaultLandingPage == 'BUSINESS_OVERVIEW') {
                $business = true;
            }
            // J.P. Morgan account
            if ($node->url == '/svc/rl/accounts/secure/v1/user/metadata/list'
                && in_array($node->response->defaultLandingPage, [
                    'GWM_OVERVIEW',
                    'ACCOUNTS',
                ])
            ) {
                $jpMorgan = true;
            }
        }

        // Business account
        if ($st->getSubAccounts() == null && isset($business) && $business === true) {
            $this->logger->notice("Business account");
            $this->logger->info("Business account", ['Header' => 2]);
            $link = "/svc/rr/accounts/secure/v4/dashboard/tiles/list";
            $options = [
                'method' => 'post',
            ];
            // Name
            $tiles = $tab->fetch($link, $options)->body;
            $this->logger->info($tiles);
            $tiles = json_decode($tiles);

            $accounts = $tiles->accountTiles ?? [];
            $version = 3;
            $subAccountBalance = $this->parseCardDetails($tab, $st, $accountOptions, $historyOptions, $accounts, $version, $accountWithoutCards);
            // You don't have any accounts to show.
            if (empty($accounts)) {
                $this->logger->error("You don't have any accounts to show.");
                return;
                //throw new CheckException("You don't have any accounts to show.", ACCOUNT_PROVIDER_ERROR);
            }
        }// if (!isset($this->Properties['SubAccounts']) && $business)
        // J.P. Morgan account
        if ($st->getSubAccounts() == null && isset($jpMorgan) && $jpMorgan === true ) {
            $this->logger->notice("J.P. Morgan account");
            $this->logger->info("J.P. Morgan account", ['Header' => 2]);
            $link = "/svc/rr/accounts/secure/v2/portfolio/account/options/list";
            $options = [
                'method' => 'post',
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query(['filterOption'=>'ALL'])
            ];
            // Name
            $account = $tab->fetch($link, $options)->body;
            $this->logger->info($account);
            $account = json_decode($account);
            $accounts = $account->accounts ?? [];

            // Access Agreement (AccountID: 4281042)
            if (empty($accounts) && $account->statusCode == 'INVESTMENT_LA_ACCEPTANCE_REQUIRED') {
                // TODO
                // $this->throwAcceptTermsMessageException();
            }

            $version = 4;
            $subAccountBalance = $this->parseCardDetails($tab, $st, $accountOptions, $historyOptions, $accounts, $version, $accountWithoutCards);
        }

        // Chase UR Total   refs #6276
        if ($st->getSubAccounts() !== null) {
            $this->logger->notice("Get page with UR Balance");
            $this->logger->notice("[Current URL]: {$tab->getUrl()}");
            $this->logger->info("Chase UR Balance", ['Header' => 3]);

            // get Total UR Balance
            $urTotalLink = "/svc/rr/accounts/secure/v2/dashboard/tile/ur/detail/list";
            $options = [
                'method' => 'post',
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'x-jpmc-csrf-token' => 'NONE',
                ],
            ];
            $detailResp = $detail = null;
            try {
                $detailResp = $tab->fetch($urTotalLink, $options);
                $this->logger->info($detailResp->body);
                $detail = json_decode($detailResp->body);
            } catch(\Exception $e) {
                $this->logger->error($e->getMessage());
                $this->logger->warning('>>> Retry');
                sleep(5);
                try {
                    $detailResp = $tab->fetch($urTotalLink, $options);
                    $this->logger->info($detailResp->body);
                    $detail = json_decode($detailResp->body);
                } catch (\Exception $e) {
                    $this->logger->error($e->getMessage());
                }
            }

            $urSummary = $detail->urSummary ?? null;
            $urTotal = $urSummary->balance ?? null;

            // refs #18142
            if ($detailResp == '{"code":"SUCCESS"}') {
                $this->logger->notice("Fixed UR Total request");
                $urTotalLinkBusiness = "/svc/rr/accounts/secure/v1/dashboard/overview/rewards/detail/list";
                $options = [
                    'method' => 'post',
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                ];
                // Name
                $detailResp = $tab->fetch($urTotalLinkBusiness, $options);
                $this->logger->info($detailResp->body);
                $detail = json_decode($detailResp->body);
                $urTotal = $detail->balance;
                $this->logger->notice("refs #18142 UR Total was found");
            }

            $this->logger->debug("Chase UR Total: " . $urTotal);

            if (isset($urTotal)) {
                $urTotalFormat = number_format($urTotal, 0, '.', ',');
            }
            $subAccountBalance = number_format($subAccountBalance, 0, '.', ',');
            $this->logger->debug("Summary of subAccounts: " . $subAccountBalance);

            if (isset($urTotalFormat) && $urTotalFormat === $subAccountBalance) {
                // refs #15790
                $st->setBalance($urTotal);

                if (isset($this->Properties['SubAccounts'])) {
                    $countSubAccounts = count($this->Properties['SubAccounts']);
                    $this->logger->debug("count subAccounts: $countSubAccounts");
                    // Don't show a single sub-account // refs #6830
                    // refs #16147
                    for ($i = 0; $i < $countSubAccounts; $i++) {
                        if (
                            stristr($this->Properties['SubAccounts'][$i]['DisplayName'], 'Amazon')
                            || stristr($this->Properties['SubAccounts'][$i]['DisplayName'], 'Disney')
                        ) {
                            $this->logger->debug(">> {$this->Properties['SubAccounts'][$i]['DisplayName']}, skip");

                            continue;
                        }

                        $this->Properties['SubAccounts'][$i]['BalanceInTotalSum'] = true;
                    }
                }
            } else {
                $st->setNoBalance(true);
            }

            // Annual Travel Credit   // refs #16043
            foreach ($this->benefitSubAccounts as $benefitSubAccount) {
                $st->addSubAccount($benefitSubAccount);
            }
        }

        // Name
        $personalDetailsLink = "/svc/rr/profile/l4/v1/overview/list";
        $options = [
            'method' => 'post',
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'x-jpmc-csrf-token' => 'NONE'
            ],
        ];
        try {
            $overviewResp = $tab->fetch($personalDetailsLink, $options);
            $this->logger->info($overviewResp->body);
            $overview = json_decode($overviewResp->body);
            $st->addProperty("Name", beautifulName($overview->fullName));
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        if (empty($st->getBalance())) {
            // TODO: AccountID: 4774336, 4885529, 4745827
            /*if (
                $overview->statusCode == 'UNAUTHORIZED'
                && in_array($this->AccountFields['Login'], [
                    'danseaman06',
                    'ellaria88',
                    'peter24601',
                    'jgpaluch77',
                ])
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }*/
        }

        // refs #20165
        $this->logger->info('Rewards balances', ['Header' => 3]);
        $rewardsLink = "/svc/rr/accounts/secure/card/rewards/v1/summary/list";
        $options = [
            'method' => 'post',
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'x-jpmc-csrf-token' => 'NONE'
            ],
        ];
        try {
            $responseRewards = $tab->fetch($rewardsLink, $options)->body;
            $this->logger->info($responseRewards);
            $responseRewards = json_decode($responseRewards);
        } catch(\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        if (isset($responseRewards->cardRewardsSummary)) {
            foreach ($responseRewards->cardRewardsSummary as $cardRewardsSummary) {
                if ($cardRewardsSummary->cardRewardType != 'PARTNER_REWARDS') {
                    $this->logger->debug("skip {$cardRewardsSummary->accountId}: {$cardRewardsSummary->cardRewardType}");

                    continue;
                }

                $cardType = $cardRewardsSummary->cardType;

                switch ($cardType) {
                    case 'SOUTHWEST_AIRLINES':
                    case 'SOUTHWEST_PREMIER':
                    case 'SOUTHWEST_PLUS':
                        $this->logger->debug("ignore cardType: {$cardType}");
                        // refs #20925
                        /*
                        $this->AddSubAccount([
                            'ProviderUserName' => $this->Properties['Name'] ?? null,
                            'ProviderCode'     => 'rapidrewards',
                            'Code'             => 'chasePartnerRewardsRapidrewards',
                            'DisplayName'      => "Rapid Rewards® Points",
                            'Balance'          => $cardRewardsSummary->currentRewardsBalance,
                        ], true);
                        */

                        break;

                    case 'UNITED':
                    case 'UNITED_MILEAGE_PLUS_MIDDLE':
                    case 'UNITED_MILEAGEPLUS_CLUB':
                    case 'UNITED_MILEAGEPLUS_EXPLORER':
                    case 'UNITED_MILEAGEPLUS_PRESIDENTIAL_PLUS':
                        $st->addSubAccount([
                            'ProviderUserName' => $this->Properties['Name'] ?? null,
                            'ProviderCode'     => 'mileageplus',
                            'Code'             => 'chasePartnerRewardsMileageplus',
                            'DisplayName'      => "MileagePlus® Miles",
                            'Balance'          => $cardRewardsSummary->currentRewardsBalance,
                        ], true);

                        break;

                    case 'AEROPLAN_CARD':
                    case 'HYATT':
                    case 'HYATT_HOTELS':
                    case 'HYATT_BUSINESS':
                    case 'MARRIOTT':
                    case 'MARRIOTT_REWARDS_PREMIER':
                    case 'MARRIOTT_BONSAI':
                    case 'RITZ_CARLTON':
                    case 'BRITISH_AIRWAYS':
                    case 'INTERCONTINENTAL_HOTELS_GROUP':
                    case 'STARBUCKS':
                    case 'AER_LINGUS_AVIOS':
                    case 'IBERIA_AVIOS':
                        $this->logger->debug("ignore cardType: {$cardType}");

                        break;

                    default:
                        $this->logger->notice("unknown cardType: {$cardType} - refs #20165 // RR");
                        $this->logger->debug("unknown cardType: {$cardType}");
                }
            }
        }

        // refs #14648
        $this->logger->info('Zip Code', ['Header' => 3]);
        $personalDetailsLink = "/svc/rr/profile/secure/v1/address/profile/list";
        $options = [
            'method' => 'post',
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'x-jpmc-csrf-token' => 'NONE',
            ],
        ];
        try {
            $profile = $tab->fetch($personalDetailsLink, $options)->body;
            $this->logger->info($profile);
            $profile = json_decode($profile);
            $primaryAddress = $profile->primaryAddress;
            $zip = $primaryAddress->zipcode;

            if (strlen($zip) == 9) {
                $zipCode = substr($zip, 0, 5) . " " . substr($zip, 5);
            } else {
                $zipCode = $zip;
            }
            $st->addProperty("ZipCode", $zipCode);
            $st->addProperty("ParsedAddress",
                $primaryAddress->line1
                . ', ' . $primaryAddress->city
                . ', ' . $primaryAddress->stateCode
                . ', ' . $zipCode
                . ', ' . $primaryAddress->countryCode
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        // No cards with balance
        if (empty($st->getBalance()) && !empty($this->Properties['Name'])
            && ((isset($this->Properties['DetectedCards']) && count($this->Properties['DetectedCards']) > 0)
                || $accountWithoutCards)) {
            $this->logger->debug("No cards with balance");
            $st->setNoBalance(true);
        }// if (!empty($this->Properties['Name']) && isset($this->Properties['DetectedCards']) && count($this->Properties['DetectedCards']) > 0)

        $this->logger->info('FICO® Score', ['Header' => 3]);
        // refs #19100
        $this->getCreditBureauName($tab, $st);
    }

    public function parseCardDetails(Tab $tab, Statement $st, AccountOptions $accountOptions, ?ParseHistoryOptions $historyOptions, $accounts, $version, &$accountWithoutCards)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice("[Version]: {$version}");
        // Chase UR Total   refs #6276
        $subAccountBalance = 0;

        foreach ($accounts as $account) {
            $accountId = $account->accountId ?? $account->id;
            $this->logger->info("[accountId]: {$accountId}", ['Header' => 2]);
            $cardType = $account->cardType;
            $nickname = $account->nickname;
            $code = $this->findPreg("/x?(\d+)/", $account->mask);
            $unavailable = $account->unavailable ?? null;

            if ($version == 2) {
                $summaryType = $account->summaryType ?? $account->groupType;
                $summary = $account->summary ?? $account->detail ?? [];
                $accountTileDetailType = $account->accountTileDetailType ?? $account->detailType;
                $closed = $summary->closed ?? false;
            } elseif ($version == 4) {
                $summaryType = $account->accountCategoryType;
                $accountTileDetailType = $account->detailType;
                // todo: fake
                $summary = $account->summary ?? [];
                $closed = $account->closed ?? false;
            } else {
                $summaryType = $account->accountTileType;
                $accountTileDetailType = $account->accountTileDetailType;
                $summary = $account->tileDetail ?? [];
                $closed = $summary->closed ?? false;
            }
            // refs #15406 https://redmine.awardwallet.com/issues/15406#note-11
            $availableBalance = $summary->availableBalance;
            $currentBalance = $summary->currentBalance;

            $this->logger->notice("card # {$code} / {$summaryType} - {$cardType} / {$accountTileDetailType}");

            if (in_array($summaryType, ['UKN', 'AUTOLEASE', 'MORTGAGE', 'AUTOLOAN', 'LOAN'])) {
                $this->logger->debug("Skip card # {$code}, this is not credit card");
                $accountWithoutCards = true;

                continue;
            }// if (in_array($summaryType, ['UKN', 'AUTOLEASE']))

            switch ($summaryType) {
                case 'CARD':
                    $type = 'Personal ';

                    break;

                case 'DDA':
                    $type = '';

                    break;

                default:
                    $type = 'Credit ';
            }// switch ($cardType)

            switch ($accountTileDetailType) {
                case 'BCC':
                    $type = 'Business ';

                    break;

                default:
                    $this->logger->notice("Unknown type -> {$accountTileDetailType}");
            }
            $cardDescription = C_CARD_DESC_DO_NOT_EARN;
            $kind = null;
            $skip = $this->getCardType($cardType, $kind, $cardDescription, $type);
            // Co-branded card
            $coBrandedCard = false;

            $displayName = "...{$code} ({$type}Card)";
            $this->logger->notice("displayName -> $displayName");

            if (!empty($kind)) {
                $displayName = "{$kind} {$displayName}";
            }
            $this->logger->notice("displayName -> $displayName");

            if ($nickname == 'TOTAL CHECKING' || !$cardType) {
                $displayName = $nickname . " ...{$code}";
                $this->logger->notice("displayName -> $displayName");
            }// if ($nickname == 'TOTAL CHECKING')

            if ($closed) {
                $cardDescription = C_CARD_DESC_CLOSED;
            }

            if (!empty($displayName) && !empty($code)) {
                $this->logger->debug("fixed card code: " . $code);

                if (strstr($displayName, 'Sapphire Preferred')) {
                    $code = 'SP' . $code;
                } elseif (strstr($displayName, 'Freedom')) {
                    $code = 'Freedom' . $code;
                } elseif (strstr($displayName, 'Ink Unlimited')) {
                    $code = 'InkUnlimited' . $code;
                } elseif (strstr($displayName, 'Ink Cash')) {
                    $code = 'InkCash' . $code;
                }

                $this->logger->debug("new code: " . $code);
                $st->addDetectedCard([
                    "Code" => 'chase' . $code,
                    "DisplayName" => $displayName,
                    "CardDescription" => $cardDescription,
                ]);
            }

            if (empty($accountId) || $unavailable || $nickname == 'TOTAL CHECKING' || $closed || $skip
                // CHECKING, SAVINGS, Asset, Brokerage
                || (in_array($summaryType, ['DDA', 'INVESTMENT']) && in_array($accountTileDetailType,
                        ['CHK', 'SAV', 'BR2', 'WR2', 'MMA']))) {
                $this->logger->debug("Skip card # {$code}, accountId not found or card does not earn points or account has been closed");

                // refs #19660 Southwest Annual Travel Credit
                if (!empty($accountId) && in_array($cardType, ['SOUTHWEST_PREMIER', 'SOUTHWEST_AIRLINES'])) {
                    $this->logger->info("card #{$code}: {$displayName} / Southwest Annual Travel Credit",
                        ['Header' => 3]);
                    $link = "https://chaseloyalty.chase.com/home?AI={$accountId}";
                    $tab->gotoUrl($link);
                    $options = [
                        'method' => 'get',
                        'headers' => $this->headers
                    ];
                    $responseSouthwest = $tab->fetch('https://chaseloyalty.chase.com/rest/home/dashboard-trackers',
                        $options)->body;
                    $this->logger->info($responseSouthwest);
                    $responseSouthwest = json_decode($responseSouthwest);

                    if (
                        isset($responseSouthwest->maximumStatementCreditAmount, $responseSouthwest->statementCreditTrackerState)
                        && !in_array($responseSouthwest->statementCreditTrackerState, ['COMPLETE'])
                    ) {
                        /*
                        if ($responseSouthwest->maximumStatementCreditAmount != $responseSouthwest->statementCreditTrackerAmount) {
                            $this->sendNotification("refs #19660 Southwest travel credit");
                        }
                        */
                        $maximumStatementCreditAmount = floor($responseSouthwest->maximumStatementCreditAmount / 100);
                        $balance = ($responseSouthwest->maximumStatementCreditAmount - $responseSouthwest->statementCreditTrackerAmount) / 100;

                        if ($balance <= 0) {
                            $this->logger->debug("skip empty travel Southwest Annual Travel Credit for {$displayName}");

                            continue;
                        }
                        $this->benefitSubAccounts[] = [
                            'Code' => 'chaseSouthwestAnnualTravelCredit' . 'chase' . $code,
                            'DisplayName' => "$" . $maximumStatementCreditAmount . " Southwest Annual Travel Credit (card ending " . $this->findPreg("/(\.\.\.\d+)/",
                                    $displayName) . ")",
                            'Balance' => $balance,
                            'Currency' => "$",
                            'ExpirationDate' => strtotime($responseSouthwest->rewardsAnniversaryDate),
                        ];
                        $this->logger->debug("Adding subAccount...");
                        $this->logger->debug(var_export($this->benefitSubAccounts, true), ['pre' => true]);
                        unset($balance);

                        // Upgraded Boarding, refs #20908
                        if (!empty($responseSouthwest->upgradedBoardingTrackerData)) {
                            $maximumTransactionCount = $responseSouthwest->upgradedBoardingTrackerData->maximumTransactionCount;
                            $balance = $maximumTransactionCount - $responseSouthwest->upgradedBoardingTrackerData->trackerAmount;

                            if ($balance <= 0) {
                                $this->logger->debug("skip empty Upgraded Boarding for {$displayName}");
                            } else {
                                $this->benefitSubAccounts[] = [
                                    'Code' => 'chaseSouthwestUpgradedBoarding' . $code,
                                    'DisplayName' => "Southwest Upgraded Boarding (card ending " . $this->findPreg("/(\.\.\.\d+)/",
                                            $displayName) . ")",
                                    'Balance' => $balance,
                                    'ExpirationDate' => strtotime($responseSouthwest->rewardsAnniversaryDate),
                                ];
                                $this->logger->debug("Adding subAccount...");
                                $this->logger->debug(var_export($this->benefitSubAccounts, true), ['pre' => true]);
                                unset($balance);
                            }
                        }
                    }
                }
                if (!empty($accountId) && in_array($cardType, ['SOUTHWEST_PREMIER', 'SOUTHWEST_AIRLINES'])) {
                    $cardDetailLink = "/svc/rr/accounts/secure/v1/account/rewards/detail/card/list";
                    $options = [
                        'method' => 'post',
                        'headers' => $this->headers + [
                                'Content-Type' => 'application/x-www-form-urlencoded',
                                'x-jpmc-csrf-token' => 'NONE',
                            ],
                        'body' => http_build_query([
                            "accountId" => $accountId,
                        ])
                    ];
                    $rewardSW = $tab->fetch($cardDetailLink, $options)->body;
                    $this->logger->info($rewardSW);
                    $rewardSW = json_decode($rewardSW);

                    $rewardProgramNameSW = $rewardSW->rewardProgramName;
                    $this->logger->debug("rewardProgramName -> $rewardProgramNameSW");

                    $this->getCardType($cardType, $kind, $cardDescription, $rewardProgramNameSW, $type);

                    if ($closed) {
                        $cardDescription = C_CARD_DESC_CLOSED;
                    }
                    $this->logger->notice("displayName for Southwest Rapid Rewards card -> $displayName");

                    $displayName = "...{$code} ({$type}Card)";
                    $this->logger->notice("displayName -> $displayName");

                    if (!empty($kind)) {
                        $displayName = "{$kind} {$displayName}";
                    }
                    $this->logger->notice("displayName -> $displayName");

                    $this->logger->notice("displayName for Southwest Rapid Rewards card -> $displayName");

                    $st->addDetectedCard([
                        "Code" => 'chase' . $code,
                        "DisplayName" => $displayName,
                        "CardDescription" => $cardDescription,
                    ], true, true);
                }

                if (!$skip) {
                    continue;
                }

                // Co-branded card
                $coBrandedCard = true;
                $this->logger->debug("Co-branded card => #{$code}}: {$displayName}");
            }

            // get card Balance
            $this->logger->info("card #{$code}: {$displayName}", ['Header' => 3]);
            $cardDetailLink = "/svc/rr/accounts/secure/v1/account/rewards/detail/card/list";
            $options = [
                'method' => 'post',
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'x-jpmc-csrf-token' => 'NONE',
                ],
                'body' => http_build_query([
                    "accountId" => $accountId,
                ])
            ];
            $reward = null;
            try {
                $reward = $tab->fetch($cardDetailLink, $options)->body;
                $this->logger->info($reward);
                $reward = json_decode($reward);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }

            $balance = $reward->rewardBalance ?? null;
            $rewardProgramName = $reward->rewardProgramName ?? null;
            $this->logger->debug("balance -> $balance");
            $this->logger->debug("rewardProgramName -> $rewardProgramName");

            // J.P. Morgan
            // Business accounts: 3747771, 4613170
            if (
                (empty($kind) || $rewardProgramName || $this->findPreg('/^CREDIT CARD \.\.\.\d+$/', $displayName))
                && ($cardType = $reward->rewardsCardType ?? null)
            ) {
                $kind = null;
                $this->getCardType($cardType, $kind, $cardDescription, $rewardProgramName, $type);
                $this->logger->notice("displayName for Business accounts -> $displayName");

                if (!empty($kind)) {
                    $displayName = "{$kind} ..." . preg_replace('/[^\d]+/', '', $code) . " ({$type}Card)";
                }

                $this->logger->notice("displayName for Business accounts -> $displayName");
                $st->addDetectedCard([
                    "Code" => 'chase' . $code,
                    "DisplayName" => $displayName,
                    "CardDescription" => $cardDescription,
                ], true, true);
            }

            // Co-branded card
            if ($coBrandedCard === true) {
                $this->logger->debug("Co-branded card => set balance null");
                $balance = null;
            }

            if (
                (isset($balance) || $coBrandedCard === true)
                && !empty($displayName)
                && !empty($code)
            ) {
                $subAccount = [
                    "Code" => 'chase' . $code,
                    "DisplayName" => $displayName,
                    "Balance" => $balance,
                ];

                // detect closed cards
                // Co-branded card
                if ($coBrandedCard === true) {
                    $this->logger->debug("Co-branded card => set IsHidden = true");
                    $subAccount['IsHidden'] = true;
                } else {
                    $options = [
                        'method' => 'post',
                        'headers' => $this->headers + [
                            'Content-Type' => 'application/x-www-form-urlencoded',
                        ],
                        'body' => http_build_query([
                            "accountId" => $accountId,
                        ])
                    ];
                    try {
                        $cardInfo = $tab->fetch('/svc/rr/accounts/secure/v2/account/detail/card/list', $options)->body;
                        $this->logger->info($cardInfo);
                        $cardInfo = json_decode($cardInfo);
                    } catch (\Exception $e) {
                        $this->logger->error($e->getMessage());
                        continue;
                    }

                    $detail = $cardInfo->detail ?? null;
                    $replacementAccountMask = $detail->replacementAccountMask ?? null;
                    // We've transferred your account details to your new credit card
                    if ($replacementAccountMask) {
                        $this->logger->notice("We've transferred your account details to your new credit card {$replacementAccountMask}");
                        $st->addDetectedCard([
                            "Code" => $subAccount['Code'],
                            "DisplayName" => $subAccount["DisplayName"],
                            "CardDescription" => C_CARD_DESC_CLOSED,
                        ], true, true);

                        continue;
                    }
                }

                // 5% cash back  // refs #15406
                if ($kind == 'Freedom') {
                    // refs #15406 Chase Freedom 5% cash back tracking
                    $cashBackStatus = $cardInfo->cashBackStatus ?? null;
                    // https://static.chasecdn.com/content/resource-bundles/digital-ui/1-8-0-23/en/bundles.json/WEALTH/accounts.json
                    // "rewardsEnrollmentQuarterLabel.FIRST_QUARTER":"(Jan-Mar)","rewardsEnrollmentQuarterLabel.SECOND_QUARTER":"(Apr-Jun)","rewardsEnrollmentQuarterLabel.THIRD_QUARTER":"(Jul-Sep)","rewardsEnrollmentQuarterLabel.FOURTH_QUARTER":"(Oct-Dec)",
                    $current_quarter = ceil(date('n') / 3);
                    $this->logger->debug("current_quarter: {$current_quarter}");

                    switch ($current_quarter) {
                        case 1:
                            $quarter = 'Jan-Mar';

                            break;

                        case 2:
                            $quarter = 'Apr-Jun';

                            break;

                        case 3:
                            $quarter = 'Jul-Sep';

                            break;

                        case 4:
                            $quarter = 'Oct-Dec';

                            break;

                        default:
                            $quarter = '';
                    }// switch ($current_quarter)
                    $quarter = "<a target='_blank' href='https://awardwallet.com/blog/link/ChaseFreedomCurrentQuarter'>{$quarter}</a>";

                    if ($cashBackStatus == 'ENROLLED') {
                        $description = "";
//                        if (time() <= strtotime("1 Apr 2018"))
//                            $description = " for <br> Gas stations and Internet, Cable, and Phone providers";
                        $subAccount['CashBack'] = "Activated ({$quarter}){$description}";
                    }// if ($cashBackStatus == 'ENROLLED')
                    elseif ($cashBackStatus == 'ELIGIBLE') {
                        $subAccount['CashBack'] = "Not Activated ({$quarter})";
                    } // refs #15406 https://redmine.awardwallet.com/issues/15406#note-11
                    elseif ($cashBackStatus == null && $availableBalance === null && $currentBalance === null) {
                        $this->logger->notice("Skip transferred card #{$subAccount['Code']}: {$displayName}");

                        continue;
                    } else {
                        $this->logger->notice("refs #15406. Chase Freedom 5% cash back tracking");
                    }
                }// if ($kind == 'Freedom')



                if (
                    strstr($rewardProgramName, 'Ultimate Rewards')
                    || strstr($rewardProgramName, 'Chase Sapphire Reserve')
                    || strstr($rewardProgramName, 'Sapphire Preferred')
                    || strstr($rewardProgramName, 'Ink ')
                ) {
                    $subAccount["DisplayName"] = str_replace("{$kind} ...", "{$kind} / Ultimate Rewards ...",
                        $displayName);
                    $this->logger->notice("displayName -> {$subAccount["DisplayName"]}");

                    // refs #13946 Gathering transaction history for Chase
                    // New Design
                    // TODO: set mode: 'no-cors'
                    $link = "https://ultimaterewardspoints.chase.com/home?AI={$accountId}";
                    $tab->gotoUrl($link);

                    if ($this->findPreg("/window\.redirectShared\(navigator\.userAgent/", $tab->getHtml())) {
                        if ($redirect = $this->findPreg("/window.location.href = '(https:\/\/s[^\']+)/", $tab->getHtml())) {
                            $this->logger->notice("Redirect to new design from <- " . $link);
                            $tab->gotoUrl($redirect);
                            // TODO
                            return;
//                            if ($this->http->ParseForm("Data")) {
//                                $this->http->PostForm();
//                                // SAML
//                                if ($this->http->ParseForm()) {
//                                    $this->http->PostForm();
//                                }
//                            }
                        }
                    }

                    $subAccount["HistoryRows"] = $this->parseSubAccHistory($tab, $accountOptions,$historyOptions, $subAccount['Code']);

                    // Chase freedom gathering current spend   // refs #16001
                    if ($kind == 'Freedom' && isset($subAccount['CashBack']) && !strstr($subAccount['CashBack'],
                            'Not Activated')) {
                        $this->logger->info("Current spend for {$subAccount['DisplayName']}", ['Header' => 3]);
                        $options = [
                            'method' => 'get',
                        ];
                        $scenarioList = $tab->fetch("https://ultimaterewardspoints.chase.com/rest/five-percent-cashback/scenario", $options)->body;
                        $this->logger->info($scenarioList);
                        $scenarioList = json_decode($scenarioList);
                        if (is_array($scenarioList)) {
                            foreach ($scenarioList as $scenario) {
                                // in_array($scenario->status->name, ['ACTIVE', 'ACTIVE_NOT_STARTED', 'MAX_REACHED_CURRENT']) &&
                                if ($scenario->enrollmentQuarter == 'CURRENT'
                                    && (!in_array($scenario->status->name,
                                            ['OPEN']) && $scenario->status->label != '')) {
                                    $subAccount['CurrentQuarter'] = strtotime($scenario->offerStartDate);
                                    // Total Cash Back Rewards
                                    $subAccount['TotalCashBackRewards'] = "$" . $scenario->totalCashback->amount;
                                    // Max Reached
                                    if ($scenario->status->label == 'Max Reached') {
                                        $subAccount['MaxReached'] = true;
                                    } else {
                                        $subAccount['MaxReached'] = false;
                                    }

                                    if (!empty($scenario->categories)) {
                                        $description = " for <br> " . implode(', ', $scenario->categories);
                                        $subAccount['CashBack'] = "{$subAccount['CashBack']}{$description}";
                                    }// if (!empty($scenario->categories))
                                }// if ($scenario->enrollmentQuarter == 'CURRENT'
                            }
                        }
                    }

                    $this->travelBenefits($tab, $st, $subAccount["DisplayName"], $subAccount['Code']);
                }

                if ($tab->getUrl() != 'https://secure.chase.com/web/auth/dashboard#/dashboard/overview') {
                    $tab->gotoUrl('https://secure.chase.com/web/auth/dashboard#/dashboard/overview');
                    sleep(1);
                }

                // refs #19361
                if (
                    $historyOptions !== null
                    && $this->parseCategories()
                    && (
                        isset($subAccount["HistoryRows"])
                        // Co-branded card
                        || $coBrandedCard === true
                        || stristr($rewardProgramName, 'Amazon ')
                    )
                ) {
                    $this->logger->info("get categories from Chase for card {$displayName}", ['Header' => 4]);
                    // https://static.chasecdn.com/content/resource-bundles/digital-ui/3-2-1-6/en/bundles.json/BUSINESS/gallery.json
                    // https://static.chasecdn.com/web/hash/dashboard/convoDeck/js/area_f13c3555300adcd4f3c0c41fd8f7b8f2.js
                    $categoryDescription = [
                        "AUTOMOTIVE" => "Automotive",
                        "AUTO" => "Automotive",
                        "BILLS_UTILITIES" => "Bills & utilities",
                        "BILL" => "Bills & utilities",
                        "EDUCATION" => "Education",
                        "EDUC" => "Education",
                        "FOOD_DRINK" => "Food & drink",
                        "FOOD" => "Food & drink",
                        "ENTERTAINMENT" => "Entertainment",
                        "ENTT" => "Entertainment",
                        "FEES" => "Fees & adjustments",
                        "GAS" => "Gas",
                        "GASS" => "Gas",
                        "GIFTS_DONATIONS" => "Gifts & donations",
                        "GIFT" => "Gifts & donations",
                        "GROCERIES" => "Groceries",
                        "GROC" => "Groceries",
                        "HEALTH_FITNESS" => "Health & wellness",
                        "HEAL" => "Health & wellness",
                        "HOME" => "Home",
                        "MERCHANDISE_INVENTORY" => "Merchandise & inventory",
                        "MRCH" => "Merchandise & inventory",
                        "OFFICE_SHIPPING" => "Office & shipping",
                        "OFFI" => "Office & shipping",
                        "PERSONAL" => "Personal",
                        "PERS" => "Personal",
                        "PETS" => "Pet care",
                        "PROFESSIONAL_SERVICES" => "Professional services",
                        "PROF" => "Professional services",
                        "REPAIR_MAINTENANCE" => "Repairs & maintenance",
                        "REPA" => "Repairs & maintenance",
                        "SHOPPING" => "Shopping",
                        "SHOP" => "Shopping",
                        "TRANSPORTATION" => "Transportation",
                        "TRAVEL" => "Travel",
                        "TRAV" => "Travel",
                        "MISCELLANEOUS" => "Miscellaneous",
                        "MISC" => "Miscellaneous",
                        null => null,
                    ];

                    $activities = [];
                    $startDate = $historyOptions->getSubAccountStartDate($subAccount['Code']);
                    $this->logger->debug('[History start date for ' . $subAccount['Code'] . ': '
                        . ((isset($startDate)) ? $startDate->format('Y/m/d H:i:s') : 'all') . '], strictHistoryStartDate: '
                        . json_encode($historyOptions->isStrictHistoryStartDate()));

                     // refs #19361, note-78
                    if (!$historyOptions->isStrictHistoryStartDate() && isset($startDate)) {
                        $startDate = strtotime('-4 day', $startDate->format('U'));
                        $this->logger->debug('>> [set historyStartDate date -4 days]: ' . $startDate);
                    } else {
                        $startDate = 0;
                    }

                    $activityInfoPage = 0;
                    $paginationContextualText = '';
                    $lastSortField = '';

                    do {
                        $this->logger->info("[page {$activityInfoPage}]: get categories for business card {$displayName}",
                            ['Header' => 4]);
                        $activityInfoPage++;

                        if ($activityInfoPage > 0 && !empty($lastSortField) && !empty($paginationContextualText)) {
                            $lastSortField = '&last-sort-field-value=' . $lastSortField;
                            $paginationContextualText = '&pagination-contextual-text=' . str_replace('#', '%23',
                                    $paginationContextualText);
                        }

                        $this->logger->debug("increaseTimeLimit should be called");

                        $options = [
                            'method'  => 'get',
                            'headers' => $this->headers,
                        ];

                        try {
                            $response = $tab->fetch($url = '/svc/rr/accounts/secure/v4/activity/card/credit-card/transactions/inquiry-maintenance/etu-digital-card-activity/v1/profiles/' . $this->profileId . '/accounts/' . $accountId . '/account-activities?record-count=50&account-activity-end-date=' . date("Ymd") . '&account-activity-start-date=' . date("Ymd",
                                    strtotime("-2 year")) . '&request-type-code=T' . $lastSortField . '&sort-order-code=D&sort-key-code=T' . $paginationContextualText,
                                $options);
                        } catch (\Exception $e) {
                            $this->logger->error($e->getMessage());
                            break;
                        }
                        // it helps
                        if (isset($response) && $response->status == 403) {
                            sleep(3);
                            $response = $tab->fetch($url, $options);
                        }
                        //$this->logger->info($response->body);
                        $activityInfo = json_decode($response->body);
                        $activities = array_merge($activities, $activityInfo->activities ?? []);

                        $moreTransactionsIndicator = $activityInfo->moreTransactionsIndicator ?? null;
                        $lastSortField = $activityInfo->lastSortFieldValue ?? null;
                        $paginationContextualText = $activityInfo->paginationContextualText ?? null;

                        $this->logger->debug("moreTransactionsIndicator: {$moreTransactionsIndicator}");
                        $this->logger->debug("lastSortField: {$lastSortField} / " . strtotime($lastSortField));
                        $this->logger->debug("paginationContextualText: {$paginationContextualText}");
                        $this->logger->debug("activityInfoPage: {$activityInfoPage}");
                    } while (
                        !empty($lastSortField)
                        && !empty($paginationContextualText)
                        && $activityInfoPage < $this->maxActivityInfoPage
                        && $moreTransactionsIndicator === true
                        && (!isset($startDate) || isset($startDate) && strtotime($lastSortField) > $startDate)
                    );

                    $this->logger->debug("Total " . count($activities) . " activity rows were found");

                    if (
                        $coBrandedCard == true
                        || stristr($rewardProgramName, 'Amazon ')
                    ) {
                        $historyRows = [];

                        foreach ($activities as $activity) {
                            if (
                                !($activity->authorizationDate?? null)
                                &&( $activity->transactionDate?? null)
                            ) {
                                $this->logger->debug("old categories request");
                                $d = $activity->transactionDate?? null;
                                $transactionDate = strtotime($d);
                                $description = $activity->description?? null;
                                $amount = $activity->amount?? null;
                                $category = $activity->category?? null;
                            } else {
                                $d = $activity->authorizationDate?? null;
                                $transactionDate = strtotime($d);
                                $description = $activity->merchantDbaName?? null;
                                $amount = $activity->transactionAmount?? null;
                                $category = $activity->expenseCategoryCode?? null;
                            }

                            if (isset($startDate) && $transactionDate < $startDate) {
                                $this->logger->notice("break at date {$d} ($transactionDate)");

                                continue;
                            }

                            $historyRow = [
                                "Date" => $transactionDate,
                                "Description" => $description,
                                "Points" => $activity->earnedRewardsAmount?? null,
                                "Amount" => $amount,
                                "Currency" => 'USD',
                                "Transaction Description" => $activity->transactionReferenceNumber?? null,
                            ];

                            $merchantCategoryCode = $activity->merchantCategoryCode ?? null;
                            $this->logger->notice("[$d]: {$transactionDate} | {$description} | {$amount} | Category: {$category} | Merchant: {merchantCategoryCode}");
                            $merchantCategory = null;

                            if ($merchantCategoryCode !== 0) {
                                $merchantCategory = $this->getMerchantCode($merchantCategoryCode);
                                $this->logger->debug("Matched >>> set category from Chase: {$merchantCategory}");
                                $historyRow['Category'] = $merchantCategory;
                            } else {
                                $merchantCategory = $categoryDescription[$category];
                                $this->logger->debug("Matched >>> set category from UR: {$merchantCategory}");
                                $historyRow['Category'] = $merchantCategory;
                            }

                            $historyRows[] = $historyRow;
                        }
                    } else {
                        $historyRows = $subAccount["HistoryRows"];
                        $this->logger->debug("Found " . count($historyRows) . " history rows");
                        unset($historyRow);

                        foreach ($historyRows as &$historyRow) {
                            foreach ($activities as $activity) {
                                $d = $activity->authorizationDate?? null;
                                $transactionDate = strtotime($d);

                                if (isset($startDate) && $transactionDate < $startDate) {
                                    $this->logger->debug("---------------- skip searching category: {$d} / {$transactionDate} ----------------");

                                    continue;
                                }

                                $description = $activity->merchantDbaName?? null;
                                $amount = $activity->transactionAmount?? null;
                                $category = $activity->expenseCategoryCode?? null;

                                if (
                                    date("mdY", $historyRow['Date']) == date("mdY", $transactionDate)
                                    && (
                                        $historyRow['Description'] == $description
                                        || strtolower(str_replace('+ ', '',
                                            $historyRow['Description'])) == strtolower($description)// "transactionName":"+ PELOTON MEMBERSHIP CREDIT" vs "description":"Peloton Membership Credit",
                                    )
                                    && $historyRow['Amount'] == $amount
                                ) {
                                    $this->logger->notice("Matched >>> [{$d}]: {$transactionDate} | {$description} - {$amount} | category: {$categoryDescription[$category]} ({$category})");

                                    $getMerchantData = [
                                        "accountId" => $accountId,
                                        "transactionId" => $activity['derivedUniqueTransactionIdentifier'],
                                        "postDate" => $activity['transactionPostDate'],
                                        "cardReferenceNumber" => $activity['cardReferenceNumber'],
                                        "relatedAccountId" => $activity['digitalAccountIdentifier'],
                                        "merchantName" => $activity['merchantDbaName'],
                                    ];

                                    $this->logger->debug("increaseTimeLimit should be called");

                                    if ($this->profileId) {
                                        $this->logger->notice("refs #22133 need to check merchantCategoryCode");
                                        $merchantCategoryHeaders = [
                                            "Accept" => "*/*",
                                            "Accept-Language" => "en-US,en;q=0.5",
                                            "Accept-Encoding" => "gzip, deflate, br",
                                            //"Referer" => $baseURL . "/web/auth/dashboard",
                                            "x-jpmc-channel" => "id=C30",
                                            "Content-Type" => "application/x-www-form-urlencoded; charset=UTF-8",
                                            "Connection" => "keep-alive",
                                            'x-jpmc-csrf-token' => 'NONE',
                                        ];
                                        $options = [
                                            'method'  => 'get',
                                            'headers' => $merchantCategoryHeaders
                                        ];
                                        $json = $tab->fetch('/svc/wr/accounts/secure/gateway/credit-card/transactions/inquiry-maintenance/digital-card-transaction/v1/profiles/' . $this->profileId . '/card-transaction-details?digital-account-identifier=' . $activity['derivedUniqueTransactionIdentifier'] . '&transaction-post-date=' . $activity['transactionPostDate'] . '&transaction-post-time=' . $activity['transactionPostTime'] . '&transaction-identifier=' . str_replace('#',
                                                '%23', $activity['derivedUniqueTransactionIdentifier']), $options);

                                    } else {
                                        $this->logger->notice("refs #22133 need to check merchantCategoryCode");

                                        $options = [
                                            'method'  => 'post',
                                            'headers' =>  ['Content-Type' => 'application/x-www-form-urlencoded'],
                                            'body' => $getMerchantData
                                        ];
                                        $json = $tab->fetch('/svc/rr/accounts/secure/card/activity/ods/v2/detail/list', $options);
                                    }
                                    $responsetransactionMerchantData = json_decode($json->body);
                                    $merchantCategoryCode =
                                        $responsetransactionMerchantData->merchantCategoryCode
                                        ?? $responsetransactionMerchantData->transactionDetails->merchantCategoryCode
                                        ?? null;

                                    $merchantCategory = $this->getMerchantCode($merchantCategoryCode);
                                    $this->logger->notice("set category from Chase: {$merchantCategory}");
                                    $historyRow['Category'] = $merchantCategory;

                                    // https://redmine.awardwallet.com/issues/19361#note-48
                                    if (
                                        (!$merchantCategoryCode && (
                                            $responsetransactionMerchantData->cardReferenceNumber == 0
                                                || $responsetransactionMerchantData->transactionDetails->cardReferenceNumber == 0
                                                || $json->body == '{"code":"SUCCESS"}'
                                            ))
                                        || $merchantCategoryCode == 0
                                    ) {
                                        $merchantCategory = $categoryDescription[$category];
                                        $this->logger->debug("fixed empty category, set category from UR: {$merchantCategory}");
                                        $this->logger->debug("[$transactionDate]: {$description} - {$amount} set category: {$merchantCategory} / {$category}");
                                        $historyRow['Category'] = $merchantCategory;
                                    }

                                    break;
                                }
                            }
                        }

                        unset($historyRow);

                        foreach ($historyRows as $historyRow) {
                            if (
                                empty($historyRow['Category'])
                                && !strstr($historyRow['Description'], 'Points Moved')
                                && !strstr($historyRow['Description'], 'Points redeemed:')
                                && !strstr($historyRow['Description'], 'Travel booked through Chase')
                                && !strstr($historyRow['Description'], 'Adjustment')
                                && !strstr($historyRow['Description'], 'Way to go! You redeemed your points')
                                && !strstr($historyRow['Description'], 'Chase Dining purchase')
                                && $historyRow['Description'] != 'Statement Credit'
                                && $historyRow['Description'] != 'Canceled: Amazon.com points redemption'
                                && $historyRow['Description'] != 'Your point transfer request is in progress'
                                && $historyRow['Description'] != 'Canceled: travel partner point transfer'
                                && $historyRow['Description'] != 'Canceled: Chase Dining'
                                && $historyRow['Description'] != 'TRAVEL CREDIT $300/YEAR'
                            ) {
                                $this->logger->notice("[" . date("mdY",
                                        $historyRow['Date']) . "]: category not found | {$historyRow['Date']} | {$historyRow['Description']}");
                                $this->logger->debug(var_export($historyRow, true), ['pre' => true]);

                                if ($historyRow['Date'] < strtotime("-4 days")) {
                                    $this->logger->notice("[" . date("mdY",
                                            $historyRow['Date']) . "]: category not found | {$historyRow['Date']} | {$historyRow['Description']} - refs #19361 // RR",
                                         );
                                }
                            }
                        }// foreach ($historyRows as $historyRow)
                    }

//                    $this->logger->debug("Chase history:");
//                    $this->logger->debug(json_encode($activities));

                    $subAccount["HistoryRows"] = $historyRows;
                }

                $st->addSubAccount($subAccount);
                // not Co-branded card
                if ($coBrandedCard === false) {
                    $st->addDetectedCard([
                        "Code" => $subAccount['Code'],
                        "DisplayName" => $subAccount["DisplayName"],
                        "CardDescription" => C_CARD_DESC_ACTIVE,
                    ], true, true);
                }
                $this->logger->debug("subAccountBalance: {$subAccountBalance}");

                if (
                    !stristr($rewardProgramName, 'Amazon')
                    && !stristr($rewardProgramName, 'Disney')
                    && !stristr($rewardProgramName, 'Prime Visa')// Amazon, refs #23708
                ) {
                    $subAccountBalance += floatval(str_replace([',', '.'], ['', ','], $balance));
                }
                $this->logger->debug("subAccountBalance after counting: {$subAccountBalance}");
            }
        }

        return $subAccountBalance;
    }
    private function getCardType($cardType, &$kind, &$cardDescription, $rewardProgramName = null, $typeForDebug = null)
    {
        $this->logger->notice(__METHOD__);
        $skip = false;

        switch ($cardType) {
            case 'AARP':
                $kind = 'AARP Credit Card from Chase';

                break;

            case 'AER_LINGUS_AVIOS':
                $kind = 'Aer Lingus Visa Signature® Card';
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Aer Lingus', 184], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'AEROPLAN_CARD':
                $kind = 'Aeroplan® Card';
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Air Canada', 2], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'AMAZON':
                $kind = 'Amazon Rewards Visa Signature Card';

                break;

            case 'AMAZON_PRIME':
                $kind = 'Amazon Prime Rewards Visa Signature Card';

                break;

            case 'AMAZON_REWARDS_VISA':
                $kind = 'Amazon';

                break;

            case 'AIRFORCE_CLUB':
            case 'ARMY_AND_AIR_FORCE_EXCHANGE_SERVICE':
            case 'ARMY_MWR':
                $kind = 'Military Free Cash Rewards';
                $this->logger->notice("Card {$cardType } / {$typeForDebug} - refs #18775 // RR");

                break;

            case 'BRITISH_AIRWAYS':
                $kind = 'British Airways Visa Signature® Card';
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['British Airways', 31], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'CHASE_MARSHALL':
            case 'SAPPHIRE_SENECA':
                $kind = 'Sapphire Reserve';

                break;

            case 'CHASE_INK_BUSINESS_PREFERRED_CORP':
                /*
                if ($rewardProgramName && strstr($rewardProgramName, 'Unlimited')) {
                    /*
                        'rewardProgramName' => 'Ink Business Unlimited SM',
                        'rewardsCardType' => 'CHASE_INK_BUSINESS_PREFERRED_CORP',
                    * /
                    $kind = "Ink Unlimited (Corporate)";
                }
                else {
                    /*
                        ‘rewardProgramName’ => ‘’,
                        ‘rewardsCardType’ => ‘CHASE_INK_BUSINESS_PREFERRED_CORP’,
                     * /
                }
                */
                $kind = "Ink Preferred";

                break;

            case 'CHASE_INK_BUSINESS_PREFERRED':
                if (
                    $rewardProgramName
                    && strstr($rewardProgramName, 'Unlimited')
                ) {
                    /*
                        'rewardProgramName' => 'Ink Business Unlimited SM',
                        'rewardsCardType' => 'CHASE_INK_BUSINESS_PREFERRED',
                    */
                    $kind = "Ink Unlimited";
                } else {
                    /*
                        ‘rewardProgramName’ => ‘Ink Business PreferredS SM’,
                        ‘rewardsCardType’ => ‘CHASE_INK_BUSINESS_PREFERRED’,
                     */
                    $kind = "Ink Preferred";
                }

                break;

            case 'CHASE_INK_BUSINESS_PREMIER':
                $kind = 'Ink Business Premier®';

                break;

            case 'CHASE_SAPPHIRE_PREFERRED':
                $kind = 'Sapphire Preferred';

                break;

            case 'CHASE_SAPPHIRE':
                $kind = 'Sapphire';

                break;

            case 'CHASE_SLATE':
                $kind = 'Slate';
                $skip = true;

                break;

            case 'CHASE_SLATE_EDGE':
                $kind = 'Slate Edge';
                $skip = true;

                break;

            case 'DISNEY':
                $kind = 'Disney';

                break;

            case 'FAIRMONT_HOTELS_AND_RESORTS':
                $kind = 'Fairmont';
                $this->logger->notice("Card {$cardType } / {$typeForDebug} - refs #18775 // RR");
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Fairmont', 130], C_CARD_DESC_UNIVERSAL);

                break;

            case 'FREEDOM_CARD':
            case 'FREEDOM_SIGNATURE':
            case 'FREEDOM_PLATINUM':
                $kind = 'Freedom';

                break;

            case 'FREEDOM_UNLIMITED':
                $kind = 'Freedom Unlimited';

                break;

            case 'FREEDOM_STUDENT':
                $kind = 'Freedom Student';

                break;

            case 'MBAPPE_CARD':
                $kind = 'Freedom Flex';

                break;

            case 'JPMORGAN':
            case 'JPMORGAN_PRIVATE_BANK':
                $this->logger->notice("refs #17130");
                $this->logger->notice("Card {$cardType } / {$typeForDebug} - refs #18775 // RR");
                $kind = 'J.P.MORGAN';

                break;

            case 'JPM_MARSHALL':
            case 'JPM_SENECA':
                $kind = "J.P.Morgan Palladium";

                break;

            case 'HYATT':
                $kind = 'The Hyatt Credit Card';
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['World of Hyatt', 10], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'HYATT_HOTELS':
                $kind = 'The World Of Hyatt Credit Card';
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['World of Hyatt', 10], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'HYATT_BUSINESS':
                $kind = 'World of Hyatt Business Credit Card';
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['World of Hyatt', 10], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'IBERIA_AVIOS':
                $kind = 'Iberia Visa Signature® Card';
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Iberia Plus', 86], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'INTERCONTINENTAL_HOTELS_GROUP':
                $kind = 'IHG® Rewards Premier Credit Card';
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['IHG Rewards Club', 12], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'MARRIOTT':
                $kind = 'Marriott Bonvoy Boundless™ Credit Card';
                $cardDescription = C_CARD_DESC_MARRIOTT;
                $skip = true;

                break;

            case 'MARRIOTT_REWARDS_PREMIER':
                $kind = 'Marriott Bonvoy Premier™ Plus Business Credit Card';
                $cardDescription = C_CARD_DESC_MARRIOTT;
                $skip = true;

                break;

            case 'MARRIOTT_BONSAI':
                $kind = 'Marriott Bonvoy Bold™ Credit Card';
                $cardDescription = C_CARD_DESC_MARRIOTT;
                $skip = true;

                break;

            case 'MARY_KAY':
                $kind = 'Mary Kay';

                break;

            case 'RITZ_CARLTON':
                $kind = 'The Ritz-Carlton™ Credit Card';
                $cardDescription = C_CARD_DESC_MARRIOTT;
                $skip = true;

                break;

            case 'SOUTHWEST_PREMIER':
                if ($rewardProgramName && strstr($rewardProgramName, 'Premier Business Credit Card')) {
                    /*
                        rewardProgramName: "Southwest Rapid Rewards<sup>&reg;</sup> Premier Business Credit Card",
                        rewardsCardType: "SOUTHWEST_PREMIER",
                    */
                    $kind = 'Southwest Rapid Rewards® Premier Business Credit Card';
                } else {
                    /*
                        ‘rewardProgramName’ => ‘Southwest Rapid Rewards<sup>&reg;</sup> Performance Business Credit Card"’,
                        ‘rewardsCardType’ => ‘SOUTHWEST_PREMIER’,
                    */
                    $kind = 'Southwest Rapid Rewards® Performance Business Credit Card';
                }
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Southwest', 16], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'SOUTHWEST_AIRLINES':
                if ($rewardProgramName && strstr($rewardProgramName, 'Premier Credit Card')) {
                    /*
                     rewardProgramName: "Southwest Rapid Rewards<sup>&reg;</sup> Premier Credit Card",
                     rewardsCardType: "SOUTHWEST_AIRLINES",
                     */
                    $kind = 'Southwest Rapid Rewards® Premier Credit Card';
                } elseif ($rewardProgramName && strstr($rewardProgramName, 'Plus Credit Card')) {
                    /*
                     rewardProgramName: "Southwest Rapid Rewards<sup>&reg;</sup> Plus Credit Card",
                     rewardsCardType: "SOUTHWEST_AIRLINES",
                     */
                    $kind = 'Southwest Rapid Rewards® Plus Credit Card';
                } else {
                    /*
                     ‘rewardProgramName’ => ‘Southwest Rapid Rewards<sup>&reg;</sup> Priority Credit Card"’,
                     ‘rewardsCardType’ => ‘SOUTHWEST_AIRLINES’,
                     */
                    $kind = 'Southwest Rapid Rewards® Priority Credit Card';
                }
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Southwest', 16], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'SOUTHWEST_PLUS':// Southwest Rapid Rewards Plus Business
                $kind = 'Southwest Rapid Rewards® Plus Credit Card';
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Southwest', 16], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'STARBUCKS':
                $kind = 'Starbucks Rewards';
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Starbucks Card Rewards', 195], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'UNITED':
                $kind = 'United';

                if ($rewardProgramName && strstr($rewardProgramName, 'Visa Infinite Card')) {
                    $kind = 'United Club℠ Infinite Card';
                }

                if ($rewardProgramName && strstr($rewardProgramName, 'United Gateway')) {
                    $kind = 'United Gateway℠ Card';
                }

                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['United', 26], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'UNITED_TRAVEL_CASH':
            case 'UNITED_MILEAGEPLUS_CLUB':// MileagePlus Club United Chase (Business)
            case 'UNITED_MILEAGEPLUS_EXPLORER':
            case 'UNITED_MILEAGE_PLUS_UA':
            case 'UNITED_MILEAGEPLUS_PRESIDENTIAL_PLUS':// Presidental Plus United Chase (Business)
            case 'UNITED_MILEAGE_PLUS_FCB':// MileagePlus Card United Chase (Business)
            case 'UNITED_MILEAGE_PLUS_MIDDLE':
                $kind = 'United';
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['United', 26], C_CARD_DESC_UNIVERSAL);
                $skip = true;

                break;

            case 'ZAPPOS':
                $kind = 'Zappos';
                $this->logger->notice("Card {$cardType } / {$typeForDebug} - refs #18775 // RR");

                break;

            case 'INK':
                $kind = 'Ink';

                break;

            case 'INK_CLASSIC':
                $kind = "Ink Classic";

                break;

            case 'INK_521':
                $kind = 'Ink 521';

                break;

            case 'INK_CASH':
                $kind = "Ink Cash";

                break;

            case 'INK_CASH_521':
                $kind = "Ink Cash 521";

                break;

            case 'INK_CASH_LEGACY':
                $kind = "Ink Cash (legacy)";

                break;

            case 'INK_CAPITAL':
                $kind = "Ink (Capital)";

                break;

            case 'INK_PLUS':
                $kind = "Ink Plus";

                break;

            case 'INK_PLUS_521':
                $kind = "Ink Plus"; // refs #17136
//                    $kind = "Ink Plus 521";// refs #15541
                break;

            case 'INK_PLUS_521_CORP':
                $kind = "Ink Plus 521 (Corporate)";

                break;

            case 'INK_BOLD':
                $kind = "Ink Bold";

                break;

            case 'INK_BOLD_521':
                $kind = "Ink Bold 521";

                break;

            case 'INK_BOLD_EXCLUSIVES':
                $kind = "Ink Bold Exclusives";

                break;

            case 'INK_BUSINESS':
                $kind = "Ink Preferred";

                break;

            case 'ICEBERG':
                $this->logger->notice("refs #18775 - Instacart // RR");
                $kind = "Instacart";

                break;

            default:
                $kind = '';

                if (!empty($cardType)) {
                    $this->logger->info("Unknown kind -> {$cardType}", ['Header' => 3]);
                    $this->logger->notice("refs #18775: Unknown kind -> {$cardType}");
                }// if (!empty($cardType))
        }// switch ($cardType)

        return $skip;
    }

    private function parseSubAccHistory(Tab $tab, AccountOptions $options, ?ParseHistoryOptions $historyOptions, $code)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        if ($historyOptions === null) {
            return $result;
        }
        $this->logger->info("History for card ...{$code}", ['Header' => 3]);
        $startDate = $historyOptions->getSubAccountStartDate($code);
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');

        // refs #19361, note-78
        if (!$historyOptions->isStrictHistoryStartDate() && $startDate !== null) {
            $startDate = strtotime("-4 day", $startDate);
            $this->logger->debug('[Set history start date -4 days for ' . $code . ': ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        }

        $page = 0;
        $endHistory = false;
        $newURL = false;
        // endHistory does not work, paging will return 500, when there are no more pages
        // refs #21240
        $urlUR = "https://ultimaterewardspoints.chase.com/rewardsActivity?cycle=";
        $options = [
            'method' => 'get',
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json, text/plain, */*'
            ]
        ];
        do {
            $this->logger->debug("[Page: {$page}]");
            try {
                $rewardsActivityResp = $tab->fetch("$urlUR$page", $options);
                $this->logger->info($rewardsActivityResp->body);
                $rewardsActivity = json_decode($rewardsActivityResp->body);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                break;
            }

            $startIndex = sizeof($result);
            $result = array_merge($result, $this->parsePageSubAccHistory($startIndex, $startDate, $rewardsActivity, $endHistory));
            $page++;

            // refs #19361, Parsing Credit Card statements instead of the Ultimate Rewards transactions
            if ($this->parseCategories() && count($result) > $this->maxHistoryRows) {
                $this->logger->notice("break: stop parse history at " . count($result));

                break;
            }
        } while (
            $page < 15
            && (!$this->findPreg("/\[\]/", $rewardsActivityResp->body) || ($this->findPreg("/\[\]/", $rewardsActivityResp->body) && $page < 3))
            && $rewardsActivityResp->status === 200
            && !$endHistory
        );

        // refs #19361, Parsing Credit Card statements instead of the Ultimate Rewards transactions
        if ($this->parseCategories()) {
            $this->logger->debug("history rows: " . count($result));
            $result = array_slice($result, 0, $this->maxHistoryRows);
            $this->logger->debug("history rows after truncating: " . count($result));
        }

        return $result;
    }

    private function parsePageSubAccHistory($startIndex, $startDate, $response, &$endHistory)
    {
        $result = [];
        $this->logger->debug("Total " . (is_array($response) ? count($response) : "not array") . " activity rows were found");

        if (!empty($response)) {
            foreach ($response as $activity) {
                $dateStr = $activity->transactionDateInMillis;

                if (!empty($dateStr)) {
                    $postDate = $dateStr / 1000;
                } else {
                    $postDate = null;
                }
                $dateStr = date("m/d/Y", $dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");
                    $endHistory = true;

                    continue;
                }
                // Transaction
                $result[$startIndex]['Date'] = $postDate;
                // Description
                $result[$startIndex]['Description'] = $activity->transactionName;

                if (empty($result[$startIndex]['Description']) && in_array($activity->activityType, [
                        'STATEMENT_CREDIT',
                    ])
                ) {
                    $result[$startIndex]['Description'] = 'Statement Credit';
                }

                // Amount
                $result[$startIndex]['Amount'] = $activity->amountSpent->amount;

                if ($result[$startIndex]['Amount']) {
                    $result[$startIndex]['Currency'] = 'USD';
                }
                // Points
                $result[$startIndex]['Points'] = $activity->pointsEarned->amount ?? null;

                if (!$result[$startIndex]['Points']) {
                    $result[$startIndex]['Points'] = $activity->pointsActivity->amount ?? null;
                }

                // fixed transfer transaction
                if (
                    stristr($result[$startIndex]['Description'], 'Points Moved To')
                    || in_array($activity->activityType, ['STATEMENT_CREDIT', 'TRIPS', 'REDEMPTION'])
                ) {
                    if ($result[$startIndex]['Points'] > 0) {
                        $result[$startIndex]['Points'] = -$result[$startIndex]['Points'];
                    }

                    if ($result[$startIndex]['Amount'] > 0) {
                        $result[$startIndex]['Amount'] = -$result[$startIndex]['Amount'];
                    }
                }

                // Details: Statement Credit, Qualified purchase, Bonus earn etc.
                if ($activity->bonusCategory == true) {
                    // https://redmine.awardwallet.com/issues/15714#note-4
                    if ($result[$startIndex]['Points'] < 0) {
                        $result[$startIndex]['Details'] = 'Return, Bonus earn';
                    } else {
                        $result[$startIndex]['Details'] = 'Bonus earn';
                    }
                }

                // https://redmine.awardwallet.com/issues/19835#note-8
                if ($result[$startIndex]['Points'] < 0 && $result[$startIndex]['Amount'] > 0) {
                    $result[$startIndex]['Amount'] *= -1;
                }

                // #note-57
                $activityItems = $activity->activityItems ?? [];
                $result[$startIndex]['Transaction Description'] = json_encode($activityItems);

                foreach ($activityItems as $activityItem) {
                    $earnedTransactionDescription = $activityItem->earnedTransactionDescription;
                    $this->logger->debug("activityItem: {$earnedTransactionDescription}");
                    $description =
                        $this->findPreg("/(?:Pts|Points?) per \\$1\s*(?:earned on all|earned on|on all|on|)\s*(.+)(?:purchases|)/ims", $earnedTransactionDescription)
                        ?? $this->findPreg("/(?:cat|category):\s*(.+)/ims", $earnedTransactionDescription)
                        ?? $this->findPreg("/Bonus on purchases at (.+)/ims", $earnedTransactionDescription)
                    ;
                    $this->logger->debug("result: {$description}");

                    if ($description === 'Paypal') {
                        $this->logger->debug(var_export($activityItem, true), ['pre' => true]);

                        $this->logger->notice("Paypal category was found - refs #20427 // RR");
                        $description = $this->findPreg("/Bonus on purchases at (.+)/ims", $earnedTransactionDescription);
                    }

                    $category = trim(preg_replace('/(other purchases|purchases|you spend|earned on all purchases|on all other purchases|earned on |^on )/ims', '', str_replace(',', ', ', $description)));
                    $result[$startIndex]['Category'] = $category;

                    if (!empty($category)) {
                        break;
                    }
                }

                $startIndex++;
            }
        }

        return $result;
    }

    // refs #16043, 17502
    private function travelBenefits(Tab $tab, Statement $st, $subAccountDisplayName, $subAccountCode)
    {
        $this->logger->notice(__METHOD__);

        if (
            !stristr($subAccountDisplayName, 'Sapphire Reserve')
            && !stristr($subAccountDisplayName, 'J.P.Morgan Reserve')
            && !stristr($subAccountDisplayName, 'Sapphire Preferred') // refs #22475
        ) {
            return;
        }

        if (stristr($subAccountDisplayName, 'Sapphire Preferred')) {
            $this->logger->notice("refs #22475 - need to check 'Travel hotel credit' // RR");
        }

        $this->logger->info("Annual Travel Credit for card ...{$subAccountCode}", ['Header' => 3]);
        $benefits = [];

        // Airport Lounge Access
        $options = [
            'method' => 'get',
            'headers' => $this->headers
        ];
        $status = $tab->fetch('https://ultimaterewardspoints.chase.com/rest/card-benefits/benefit/status', $options)->body;
        $this->logger->info($status);
        $status = json_decode($status);
        if (isset($status->payload) && $status->payload == 'ACTIVE') {
            $st->addProperty("AirportLoungeAccess", "Activated");
        } elseif (isset($status->payload) && in_array($status->payload, ["NOT ENROLLED", "Not Enrolled"])) {
            $st->addProperty("AirportLoungeAccess", "Not Activated");
        } else {
            $this->logger->notice("refs #16043 Airport Lounge Access");
        }


        $travelstatementcredit = $tab->fetch('https://ultimaterewardspoints.chase.com/rest/travelstatementcredit', $options)->body;
        $this->logger->info($travelstatementcredit);
        $travelstatementcredit = json_decode($travelstatementcredit);
        if (isset($travelstatementcredit->availableAmount)) {
            [$month, $year] = explode('/', $travelstatementcredit->travelCreditRefreshDate);

            $balance = 300 - $travelstatementcredit->availableAmount;

            if ($balance === 0) {
                $this->logger->notice(">>> Skip used Annual Travel Credit: [$travelstatementcredit->title]: {$balance}");
            } else {
                $this->benefitSubAccounts[] = [
                    'Code'           => 'chaseAnnualTravelCredit' . $subAccountCode,
                    'DisplayName'    => $travelstatementcredit->earnStateHeader . " (" . $this->findPreg("/(\.\.\.\d+)/", $subAccountDisplayName) . ")",
                    'Balance'        => $balance,
                    'Currency'       => "$",
                    'ExpirationDate' => mktime(0, 0, 0, $month, 1, $year),
                ];
                $this->logger->debug("Adding subAccount...");
                $this->logger->debug(var_export($this->benefitSubAccounts, true), ['pre' => true]);
            }
        }

        // Chase sign up bonus tracker // refs #17639
        $this->logger->info("Chase sign up bonus tracker for card ...{$subAccountCode}", ['Header' => 3]);
        $options = [
            'method' => 'get',
            'headers' => $this->headers
        ];
        $offer = null;
        try {
            $offer = $tab->fetch('https://ultimaterewardspoints.chase.com/rest/earn-offer/premium-tracker-offer',
                $options)->body;
            $this->logger->info($offer);
            $offer = json_decode($offer);
        }catch(\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        $displayTracker = $offer->displayTracker ?? false;
        $bonusState = $offer->bonusState ?? false;
        $balance = $offer->amountLeftToSpend ?? null;

        if (!$displayTracker || $balance <= 0 || $bonusState == 'received') {
            return;
        }

        $displayName = preg_replace('/\s*\(.+\)$/', '', $subAccountDisplayName);
        $displayName = str_replace('/ Ultimate Rewards ', '', $displayName);
        $this->logger->debug("[DisplayName]: $displayName");
        $exp = $this->findPreg("/(?:until|by) (.+) to earn/", $offer->shortDescription);
        $this->benefitSubAccounts[] = [
            'Code'           => 'chaseMinimumSpendAmountLeft' . $subAccountCode,
            'DisplayName'    => "Minimum spend amount left on card " . $this->findPreg("/(\.\.\.\d+)/", $displayName),
            'Balance'        => $balance,
            'Currency'       => "$",
            'ExpirationDate' => strtotime($exp),
            'Spent'          => "$" . $offer->amountSpent ?? null,
        ];
    }
    private function parseCategories()
    {
        $this->logger->notice("parseCategories: true");

        return true;
    }

    private function getCreditBureauName(Tab $tab, Statement $st)
    {
        $this->logger->notice(__METHOD__);

        $options = [
            'method' => 'get',
            'headers' => [
                "Accept"            => "*/*",
                "Accept-Language"   => "en-US,en;q=0.5",
                "Accept-Encoding"   => "gzip, deflate, br",
                "Referer"           => $this->baseURL . "/web/auth/cjp",
                "x-jpmc-channel"    => "id=C30",
                "Content-Type"      => "application/x-www-form-urlencoded; charset=UTF-8",
                "Connection"        => "keep-alive",
            ]
        ];
        try {
            $response = $tab->fetch('/svc/wr/profile/secure/creditscore/v2/credit-journey/servicing/inquiry-maintenance/v2/customers/credit-score-outlines',
                $options)->body;
            $this->logger->info($response);
            $response = json_decode($response);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }

        // VantageScore® 3.0 (Experian)
        if (isset($response->creditBureauName, $response->creditScore->currentCreditScoreSummary->creditRiskScore)) {
            $creditBureauName = beautifulName($response->creditBureauName);
            $riskModelName = $response->creditScoreModelIdentifier->riskModelName;
            $riskModelVersionNumber = $response->creditScoreModelIdentifier->riskModelVersionNumber;
            $this->logger->info("{$riskModelName} {$riskModelVersionNumber} ({$creditBureauName})", ['Header' => 4]);
            $st->addSubAccount([
                "Code"               => "chaseFICO",
                "DisplayName"        => "{$riskModelName} {$riskModelVersionNumber} ({$creditBureauName})",
                "Balance"            => $response->creditScore->currentCreditScoreSummary->creditRiskScore,
                // As of
                "FICOScoreUpdatedOn" => preg_replace('/(\d{4})(\d{2})(\d{2})/', '$2/$3/$1', $response->updateDate),
            ]);
        }
    }

    private function getMerchantCode($code)
    {
        // https://static.chasecdn.com/content/site-services/content-pairs/configuration/en/merchant-codes.json
        $mappings = [
            [
                "key"   => "1520",
                "value" => "General contractors: residential and commercial",
            ],
            [
                "key"   => "1711",
                "value" => "Heating, plumbing and air conditioning contractors",
            ],
            [
                "key"   => "1731",
                "value" => "Electrical contractors",
            ],
            [
                "key"   => "1740",
                "value" => "Masonry, stonework, tile setting, plastering, insulation",
            ],
            [
                "key"   => "1750",
                "value" => "Carpentry ",
            ],
            [
                "key"   => "1761",
                "value" => "Roofing, siding and sheet metal work contractors",
            ],
            [
                "key"   => "1771",
                "value" => "Concrete work contractors",
            ],
            [
                "key"   => "1799",
                "value" => "Special trade contractors",
            ],
            [
                "key"   => "2741",
                "value" => "Publishing and printing services",
            ],
            [
                "key"   => "2791",
                "value" => "Typesetting, plate making and related services",
            ],
            [
                "key"   => "2842",
                "value" => "Speciality cleaning, polishing and sanitation preparations",
            ],
            [
                "key"   => "3000",
                "value" => "UNITED AIRLINES",
            ],
            [
                "key"   => "3001",
                "value" => "AMERICAN AIRLINES",
            ],
            [
                "key"   => "3002",
                "value" => "PAN AMERICAN",
            ],
            [
                "key"   => "3003",
                "value" => "EUROFLY AIRLINES",
            ],
            [
                "key"   => "3004",
                "value" => "DRAGON AIRLINES",
            ],
            [
                "key"   => "3005",
                "value" => "BRITISH AIRWAYS",
            ],
            [
                "key"   => "3006",
                "value" => "JAPAN AIR LINES",
            ],
            [
                "key"   => "3007",
                "value" => "AIR FRANCE",
            ],
            [
                "key"   => "3008",
                "value" => "LUFTHANSA",
            ],
            [
                "key"   => "3009",
                "value" => "AIR CANADA",
            ],
            [
                "key"   => "3010",
                "value" => "KLM (ROYAL DUTCH AIRLINES)",
            ],
            [
                "key"   => "3011",
                "value" => "AEROFLOT",
            ],
            [
                "key"   => "3012",
                "value" => "QANTAS",
            ],
            [
                "key"   => "3013",
                "value" => "ALITALIA",
            ],
            [
                "key"   => "3014",
                "value" => "SAUDI ARABIAN AIRLINES",
            ],
            [
                "key"   => "3015",
                "value" => "SWISS INTERNATIONAL AIRLINES",
            ],
            [
                "key"   => "3016",
                "value" => "SAS",
            ],
            [
                "key"   => "3017",
                "value" => "SOUTH AFRICAN AIRWAYS",
            ],
            [
                "key"   => "3018",
                "value" => "VARIG (BRAZIL)",
            ],
            [
                "key"   => "3019",
                "value" => "GERMANWINGS",
            ],
            [
                "key"   => "3020",
                "value" => "AIR INDIA",
            ],
            [
                "key"   => "3021",
                "value" => "AIR ALGERIE",
            ],
            [
                "key"   => "3022",
                "value" => "PHILIPPINE AIRLINES",
            ],
            [
                "key"   => "3023",
                "value" => "MEXICANA",
            ],
            [
                "key"   => "3024",
                "value" => "PAKISTAN INTERNATIONAL",
            ],
            [
                "key"   => "3025",
                "value" => "AIR NEW ZEALAND ",
            ],
            [
                "key"   => "3026",
                "value" => "EMIRATES AIRLINES",
            ],
            [
                "key"   => "3027",
                "value" => "UTA/INTERAIR",
            ],
            [
                "key"   => "3028",
                "value" => "AIR MALTA",
            ],
            [
                "key"   => "3029",
                "value" => "SN BRUSSELS AIRLINES",
            ],
            [
                "key"   => "3030",
                "value" => "AEROLINEAS ARGENTINAS",
            ],
            [
                "key"   => "3031",
                "value" => "OLYMPIC AIRWAYS",
            ],
            [
                "key"   => "3032",
                "value" => "EL AL",
            ],
            [
                "key"   => "3033",
                "value" => "ANSETT AIRLINES",
            ],
            [
                "key"   => "3034",
                "value" => "ETIHAD AIRWAYS",
            ],
            [
                "key"   => "3035",
                "value" => "TAP (PORTUGAL)",
            ],
            [
                "key"   => "3036",
                "value" => "VASP (BRAZIL)",
            ],
            [
                "key"   => "3037",
                "value" => "EGYPTAIR",
            ],
            [
                "key"   => "3038",
                "value" => "KUWAIT AIRWAYS",
            ],
            [
                "key"   => "3039",
                "value" => "AVIANCA",
            ],
            [
                "key"   => "3040",
                "value" => "GULF AIR (BAHRAIN)",
            ],
            [
                "key"   => "3041",
                "value" => "BALKAN-BULGARIAN AIRLINES",
            ],
            [
                "key"   => "3042",
                "value" => "FINNAIR",
            ],
            [
                "key"   => "3043",
                "value" => "AER LINGUS",
            ],
            [
                "key"   => "3044",
                "value" => "AIR LANKA",
            ],
            [
                "key"   => "3045",
                "value" => "NIGERIA AIRWAYS",
            ],
            [
                "key"   => "3046",
                "value" => "CRUZEIRO DO SUL (BRAZIL)",
            ],
            [
                "key"   => "3047",
                "value" => "TURKISH AIRLINES",
            ],
            [
                "key"   => "3048",
                "value" => "ROYAL AIR MAROC",
            ],
            [
                "key"   => "3049",
                "value" => "TUNIS AIR",
            ],
            [
                "key"   => "3050",
                "value" => "ICELANDAIR",
            ],
            [
                "key"   => "3051",
                "value" => "AUSTRIAN AIRLINES",
            ],
            [
                "key"   => "3052",
                "value" => "LAN AIR",
            ],
            [
                "key"   => "3053",
                "value" => "AVIACO (SPAIN)",
            ],
            [
                "key"   => "3054",
                "value" => "LADECO (CHILE)",
            ],
            [
                "key"   => "3055",
                "value" => "LAB (BOLIVIA)",
            ],
            [
                "key"   => "3056",
                "value" => "JET AIRWAYS",
            ],
            [
                "key"   => "3057",
                "value" => "VIRGIN AMERICA",
            ],
            [
                "key"   => "3058",
                "value" => "DELTA",
            ],
            [
                "key"   => "3059",
                "value" => "DBA AIRLINES",
            ],
            [
                "key"   => "3060",
                "value" => "NORTHWEST ",
            ],
            [
                "key"   => "3061",
                "value" => "CONTINENTAL",
            ],
            [
                "key"   => "3062",
                "value" => "HAPAG-LLOYD EXPRESS AIRLINES",
            ],
            [
                "key"   => "3063",
                "value" => "US AIRWAYS",
            ],
            [
                "key"   => "3064",
                "value" => "ADRIA AIRWAYS",
            ],
            [
                "key"   => "3065",
                "value" => "AIRINTER",
            ],
            [
                "key"   => "3066",
                "value" => "SOUTHWEST ",
            ],
            [
                "key"   => "3067",
                "value" => "VANGUARD AIRLINES",
            ],
            [
                "key"   => "3068",
                "value" => "AIR ASTANA",
            ],
            [
                "key"   => "3069",
                "value" => "SUN COUNTRY AIRLINES",
            ],
            [
                "key"   => "3071",
                "value" => "AIR BRITISH COLUMBIA",
            ],
            [
                "key"   => "3072",
                "value" => "CEBU PACIFIC AIRLINES",
            ],
            [
                "key"   => "3075",
                "value" => "SINGAPORE AIRLINES",
            ],
            [
                "key"   => "3076",
                "value" => "AEROMEXICO",
            ],
            [
                "key"   => "3077",
                "value" => "THAI AIRWAYS",
            ],
            [
                "key"   => "3078",
                "value" => "CHINA AIRLINES",
            ],
            [
                "key"   => "3079",
                "value" => "JETSTAR AIRWAYS",
            ],
            [
                "key"   => "3081",
                "value" => "NORDAIR",
            ],
            [
                "key"   => "3082",
                "value" => "KOREAN AIRLINES",
            ],
            [
                "key"   => "3083",
                "value" => "AIR AFRIQUE",
            ],
            [
                "key"   => "3084",
                "value" => "EVA AIRLINES",
            ],
            [
                "key"   => "3085",
                "value" => "MIDWEST EXPRESS AIRLINES",
            ],
            [
                "key"   => "3086",
                "value" => "CARNIVAL AIRLINES",
            ],
            [
                "key"   => "3087",
                "value" => "METRO AIRLINES",
            ],
            [
                "key"   => "3088",
                "value" => "CROATIA AIR",
            ],
            [
                "key"   => "3089",
                "value" => "TRANSAERO",
            ],
            [
                "key"   => "3090",
                "value" => "UNI AIRWAYS CORPORATION",
            ],
            [
                "key"   => "3092",
                "value" => "MIDWAY AIRLINES",
            ],
            [
                "key"   => "3094",
                "value" => "ZAMBIA AIRWAYS",
            ],
            [
                "key"   => "3096",
                "value" => "AIR ZIMBABWE",
            ],
            [
                "key"   => "3097",
                "value" => "SPANAIR",
            ],
            [
                "key"   => "3098",
                "value" => "ASIANA AIRLINES",
            ],
            [
                "key"   => "3099",
                "value" => "CATHAY PACIFIC",
            ],
            [
                "key"   => "3100",
                "value" => "MALAYSIAN AIRLINE SYSTEM",
            ],
            [
                "key"   => "3102",
                "value" => "IBERIA",
            ],
            [
                "key"   => "3103",
                "value" => "GARUDA (INDONESIA)",
            ],
            [
                "key"   => "3106",
                "value" => "BRAATHENS S.A.F.E. (NORWAY)",
            ],
            [
                "key"   => "3110",
                "value" => "WINGS AIRWAYS",
            ],
            [
                "key"   => "3111",
                "value" => "BRITISH MIDLAND",
            ],
            [
                "key"   => "3112",
                "value" => "WINDWARD ISLAND",
            ],
            [
                "key"   => "3115",
                "value" => "TOWER AIR",
            ],
            [
                "key"   => "3117",
                "value" => "VIASA",
            ],
            [
                "key"   => "3118",
                "value" => "VALLEY AIRLINES",
            ],
            [
                "key"   => "3125",
                "value" => "TAN",
            ],
            [
                "key"   => "3126",
                "value" => "TALAIR",
            ],
            [
                "key"   => "3127",
                "value" => "TACA INTERNATIONAL",
            ],
            [
                "key"   => "3129",
                "value" => "SURINAM AIRWAYS",
            ],
            [
                "key"   => "3130",
                "value" => "SUNWORLD INTERNATIONAL",
            ],
            [
                "key"   => "3131",
                "value" => "VLM AIRLINES",
            ],
            [
                "key"   => "3132",
                "value" => "FRONTIER AIRLINES",
            ],
            [
                "key"   => "3133",
                "value" => "SUNBELT AIRLINES",
            ],
            [
                "key"   => "3135",
                "value" => "SUDAN AIRWAYS",
            ],
            [
                "key"   => "3136",
                "value" => "QATAR AIRWAYS",
            ],
            [
                "key"   => "3137",
                "value" => "SINGLETON",
            ],
            [
                "key"   => "3138",
                "value" => "SIMMONS AIRLINES",
            ],
            [
                "key"   => "3143",
                "value" => "SCENIC AIRLINES",
            ],
            [
                "key"   => "3144",
                "value" => "VIRGIN ATLANTIC",
            ],
            [
                "key"   => "3145",
                "value" => "SAN JUAN AIRLINES",
            ],
            [
                "key"   => "3146",
                "value" => "LUXAIR",
            ],
            [
                "key"   => "3148",
                "value" => "AIR LITTORAL SA",
            ],
            [
                "key"   => "3151",
                "value" => "AIR ZAIRE",
            ],
            [
                "key"   => "3154",
                "value" => "PRINCEVILLE",
            ],
            [
                "key"   => "3156",
                "value" => "GO FLY, LTD",
            ],
            [
                "key"   => "3159",
                "value" => "PBA",
            ],
            [
                "key"   => "3161",
                "value" => "ALL NIPPON AIRWAYS",
            ],
            [
                "key"   => "3164",
                "value" => "NORONTAIR",
            ],
            [
                "key"   => "3165",
                "value" => "NEW YORK HELICOPTER",
            ],
            [
                "key"   => "3167",
                "value" => "AEROCONTINENTE",
            ],
            [
                "key"   => "3170",
                "value" => "MOUNT COOK",
            ],
            [
                "key"   => "3171",
                "value" => "CANADIAN AIRLINES INTERNATIONAL",
            ],
            [
                "key"   => "3172",
                "value" => "NATIONAIR",
            ],
            [
                "key"   => "3174",
                "value" => "JETBLUE AIRWAYS",
            ],
            [
                "key"   => "3175",
                "value" => "MIDDLE EAST AIR",
            ],
            [
                "key"   => "3176",
                "value" => "METROFLIGHT AIRLINES",
            ],
            [
                "key"   => "3177",
                "value" => "AIRTRAN AIRWAYS",
            ],
            [
                "key"   => "3178",
                "value" => "MESA AIR",
            ],
            [
                "key"   => "3180",
                "value" => "WESTJET AIRLINES",
            ],
            [
                "key"   => "3181",
                "value" => "MALEV",
            ],
            [
                "key"   => "3182",
                "value" => "LOT (POLAND)",
            ],
            [
                "key"   => "3183",
                "value" => "OMAN AVIATION SERVICES",
            ],
            [
                "key"   => "3184",
                "value" => "LIAT",
            ],
            [
                "key"   => "3185",
                "value" => "LAV (VENEZUELA)",
            ],
            [
                "key"   => "3186",
                "value" => "LAP (PARAGUAY)",
            ],
            [
                "key"   => "3187",
                "value" => "LACSA (COSTA RICA)",
            ],
            [
                "key"   => "3188",
                "value" => "VIRGIN EXPRESS",
            ],
            [
                "key"   => "3190",
                "value" => "JUGOSLAV AIR",
            ],
            [
                "key"   => "3191",
                "value" => "ISLAND AIRLINES",
            ],
            [
                "key"   => "3192",
                "value" => "IRAN AIR",
            ],
            [
                "key"   => "3193",
                "value" => "INDIAN AIRLINES",
            ],
            [
                "key"   => "3196",
                "value" => "HAWAIIAN AIR",
            ],
            [
                "key"   => "3197",
                "value" => "HAVASU AIRLINES",
            ],
            [
                "key"   => "3200",
                "value" => "GUYANA AIRWAYS",
            ],
            [
                "key"   => "3203",
                "value" => "GOLDEN PACIFIC AIR",
            ],
            [
                "key"   => "3204",
                "value" => "FREEDOM AIR",
            ],
            [
                "key"   => "3206",
                "value" => "CHINA EASTERN AIRLINES",
            ],
            [
                "key"   => "3211",
                "value" => "NORWEGIAN AIR SHUTTLE",
            ],
            [
                "key"   => "3212",
                "value" => "DOMINICANA",
            ],
            [
                "key"   => "3213",
                "value" => "BRAATHENS REGIONAL AIR",
            ],
            [
                "key"   => "3215",
                "value" => "DAN AIR SERVICES",
            ],
            [
                "key"   => "3216",
                "value" => "CUMBERLAND AIRLINES",
            ],
            [
                "key"   => "3217",
                "value" => "CSA",
            ],
            [
                "key"   => "3218",
                "value" => "CROWN AIR",
            ],
            [
                "key"   => "3219",
                "value" => "COPA",
            ],
            [
                "key"   => "3220",
                "value" => "COMPANIA FAUCETT",
            ],
            [
                "key"   => "3221",
                "value" => "TRANSPORTES AEROS MILITARES ECUATORIANOS",
            ],
            [
                "key"   => "3222",
                "value" => "COMMAND AIRWAYS",
            ],
            [
                "key"   => "3223",
                "value" => "COMAIR",
            ],
            [
                "key"   => "3226",
                "value" => "SKYWAYS AIR",
            ],
            [
                "key"   => "3228",
                "value" => "CAYMAN AIRWAYS",
            ],
            [
                "key"   => "3229",
                "value" => "SAETA-SOCIEDAD ECUATORIANOS DE TRANSPORTES AEREOS",
            ],
            [
                "key"   => "3231",
                "value" => "SAHSA-SERVICIO AERO DE HONDURAS",
            ],
            [
                "key"   => "3233",
                "value" => "CAPITOL AIR",
            ],
            [
                "key"   => "3234",
                "value" => "CARIBBEAN AIRLINES",
            ],
            [
                "key"   => "3235",
                "value" => "BROCKWAY AIR",
            ],
            [
                "key"   => "3236",
                "value" => "AIR ARABIA",
            ],
            [
                "key"   => "3238",
                "value" => "BEMIDJI AVIATION",
            ],
            [
                "key"   => "3239",
                "value" => "BAR HARBOR AIRLINES",
            ],
            [
                "key"   => "3240",
                "value" => "BAHAMASAIR",
            ],
            [
                "key"   => "3241",
                "value" => "AVIATECA (GUATEMALA)",
            ],
            [
                "key"   => "3242",
                "value" => "AVENSA",
            ],
            [
                "key"   => "3243",
                "value" => "AUSTRIAN AIR SERVICE",
            ],
            [
                "key"   => "3245",
                "value" => "EASYJET AIRLINES",
            ],
            [
                "key"   => "3246",
                "value" => "RYANAIR",
            ],
            [
                "key"   => "3247",
                "value" => "GOL AIRLINES",
            ],
            [
                "key"   => "3248",
                "value" => "TAM AIRLINES",
            ],
            [
                "key"   => "3251",
                "value" => "ALOHA AIRLINES",
            ],
            [
                "key"   => "3252",
                "value" => "ALM",
            ],
            [
                "key"   => "3253",
                "value" => "AMERICA WEST",
            ],
            [
                "key"   => "3254",
                "value" => "US AIR SHUTTLE",
            ],
            [
                "key"   => "3256",
                "value" => "ALASKA AIRLINES",
            ],
            [
                "key"   => "3259",
                "value" => "AMERICAN TRANS AIR",
            ],
            [
                "key"   => "3260",
                "value" => "SPIRIT AIRLINES",
            ],
            [
                "key"   => "3261",
                "value" => "AIR CHINA",
            ],
            [
                "key"   => "3262",
                "value" => "RENO AIR, INC.",
            ],
            [
                "key"   => "3263",
                "value" => "AERO SERVICIO CARABOBO",
            ],
            [
                "key"   => "3266",
                "value" => "AIR SEYCHELLES",
            ],
            [
                "key"   => "3267",
                "value" => "AIR PANAMA",
            ],
            [
                "key"   => "3273",
                "value" => "RICA HOTELS",
            ],
            [
                "key"   => "3274",
                "value" => "INTER NOR HOTELS",
            ],
            [
                "key"   => "3280",
                "value" => "AIR JAMAICA",
            ],
            [
                "key"   => "3281",
                "value" => "AIR DJIBOUTI",
            ],
            [
                "key"   => "3282",
                "value" => "AIR DJIBOUTI",
            ],
            [
                "key"   => "3284",
                "value" => "AERO VIRGIN ISLANDS",
            ],
            [
                "key"   => "3285",
                "value" => "AEROPERU",
            ],
            [
                "key"   => "3286",
                "value" => "AEROLINEAS NICARAGUENSIS",
            ],
            [
                "key"   => "3287",
                "value" => "AERO COACH AVIATION",
            ],
            [
                "key"   => "3292",
                "value" => "CYPRUS AIRWAYS",
            ],
            [
                "key"   => "3293",
                "value" => "EQUATORIANA",
            ],
            [
                "key"   => "3294",
                "value" => "ETHIOPIAN AIRLINES",
            ],
            [
                "key"   => "3295",
                "value" => "KENYA AIRWAYS",
            ],
            [
                "key"   => "3296",
                "value" => "AIR BERLIN",
            ],
            [
                "key"   => "3297",
                "value" => "TAROM ROMANIAN AIR TRANSPORT",
            ],
            [
                "key"   => "3298",
                "value" => "AIR MAURITIUS",
            ],
            [
                "key"   => "3299",
                "value" => "WIDEROES FLYVESELSKAP",
            ],
            [
                "key"   => "3300",
                "value" => "AZUL AIR",
            ],
            [
                "key"   => "3301",
                "value" => "WIZZ AIR",
            ],
            [
                "key"   => "3302",
                "value" => "FLYBE LTD",
            ],
            [
                "key"   => "3351",
                "value" => "AFFILIATED AUTO RENTAL",
            ],
            [
                "key"   => "3352",
                "value" => "AMERICAN INTL RENT-A-CAR",
            ],
            [
                "key"   => "3353",
                "value" => "BROOKS RENT-A-CAR",
            ],
            [
                "key"   => "3354",
                "value" => "ACTION AUTO RENTAL",
            ],
            [
                "key"   => "3355",
                "value" => "SIXT CAR RENTAL",
            ],
            [
                "key"   => "3357",
                "value" => "HERTZ ",
            ],
            [
                "key"   => "3359",
                "value" => "PAYLESS CAR RENTAL",
            ],
            [
                "key"   => "3360",
                "value" => "SNAPPY CAR RENTAL",
            ],
            [
                "key"   => "3361",
                "value" => "AIRWAYS RENT-A-CAR",
            ],
            [
                "key"   => "3362",
                "value" => "ALTRA AUTO RENTAL",
            ],
            [
                "key"   => "3364",
                "value" => "AGENCY RENT-A-CAR",
            ],
            [
                "key"   => "3366",
                "value" => "BUDGET RENT-A-CAR",
            ],
            [
                "key"   => "3368",
                "value" => "HOLIDAY RENT-A-CAR",
            ],
            [
                "key"   => "3370",
                "value" => "RENT-A-WRECK",
            ],
            [
                "key"   => "3374",
                "value" => "ACCENT RENT-A-CAR",
            ],
            [
                "key"   => "3376",
                "value" => "AJAX RENT-A-CAR",
            ],
            [
                "key"   => "3380",
                "value" => "TRIANGLE RENT A CAR",
            ],
            [
                "key"   => "3381",
                "value" => "EUROPCAR",
            ],
            [
                "key"   => "3385",
                "value" => "TROPICAL RENT-A-CAR",
            ],
            [
                "key"   => "3386",
                "value" => "SHOWCASE RENTAL CARS",
            ],
            [
                "key"   => "3387",
                "value" => "ALAMO RENT-A-CAR",
            ],
            [
                "key"   => "3388",
                "value" => "MERCHANTS RENT-A-CAR",
            ],
            [
                "key"   => "3389",
                "value" => "AVIS RENT-A-CAR",
            ],
            [
                "key"   => "3390",
                "value" => "DOLLAR RENT-A-CAR",
            ],
            [
                "key"   => "3391",
                "value" => "EUROPE BY CAR",
            ],
            [
                "key"   => "3393",
                "value" => "NATIONAL CAR RENTAL",
            ],
            [
                "key"   => "3394",
                "value" => "KEMWELL GROUP RENT-A-CAR",
            ],
            [
                "key"   => "3395",
                "value" => "THRIFTY CAR RENTAL",
            ],
            [
                "key"   => "3396",
                "value" => "TILDEN RENT-A-CAR",
            ],
            [
                "key"   => "3398",
                "value" => "ECONO-CAR RENT-A-CAR",
            ],
            [
                "key"   => "3400",
                "value" => "AUTO HOST RENTAL CARS",
            ],
            [
                "key"   => "3405",
                "value" => "ENTERPRISE RENT-A-CAR",
            ],
            [
                "key"   => "3409",
                "value" => "GENERAL RENT-A-CAR",
            ],
            [
                "key"   => "3412",
                "value" => "A-1 RENT-A-CAR",
            ],
            [
                "key"   => "3414",
                "value" => "GODFREY NATIONAL RENT-A-CAR",
            ],
            [
                "key"   => "3420",
                "value" => "ANSA INTERNATIONAL RENT-A-CAR",
            ],
            [
                "key"   => "3421",
                "value" => "ALLSTATE RENT-A-CAR",
            ],
            [
                "key"   => "3423",
                "value" => "AVCAR RENT-A-CAR",
            ],
            [
                "key"   => "3425",
                "value" => "AUTOMATE RENT-A-CAR",
            ],
            [
                "key"   => "3427",
                "value" => "AVON RENT-A-CAR",
            ],
            [
                "key"   => "3428",
                "value" => "CAREY RENT-A-CAR",
            ],
            [
                "key"   => "3429",
                "value" => "INSURANCE RENT-A-CAR",
            ],
            [
                "key"   => "3430",
                "value" => "MAJOR RENT-A-CAR",
            ],
            [
                "key"   => "3431",
                "value" => "REPLACEMENT RENT-A-CAR",
            ],
            [
                "key"   => "3432",
                "value" => "RESERVE RENT-A-CAR",
            ],
            [
                "key"   => "3433",
                "value" => "UGLY DUCKLING RENT-A-CAR",
            ],
            [
                "key"   => "3434",
                "value" => "USA RENT-A-CAR",
            ],
            [
                "key"   => "3435",
                "value" => "VALUE RENT-A-CAR",
            ],
            [
                "key"   => "3436",
                "value" => "AUTOHANSA RENT-A-CAR",
            ],
            [
                "key"   => "3437",
                "value" => "CITE RENT-A-CAR",
            ],
            [
                "key"   => "3438",
                "value" => "INTERENT RENT-A-CAR",
            ],
            [
                "key"   => "3439",
                "value" => "MILLEVILLE RENT-A-CAR",
            ],
            [
                "key"   => "3441",
                "value" => "ADVANTAGE RENT A CAR",
            ],
            [
                "key"   => "3501",
                "value" => "HOLIDAY INNS",
            ],
            [
                "key"   => "3502",
                "value" => "BEST WESTERN HOTELS",
            ],
            [
                "key"   => "3503",
                "value" => "SHERATON",
            ],
            [
                "key"   => "3504",
                "value" => "HILTON HOTELS",
            ],
            [
                "key"   => "3505",
                "value" => "FORTE HOTELS",
            ],
            [
                "key"   => "3506",
                "value" => "GOLDEN TULIP HOTELS",
            ],
            [
                "key"   => "3507",
                "value" => "FRIENDSHIP INNS",
            ],
            [
                "key"   => "3508",
                "value" => "QUALITY INNS",
            ],
            [
                "key"   => "3509",
                "value" => "MARRIOTT",
            ],
            [
                "key"   => "3510",
                "value" => "DAYS INNS",
            ],
            [
                "key"   => "3511",
                "value" => "ARABELLA HOTELS",
            ],
            [
                "key"   => "3512",
                "value" => "INTERCONTINENTAL HOTELS",
            ],
            [
                "key"   => "3513",
                "value" => "WESTIN",
            ],
            [
                "key"   => "3514",
                "value" => "AMERISUITES",
            ],
            [
                "key"   => "3515",
                "value" => "RODEWAY INNS",
            ],
            [
                "key"   => "3516",
                "value" => "LA QUINTA INN AND SUITES",
            ],
            [
                "key"   => "3517",
                "value" => "AMERICANA HOTELS",
            ],
            [
                "key"   => "3518",
                "value" => "SOL HOTELS",
            ],
            [
                "key"   => "3519",
                "value" => "PULLMAN INTERNATIONAL HOTELS",
            ],
            [
                "key"   => "3520",
                "value" => "MERIDIEN HOTELS",
            ],
            [
                "key"   => "3521",
                "value" => "CREST HOTELS",
            ],
            [
                "key"   => "3522",
                "value" => "TOKYO HOTEL",
            ],
            [
                "key"   => "3523",
                "value" => "PENINSULA HOTELS",
            ],
            [
                "key"   => "3524",
                "value" => "WELCOMGROUP HOTELS",
            ],
            [
                "key"   => "3525",
                "value" => "DUNFEY HOTELS",
            ],
            [
                "key"   => "3526",
                "value" => "PRINCE HOTELS",
            ],
            [
                "key"   => "3527",
                "value" => "DOWNTOWNER-PASSPORT HOTEL",
            ],
            [
                "key"   => "3528",
                "value" => "RED LION INNS",
            ],
            [
                "key"   => "3529",
                "value" => "CP HOTELS ",
            ],
            [
                "key"   => "3530",
                "value" => "RENAISSANCE HOTELS",
            ],
            [
                "key"   => "3531",
                "value" => "KAUAI COCONUT BEACH RESORT",
            ],
            [
                "key"   => "3532",
                "value" => "ROYAL KONA RESORT",
            ],
            [
                "key"   => "3533",
                "value" => "HOTEL IBIS",
            ],
            [
                "key"   => "3534",
                "value" => "SOUTHERN PACIFIC HOTELS",
            ],
            [
                "key"   => "3535",
                "value" => "HILTON INTERNATIONAL",
            ],
            [
                "key"   => "3536",
                "value" => "AMFAC HOTELS",
            ],
            [
                "key"   => "3537",
                "value" => "ANA HOTELS",
            ],
            [
                "key"   => "3538",
                "value" => "CONCORDE HOTELS",
            ],
            [
                "key"   => "3539",
                "value" => "SUMMERFIELD SUITES HOTEL",
            ],
            [
                "key"   => "3540",
                "value" => "IBEROTEL HOTELS",
            ],
            [
                "key"   => "3541",
                "value" => "HOTEL OKURA",
            ],
            [
                "key"   => "3542",
                "value" => "ROYAL HOTELS",
            ],
            [
                "key"   => "3543",
                "value" => "FOUR SEASONS HOTELS",
            ],
            [
                "key"   => "3544",
                "value" => "CIGA HOTELS",
            ],
            [
                "key"   => "3545",
                "value" => "SHANGRI-LA INTERNATIONAL",
            ],
            [
                "key"   => "3546",
                "value" => "HOTEL SIERRA",
            ],
            [
                "key"   => "3547",
                "value" => "THE BREAKERS RESORT",
            ],
            [
                "key"   => "3548",
                "value" => "HOTELS MELIA",
            ],
            [
                "key"   => "3549",
                "value" => "AUBERGE DES GOVERNEURS",
            ],
            [
                "key"   => "3550",
                "value" => "REGAL 8 INNS",
            ],
            [
                "key"   => "3551",
                "value" => "MIRAGE HOTEL AND CASINO",
            ],
            [
                "key"   => "3552",
                "value" => "COAST HOTELS",
            ],
            [
                "key"   => "3553",
                "value" => "PARK INN BY RADISSON",
            ],
            [
                "key"   => "3554",
                "value" => "PINEHURST RESORT",
            ],
            [
                "key"   => "3555",
                "value" => "TREASURE ISLAND HOTEL AND CASINO",
            ],
            [
                "key"   => "3556",
                "value" => "BARTON CREEK RESORT",
            ],
            [
                "key"   => "3557",
                "value" => "MANHATTAN EAST SUITE HOTELS",
            ],
            [
                "key"   => "3558",
                "value" => "JOLLY HOTELS",
            ],
            [
                "key"   => "3559",
                "value" => "CANDLEWOOD SUITES",
            ],
            [
                "key"   => "3560",
                "value" => "ALADDIN RESORT AND CASINO",
            ],
            [
                "key"   => "3561",
                "value" => "GOLDEN NUGGET",
            ],
            [
                "key"   => "3562",
                "value" => "COMFORT INNS",
            ],
            [
                "key"   => "3563",
                "value" => "JOURNEY'S END MOTELS",
            ],
            [
                "key"   => "3564",
                "value" => "SAM'S TOWN HOTEL AND CASINO",
            ],
            [
                "key"   => "3565",
                "value" => "RELAX INNS",
            ],
            [
                "key"   => "3566",
                "value" => "GARDEN PLACE HOTEL",
            ],
            [
                "key"   => "3567",
                "value" => "SOHO FRAND HOTEL",
            ],
            [
                "key"   => "3568",
                "value" => "LADBROKE HOTELS",
            ],
            [
                "key"   => "3569",
                "value" => "TRIBECA GRAND HOTEL",
            ],
            [
                "key"   => "3570",
                "value" => "FORUM HOTELS",
            ],
            [
                "key"   => "3571",
                "value" => "GRAND WAILEA RESORT",
            ],
            [
                "key"   => "3572",
                "value" => "MIYAKO HOTEL",
            ],
            [
                "key"   => "3573",
                "value" => "SANDMAN HOTELS",
            ],
            [
                "key"   => "3574",
                "value" => "VENTURE INN",
            ],
            [
                "key"   => "3575",
                "value" => "VAGABOND HOTELS",
            ],
            [
                "key"   => "3576",
                "value" => "LA QUINTA RESORT",
            ],
            [
                "key"   => "3577",
                "value" => "MANDARIN ORIENTAL HOTEL",
            ],
            [
                "key"   => "3578",
                "value" => "FRANKENMUTH BAVARIAN",
            ],
            [
                "key"   => "3579",
                "value" => "HOTEL MERCURE",
            ],
            [
                "key"   => "3580",
                "value" => "HOTEL DEL CORONADO",
            ],
            [
                "key"   => "3581",
                "value" => "DELTA HOTELS",
            ],
            [
                "key"   => "3582",
                "value" => "CALIFORNIA HOTEL AND CASINO",
            ],
            [
                "key"   => "3583",
                "value" => "RADISSON BLU",
            ],
            [
                "key"   => "3584",
                "value" => "PRINCESS HOTELS INTERNATIONAL",
            ],
            [
                "key"   => "3585",
                "value" => "HUNGAR HOTELS",
            ],
            [
                "key"   => "3586",
                "value" => "SOKOS HOTEL",
            ],
            [
                "key"   => "3587",
                "value" => "DORAL HOTELS",
            ],
            [
                "key"   => "3588",
                "value" => "HELMSLEY HOTELS",
            ],
            [
                "key"   => "3589",
                "value" => "DORAL GOLF RESORT",
            ],
            [
                "key"   => "3590",
                "value" => "FAIRMONT HOTELS",
            ],
            [
                "key"   => "3591",
                "value" => "SONESTA HOTELS",
            ],
            [
                "key"   => "3592",
                "value" => "OMNI HOTELS",
            ],
            [
                "key"   => "3593",
                "value" => "CUNARD HOTELS",
            ],
            [
                "key"   => "3594",
                "value" => "ARIZONA BILTMORE",
            ],
            [
                "key"   => "3595",
                "value" => "HOSPITALITY INNS",
            ],
            [
                "key"   => "3596",
                "value" => "WYNN LAS VEGAS",
            ],
            [
                "key"   => "3597",
                "value" => "RIVERSIDE RESORT AND CASINO",
            ],
            [
                "key"   => "3598",
                "value" => "REGENT INTERNATIONAL HOTELS",
            ],
            [
                "key"   => "3599",
                "value" => "PANNONIA HOTELS",
            ],
            [
                "key"   => "3600",
                "value" => "SADDLEBROOK RESORT TAMPA",
            ],
            [
                "key"   => "3601",
                "value" => "TRADEWINDS RESORTS",
            ],
            [
                "key"   => "3602",
                "value" => "HUDSON HOTEL",
            ],
            [
                "key"   => "3603",
                "value" => "NOAH'S HOTEL",
            ],
            [
                "key"   => "3604",
                "value" => "HILTON GARDEN INN",
            ],
            [
                "key"   => "3605",
                "value" => "JURYS DOYLE HOTEL GROUP",
            ],
            [
                "key"   => "3606",
                "value" => "JEFFERSON HOTEL",
            ],
            [
                "key"   => "3607",
                "value" => "FONTAINEBLEAU RESORT",
            ],
            [
                "key"   => "3608",
                "value" => "GAYLORD OPRYLAND",
            ],
            [
                "key"   => "3609",
                "value" => "GAYLORD PALMS",
            ],
            [
                "key"   => "3610",
                "value" => "GAYLORD TEXAN",
            ],
            [
                "key"   => "3611",
                "value" => "C MON INN",
            ],
            [
                "key"   => "3612",
                "value" => "MOEVENPICK HOTELS",
            ],
            [
                "key"   => "3613",
                "value" => "MICROTEL INNS & SUITES",
            ],
            [
                "key"   => "3614",
                "value" => "AMERICINN",
            ],
            [
                "key"   => "3615",
                "value" => "TRAVELODGE",
            ],
            [
                "key"   => "3616",
                "value" => "HERMITAGE HOTEL",
            ],
            [
                "key"   => "3617",
                "value" => "AMERICA'S BEST VALUE INN",
            ],
            [
                "key"   => "3618",
                "value" => "GREAT WOLF",
            ],
            [
                "key"   => "3619",
                "value" => "ALOFT",
            ],
            [
                "key"   => "3620",
                "value" => "BINION'S HORSESHOE CLUB",
            ],
            [
                "key"   => "3621",
                "value" => "EXTENDED STAY",
            ],
            [
                "key"   => "3622",
                "value" => "MERLIN HOTELS",
            ],
            [
                "key"   => "3623",
                "value" => "DORINT HOTELS",
            ],
            [
                "key"   => "3624",
                "value" => "LADY LUCK HOTEL AND CASINO",
            ],
            [
                "key"   => "3625",
                "value" => "HOTEL UNIVERSALE",
            ],
            [
                "key"   => "3626",
                "value" => "STUDIO PLUS",
            ],
            [
                "key"   => "3627",
                "value" => "EXTENDED STAY AMERICA",
            ],
            [
                "key"   => "3628",
                "value" => "EXCALIBUR HOTEL AND CASINO",
            ],
            [
                "key"   => "3629",
                "value" => "DAN HOTELS",
            ],
            [
                "key"   => "3630",
                "value" => "EXTENDED STAY DELUXE",
            ],
            [
                "key"   => "3631",
                "value" => "SLEEP INN",
            ],
            [
                "key"   => "3632",
                "value" => "THE PHOENICIAN",
            ],
            [
                "key"   => "3633",
                "value" => "RANK HOTELS",
            ],
            [
                "key"   => "3634",
                "value" => "SWISSOTEL",
            ],
            [
                "key"   => "3635",
                "value" => "RESO HOTELS",
            ],
            [
                "key"   => "3636",
                "value" => "SAROVA HOTELS",
            ],
            [
                "key"   => "3637",
                "value" => "RAMADA INNS",
            ],
            [
                "key"   => "3638",
                "value" => "HOWARD JOHNSON",
            ],
            [
                "key"   => "3639",
                "value" => "MOUNT CHARLOTTE THISTLE",
            ],
            [
                "key"   => "3640",
                "value" => "HYATT HOTELS",
            ],
            [
                "key"   => "3641",
                "value" => "SOFITEL HOTELS",
            ],
            [
                "key"   => "3642",
                "value" => "NOVOTEL HOTELS",
            ],
            [
                "key"   => "3643",
                "value" => "STEIGENBERGER HOTELS",
            ],
            [
                "key"   => "3644",
                "value" => "ECONO LODGES",
            ],
            [
                "key"   => "3645",
                "value" => "QUEENS MOAT HOUSES",
            ],
            [
                "key"   => "3646",
                "value" => "SWALLOW HOTELS",
            ],
            [
                "key"   => "3647",
                "value" => "HUSA HOTELS",
            ],
            [
                "key"   => "3648",
                "value" => "DE VERE HOTELS",
            ],
            [
                "key"   => "3649",
                "value" => "RADISSON  HOTELS",
            ],
            [
                "key"   => "3650",
                "value" => "RED ROOF INNS",
            ],
            [
                "key"   => "3651",
                "value" => "IMPERIAL LONDON HOTEL",
            ],
            [
                "key"   => "3652",
                "value" => "EMBASSY HOTELS",
            ],
            [
                "key"   => "3653",
                "value" => "PENTA HOTELS",
            ],
            [
                "key"   => "3654",
                "value" => "LOEWS HOTELS",
            ],
            [
                "key"   => "3655",
                "value" => "SCANDIC HOTELS",
            ],
            [
                "key"   => "3656",
                "value" => "SARA HOTELS",
            ],
            [
                "key"   => "3657",
                "value" => "OBEROI HOTELS",
            ],
            [
                "key"   => "3658",
                "value" => "NEW OTANI HOTELS",
            ],
            [
                "key"   => "3659",
                "value" => "TAJ HOTELS INTERNATIONAL",
            ],
            [
                "key"   => "3660",
                "value" => "KNIGHTS INNS",
            ],
            [
                "key"   => "3661",
                "value" => "METROPOLE HOTELS",
            ],
            [
                "key"   => "3662",
                "value" => "CIRCUS CIRCUS HOTEL AND CASINO",
            ],
            [
                "key"   => "3663",
                "value" => "HOTELES EL PRESIDENTE",
            ],
            [
                "key"   => "3664",
                "value" => "FLAG INN",
            ],
            [
                "key"   => "3665",
                "value" => "HAMPTON INN",
            ],
            [
                "key"   => "3666",
                "value" => "STAKIS HOTELS",
            ],
            [
                "key"   => "3667",
                "value" => "LUXOR HOTEL AND CASINO",
            ],
            [
                "key"   => "3668",
                "value" => "MARITIM HOTELS",
            ],
            [
                "key"   => "3669",
                "value" => "ELDORADO HOTEL AND CASINO",
            ],
            [
                "key"   => "3670",
                "value" => "ARCADE HOTELS",
            ],
            [
                "key"   => "3671",
                "value" => "ARCTIA HOTELS",
            ],
            [
                "key"   => "3672",
                "value" => "CAMPANILE HOTELS",
            ],
            [
                "key"   => "3673",
                "value" => "IBUSZ HOTELS",
            ],
            [
                "key"   => "3674",
                "value" => "RANTASIPI HOTELS",
            ],
            [
                "key"   => "3675",
                "value" => "INTERHOTEL CEDOK",
            ],
            [
                "key"   => "3676",
                "value" => "MONTE CARLO HOTEL AND CASINO",
            ],
            [
                "key"   => "3677",
                "value" => "CLIMAT DE FRANCE HOTELS",
            ],
            [
                "key"   => "3678",
                "value" => "CUMULUS HOTELS",
            ],
            [
                "key"   => "3679",
                "value" => "SILVER LEGACY HOTEL AND CASINO",
            ],
            [
                "key"   => "3680",
                "value" => "HOTEIS OTHAN",
            ],
            [
                "key"   => "3681",
                "value" => "ADAMS MARK HOTELS",
            ],
            [
                "key"   => "3682",
                "value" => "SAHARA HOTEL AND CASINO",
            ],
            [
                "key"   => "3683",
                "value" => "BRADBURY SUITES",
            ],
            [
                "key"   => "3684",
                "value" => "BUDGET HOST INNS",
            ],
            [
                "key"   => "3685",
                "value" => "BUDGETEL HOTELS",
            ],
            [
                "key"   => "3686",
                "value" => "SUSSE CHALET",
            ],
            [
                "key"   => "3687",
                "value" => "CLARION HOTEL",
            ],
            [
                "key"   => "3688",
                "value" => "COMPRI HOTEL",
            ],
            [
                "key"   => "3689",
                "value" => "CONSORT HOTELS",
            ],
            [
                "key"   => "3690",
                "value" => "COURTYARD BY MARRIOTT",
            ],
            [
                "key"   => "3691",
                "value" => "DILLON INNS",
            ],
            [
                "key"   => "3692",
                "value" => "DOUBLETREE HOTELS",
            ],
            [
                "key"   => "3693",
                "value" => "DRURY INN",
            ],
            [
                "key"   => "3694",
                "value" => "ECONOMY INNS OF AMERICA",
            ],
            [
                "key"   => "3695",
                "value" => "EMBASSY SUITES",
            ],
            [
                "key"   => "3696",
                "value" => "EXCEL INN",
            ],
            [
                "key"   => "3697",
                "value" => "FAIRFIELD HOTELS",
            ],
            [
                "key"   => "3698",
                "value" => "HARLEY HOTELS",
            ],
            [
                "key"   => "3699",
                "value" => "MIDWAY MOTOR LODGE",
            ],
            [
                "key"   => "3700",
                "value" => "MOTEL 6",
            ],
            [
                "key"   => "3701",
                "value" => "LA MANSION DEL RIO",
            ],
            [
                "key"   => "3702",
                "value" => "THE REGISTRY HOTELS",
            ],
            [
                "key"   => "3703",
                "value" => "RESIDENCE INN",
            ],
            [
                "key"   => "3704",
                "value" => "ROYCE HOTELS",
            ],
            [
                "key"   => "3705",
                "value" => "SANDMAN INN",
            ],
            [
                "key"   => "3706",
                "value" => "SHILO INN",
            ],
            [
                "key"   => "3707",
                "value" => "SHONEY'S INN",
            ],
            [
                "key"   => "3708",
                "value" => "VIRGIN RIVER HOTEL AND CASINO",
            ],
            [
                "key"   => "3709",
                "value" => "SUPER 8 MOTELS",
            ],
            [
                "key"   => "3710",
                "value" => "THE RITZ-CARLTON",
            ],
            [
                "key"   => "3711",
                "value" => "FLAG INNS (AUSTRALIA)",
            ],
            [
                "key"   => "3712",
                "value" => "BUFFALO BILL'S HOTEL AND CASINO",
            ],
            [
                "key"   => "3713",
                "value" => "QUALITY PACIFIC HOTEL",
            ],
            [
                "key"   => "3714",
                "value" => "FOUR SEASONS HOTEL (AUSTRALIA)",
            ],
            [
                "key"   => "3715",
                "value" => "FAIRFIELD INN",
            ],
            [
                "key"   => "3716",
                "value" => "CARLTON HOTELS",
            ],
            [
                "key"   => "3717",
                "value" => "CITY LODGE HOTELS",
            ],
            [
                "key"   => "3718",
                "value" => "KAROS HOTELS",
            ],
            [
                "key"   => "3719",
                "value" => "PROTEA HOTELS",
            ],
            [
                "key"   => "3720",
                "value" => "SOUTHERN SUN HOTELS",
            ],
            [
                "key"   => "3721",
                "value" => "CONRAD HOTELS",
            ],
            [
                "key"   => "3722",
                "value" => "WYNDHAM",
            ],
            [
                "key"   => "3723",
                "value" => "RICA HOTLES",
            ],
            [
                "key"   => "3724",
                "value" => "INTER NOR HOTELS",
            ],
            [
                "key"   => "3725",
                "value" => "SEA PINES RESORT",
            ],
            [
                "key"   => "3726",
                "value" => "RIO SUITES",
            ],
            [
                "key"   => "3727",
                "value" => "BROADMOOR HOTEL",
            ],
            [
                "key"   => "3728",
                "value" => "BALLY'S HOTEL AND CASINO",
            ],
            [
                "key"   => "3729",
                "value" => "JOHN ASCUAGA'S NUGGET",
            ],
            [
                "key"   => "3730",
                "value" => "MGM GRAND HOTEL",
            ],
            [
                "key"   => "3731",
                "value" => "HARRAH'S HOTELS AND CASINOS",
            ],
            [
                "key"   => "3732",
                "value" => "OPRYLAND HOTEL",
            ],
            [
                "key"   => "3733",
                "value" => "BOCA RATON RESORT",
            ],
            [
                "key"   => "3734",
                "value" => "HARVEY BRISTOL HOTELS",
            ],
            [
                "key"   => "3735",
                "value" => "MASTERS ECONOMY INNS",
            ],
            [
                "key"   => "3736",
                "value" => "COLORADO BELLE EDGEWATER RESORT",
            ],
            [
                "key"   => "3737",
                "value" => "RIVIERA HOTEL AND CASINO",
            ],
            [
                "key"   => "3738",
                "value" => "TROPICANA RESORT AND CASINO",
            ],
            [
                "key"   => "3739",
                "value" => "WOODSIDE HOTELS AND RESORTS",
            ],
            [
                "key"   => "3740",
                "value" => "TOWNEPLACE SUITES",
            ],
            [
                "key"   => "3741",
                "value" => "MILLENNIUM HOTELS",
            ],
            [
                "key"   => "3742",
                "value" => "CLUB MED",
            ],
            [
                "key"   => "3743",
                "value" => "BILTMORE HOTEL AND SUITES",
            ],
            [
                "key"   => "3744",
                "value" => "CAREFREE RESORTS",
            ],
            [
                "key"   => "3745",
                "value" => "ST. REGIS HOTEL",
            ],
            [
                "key"   => "3746",
                "value" => "THE ELIOT HOTEL",
            ],
            [
                "key"   => "3747",
                "value" => "CLUB CORP/CLUB RESORTS",
            ],
            [
                "key"   => "3748",
                "value" => "WELLESLEY INNS",
            ],
            [
                "key"   => "3749",
                "value" => "THE BEVERLY HILLS HOTEL",
            ],
            [
                "key"   => "3750",
                "value" => "CROWNE PLAZA HOTELS",
            ],
            [
                "key"   => "3751",
                "value" => "HOMEWOOD SUITES",
            ],
            [
                "key"   => "3752",
                "value" => "PEABODY HOTELS",
            ],
            [
                "key"   => "3753",
                "value" => "GREENBRIAR RESORTS",
            ],
            [
                "key"   => "3754",
                "value" => "AMELIA ISLAND PLANTATION",
            ],
            [
                "key"   => "3755",
                "value" => "THE HOMESTEAD",
            ],
            [
                "key"   => "3756",
                "value" => "SOUTH SEAS RESORTS",
            ],
            [
                "key"   => "3757",
                "value" => "CANYON RANCH",
            ],
            [
                "key"   => "3758",
                "value" => "KAHALA MANDARIN ORIENTAL HOTEL",
            ],
            [
                "key"   => "3759",
                "value" => "THE ORCHID AT MAUNA LAI",
            ],
            [
                "key"   => "3760",
                "value" => "HALEKULANI HOTEL/WAIKIKI PARC",
            ],
            [
                "key"   => "3761",
                "value" => "PRIMADONNA HOTEL AND CASINO",
            ],
            [
                "key"   => "3762",
                "value" => "WHISKEY PETE'S HOTEL AND CASINO",
            ],
            [
                "key"   => "3763",
                "value" => "CHATEAU ELAN WINERY AND RESORT",
            ],
            [
                "key"   => "3764",
                "value" => "BEAU RIVAGE HOTEL AND CASINO",
            ],
            [
                "key"   => "3765",
                "value" => "BELLAGIO",
            ],
            [
                "key"   => "3766",
                "value" => "FREMONT HOTEL AND CASINO",
            ],
            [
                "key"   => "3767",
                "value" => "MAIN STREET HOTEL AND CASINO",
            ],
            [
                "key"   => "3768",
                "value" => "SILVER STAR HOTEL AND CASINO",
            ],
            [
                "key"   => "3769",
                "value" => "STRATOSPHERE HOTEL AND CASINO",
            ],
            [
                "key"   => "3770",
                "value" => "SPRINGHILL SUITES",
            ],
            [
                "key"   => "3771",
                "value" => "CAESARS HOTEL AND CASINO",
            ],
            [
                "key"   => "3772",
                "value" => "NEMACOLIN WOODLANDS",
            ],
            [
                "key"   => "3773",
                "value" => "THE VENETIAN RESORT HOTEL AND CASINO",
            ],
            [
                "key"   => "3774",
                "value" => "NEWYORK-NEWYORKHOTELANDCASINO",
            ],
            [
                "key"   => "3775",
                "value" => "SANDS RESORT",
            ],
            [
                "key"   => "3776",
                "value" => "NEVELE GRAND RESORT AND COUNTRY CLUB",
            ],
            [
                "key"   => "3777",
                "value" => "MANDALAY BAY RESORT",
            ],
            [
                "key"   => "3778",
                "value" => "FOUR POINTS HOTELS",
            ],
            [
                "key"   => "3779",
                "value" => "W HOTELS",
            ],
            [
                "key"   => "3780",
                "value" => "DISNEY RESORTS",
            ],
            [
                "key"   => "3781",
                "value" => "PATRICIA GRAND RESORT HOTELS",
            ],
            [
                "key"   => "3782",
                "value" => "ROSEN HOTELS AND RESORTS",
            ],
            [
                "key"   => "3783",
                "value" => "TOWN AND COUNTRY RESORT & CONVENTION CENTER",
            ],
            [
                "key"   => "3784",
                "value" => "FIRST HOSPITALITY HOTELS",
            ],
            [
                "key"   => "3785",
                "value" => "OUTRIGGER HOTELS AND RESORTS",
            ],
            [
                "key"   => "3786",
                "value" => "OHANA HOTELS OF HAWAII",
            ],
            [
                "key"   => "3787",
                "value" => "CARIBE ROYAL RESORTS",
            ],
            [
                "key"   => "3788",
                "value" => "ALA MOANA HOTEL",
            ],
            [
                "key"   => "3789",
                "value" => "SMUGGLER'S NOTCH RESORT",
            ],
            [
                "key"   => "3790",
                "value" => "RAFFLES HOTELS",
            ],
            [
                "key"   => "3791",
                "value" => "STAYBRIDGE SUITES",
            ],
            [
                "key"   => "3792",
                "value" => "CLARIDGE CASINO HOTEL",
            ],
            [
                "key"   => "3793",
                "value" => "FLAMINGO HOTELS",
            ],
            [
                "key"   => "3794",
                "value" => "GRAND CASINO HOTELS",
            ],
            [
                "key"   => "3795",
                "value" => "PARIS LAS VEGAS HOTEL",
            ],
            [
                "key"   => "3796",
                "value" => "PEPPERMILL HOTEL CASINO",
            ],
            [
                "key"   => "3797",
                "value" => "ATLANTIC CITY HILTON RESORTS",
            ],
            [
                "key"   => "3798",
                "value" => "EMBASSY VACATION RESORT",
            ],
            [
                "key"   => "3799",
                "value" => "HALE KOA HOTEL",
            ],
            [
                "key"   => "3800",
                "value" => "HOMESTEAD SUITES",
            ],
            [
                "key"   => "3801",
                "value" => "WILDERNESS HOTEL & RESORT",
            ],
            [
                "key"   => "3802",
                "value" => "THE PALACE HOTEL",
            ],
            [
                "key"   => "3803",
                "value" => "THE WIGWAM GOLF RESORT AND SPA",
            ],
            [
                "key"   => "3804",
                "value" => "THE DIPLOMAT COUNTRY CLUB AND SPA",
            ],
            [
                "key"   => "3805",
                "value" => "THE ATLANTIC",
            ],
            [
                "key"   => "3806",
                "value" => "PRINCEVILLE RESORT",
            ],
            [
                "key"   => "3807",
                "value" => "ELEMENT",
            ],
            [
                "key"   => "3808",
                "value" => "LXR",
            ],
            [
                "key"   => "3809",
                "value" => "SETTLE INN",
            ],
            [
                "key"   => "3810",
                "value" => "LA COSTA RESORT",
            ],
            [
                "key"   => "3811",
                "value" => "PREMIER INN",
            ],
            [
                "key"   => "3812",
                "value" => "HYATT PLACE",
            ],
            [
                "key"   => "3813",
                "value" => "HOTEL INDIGO",
            ],
            [
                "key"   => "3814",
                "value" => "THE ROOSEVELT HOTEL NY",
            ],
            [
                "key"   => "3815",
                "value" => "NICKELODEON FAMILY SUITES BY HOLIDAY INN",
            ],
            [
                "key"   => "3816",
                "value" => "HOME2SUITES",
            ],
            [
                "key"   => "3817",
                "value" => "AFFINIA",
            ],
            [
                "key"   => "3818",
                "value" => "MAINSTAY SUITES",
            ],
            [
                "key"   => "3819",
                "value" => "OXFORD SUITES",
            ],
            [
                "key"   => "3820",
                "value" => "JUMEIRAH ESSEX HOUSE",
            ],
            [
                "key"   => "3821",
                "value" => "CARIBE ROYALE",
            ],
            [
                "key"   => "3822",
                "value" => "CROSSLAND",
            ],
            [
                "key"   => "3823",
                "value" => "GRAND SIERRA RESORT",
            ],
            [
                "key"   => "3824",
                "value" => "ARIA",
            ],
            [
                "key"   => "3825",
                "value" => "VDARA",
            ],
            [
                "key"   => "3826",
                "value" => "AUTOGRAPH",
            ],
            [
                "key"   => "3827",
                "value" => "GALT HOUSE",
            ],
            [
                "key"   => "3828",
                "value" => "COSMOPOLITAN OF LAS VEGAS",
            ],
            [
                "key"   => "3829",
                "value" => "COUNTRY INN BY CARLSON",
            ],
            [
                "key"   => "3830",
                "value" => "PARK PLAZA HOTEL",
            ],
            [
                "key"   => "3831",
                "value" => "WALDORF",
            ],
            [
                "key"   => "3832",
                "value" => "CURIO HOTELS",
            ],
            [
                "key"   => "3833",
                "value" => "CANOPY",
            ],
            [
                "key"   => "3834",
                "value" => "BAYMONT INN & SUITES",
            ],
            [
                "key"   => "3835",
                "value" => "DOLCE HOTELS AND RESORTS",
            ],
            [
                "key"   => "3836",
                "value" => "HAWTHORNE BY WYNDHAM",
            ],
            [
                "key"   => "3837",
                "value" => "HOSHINO RESORTS",
            ],
            [
                "key"   => "3838",
                "value" => "KIMPTON HOTELS",
            ],
            [
                "key"   => "4011",
                "value" => "Railroads: freight",
            ],
            [
                "key"   => "4111",
                "value" => "Local and suburban commuter transportation",
            ],
            [
                "key"   => "4112",
                "value" => "Passenger railways",
            ],
            [
                "key"   => "4119",
                "value" => "Ambulance services",
            ],
            [
                "key"   => "4121",
                "value" => "Taxicabs and limousines",
            ],
            [
                "key"   => "4131",
                "value" => "Bus lines",
            ],
            [
                "key"   => "4214",
                "value" => "Motor freight carriers and trucking",
            ],
            [
                "key"   => "4215",
                "value" => "Courier services and freight forwarders",
            ],
            [
                "key"   => "4225",
                "value" => "Public warehousing and storage",
            ],
            [
                "key"   => "4411",
                "value" => "Steamship and cruise lines",
            ],
            [
                "key"   => "4457",
                "value" => "Boat rentals and leasing",
            ],
            [
                "key"   => "4468",
                "value" => "Marinas, marine services and supplies",
            ],
            [
                "key"   => "4511",
                "value" => "Airlines and air carriers",
            ],
            [
                "key"   => "4582",
                "value" => "Airports and airport terminals",
            ],
            [
                "key"   => "4722",
                "value" => "Travel agencies and tour operators",
            ],
            [
                "key"   => "4723",
                "value" => "Package tour operators ",
            ],
            [
                "key"   => "4761",
                "value" => "Travel arrangement services",
            ],
            [
                "key"   => "4784",
                "value" => "Tolls and bridge fees",
            ],
            [
                "key"   => "4789",
                "value" => "Transportation services ",
            ],
            [
                "key"   => "4812",
                "value" => "Telecommunication equipment and phone sales",
            ],
            [
                "key"   => "4813",
                "value" => "Key-entry telecom merchant ",
            ],
            [
                "key"   => "4814",
                "value" => "Telecommunication services",
            ],
            [
                "key"   => "4815",
                "value" => "Visa phone",
            ],
            [
                "key"   => "4816",
                "value" => "Computer network and information services",
            ],
            [
                "key"   => "4821",
                "value" => "Telegraph services",
            ],
            [
                "key"   => "4829",
                "value" => "Money transfer",
            ],
            [
                "key"   => "4899",
                "value" => "Cable and paid television services",
            ],
            [
                "key"   => "4900",
                "value" => "Utilities: electric, gas, water and sanitation ",
            ],
            [
                "key"   => "5013",
                "value" => "Motor vehicle supplies and new parts",
            ],
            [
                "key"   => "5021",
                "value" => "Office and commercial furniture",
            ],
            [
                "key"   => "5039",
                "value" => "Construction materials",
            ],
            [
                "key"   => "5044",
                "value" => "Photo, photocopy, microfilm equipment and supplies",
            ],
            [
                "key"   => "5045",
                "value" => "Computers, equipment and software",
            ],
            [
                "key"   => "5046",
                "value" => "Commercial equipment",
            ],
            [
                "key"   => "5047",
                "value" => "Medical, dental, lab, ophthalmic and hospital equipment",
            ],
            [
                "key"   => "5051",
                "value" => "Metal service centers and offices",
            ],
            [
                "key"   => "5065",
                "value" => "Electrical parts and equipment",
            ],
            [
                "key"   => "5072",
                "value" => "Hardware, equipment and supplies",
            ],
            [
                "key"   => "5074",
                "value" => "Plumbing and heating equipment and supplies",
            ],
            [
                "key"   => "5085",
                "value" => "Industrial supplies ",
            ],
            [
                "key"   => "5094",
                "value" => "Precious stones and metals, watches and jewelry",
            ],
            [
                "key"   => "5099",
                "value" => "Durable goods",
            ],
            [
                "key"   => "5111",
                "value" => "Stationery, office supplies, printing and writing paper",
            ],
            [
                "key"   => "5122",
                "value" => "Drugs and druggist sundries",
            ],
            [
                "key"   => "5131",
                "value" => "Piece goods, notions and other dry goods",
            ],
            [
                "key"   => "5137",
                "value" => "Uniforms and commercial clothing",
            ],
            [
                "key"   => "5139",
                "value" => "Commercial footwear",
            ],
            [
                "key"   => "5169",
                "value" => "Chemicals and allied products ",
            ],
            [
                "key"   => "5172",
                "value" => "Petroleum products",
            ],
            [
                "key"   => "5192",
                "value" => "Books, periodicals and newspapers",
            ],
            [
                "key"   => "5193",
                "value" => "Florist supplies, nursery stock and flowers",
            ],
            [
                "key"   => "5198",
                "value" => "Paints, varnishes and supplies",
            ],
            [
                "key"   => "5199",
                "value" => "Nondurable goods ",
            ],
            [
                "key"   => "5200",
                "value" => "Home supply warehouses",
            ],
            [
                "key"   => "5211",
                "value" => "Lumber and building materials stores",
            ],
            [
                "key"   => "5231",
                "value" => "Glass, paint and wallpaper stores",
            ],
            [
                "key"   => "5251",
                "value" => "Hardware stores",
            ],
            [
                "key"   => "5261",
                "value" => "Nurseries, lawn and garden supply stores",
            ],
            [
                "key"   => "5262",
                "value" => "Online marketplaces",
            ],
            [
                "key"   => "5271",
                "value" => "Mobile home dealers",
            ],
            [
                "key"   => "5300",
                "value" => "Wholesale clubs",
            ],
            [
                "key"   => "5309",
                "value" => "Duty-free stores",
            ],
            [
                "key"   => "5310",
                "value" => "Discount stores",
            ],
            [
                "key"   => "5311",
                "value" => "Department stores",
            ],
            [
                "key"   => "5331",
                "value" => "Variety stores",
            ],
            [
                "key"   => "5399",
                "value" => "Miscellaneous general merchandise",
            ],
            [
                "key"   => "5411",
                "value" => "Grocery stores and supermarkets",
            ],
            [
                "key"   => "5422",
                "value" => "Freezer and locker meat provisioners",
            ],
            [
                "key"   => "5441",
                "value" => "Candy, nut, and confectionary stores",
            ],
            [
                "key"   => "5451",
                "value" => "Dairy products stores",
            ],
            [
                "key"   => "5462",
                "value" => "Bakeries",
            ],
            [
                "key"   => "5499",
                "value" => "Convenience stores and specialty markets",
            ],
            [
                "key"   => "5511",
                "value" => "Car and truck dealers, service, repairs and parts (new & used)",
            ],
            [
                "key"   => "5521",
                "value" => "Car and truck dealers, service, repairs and parts (used)",
            ],
            [
                "key"   => "5531",
                "value" => "Auto and home supply stores",
            ],
            [
                "key"   => "5532",
                "value" => "Automotive tire stores",
            ],
            [
                "key"   => "5533",
                "value" => "Automotive parts and accessories stores",
            ],
            [
                "key"   => "5541",
                "value" => "Service stations ",
            ],
            [
                "key"   => "5542",
                "value" => "Automated fuel dispensers",
            ],
            [
                "key"   => "5551",
                "value" => "Boat dealers",
            ],
            [
                "key"   => "5552",
                "value" => "Electric vehicle charging",
            ],
            [
                "key"   => "5561",
                "value" => "Camper, recreational and utility trailer dealers",
            ],
            [
                "key"   => "5571",
                "value" => "Motorcycle shops and dealers",
            ],
            [
                "key"   => "5592",
                "value" => "Motor homes dealers",
            ],
            [
                "key"   => "5598",
                "value" => "Snowmobile dealers",
            ],
            [
                "key"   => "5599",
                "value" => "Automotive, aircraft and farm equipment dealers",
            ],
            [
                "key"   => "5611",
                "value" => "Clothing and accessories stores",
            ],
            [
                "key"   => "5621",
                "value" => "Ready-to-wear stores",
            ],
            [
                "key"   => "5631",
                "value" => "Accessory and speciality shops",
            ],
            [
                "key"   => "5641",
                "value" => "Children's clothing stores",
            ],
            [
                "key"   => "5651",
                "value" => "Family clothing stores",
            ],
            [
                "key"   => "5655",
                "value" => "Sports and riding apparel stores",
            ],
            [
                "key"   => "5661",
                "value" => "Shoe stores",
            ],
            [
                "key"   => "5681",
                "value" => "Furriers and fur shops",
            ],
            [
                "key"   => "5691",
                "value" => "Clothing stores",
            ],
            [
                "key"   => "5697",
                "value" => "Tailors, seamstresses, mending and alterations",
            ],
            [
                "key"   => "5698",
                "value" => "Wig and toupee shops",
            ],
            [
                "key"   => "5699",
                "value" => "Apparel and accessory stores",
            ],
            [
                "key"   => "5712",
                "value" => "Home furnishings and equipment stores",
            ],
            [
                "key"   => "5713",
                "value" => "Floor covering stores",
            ],
            [
                "key"   => "5714",
                "value" => "Drapery, window covering and upholstery stores",
            ],
            [
                "key"   => "5718",
                "value" => "Fireplace and accessories stores",
            ],
            [
                "key"   => "5719",
                "value" => "Home furnishing specialty stores",
            ],
            [
                "key"   => "5722",
                "value" => "Household appliance stores",
            ],
            [
                "key"   => "5732",
                "value" => "Electronics stores",
            ],
            [
                "key"   => "5733",
                "value" => "Music stores",
            ],
            [
                "key"   => "5734",
                "value" => "Computer software stores",
            ],
            [
                "key"   => "5735",
                "value" => "Record shops",
            ],
            [
                "key"   => "5811",
                "value" => "Caterers",
            ],
            [
                "key"   => "5812",
                "value" => "Restaurants",
            ],
            [
                "key"   => "5813",
                "value" => "Bars, taverns, clubs",
            ],
            [
                "key"   => "5814",
                "value" => "Fast food",
            ],
            [
                "key"   => "5815",
                "value" => "Digital media, books, movies, music",
            ],
            [
                "key"   => "5816",
                "value" => "Digital games",
            ],
            [
                "key"   => "5817",
                "value" => "Digital apps",
            ],
            [
                "key"   => "5818",
                "value" => "Digital goods: large merchant",
            ],
            [
                "key"   => "5912",
                "value" => "Drug stores and pharmacies",
            ],
            [
                "key"   => "5921",
                "value" => "Package stores: beer, wine and liquor",
            ],
            [
                "key"   => "5931",
                "value" => "Secondhand stores",
            ],
            [
                "key"   => "5932",
                "value" => "Antique shops",
            ],
            [
                "key"   => "5933",
                "value" => "Pawn shops",
            ],
            [
                "key"   => "5935",
                "value" => "Wrecking and salvage yards",
            ],
            [
                "key"   => "5937",
                "value" => "Antique reproduction stores",
            ],
            [
                "key"   => "5940",
                "value" => "Bicycle shops ",
            ],
            [
                "key"   => "5941",
                "value" => "Sporting goods stores",
            ],
            [
                "key"   => "5942",
                "value" => "Bookstores",
            ],
            [
                "key"   => "5943",
                "value" => "Stationary, office and school supply stores",
            ],
            [
                "key"   => "5944",
                "value" => "Jewelry, watch, clock and silverware stores",
            ],
            [
                "key"   => "5945",
                "value" => "Hobby, toy and game shops",
            ],
            [
                "key"   => "5946",
                "value" => "Camera and photo supply stores",
            ],
            [
                "key"   => "5947",
                "value" => "Gift, card, novelty and souvenir shops",
            ],
            [
                "key"   => "5948",
                "value" => "Luggage and leather goods stores",
            ],
            [
                "key"   => "5949",
                "value" => "Sewing, needlework and fabric stores",
            ],
            [
                "key"   => "5950",
                "value" => "Glassware and crystal stores",
            ],
            [
                "key"   => "5960",
                "value" => "Direct marketing: insurance services",
            ],
            [
                "key"   => "5961",
                "value" => "Mail order houses ",
            ],
            [
                "key"   => "5962",
                "value" => "Direct marketing: travel services",
            ],
            [
                "key"   => "5963",
                "value" => "Door-to-door sales",
            ],
            [
                "key"   => "5964",
                "value" => "Catalog merchants",
            ],
            [
                "key"   => "5965",
                "value" => "Catalog and retail merchants",
            ],
            [
                "key"   => "5966",
                "value" => "Outbound telemarketing merchants",
            ],
            [
                "key"   => "5967",
                "value" => "Inbound telemarketing merchants",
            ],
            [
                "key"   => "5968",
                "value" => "Continuity/subscription merchants",
            ],
            [
                "key"   => "5969",
                "value" => "Direct marketers ",
            ],
            [
                "key"   => "5970",
                "value" => "Art supply and craft stores",
            ],
            [
                "key"   => "5971",
                "value" => "Art dealers and galleries",
            ],
            [
                "key"   => "5972",
                "value" => "Stamp and coin stores",
            ],
            [
                "key"   => "5973",
                "value" => "Religious goods stores",
            ],
            [
                "key"   => "5974",
                "value" => "Rubber stamp stores",
            ],
            [
                "key"   => "5975",
                "value" => "Hearing aids: sales, service and supplies",
            ],
            [
                "key"   => "5976",
                "value" => "Orthopedic goods and prosthetic devices",
            ],
            [
                "key"   => "5977",
                "value" => "Cosmetic stores",
            ],
            [
                "key"   => "5978",
                "value" => "Typewriter stores: sales, rental and service",
            ],
            [
                "key"   => "5983",
                "value" => "Fuel dealers",
            ],
            [
                "key"   => "5992",
                "value" => "Florists",
            ],
            [
                "key"   => "5993",
                "value" => "Cigar stores ",
            ],
            [
                "key"   => "5994",
                "value" => "News dealers and newsstands",
            ],
            [
                "key"   => "5995",
                "value" => "Pet shops, pet foods and supply stores",
            ],
            [
                "key"   => "5996",
                "value" => "Swimming pools: sales and service",
            ],
            [
                "key"   => "5997",
                "value" => "Electric razor stores: sales and service",
            ],
            [
                "key"   => "5998",
                "value" => "Tent and awning shops",
            ],
            [
                "key"   => "5999",
                "value" => "Miscellaneous and specialty retail stores",
            ],
            [
                "key"   => "6010",
                "value" => "Financial institutions: manual cash disbursements",
            ],
            [
                "key"   => "6011",
                "value" => "Financial institutions: automated cash disbursements",
            ],
            [
                "key"   => "6012",
                "value" => "Financial institutions: merchandise and services",
            ],
            [
                "key"   => "6050",
                "value" => "Quasi cash: member financial",
            ],
            [
                "key"   => "6051",
                "value" => "Foreign currency, money orders, debt repayment",
            ],
            [
                "key"   => "6211",
                "value" => "Security brokers and dealers",
            ],
            [
                "key"   => "6300",
                "value" => "Insurance sales, underwriting and premiums",
            ],
            [
                "key"   => "6381",
                "value" => "Insurance: premiums",
            ],
            [
                "key"   => "6399",
                "value" => "Insurance",
            ],
            [
                "key"   => "6513",
                "value" => "Real estate agents and managers",
            ],
            [
                "key"   => "6529",
                "value" => "Remote stored value load: member financial institution",
            ],
            [
                "key"   => "6530",
                "value" => "Remote stored value load: merchant",
            ],
            [
                "key"   => "6531",
                "value" => "Payment service provider",
            ],
            [
                "key"   => "6532",
                "value" => "Payment transaction: member financial institution",
            ],
            [
                "key"   => "6533",
                "value" => "Payment transaction: merchant",
            ],
            [
                "key"   => "6535",
                "value" => "Value purchase: member financial institution",
            ],
            [
                "key"   => "6536",
                "value" => "Moneysend intracountry",
            ],
            [
                "key"   => "6537",
                "value" => "Moneysend intercountry",
            ],
            [
                "key"   => "6538",
                "value" => "Moneysend funding",
            ],
            [
                "key"   => "6540",
                "value" => "Stored value card purchase",
            ],
            [
                "key"   => "6555",
                "value" => "Mastercard-initiated rebate/reward",
            ],
            [
                "key"   => "6611",
                "value" => "Overpayments",
            ],
            [
                "key"   => "6760",
                "value" => "Savings bonds",
            ],
            [
                "key"   => "7011",
                "value" => "Lodging: hotels, motels and resorts",
            ],
            [
                "key"   => "7012",
                "value" => "Timeshares",
            ],
            [
                "key"   => "7032",
                "value" => "Sporting and recreational camps",
            ],
            [
                "key"   => "7033",
                "value" => "Trailer parks and campgrounds",
            ],
            [
                "key"   => "7210",
                "value" => "Laundry, cleaning and garment services",
            ],
            [
                "key"   => "7211",
                "value" => "Laundries: family and commercial",
            ],
            [
                "key"   => "7216",
                "value" => "Dry cleaners",
            ],
            [
                "key"   => "7217",
                "value" => "Carpet and upholstery cleaning",
            ],
            [
                "key"   => "7221",
                "value" => "Photo studios",
            ],
            [
                "key"   => "7230",
                "value" => "Beauty shops and barber shops",
            ],
            [
                "key"   => "7251",
                "value" => "Shoe repair, shoe shine and hat cleaning shops",
            ],
            [
                "key"   => "7261",
                "value" => "Funeral services ",
            ],
            [
                "key"   => "7273",
                "value" => "Dating services",
            ],
            [
                "key"   => "7276",
                "value" => "Tax preparation services",
            ],
            [
                "key"   => "7277",
                "value" => "Counseling services",
            ],
            [
                "key"   => "7278",
                "value" => "Buying and shopping services and clubs",
            ],
            [
                "key"   => "7280",
                "value" => "Hospital patient personal funds withdrawl accounts",
            ],
            [
                "key"   => "7295",
                "value" => "Babysitting services",
            ],
            [
                "key"   => "7296",
                "value" => "Clothing rental ",
            ],
            [
                "key"   => "7297",
                "value" => "Massage and spa services",
            ],
            [
                "key"   => "7298",
                "value" => "Health and beauty spas",
            ],
            [
                "key"   => "7299",
                "value" => "Miscellaneous personal services",
            ],
            [
                "key"   => "7311",
                "value" => "Advertising services",
            ],
            [
                "key"   => "7321",
                "value" => "Consumer credit reporting agencies",
            ],
            [
                "key"   => "7322",
                "value" => "Debt collection agencies",
            ],
            [
                "key"   => "7332",
                "value" => "Blueprinting and photocopying services",
            ],
            [
                "key"   => "7333",
                "value" => "Commercial photography, art and graphics",
            ],
            [
                "key"   => "7338",
                "value" => "Quick-copy and reproduction services",
            ],
            [
                "key"   => "7339",
                "value" => "Stenographic services",
            ],
            [
                "key"   => "7342",
                "value" => "Exterminating and disenfecting services",
            ],
            [
                "key"   => "7349",
                "value" => "Cleaning, maintenance and janitorial services",
            ],
            [
                "key"   => "7361",
                "value" => "Employment agencies, temporary help services",
            ],
            [
                "key"   => "7372",
                "value" => "Computer programming and design services",
            ],
            [
                "key"   => "7375",
                "value" => "Information retrieval services",
            ],
            [
                "key"   => "7379",
                "value" => "Computer maintenance, repair and services",
            ],
            [
                "key"   => "7392",
                "value" => "Management, consulting, and public relations",
            ],
            [
                "key"   => "7393",
                "value" => "Detective, protective and security services",
            ],
            [
                "key"   => "7394",
                "value" => "Equipment, furniture and appliance rental and leasing",
            ],
            [
                "key"   => "7395",
                "value" => "Photo developing",
            ],
            [
                "key"   => "7399",
                "value" => "Business services",
            ],
            [
                "key"   => "742",
                "value" => "Veterinary services",
            ],
            [
                "key"   => "7511",
                "value" => "Truck stop transactions",
            ],
            [
                "key"   => "7512",
                "value" => "Auto rental agency",
            ],
            [
                "key"   => "7513",
                "value" => "Truck and utility trailer rental",
            ],
            [
                "key"   => "7519",
                "value" => "Motor home and recreational vehicle rental",
            ],
            [
                "key"   => "7523",
                "value" => "Parking lots and garages",
            ],
            [
                "key"   => "7524",
                "value" => "Express payment services: parking/garages",
            ],
            [
                "key"   => "7531",
                "value" => "Automotive and body repair shops",
            ],
            [
                "key"   => "7534",
                "value" => "Tire retreading and repair shops",
            ],
            [
                "key"   => "7535",
                "value" => "Automotive paint shops",
            ],
            [
                "key"   => "7538",
                "value" => "Automotive repair shops",
            ],
            [
                "key"   => "7542",
                "value" => "Car washes",
            ],
            [
                "key"   => "7549",
                "value" => "Towing services",
            ],
            [
                "key"   => "7622",
                "value" => "Electronics repair shops",
            ],
            [
                "key"   => "7623",
                "value" => "Air conditioning and refrigeration repair shops",
            ],
            [
                "key"   => "7629",
                "value" => "Electrical and small appliance repair shops",
            ],
            [
                "key"   => "763",
                "value" => "Agricultural cooperatives",
            ],
            [
                "key"   => "7631",
                "value" => "Watch, clock and jewelry repair ",
            ],
            [
                "key"   => "7641",
                "value" => "Furniture reupholstry, repair and refinishing",
            ],
            [
                "key"   => "7692",
                "value" => "Welding services",
            ],
            [
                "key"   => "7699",
                "value" => "Miscellaneous repair shops and related services",
            ],
            [
                "key"   => "780",
                "value" => "Landscaping and horticultural services",
            ],
            [
                "key"   => "7800",
                "value" => "Government-owned lotteries",
            ],
            [
                "key"   => "7801",
                "value" => "Government-licensed online casinos ",
            ],
            [
                "key"   => "7802",
                "value" => "Government-licensed horse/dog racing",
            ],
            [
                "key"   => "7829",
                "value" => "Movie and video: production and distribution",
            ],
            [
                "key"   => "7832",
                "value" => "Movie theaters",
            ],
            [
                "key"   => "7833",
                "value" => "Express payment service: movie theaters",
            ],
            [
                "key"   => "7841",
                "value" => "DVD rental stores",
            ],
            [
                "key"   => "7911",
                "value" => "Dance halls, studios and schools",
            ],
            [
                "key"   => "7922",
                "value" => "Ticket agencies and theatrical producers ",
            ],
            [
                "key"   => "7929",
                "value" => "Bands, orchestras and entertainers",
            ],
            [
                "key"   => "7932",
                "value" => "Billiard and pool halls",
            ],
            [
                "key"   => "7933",
                "value" => "Bowling alleys",
            ],
            [
                "key"   => "7941",
                "value" => "Commercial sports",
            ],
            [
                "key"   => "7991",
                "value" => "Tourist attractions and exhibits",
            ],
            [
                "key"   => "7992",
                "value" => "Public golf courses",
            ],
            [
                "key"   => "7993",
                "value" => "Video amusement game supplies",
            ],
            [
                "key"   => "7994",
                "value" => "Video game arcades ",
            ],
            [
                "key"   => "7995",
                "value" => "Gambling transactions",
            ],
            [
                "key"   => "7996",
                "value" => "Amusement parks, circuses, carnivals, fortune tellers",
            ],
            [
                "key"   => "7997",
                "value" => "Membership clubs, country clubs, private golf courses",
            ],
            [
                "key"   => "7998",
                "value" => "Aquariums and zoos",
            ],
            [
                "key"   => "7999",
                "value" => "Recreation services ",
            ],
            [
                "key"   => "8011",
                "value" => "Doctors and physicians ",
            ],
            [
                "key"   => "8021",
                "value" => "Dentists and orthodontists",
            ],
            [
                "key"   => "8031",
                "value" => "Osteopaths",
            ],
            [
                "key"   => "8041",
                "value" => "Chiropractors",
            ],
            [
                "key"   => "8042",
                "value" => "Optometrists and opthamologists",
            ],
            [
                "key"   => "8043",
                "value" => "Opticians",
            ],
            [
                "key"   => "8044",
                "value" => "Optical goods and eyeglasses",
            ],
            [
                "key"   => "8049",
                "value" => "Podiatrists and chiropodists",
            ],
            [
                "key"   => "8050",
                "value" => "Nursing and personal care facilities",
            ],
            [
                "key"   => "8062",
                "value" => "Hospitals",
            ],
            [
                "key"   => "8071",
                "value" => "Medical and dental laboratories",
            ],
            [
                "key"   => "8099",
                "value" => "Medical services and health practitioners ",
            ],
            [
                "key"   => "8111",
                "value" => "Legal services and attorneys",
            ],
            [
                "key"   => "8211",
                "value" => "Elementary and secondary schools",
            ],
            [
                "key"   => "8220",
                "value" => "Colleges, universities and professional schools",
            ],
            [
                "key"   => "8241",
                "value" => "Correspondence schools",
            ],
            [
                "key"   => "8244",
                "value" => "Business and secretarial schools",
            ],
            [
                "key"   => "8249",
                "value" => "Trade and vocational schools",
            ],
            [
                "key"   => "8299",
                "value" => "Schools and educational services",
            ],
            [
                "key"   => "8351",
                "value" => "Child care services",
            ],
            [
                "key"   => "8398",
                "value" => "Charitable and social service organizations",
            ],
            [
                "key"   => "8641",
                "value" => "Civic, social and fraternal associations",
            ],
            [
                "key"   => "8651",
                "value" => "Political organizations",
            ],
            [
                "key"   => "8661",
                "value" => "Religious organizations",
            ],
            [
                "key"   => "8675",
                "value" => "Automobile associations",
            ],
            [
                "key"   => "8699",
                "value" => "Membership organizations",
            ],
            [
                "key"   => "8734",
                "value" => "Testing laboratories",
            ],
            [
                "key"   => "8911",
                "value" => "Engineering, architectural and surveying services",
            ],
            [
                "key"   => "8931",
                "value" => "Accounting, auditing and bookkeeping services",
            ],
            [
                "key"   => "8999",
                "value" => "Professional services",
            ],
            [
                "key"   => "9034",
                "value" => "I-Purchasing ",
            ],
            [
                "key"   => "9045",
                "value" => "Intra-government purchases: government only",
            ],
            [
                "key"   => "9211",
                "value" => "Court costs including alimony and child support",
            ],
            [
                "key"   => "9222",
                "value" => "Fines",
            ],
            [
                "key"   => "9223",
                "value" => "Bail and bond payments",
            ],
            [
                "key"   => "9311",
                "value" => "Tax payments",
            ],
            [
                "key"   => "9399",
                "value" => "Government services",
            ],
            [
                "key"   => "9401",
                "value" => "I-Purchasing",
            ],
            [
                "key"   => "9402",
                "value" => "Postal services",
            ],
            [
                "key"   => "9405",
                "value" => "U.S. Federal Government agencies or departments",
            ],
            [
                "key"   => "9406",
                "value" => "Government-owned lotteries",
            ],
            [
                "key"   => "9700",
                "value" => "Automated referral service",
            ],
            [
                "key"   => "9701",
                "value" => "Visa credential server",
            ],
            [
                "key"   => "9702",
                "value" => "GCAS emergency services ",
            ],
            [
                "key"   => "9751",
                "value" => "U.K. supermarkets",
            ],
            [
                "key"   => "9752",
                "value" => "U.K. petrol stations",
            ],
            [
                "key"   => "9754",
                "value" => "Gambling, horse racing, dog racing, state lottery",
            ],
            [
                "key"   => "9950",
                "value" => "Intra-company purchases",
            ],
        ];

        $value = null;

        foreach ($mappings as $mapping) {
            if ($mapping['key'] == $code) {
                $value = $mapping['value'];
            }
        }

        if ($value === null) {
            $this->logger->notice("merchant for {$code} not found");
        }

        return $value;
    }

    public function isActiveTab(AccountOptions $options): bool
    {
        return true;
    }
}
