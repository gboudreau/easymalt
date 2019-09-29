<?php

namespace EasyMalt;

use Exception;
use stdClass;

class DBQueryBuilder
{
    private $_op = NULL;
    private const OP_SELECT = 1;
    private const OP_UPDATE = 2;
    private const OP_INSERT = 3;
    private const OP_DELETE = 4;

    private $_tableRefs = [];
    private $_params = [];

    private $_msTarget = ''; // Auto-select Master-Slave
    private const MS_SLAVE = '/*ms=slave*/ ';
    private const MS_MASTER = '/*ms=master*/ ';

    private $_cached = FALSE;

    // SELECT
    private $_selectExpr = [];
    private $_joins = [];
    private $_whereConditions = [];
    private $_groupBy = [];
    private $_havingConditions = [];
    private $_orderBy = [];
    private $_limit = '';

    // INSERT, UPDATE
    private $_colValues = [];
    private $_onDuplicateKeyUpdateColumns = [];
    private $_option = '';

    public function __construct() {
    }

    public function onMaster() : self {
        $this->_msTarget = static::MS_MASTER;
        return $this;
    }

    public function onSlave() : self {
        $this->_msTarget = static::MS_SLAVE;
        return $this;
    }

    /**
     * Save to cache the values, before returning them. Or, if the cache already contains a hit, return the cached values.
     * Usage: $builder->select()->from()->where()->cached()->getFirst|getFirstValue|getAll|getAllValues()
     *
     * @param bool $cache_enabled Specify FALSE to disable the cache (default). Specify TRUE, or pass no parameters, to enable the cache.
     *
     * @return DBQueryBuilder
     */
    public function cached(bool $cache_enabled = TRUE) : self {
        $this->_cached = $cache_enabled;
        return $this;
    }

    /**
     * Defines the select expression for the query.
     * Usage: $builder->select()->from()->where()->groupBy()->having()->orderBy()->limit()->getFirst|getFirstValue|getAll|getAllValues()
     *
     * @param string $selectExpr Select expression
     *
     * @return DBQueryBuilder
     */
    public function select(string $selectExpr) : self {
        $this->_op = static::OP_SELECT;
        $this->_selectExpr[] = $selectExpr;
        return $this;
    }

    /**
     * Defines the COUNT expression for the query.
     * Usage: $builder->count()->from()->where()->getFirstValue()
     *
     * @param string $countExpr COUNT expression; defaults to '*'
     *
     * @return DBQueryBuilder
     */
    public function count(string $countExpr = '*') : self {
        $this->_op = static::OP_SELECT;
        $this->_selectExpr[] = "COUNT($countExpr)";
        return $this;
    }

    /**
     * Checks if a specific row exists in a table.
     * Usage: $builder->exists()->where()->doExists()
     *
     * @param string      $tableRef Table reference
     * @param string|NULL $alias    Optional table alias
     *
     * @return DBQueryBuilder
     */
    public function exists(string $tableRef, ?string $alias = NULL) : self {
        $this->select('1')->from($tableRef, $alias);
        return $this;
    }

    /**
     * Defines the table reference for a SELECT query.
     * Examples: $builder->from("table1")
     *           $builder->from("table1", "t1")->from("table2", "t2")->where("t1.col1 = t2.col2")
     *
     * @param string      $tableRef Table reference
     * @param string|NULL $alias    Optional table alias
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function from(string $tableRef, ?string $alias = NULL) : self {
        $this->_tableRefs[] = static::_escapeColumnName($tableRef) . (!empty($alias) ? ' ' . static::_escapeColumnName($alias) : '');
        return $this;
    }

    /**
     * Helper method to create a JOIN in a SELECT query.
     * Equivalent to $builder->from()->where()
     *
     * @param string $tableRef        Table reference
     * @param string $whereCondition  Where expression
     * @param array  ...$param_values Optional parameter values, if the where condition contains placeholders
     *
     * @return DBQueryBuilder
     */
    public function join(string $tableRef, string $whereCondition, ...$param_values) : self {
        $this->_applyParams($whereCondition, $param_values);
        $this->_joins["JOIN " . static::_escapeColumnName($tableRef)] = $whereCondition;
        return $this;
    }

