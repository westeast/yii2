<?php
/**
 * QueryBuilder class file.
 *
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\db\mysql;

use yii\db\Exception;
use yii\base\InvalidCallException;

/**
 * QueryBuilder is the query builder for MySQL databases.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class QueryBuilder extends \yii\db\QueryBuilder
{
	/**
	 * @var array mapping from abstract column types (keys) to physical column types (values).
	 */
	public $typeMap = array(
		Schema::TYPE_PK => 'int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY',
		Schema::TYPE_STRING => 'varchar(255)',
		Schema::TYPE_TEXT => 'text',
		Schema::TYPE_SMALLINT => 'smallint(6)',
		Schema::TYPE_INTEGER => 'int(11)',
		Schema::TYPE_BIGINT => 'bigint(20)',
		Schema::TYPE_FLOAT => 'float',
		Schema::TYPE_DECIMAL => 'decimal',
		Schema::TYPE_DATETIME => 'datetime',
		Schema::TYPE_TIMESTAMP => 'timestamp',
		Schema::TYPE_TIME => 'time',
		Schema::TYPE_DATE => 'date',
		Schema::TYPE_BINARY => 'blob',
		Schema::TYPE_BOOLEAN => 'tinyint(1)',
		Schema::TYPE_MONEY => 'decimal(19,4)',
	);

	/**
	 * Builds a SQL statement for renaming a column.
	 * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
	 * @param string $oldName the old name of the column. The name will be properly quoted by the method.
	 * @param string $newName the new name of the column. The name will be properly quoted by the method.
	 * @return string the SQL statement for renaming a DB column.
	 * @throws Exception
	 */
	public function renameColumn($table, $oldName, $newName)
	{
		$quotedTable = $this->connection->quoteTableName($table);
		$row = $this->connection->createCommand('SHOW CREATE TABLE ' . $quotedTable)->queryRow();
		if ($row === false) {
			throw new Exception("Unable to find '$oldName' in table '$table'.");
		}
		if (isset($row['Create Table'])) {
			$sql = $row['Create Table'];
		} else {
			$row = array_values($row);
			$sql = $row[1];
		}
		if (preg_match_all('/^\s*`(.*?)`\s+(.*?),?$/m', $sql, $matches)) {
			foreach ($matches[1] as $i => $c) {
				if ($c === $oldName) {
					return "ALTER TABLE $quotedTable CHANGE "
						. $this->connection->quoteColumnName($oldName) . ' '
						. $this->connection->quoteColumnName($newName) . ' '
						. $matches[2][$i];
				}
			}
		}
		// try to give back a SQL anyway
		return "ALTER TABLE $quotedTable CHANGE "
			. $this->connection->quoteColumnName($oldName) . ' '
			. $this->connection->quoteColumnName($newName);
	}

	/**
	 * Builds a SQL statement for dropping a foreign key constraint.
	 * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
	 * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
	 * @return string the SQL statement for dropping a foreign key constraint.
	 */
	public function dropForeignKey($name, $table)
	{
		return 'ALTER TABLE ' . $this->connection->quoteTableName($table)
			. ' DROP FOREIGN KEY ' . $this->connection->quoteColumnName($name);
	}

	/**
	 * Creates a SQL statement for resetting the sequence value of a table's primary key.
	 * The sequence will be reset such that the primary key of the next new row inserted
	 * will have the specified value or 1.
	 * @param string $tableName the name of the table whose primary key sequence will be reset
	 * @param mixed $value the value for the primary key of the next new row inserted. If this is not set,
	 * the next new row's primary key will have a value 1.
	 * @return string the SQL statement for resetting sequence
	 * @throws InvalidCallException if the table does not exist or there is no sequence associated with the table.
	 */
	public function resetSequence($tableName, $value = null)
	{
		$table = $this->connection->getTableSchema($tableName);
		if ($table !== null && $table->sequenceName !== null) {
			$tableName = $this->connection->quoteTableName($tableName);
			if ($value === null) {
				$key = reset($table->primaryKey);
				$value = $this->connection->createCommand("SELECT MAX(`$key`) FROM $tableName")->queryScalar() + 1;
			} else {
				$value = (int)$value;
			}
			return "ALTER TABLE $tableName AUTO_INCREMENT=$value";
		} elseif ($table === null) {
			throw new InvalidCallException("Table not found: $tableName");
		} else {
			throw new InvalidCallException("There is not sequence associated with table '$tableName'.'");
		}
	}

	/**
	 * Builds a SQL statement for enabling or disabling integrity check.
	 * @param boolean $check whether to turn on or off the integrity check.
	 * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
	 * @return string the SQL statement for checking integrity
	 */
	public function checkIntegrity($check = true, $schema = '')
	{
		return 'SET FOREIGN_KEY_CHECKS=' . ($check ? 1 : 0);
	}
}