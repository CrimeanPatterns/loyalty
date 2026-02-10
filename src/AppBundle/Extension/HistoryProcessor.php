<?php

namespace AppBundle\Extension;

use AppBundle\Model\Resources\History;
use AppBundle\Model\Resources\HistoryColumn;
use AppBundle\Model\Resources\HistoryField;
use AppBundle\Model\Resources\HistoryRow;
use AppBundle\Model\Resources\HistoryState;
use AppBundle\Model\Resources\HistoryState\StructureVersion1;
use AppBundle\Model\Resources\RequestItemHistory;
use AppBundle\Model\Resources\SubAccountHistory;
use AwardWallet\ExtensionWorker\ParseHistoryOptions;
use Doctrine\DBAL\Connection;
use JMS\Serializer\Serializer;
use Psr\Log\LoggerInterface;

class HistoryProcessor
{

    /** @var Serializer */
    private $serializer;
    /** @var Connection */
    private $connection;
    /** @var string */
    private $aesKey;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Serializer $serializer, Connection $connection, $aesKey, LoggerInterface $logger)
    {
        $this->serializer = $serializer;
        $this->connection = $connection;
        $this->aesKey = $aesKey;
        $this->logger = $logger;
    }

    /**
     * Filling history properties before parsing
     * @returns ParseHistoryOptions|null - null in case we do not want to parse history
     */
    public function getParseHistoryOptions(RequestItemHistory $history, $provider) : ?ParseHistoryOptions
    {
        if (!in_array($history->getRange(), History::HISTORY_RANGES)) {
            $this->logger->info("will not check history, range is unknown: {$history->getRange()}");

            return null;
        }

        $wantHistory = $this->providerCanCheckHistory($provider);

        if ($wantHistory === false) {
            $this->logger->info("this provider could not check history");

            return null;
        }

        if ($history->getRange() === History::HISTORY_COMPLETE) {
            $this->logger->info("requested complete history range, ignoring request history state");
            return ParseHistoryOptions::complete();
        }

        if (empty($history->getState())) {
            $this->logger->info("no history state in the request");
            return ParseHistoryOptions::complete();
        }

        /** @var StructureVersion1 $state */
        $state = $this->decryptHistoryState($history->getState());
        if (!$state instanceof HistoryState) // структура битая, чего то не хватает
        {
            $this->logger->info("invalid history state structure, ignoring request history state");
            return ParseHistoryOptions::complete();
        }

        if ($state->getType() < HistoryState::MINIMAL_VERSION_NUMBER) // structureVersion не совпадает с поддерживаемым, на будущее, можем увеличить эту переменную, чтобы откинуть все старые состояния
        {
            $this->logger->info("received history state version: {$state->getType()}, minimum is " . HistoryState::MINIMAL_VERSION_NUMBER . ", ignoring");
            return ParseHistoryOptions::complete();
        }

        $cacheVersion = $this->getCacheVersion($provider);
        if ($cacheVersion !== $state->getCacheVersion()) // cacheVersion не совпадает с Provider.CacheVersion (Рома периодически сбрасывает таким образом кэш)
        {
            $this->logger->info("received history cache version: {$state->getCacheVersion()}, actual is {$cacheVersion}, ignoring");
            return ParseHistoryOptions::complete();
        }

        $this->logger->info("received valid history state version: {$state->getType()}, current version is: $cacheVersion, range: {$history->getRange()}");
        $startDate = null;
        $subAccountDates = [];

        if ($state->getLastDate() instanceof \DateTime) {
            $startDate = $state->getLastDate();
            $this->logger->info("request history start date: " . $state->getLastDate()->format("Y-m-d"));
        }

        if (!empty($state->getSubAccountLastDates())) {
            foreach ($state->getSubAccountLastDates() as $code => $dt) {
                $this->logger->info("request history start date for subaccount {$code}: " . $dt->format("Y-m-d"));
                $subAccountDates[$code] = $dt;
            }
        }

        $strict = true;
        if ($history->getRange() === History::HISTORY_INCREMENTAL2) {
            $strict = false;
            $this->logger->info("incremental2, set strictHistoryStartDate to false");
        }

        return new ParseHistoryOptions($startDate, $subAccountDates, $strict);
    }

    /**
     * prepare History object for response
     * @param \TAccountChecker $checker
     * @return History
     */
    public function prepareHistoryResults(\TAccountChecker $checker)
    {
        $checkerHistoryColumns = $checker->GetHistoryColumns();
        $partner = $checker->AccountFields['Partner'] ?? "";

        // filter hidden columns
        if ('awardwallet' !== $partner) {
            $hiddenCols = $checker->GetHiddenHistoryColumns();
            array_walk($hiddenCols, function ($col) use (&$checkerHistoryColumns) {
                unset($checkerHistoryColumns[$col]);
            });
        }

        if ($checker->WantHistory !== true || empty($checkerHistoryColumns)) {
            $this->logger->info("will not return history, wantHistory: " . json_encode($checker->WantHistory) . ", columns: " . json_encode(empty($checkerHistoryColumns)));
            return null;
        }

        $historyColumns = [];
        foreach ($checkerHistoryColumns as $name => $kind) {
            $historyColumns[$name] = HistoryColumn::createFromTAccountCheckerDefinition($name, $kind);
        }

        if (!isset($checker->History)) {
            $checker->History = [];
        }
        if (!is_array($checker->History)) {
            $this->logger->warning($msg = 'History must be of the type array or null, ' . gettype($checker->History) . ' given');
            $checker->History = [];
        }

        [
            'rows' => $accountHistoryRows,
            'lastDate' => $accountHistoryLastDate
        ] = $this->processCheckerAccountHistory($checker->History, $historyColumns, "main account", $checker->strictHistoryStartDate ? $checker->HistoryStartDate : null);

        $subAccHistory = [];
        $subAccLastDate = [];
        if (!empty($checker->Properties["SubAccounts"])) {
            foreach ($checker->Properties["SubAccounts"] as $subAccRow) {
                if (!isset($subAccRow['Code']) || !isset($subAccRow['HistoryRows']) || !is_array($subAccRow['HistoryRows'])) {
                    continue;
                }

                [
                    'rows' => $subAccountHistoryRows,
                    'lastDate' => $subAccountHistoryLastDate
                ] = $this->processCheckerAccountHistory($subAccRow['HistoryRows'], $historyColumns, "subaccount {$subAccRow['Code']}", $checker->strictHistoryStartDate && array_key_exists($subAccRow['Code'], $checker->historyStartDates) ? $checker->historyStartDates[$subAccRow['Code']] : null);
                if (empty($subAccountHistoryRows)) {
                    continue;
                }

                $subAccLastDate[$subAccRow['Code']] = $subAccountHistoryLastDate;
                $subAccHistory[] = new SubAccountHistory($subAccRow['Code'], $subAccountHistoryRows);
            }
        }

        $cacheVersion = $this->getCacheVersion($checker->AccountFields['ProviderCode']);
        $stateCls = HistoryState::ACTUAL_VERSION;
        /** @var StructureVersion1 $state */
        $state = new $stateCls();
        $state->setCacheVersion($cacheVersion)
            ->setLastDate($accountHistoryLastDate)
            ->setSubAccountLastDates(!empty($subAccLastDate) ? $subAccLastDate : null);

        if ($checker->HistoryStartDate === null && count($checker->historyStartDates) === 0) {
            $range = History::HISTORY_COMPLETE;
        } else {
            $range = History::HISTORY_INCREMENTAL;
        }

        if ($range === History::HISTORY_INCREMENTAL && $checker->strictHistoryStartDate === false) {
            $range = History::HISTORY_INCREMENTAL2;
        }

        $history = new History();
        $history->setRows($accountHistoryRows)
            ->setState($this->cryptHistoryState($state))
            ->setRange($range)
            ->setSubAccounts(!empty($subAccHistory) ? $subAccHistory : null);

        if (empty($history->getRows()) && empty($history->getSubAccounts())) {
            $this->logger->info("no history to return");
            return null;
        }

        $this->logger->info("returning history with cacheVersion: {$cacheVersion}, last date: " . ($accountHistoryLastDate === null ? "null" : $accountHistoryLastDate->format("Y-m-d")) . ", subAccount dates: " . json_encode($subAccLastDate) . ", rows: " . ( $accountHistoryRows === null ? "null" : count($accountHistoryRows)) . ", range: " . $history->getRange() . ", subAccounts: " . ($history->getSubAccounts() === null ? "null" : count($history->getSubAccounts())));

        return $history;
    }

    /**
     * Processing account history from parsing results
     * @param array $rows
     * @param HistoryColumn[] $historyColumns
     * @return array ["rows" => HistoryRow[], "lastDate" => <'Y-m-d'>]
     */
    private function processCheckerAccountHistory(array $rows, array $historyColumns, string $accountName, ?int $minAllowedHistoryDate)
    {
        $result = ['rows' => null, 'lastDate' => null];
        if (empty($rows)) {
            return $result;
        }

        $historyResult = [];
        $lastDate = 0;
        foreach ($rows as $row) {
            $isSetPostingDate = false;
            $historyFields = [];
            foreach ($row as $keyField => $valueField) {
                if (!isset($historyColumns[$keyField]) && $keyField !== 'Position') {
                    continue;
                }

                $columnCode = $keyField === 'Position' ? 'Position' : $historyColumns[$keyField]->getKind();

                if (trim($valueField) === '') {
                    $valueField = null;
                }

                switch ($historyColumns[$keyField]->getType()) {
                    case 'decimal':
                    {
                        $value = filterBalance($valueField, true);
                        break;
                    }
                    case 'integer':
                    {
                        $value = filterBalance($valueField, false);
                        break;
                    }
                    case 'date':
                    {
                        $valueField = (int)$valueField;

                        if ($valueField === 0) {
                            continue 2;
                        }

                        $value = (new \DateTime())->setTimestamp($valueField)->format('Y-m-d');

                        if ($columnCode === 'PostingDate') {
                            if ($minAllowedHistoryDate !== null && $valueField < $minAllowedHistoryDate) {
                                $this->logger->notice("discarding past history row, min allowed: " . date("Y-m-d", $minAllowedHistoryDate) . ", got: " . date("Y-m-d", $valueField));
                                continue 2;
                            }
                            $isSetPostingDate = true;
                            $lastDate = $valueField > $lastDate ? $valueField : $lastDate;
                        }

                        break;
                    }
                    default:
                    {
                        $value = $valueField;
                    }
                }

                $historyFields[] = new HistoryField($keyField, iconv("UTF-8", "UTF-8//IGNORE", $value));
            }
            if ($isSetPostingDate) {
                $historyResult[] = new HistoryRow($historyFields);
            }
        }

        $result['rows'] = $historyResult;
        $result['lastDate'] = $lastDate !== 0 ? (new \DateTime())->setTimestamp($lastDate) : null;

        $this->logger->info("returning " . count($result['rows']) . " history rows for {$accountName}, last date: " . ($result['lastDate'] === null ? "null" : $result['lastDate']->format("Y-m-d")));

        return $result;
    }

    /**
     * returns provider history CacheVersion
     * @param string $provider
     * @return int
     */
    private function getCacheVersion(string $provider): int
    {
        $providerRow = $this->getProviderRow($provider);
        return intval($providerRow['CacheVersion']);
    }

    /**
     * @param string $provider
     * @return bool
     */
    private function providerCanCheckHistory(string $provider): bool
    {
        $providerRow = $this->getProviderRow($provider);
        return intval($providerRow['CanCheckHistory']) === 1;
    }

    private function getProviderRow(string $provider): array
    {
        $sql = <<<SQL
              SELECT CacheVersion, CanCheckHistory FROM Provider WHERE Code = :CODE
SQL;
        return $this->connection->executeQuery($sql, [':CODE' => $provider])->fetch();
    }

    /**
     * @param HistoryState $state
     * @return string
     */
    private function cryptHistoryState(HistoryState $state)
    {
        $stateJson = $this->serializer->serialize($state, 'json');
        return base64_encode(AESEncode($stateJson, $this->aesKey));
    }

    /**
     * @param string $data
     * @return HistoryState
     */
    private function decryptHistoryState($data)
    {
        $stateJson = AESDecode(base64_decode($data), $this->aesKey);
        try {
            return $this->serializer->deserialize($stateJson, HistoryState::class, 'json');
        } catch (\JMS\Serializer\Exception\RuntimeException $exception) {
            $this->logger->warning("failed to decode history state: " . $exception->getMessage());
            return null;
        }
    }

}