    /**
     * Add left (outer) join to a SELECT query.
     * Equivalent to $builder->from()->leftJoin()
     *
     * @param string $tableRef        Table reference
     * @param string $joinCondition   Where expression for the JOIN
     * @param array  ...$param_values Optional parameter values, if the where condition contains placeholders
     *
     * @return DBQueryBuilder
     */
    public function leftJoin(string $tableRef, string $joinCondition, ...$param_values) : self {
        $this->_applyParams($joinCondition, $param_values);
        $this->_joins["LEFT JOIN " . static::_escapeColumnName($tableRef)] = $joinCondition;
        return $this;
    }

    /**
     * Add left (outer) join to a SELECT query, and a WHERE condition to return only rows that are NOT in the specified table.
     * Example: $builder->from()->excludeJoin()
     *
     * @param string $tableRef        Table reference
     * @param string $joinCondition   Where expression for the JOIN
     * @param string $nullColumn      Column (from the joined table) that should be NULL
     * @param array  ...$param_values Optional parameter values, if the where condition contains placeholders
     *
     * @return DBQueryBuilder
     */
    public function excludeJoin(string $tableRef, string $joinCondition, string $nullColumn, ...$param_values) : self {
        $this->_applyParams($joinCondition, $param_values);
        $this->leftJoin($tableRef, $joinCondition)
            ->where("$nullColumn IS NULL");
        return $this;
    }

    /**
     * Defines the where condition for a SELECT query.
     * Examples: $builder->where("col1", $val1)
     *           $builder->where("col2 = 2")
     *           $builder->where("col3 = :val3", 3)
     *           $builder->where("col4 = :val4 AND col5 = :val5", 4, 5)
     *           $builder->where("table1.col1 = table2.col2")
     *
     * @param string $whereCondition  Where expression
     * @param array  ...$param_values Optional parameter values, if the where condition contains placeholders
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function where(string $whereCondition, ...$param_values) : self {
        $this->_applyParams($whereCondition, $param_values);
        $this->_whereConditions[] = $whereCondition;
        return $this;
    }

    /**
     * Defines the grouping expression for a SELECT query.
     * Examples: $builder->groupBy("col1")
     *           $builder->groupBy("col2, col3")
     *           $builder->groupBy("1, 3, 2")
     *
     * @param string $groupBy Grouping expression
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function groupBy(string $groupBy) : self {
        $this->_groupBy[] = $groupBy;
        return $this;
    }

    /**
     * Defines the where expression for a HAVING clause of a SELECT query.
     * Examples: $builder->having("col1")
     *           $builder->groupBy("col2 ASC, col3 DESC")
     *
     * @param string $havingCondition Where expression for the having clause
     * @param array  ...$param_values Optional parameter values, if the where condition contains placeholders
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::select() for usage.
     * @see DBQueryBuilder::where() for examples.
     */
    public function having(string $havingCondition, ...$param_values) : self {
        if (!empty($param_values)) {
            if (preg_match_all('/:([a-z0-9_]+)/i', $havingCondition, $re)) {
                $i = 0;
                if (count($re[1]) != count($param_values)) {
                    die("Error: number of arguments is different from number of placeholders");
                }
                foreach ($param_values as $param_value) {
                    $this->_addParam($re[1][$i++], $param_value, $havingCondition);
                }
            } else {
                die("Error: having() requires placeholders in condition when specifying param value(s)");
            }
        }
        $this->_havingConditions[] = $havingCondition;
        return $this;
    }

    /**
     * Defines the ordering expression for a SELECT query.
     * Examples: $builder->orderBy("col1")
     *           $builder->orderBy("col2 ASC, col3 DESC")
     *
     * @param string $orderBy         Ordering expression
     * @param array  ...$param_values Optional parameter values, if the order by condition contains placeholders
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function orderBy(string $orderBy, ...$param_values) : self {
        if (!empty($param_values)) {
            if (preg_match_all('/:([a-z0-9_]+)/i', $orderBy, $re)) {
                $i = 0;
                if (count($re[1]) != count($param_values)) {
                    die("Error: number of arguments is different from number of placeholders");
                }
                foreach ($param_values as $param_value) {
                    $this->_addParam($re[1][$i++], $param_value, $orderBy);
                }
            } else {
                die("Error: orderBy() requires placeholders in condition when specifying param value(s)");
            }
        }
        $this->_orderBy[] = $orderBy;
        return $this;
    }

    /**
     * Defines the limiting expression for a SELECT query.
     * Examples: $builder->limit("1")
     *           $builder->limit("1, 100")
     *
     * @param string $limit Limiting expression
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function limit(string $limit) : self {
        $this->_limit = $limit;
        return $this;
    }

    /**
     * Defines the table(s) to use in an UPDATE query.
     * Usage: $builder->update()->set()->where()->execute()
     *
     * @param string $tableRef Table(s) to update
     *
     * @return DBQueryBuilder
     */
    public function update(string $tableRef) : self {
        $this->_op = static::OP_UPDATE;
        $this->_tableRefs[] = static::_escapeColumnName($tableRef);
        return $this;
    }

