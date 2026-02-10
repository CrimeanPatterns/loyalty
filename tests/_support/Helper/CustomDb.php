<?php
namespace Helper;

use Codeception\Lib\ModuleContainer;
use Codeception\Module\Db;
use Symfony\Component\Yaml\Yaml;
use Codeception\Lib\Driver\Db as Driver;

class CustomDb extends Db {

    public function _setConfig($config)
    {
        $params = Yaml::parse(file_get_contents(__DIR__ . '/../../../app/config/parameters.yml'))['parameters'];
        $config['dsn'] = 'mysql:host=' . $params['database_host'] . ';dbname=' . $params['database_name'];
        $config['user'] = $params['database_user'];
        $config['password'] = 'loyalty';
        parent::_setConfig($config);
    }

    private function getPrimaryColumn($tableName){
        $st = $this->query("DESCRIBE `{$tableName}`");
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            if('PRI' === $row['Key']) {
                return $row['Field'];
            }
        }
        throw new \RuntimeException('Unable to find primary column for table \'' . $tableName . '\'');
    }

    /**
     * @param string|array $tableCriteria
     * @param array|int $rowCriteria
     * @param string $column
     */
    public function haveInsertedInDatabase($tableCriteria, $rowCriteria)
    {
        if (is_array($rowCriteria)) {
            $this->assertTrue(is_array($tableCriteria));
            $this->assertNotEmpty($rowCriteria);
            $tableName = key($tableCriteria);
            $tableKeyColumn = $tableCriteria[$tableName];
            $tableCriteria = $tableName;
            $rowCriteria = $this->grabFromDatabase($tableName, $tableKeyColumn, $rowCriteria);
        }

        $this->insertedIds[] = ['table' => $tableCriteria, 'id' => $rowCriteria];

    }

    /**
     * @param string $query
     * @return int affected rows count
     */
    public function executeQuery($query)
    {
        return $this->catchGoneAway(function() use($query){ return $this->driver->getDbh()->exec($query); });
    }

    /**
     * @param string $query
     * @return \PDOStatement
     */
    public function query($query, $params = null)
    {
        return $this->catchGoneAway(function() use($query, $params){
            $statement = $this->driver->getDbh()->prepare($query, [\PDO::ATTR_ERRMODE =>\PDO::ERRMODE_EXCEPTION]);
            $statement->execute($params);
            return $statement;
        });
    }

    public function getLastInsertId()
    {
        return $this->catchGoneAway(function(){ return $this->driver->getDbh()->lastInsertId(); });
    }

	public function shouldHaveInDatabase($tableName, array $values){
		$key = $this->getPrimaryColumn($tableName);
		$existing = $this->grabFromDatabase($tableName, $key, $values);
		if(empty($existing))
			return $this->haveInDatabase($tableName, $values);
        return $existing;
	}

    public function grabCountFromDatabase($table, $criteria = [])
    {
        return $this->catchGoneAway(function() use($table, $criteria){ return $this->proceedSeeInDatabase($table, 'count(*)', $criteria); });
    }

    private function catchGoneAway(callable $function){
        $processException = function(\Exception $e) use ($function){
            if(stripos($e->getMessage(), 'server has gone away') !== false) {
                $this->driver = Driver::create($this->config['dsn'], $this->config['user'], $this->config['password']);
                $this->dbh = $this->driver->getDbh();
                return call_user_func($function);
            }
            else
                throw $e;
        };
        try {
            // MySql gone away will be issued as warning, despite of  $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            // in Driver/Db, so we will supress warnings
            return @call_user_func($function);
        }
        catch(\PDOException $e){
            return $processException($e);
        }
        catch(\PHPUnit_Framework_Exception $e){
            return $processException($e);
        }
    }

    protected function proceedSeeInDatabase($table, $column, $criteria)
    {
        return $this->catchGoneAway(function() use($table, $column, $criteria){ return parent::proceedSeeInDatabase($table, $column, $criteria); });
    }

    public function haveInDatabase($table, array $data)
    {
        return $this->catchGoneAway(function() use($table, $data){ return parent::haveInDatabase($table, $data); });
    }


}