<?php
/**
 * 坤发卡 - 数据库访问层(PDO MySQL)
 */
if (!class_exists('DB')) {
    class DB
    {
    /** @var PDO */
    private static $pdo;

    /** 暴露底层PDO(自动更新器执行数据库升级用) */
    public static function handle()
    {
        return self::$pdo;
    }

    /** PDO驱动名(mysql/sqlite), 供需要方言差异的逻辑判断(如FOR UPDATE行锁) */
    public static function driver()
    {
        return self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /** 开启事务(发货等需要"选卡→占卡→写单"原子完成的场景) */
    public static function begin()
    {
        if (!self::$pdo->inTransaction()) self::$pdo->beginTransaction();
    }

    public static function commit()
    {
        if (self::$pdo->inTransaction()) self::$pdo->commit();
    }

    public static function rollBack()
    {
        if (self::$pdo->inTransaction()) self::$pdo->rollBack();
    }

    public static function init()
    {
        $dsn = 'mysql:host=' . YF_DB_HOST . ';port=' . YF_DB_PORT . ';dbname=' . YF_DB_NAME . ';charset=utf8mb4';
        self::$pdo = new PDO($dsn, YF_DB_USER, YF_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function query($sql, $params = [])
    {
        $st = self::$pdo->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** 单行, 无则null */
    public static function fetch($sql, $params = [])
    {
        $r = self::query($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    /** 多行 */
    public static function fetchAll($sql, $params = [])
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** 单值 */
    public static function value($sql, $params = [])
    {
        $r = self::query($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    /** 插入, 返回自增ID */
    public static function insert($table, $data)
    {
        self::assertIdent($table);
        $cols = array_keys($data);
        foreach ($cols as $c) self::assertIdent($c);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')';
        self::query($sql, array_values($data));
        return self::$pdo->lastInsertId();
    }

    /** 更新, 返回影响行数 */
    public static function update($table, $data, $where, $params = [])
    {
        self::assertIdent($table);
        $sets = [];
        foreach (array_keys($data) as $c) {
            self::assertIdent($c);
            $sets[] = '`' . $c . '` = ?';
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        $st = self::query($sql, array_merge(array_values($data), $params));
        return $st->rowCount();
    }

    /** 表名/列名白名单校验: 标识符无法参数化, 以白名单防未来调用误传用户输入形成注入 */
    private static function assertIdent($name)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$name)) throw new Exception('非法数据库标识符');
    }

    public static function exec($sql, $params = [])
    {
        return self::query($sql, $params)->rowCount();
    }
    }
}