    /**
     * Defines the table to use in an INSERT query.
     * Usage: $builder->insertInto()->ignore()->set()->insert()
     *
     * @param string $tableRef Table to insert into
     *
     * @return DBQueryBuilder
     */
    public function insertInto(?string $tableRef = NULL) : self {
        $this->_op = static::OP_INSERT;
        if (!empty($tableRef)) {
            $this->_tableRefs[] = static::_escapeColumnName($tableRef);
        }
        return $this;
    }

    /**
     * Add the IGNORE keyword in an INSERT query.
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::insert() for usage.
     */
    public function ignore() : self {
        $this->_option = ' IGNORE';
        return $this;
    }

    /**
     * Defines the values to set for INSERT and UPDATE queries.
     * Examples: $builder->set("col1", $val1)
     *           $builder->set(["col2", "col3"], $val2, $val3)
     *           $builder->set("col4", 4)
     *           $builder->set("col5 = :val5", $val5)
     *
     * @param string|array $column          Column name (string) or column names (array of string)
     * @param array        ...$param_values Value(s) for the specified column(s)
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::insertInto() for usage in INSERT query.
     * @see DBQueryBuilder::update() for usage in UPDATE query.
     */
    public function set($column, ...$param_values) : self {
        if (is_array($column)) {
            $array = $column;
            foreach ($array as $column_name => $param_value) {
                $this->set($column_name, $param_value);
            }
            return $this;
        }
        if (!empty($param_values)) {
            if (preg_match_all('/:([a-z0-9_]+)/i', $column, $re)) {
                if (count($re[1]) != count($param_values)) {
                    die("Error: number of arguments is different from number of placeholders");
                }
                $i = 0;
                foreach ($param_values as $param_value) {
                    $this->_addParam($re[1][$i++], $param_value);
                }
            } elseif (count($param_values) == 1) {
                if (preg_match('/^[a-z0-9_]+\.([a-z0-9_]+)$/i', $column, $re) || preg_match('/^([a-z0-9_]+)$/i', $column, $re)) {
                    $this->_addParam($re[1], $param_values[0]);
                    $column = static::_escapeColumnName($column) . " = :" . $re[1];
                } else {
                    die("Error: can't find where to inject param value in '$column'.");
                }
            } else {
                die("Error: placeholders params needed when specifying multiple param values.");
            }
        }
        $this->_colValues[] = $column;
        return $this;
    }

