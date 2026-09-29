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

## Roles

| Rol | Cómo se obtiene |
|---|---|
| Usuario | Registro público o alta desde el panel |
| Administrador | `php artisan admin:grant {email}`, o el super admin desde el panel |
| Super administrador | Solo el seeder (único) |

Para quitar el rol de administrador: `php artisan admin:revoke {email}`.
