<?php
/**
 * Conexión única (Singleton) a MySQL usando PDO.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            $dsn = defined('DB_DSN')
                ? DB_DSN
                : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // sentencias preparadas reales
            ]);
        }
        return self::$pdo;
    }

    /** Ejecuta una consulta preparada y devuelve el statement. */
    public static function consulta(string $sql, array $parametros = []): PDOStatement
    {
        $stmt = self::conexion()->prepare($sql);
        $stmt->execute($parametros);
        return $stmt;
    }

    /**
     * Ejecuta $callback dentro de una transacción.
     * Si algo falla hace rollback y relanza la excepción.
     */
    public static function transaccion(callable $callback)
    {
        $pdo = self::conexion();
        $pdo->beginTransaction();
        try {
            $resultado = $callback($pdo);
            $pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
