<?php

namespace EasyMalt;

class DB
{
	private static $handle;
    private static $lock_timeout_retries = 0;

    public static function connect() {
        try {
            // Example connect string: 'mysql:host=localhost;port=9005;dbname=test', 'sqlite:/tmp/foo.db'
            $connect_string = Config::get('DB_ENGINE') . ':';
            if (Config::get('DB_FILE')) {
                $connect_string .= Config::get('DB_FILE');
            } elseif (Config::get('DB_HOST')) {
                $connect_string .= 'host=' . Config::get('DB_HOST');
                if (Config::get('DB_PORT')) {
                    $connect_string .= ';port=' . Config::get('DB_PORT');
                }
                if (Config::get('DB_NAME')) {
                    $connect_string .= ';dbname=' . Config::get('DB_NAME');
                }
                $connect_string .= ';charset=utf8';
            }
            $opt = array(
                \PDO::ATTR_TIMEOUT => 10,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            );
            if (Config::get('DB_CA_CERT')) {
                $opt[\PDO::MYSQL_ATTR_SSL_CA] = Config::get('DB_CA_CERT');
            }
            if (Config::get('DB_PWD')) {
                static::$handle = new \PDO($connect_string, Config::get('DB_USER'), Config::get('DB_PWD'), $opt);
            } else {
                static::$handle = new \PDO($connect_string, NULL, NULL, $opt);
            }
            // We don't need REPEATABLE-READ, which is the default, and will keep locks longer for no good reason.
            DB::execute("SET tx_isolation = 'READ-COMMITTED'");
        } catch (\PDOException $e) {
            throw new \Exception("Can't connect to the database. Please try again later. Error: " . $e->getMessage());
        }
    }

    public static function execute($q, $args = array(), $retryOnError = TRUE) {
        // Handle array arguments; eg. $q = "[...] WHERE id IN (:users)", $args = ['users' => array([...])]
        if (is_array($args)) {
            foreach ($args as $k => $v) {
                if (is_array($v)) {
                    unset($args[$k]);
                    $inQuery = [];
                    $i = 0;
                    foreach ($v as $el) {
                        $inQuery[] = ":$k" . "_$i";
                        $args["$k" . "_$i"] = $el;
                        $i++;
                    }
                    $q = str_replace(":$k", implode(', ', $inQuery), $q);
                }
            }
        }

        $stmt = static::$handle->prepare($q);

        if (!is_array($args)) {
            // If there is only one argument; no need to use an array for $args
            if (preg_match('/:([a-z0-9_]+)/', $q, $re)) {
                $args = array($re[1] => $args);
            }
        }

        // Bind arguments
        foreach ($args as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        try {
            $stmt->execute();
            return $stmt;
        } catch (\PDOException $e) {
            $error_info = $stmt->errorInfo();
            if ($error_info[1] == NULL) {
                // PDO (not MySQL) error
                $error_info = array($e->getCode(), $e->getCode(), $e->getMessage());
            }
            $error_code = (int) $error_info[1];
            if ($retryOnError) {
                if ($error_code == 1205) {
                    sleep(1);
                    error_log("Will retry query $q following a 'Lock wait timeout exceeded' error ($error_code).");
                    return static::execute($q, $args, static::$lock_timeout_retries++ < 10*60); // $retryOnError == TRUE : retry 'lock wait timeout' errors for 10 minutes, then give up.
                }
                if ($error_code == 2013 || $error_code == 2006 || $error_code == 2055) {
                    sleep(5);
                    DB::connect();
                    error_log("Will retry query $q following a 'Lost connection to DB' error ($error_code).");
                    return static::execute($q, $args, FALSE);
                }
            }
            $error_message = "Can't execute query: $q; error: [$error_code] " . $error_info[2];
            throw new \Exception($error_message, $error_code);
        }
    }

    public static function insert($q, $args = array()) {
        DB::execute($q, $args);
        return DB::lastInsertedId();
    }

    public static function getFirst($q, $args = array()) {
        $stmt = DB::execute($q, $args);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($result === FALSE) {
            return FALSE;
        }
        return (object) $result;
    }

    public static function getFirstValue($q, $args = array()) {
        $stmt = DB::execute($q, $args);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return FALSE;
        }
        return array_shift($row);
    }

    public static function getAll($q, $args = array(), $index_field=NULL, $indexed_arrays=FALSE) {
        $stmt = DB::execute($q, $args);
        $rows = array();
        $i = 0;
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $index = $i++;
            if (!empty($index_field)) {
                $index = $row[$index_field];
            }
            if ($indexed_arrays) {
                $rows[$index][] = (object) $row;
            } else {
                $rows[$index] = (object) $row;
            }
        }
        return $rows;
    }

    public static function getAllValues($q, $args = array(), $data_type=null) {
        $stmt = DB::execute($q, $args);
        $values = array();
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                return FALSE;
            }

            $value = array_shift($row);
            if (!empty($data_type)) {
                settype($value, $data_type);
            }
            $values[] = $value;
        }
        return $values;
    }

    public static function lastInsertedId() {
        if (Config::get('DB_ENGINE') == 'mysql') {
            $q = "SELECT LAST_INSERT_ID()";
            return DB::getFirstValue($q);
        }
        return TRUE;
    }

    public static function startTransaction() {
        static::execute("SET autocommit=0");
    }

    public static function commitTransaction() {
        static::execute("COMMIT");
        static::execute("SET autocommit=1");
    }

    public static function rollbackTransaction() {
        static::execute("ROLLBACK");
        static::execute("SET autocommit=1");
    }
}
