# Biblioteca Personal

CMS para gestionar bibliotecas físicas personales. Laravel 12 + starter kit de Livewire (Flux + Volt), Tailwind CSS y MySQL.

El desarrollo sigue specs: los principios están en [`docs/constitution.md`](docs/constitution.md) y las funcionalidades en [`docs/specs/`](docs/specs/).

## Instalación

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

En el `.env`, configura la base de datos MySQL y las credenciales del super administrador:

```dotenv
SUPER_ADMIN_NAME="Super administrador"
SUPER_ADMIN_EMAIL=
SUPER_ADMIN_PASSWORD=
```

Después:

```bash
php artisan migrate --seed
```

El seeder crea el único super administrador. Si ya existe uno, no crea otro. En entorno local crea también el usuario `test@example.com` con la contraseña `password`.

## Uso

- Ejecutar la aplicación: `php artisan serve`
- Tests: `php artisan test`
- Formato: `./vendor/bin/pint`

## Tareas programadas

Todos los días a las 2:00, `loans:check-overdue` marca como vencidos los préstamos activos de más de 2 meses. Para que se ejecute, el servidor necesita el cron de Laravel:

```bash
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

## Portadas

Las portadas se guardan como WebP en `storage/app/private/covers`. No son públicas: se sirven con una ruta que comprueba que quien las pide puede ver el libro. Los límites están en `config/books.php`.

## Roles

| Rol | Cómo se obtiene |
|---|---|
| Usuario | Registro público o alta desde el panel |
| Administrador | `php artisan admin:grant {email}`, o el super admin desde el panel |
| Super administrador | Solo el seeder (único) |

Para quitar el rol de administrador: `php artisan admin:revoke {email}`.
