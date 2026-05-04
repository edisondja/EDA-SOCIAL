# Backend (Laravel)

Esta carpeta es la aplicación **Laravel 7** de EDA_SOCIAL: rutas web (Blade), API JSON, base de datos, jobs y administración.

La documentación del proyecto (instalación, API, comentarios en hilos, importación videosegg, colas, etc.) está en el README de la raíz del repositorio:

**[../README.md](../README.md)**

Comandos habituales desde aquí:

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```
