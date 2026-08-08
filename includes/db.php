<?php
class DB
{
    private static $pdo = null;
    private static $config = [];

    public static function init($config)
    {
        self::$config = $config;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['name'], $config['charset']);
        self::$pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    public static function getInstance()
    {
        if (self::$pdo === null) {
            self::init(self::$config);
        }
        return self::$pdo;
    }

    public static function query($sql, $params = [])
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch($sql, $params = [])
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetch() ?: false;
    }

    public static function fetchAll($sql, $params = [])
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function value($sql, $params = [])
    {
        $row = self::query($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row ? $row[0] : null;
    }

    public static function insert($table, $data)
    {
        $fields = array_keys($data);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $fields) . ') VALUES (:' . implode(',:', $fields) . ')';
        self::query($sql, $data);
        return (int)self::getInstance()->lastInsertId();
    }

    public static function update($table, $data, $where, $whereParams = [])
    {
        $sets = [];
        foreach ($data as $field => $value) {
            $sets[] = $field . ' = :' . $field;
        }
        $params = self::prefixParams($data, '');
        $i = 0;
        $whereSql = preg_replace_callback('/\?/', function () use (&$i) {
            return ':w' . $i++;
        }, $where);
        $params = array_merge($params, self::prefixParams($whereParams, 'w_'));
        $sql = 'UPDATE ' . $table . ' SET ' . implode(',', $sets) . ' WHERE ' . $whereSql;
        return self::query($sql, $params)->rowCount();
    }

    public static function delete($table, $where, $params = [])
    {
        $i = 0;
        $whereSql = preg_replace_callback('/\?/', function () use (&$i) {
            return ':w' . $i++;
        }, $where);
        return self::query('DELETE FROM ' . $table . ' WHERE ' . $whereSql, $params)->rowCount();
    }

    public static function count($table, $where = '', $params = [])
    {
        $sql = 'SELECT COUNT(*) FROM ' . $table . ($where ? ' WHERE ' . $where : '');
        return (int)self::query($sql, $params)->fetchColumn();
    }

    public static function begin()
    {
        return self::getInstance()->beginTransaction();
    }

    public static function commit()
    {
        return self::getInstance()->commit();
    }

    public static function rollback()
    {
        return self::getInstance()->rollBack();
    }

    public static function inTransaction()
    {
        return self::getInstance()->inTransaction();
    }

    public static function lastInsertId()
    {
        return self::getInstance()->lastInsertId();
    }

    private static function prefixParams($params, $prefix)
    {
        $out = [];
        foreach ($params as $k => $v) {
            if (is_string($k)) {
                $out[':' . $prefix . ltrim($k, ':')] = $v;
            } else {
                $out[] = $v;
            }
        }
        return $out;
    }
}