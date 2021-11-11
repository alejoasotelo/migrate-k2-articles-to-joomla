# K2 to Joomla

Utilidad para migrar artículos de k2 a artículos nativos de Joomla! 3

## Instalación

Hay que clonar el repositorio y colocarlo en la carpet a *cli* del Joomla 3 dónde se va a ejecutar la migración. 
Quedando de la siguiente manera:

```bash
mi-joomla
|__ cli
    |__ k2tojoomla
        |__ bootstrap.php
        |__ cli.php
        |__ migration.js
        |__ migration.php
        |__ README.md
        |__ web.php
```

## Ejecución

### Por SSH

Primero iniciamos sesión en el administrator de nuestro Joomla y buscamos el usuario al que le vamos a asignar la creación de los artículos, obtenemos su ID y lo anotamos.

Este userid se va a asignar a los artículos creados de Joomla en la migración.

Ejecutamos y esperamos que finalice la migración:
```
cd mi-joomla/cli/k2tojoomla
php cli.php --userid=123
```