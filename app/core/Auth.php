<?php

final class Auth
{
    public const COMPRADOR = 'comprador';
    public const VENDEDOR  = 'vendedor';
    public const ADMIN     = 'administrador';

    /** Interfaz de inicio de cada rol */
    private const RUTAS_POR_ROL = [
        self::ADMIN     => 'admin/index.php',
        self::VENDEDOR  => 'vendedor/perfil.php',
        self::COMPRADOR => 'index.php',
    ];

    public static function login(array $usuario, array $roles): void
    {
        session_regenerate_id(true); 

        $_SESSION['usuario'] = [
            'id'     => (int) $usuario['id'],
            'nombre' => $usuario['nombre'],
            'correo' => $usuario['correo'],
            'roles'  => array_values($roles),
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['usuario']['id']) ? (int) $_SESSION['usuario']['id'] : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function roles(): array
    {
        return $_SESSION['usuario']['roles'] ?? [];
    }

    public static function tieneRol(string $rol): bool
    {
        return in_array($rol, self::roles(), true);
    }

    /* PERFILES EXCLUSIVOS */
    public static function esStaff(): bool
    {
        return self::tieneRol(self::ADMIN) || self::tieneRol(self::VENDEDOR);
    }

    /** Comprador no puede vender*/
    public static function esComprador(): bool
    {
        return self::tieneRol(self::COMPRADOR) && !self::esStaff();
    }

    /** Visitantes solo puede ver*/
    public static function puedeComprar(): bool
    {
        return !self::check() || self::esComprador();
    }

    /**
     * Protege las páginas y funciones EXCLUSIVAS del comprador
     * (Mis compras, finalizar compra, reseñas).
     */
    public static function requerirComprador(): void
    {
        if (!self::check()) {
            if (es_peticion_api()) {
                json_response(['ok' => false, 'requiere_login' => true, 'mensaje' => 'Inicia sesión con una cuenta de comprador para continuar.'], 401);
            }
            self::requerirLogin();
        }

        self::refrescar();

        if (!self::esComprador()) {
            if (es_peticion_api()) {
                json_response(['ok' => false, 'mensaje' => 'Esta función es exclusiva de las cuentas de comprador.'], 403);
            }
            if (!self::check()) {
                self::requerirLogin();
            }
            flash('error', 'Acceso denegado', 'Esa sección es exclusiva de las cuentas de comprador.');
            redirect(self::rutaInicio());
        }
    }

    /** Bloquea a vendedores y administradores (deja pasar visitantes y compradores). */
    public static function bloquearStaff(): void
    {
        if (self::check()) {
            self::refrescar();
        }
        if (self::check() && self::esStaff()) {
            flash('error', 'Acceso denegado', 'Las cuentas de vendedor y administrador no pueden comprar en la tienda.');
            redirect(self::rutaInicio());
        }
    }

    /** Devuelve la ruta de la interfaz que corresponde a los roles del usuario. */
    public static function rutaInicio(?array $roles = null): string
    {
        $roles = $roles ?? self::roles();
        foreach (self::RUTAS_POR_ROL as $rol => $ruta) {
            if (in_array($rol, $roles, true)) {
                return $ruta;
            }
        }
        return 'index.php';
    }

    /** Vuelve a leer los roles desde la BD (por si un admin los cambió). */
    public static function refrescar(): void
    {
        if (!self::check()) {
            return;
        }
        $usuario = Usuario::buscarPorId(self::id());
        if (!$usuario || $usuario['estado'] !== 'activo') {
            self::logout();
            return;
        }
        $_SESSION['usuario']['nombre'] = $usuario['nombre'];
        $_SESSION['usuario']['correo'] = $usuario['correo'];
        $_SESSION['usuario']['roles']  = Usuario::roles((int) $usuario['id']);
    }

    public static function requerirLogin(): void
    {
        if (!self::check()) {
            flash('info', 'Inicia sesión', 'Debes iniciar sesión para continuar.');
            redirect('login.php');
        }
    }

    /** Permite el acceso solo si el usuario tiene al menos uno de los roles indicados. */
    public static function requerirRol(string ...$rolesPermitidos): void
    {
        self::requerirLogin();
        self::refrescar();
        self::requerirLogin(); // por si la cuenta fue bloqueada

        foreach ($rolesPermitidos as $rol) {
            if (self::tieneRol($rol)) {
                return;
            }
        }

        flash('error', 'Acceso denegado', 'No tienes permisos para ver esa sección.');
        redirect(self::rutaInicio());
    }
}