    /**
     * Add the ON DUPLICATE KEY UPDATE clause to an INSERT query.
     *
     * @param array ...$columns The columns to update, if the row already exists
     *
     * @return DBQueryBuilder
     * @throws Exception
     *
     * @see DBQueryBuilder::insert() for usage.
     */
    public function onDuplicateKeyUpdate(...$columns) : self {
        if ($this->_op != static::OP_INSERT) {
            throw new Exception("onDuplicateKeyUpdate() is only available for INSERT queries.");
        }
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }
        $this->_onDuplicateKeyUpdateColumns = $columns;
        return $this;
    }

    /**
     * Checks if the builder object has one or more values to UPDATE or INSERT.
     * Use this before you call execute() or insert() on a builder object that was built dynamically.
     *
     * @return bool TRUE if the builder has one or more values to UPDATE or INSERT.
     */
    public function hasUpdates() : bool {
        return !empty($this->_colValues);
    }

    /**
     * Builds the SQL query.
     *
     * @return string SQL query
     *
     * @throws Exception
     */
    public function build() : string {
        switch ($this->_op) {
        case static::OP_SELECT:
            $q = "SELECT " . implode(', ', $this->_selectExpr) . " FROM " . implode(', ', $this->_tableRefs);
            foreach ($this->_joins as $tableRef => $whereCondition) {
                $q .= " $tableRef ON ($whereCondition)";
            }
            if (!empty($this->_whereConditions)) {
                $q .= " WHERE (" . implode(') AND (', $this->_whereConditions) . ")";
            }
            if (!empty($this->_groupBy)) {
                $q .= " GROUP BY " . implode(', ', $this->_groupBy);
            }
            if (!empty($this->_havingConditions)) {
                $q .= " HAVING " . implode(' AND ', $this->_havingConditions);
            }
            if (!empty($this->_orderBy)) {
                $q .= " ORDER BY " . implode(', ', $this->_orderBy);
            }
            if (!empty($this->_limit)) {
                $q .= " LIMIT " . $this->_limit;
            }
            break;
        case static::OP_UPDATE:
            if (empty($this->_whereConditions)) {
                throw new Exception("UPDATE queries need WHERE conditions");
            }
            $q = "UPDATE" . $this->_option . " " . implode(', ', $this->_tableRefs) . " SET " . implode(', ', $this->_colValues) . " WHERE (" . implode(') AND (', $this->_whereConditions) . ")";
            break;
        case static::OP_INSERT:
            $q = "INSERT" . $this->_option . " INTO " . implode(', ', $this->_tableRefs) . " SET " . implode(', ', $this->_colValues);
            if (!empty($this->_onDuplicateKeyUpdateColumns)) {
                $values = [];
                foreach ($this->_onDuplicateKeyUpdateColumns as $col) {
                    $values[] = static::_escapeColumnName($col) . " = VALUES(" . static::_escapeColumnName($col) . ")";
                }
                $q .= " ON DUPLICATE KEY UPDATE " . implode(", ", $values);
            }
            break;
        case static::OP_DELETE:
            /** @noinspection SqlWithoutWhere */
            $q = "DELETE FROM " . implode(', ', $this->_tableRefs);
            $q .= " WHERE (" . implode(') AND (', $this->_whereConditions) . ")";
            break;
        default:
            throw new Exception("Invalid query operation");
            break;
        }
        return $this->_msTarget . $q;
    }

    /**
     * Returns the parameters to use alongside the SQL query.
     *
     * @return array The parameters to use when executing the SQL query
     */
    public function getParams() : array {
        return $this->_params;
    }

    /**
     * Executes the query.
     *
     * @return void
     */
    public function execute() : void {
        if ($this->_op == static::OP_UPDATE && !$this->hasUpdates()) {
            return;
        }
        DB::execute($this->build(), $this->getParams());
    }

    /**
     * Executes an INSERT query.
     *
     * @return int|bool The autoincrement ID of the new row.
     *
     * @see DBQueryBuilder::insertInto() for usage.
     */
    public function insert() {
        return DB::insert($this->build(), $this->getParams());
    }

    /**
     * Defines the table to use in a DELETE query.
     * Usage: $builder->delete()->where()->execute()
     *
     * @param string $tableRef Table to delete
     *
     * @return DBQueryBuilder
     */
    public function delete(string $tableRef) : self {
        $this->_op          = static::OP_DELETE;
        $this->_tableRefs[] = static::_escapeColumnName($tableRef);

        return $this;
    }

    /**
     * Returns an object corresponding to the first row returned by a SELECT query, or FALSE if nothing matched the where expression.
     *
     * @param null|string $class_type If not empty, the object returned will be of the specified class.
     *
     * @return bool|stdClass An object corresponding to the first row returned by a SELECT query, or FALSE if nothing matched the where expression.
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function getFirst(?string $class_type = NULL) {
        $options = ( $this->_cached ? DB::GET_OPT_CACHED : 0 );
        return DB::getFirst($this->build(), $this->getParams(), $options, $class_type);
    }

    /**
     * Returns a value corresponding to the first column of the first row returned by a SELECT query, or FALSE if nothing matched the where expression.
     *
     * @return bool|string A (string) value corresponding to the first column of the first row returned by a SELECT query, or FALSE if nothing matched the where expression.
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function getFirstValue() {
        $options = ( $this->_cached ? DB::GET_OPT_CACHED : 0 );
        return DB::getFirstValue($this->build(), $this->getParams(), $options);
    }

    /**
     * Returns an array of objects corresponding to the rows returned by a SELECT query.
     *
     * @param string|null $index_field If specified, the returned array will use the values of this select expression as the indices.
     * @param int         $options     Specify GET_OPT_INDEXED_ARRAYS if you'd like the returned array to be an array of arrays.
     * @param null|string $class_type  If not empty, returned objects will be of the specified class.
     *
     * @return array An array of objects corresponding to the rows returned by a SELECT query.
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function getAll(?string $index_field = NULL, int $options = 0, ?string $class_type = NULL) : array {
        if ($this->_cached) {
            $options = $options | DB::GET_OPT_CACHED;
        }
        return DB::getAll($this->build(), $this->getParams(), $index_field, $options, $class_type);
    }

    /**
     * Returns an array of values corresponding to the first column of the rows returned by a SELECT query.
     *
     * @param string|null $data_type Will cast the values into the specified type. If not specified, returned values will be strings.
     *
     * @return array An array of values corresponding to the first column of the rows returned by a SELECT query.
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function getAllValues(?string $data_type = NULL) : array {
        $options = ( $this->_cached ? DB::GET_OPT_CACHED : 0 );
        return DB::getAllValues($this->build(), $this->getParams(), $data_type, $options);
    }

    /**
     * Checks if the specified where expression matched a row.
     *
     * @return bool TRUE if the specified where expression matched a row.
     *
     * @see DBQueryBuilder::exists() for usage.
     */
    public function doExists() : bool {
        return ( $this->getFirstValue() === '1' );
    }

    public function getDebug() {
        $qs = [];
        foreach ($this->getParams() as $k => $v) {
            $qs[] = "SET @$k = '$v'";
        }
        $qs[] = str_replace(':', '@', $this->build());
        return implode(';', $qs);
    }

    private static function _escapeColumnName(string $colName) : string {
        if (preg_match('/^([a-z0-9_]+)$/i', $colName, $re)) {
            return '`' . $re[1] . '`';
        }
        if (preg_match('/^([a-z0-9_]+) ([a-z0-9_]+)$/i', $colName, $re)) {
            return '`' . $re[1] . '` ' . $re[2];
        }
        return $colName;
    }

    private function _addParam(string &$name, $value, ?string &$whereCondition = NULL) {
        if (is_array($value)) {
            $inQuery = [];
            $i = 0;
            foreach ($value as $el) {
                $el_name = "{$name}_{$i}";
                $this->_addParam($el_name, $el);
                $inQuery[] = ":$el_name";
                $i++;
            }
            $whereCondition = str_replace(":$name", implode(', ', $inQuery), $whereCondition);
            return;
        }

        $new_name = $name;
        $i = 1;
        while (isset($this->_params[$new_name])) {
            $new_name = $name . "_" . $i++;
        }
        $name = $new_name;
        $this->_params[$new_name] = $value;
    }

    private function _applyParams(&$whereCondition, $param_values) {
        if (!empty($param_values)) {
            if (preg_match_all('/:([a-z0-9_]+)/i', $whereCondition, $re)) {
                $i = 0;
                $re[1] = array_values(array_unique($re[1]));
                if (count($re[1]) != count($param_values)) {
                    die("Error: number of arguments is different from number of placeholders");
                }
                foreach ($param_values as $param_value) {
                    $this->_addParam($re[1][$i++], $param_value, $whereCondition);
                }
            } elseif (count($param_values) == 1) {
                if ($param_values[0] === NULL) {
                    $whereCondition = static::_escapeColumnName($whereCondition) . " IS NULL";
                } else {
                    if (preg_match('/^[a-z0-9_]+\.([a-z0-9_]+)$/i', $whereCondition, $re) || preg_match('/^([a-z0-9_]+)$/i', $whereCondition, $re)) {
                        $this->_addParam($re[1], $param_values[0]);
                        $whereCondition = static::_escapeColumnName($whereCondition) . " = :" . $re[1];
                    } else {
                        die("Error: can't find where to inject param value in '$whereCondition'.");
                    }
                }
            } else {
                die("Error: placeholders params needed when specifying multiple param values.");
            }
        }
    }
}
