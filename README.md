# PHPValencia Website

Página web principal de PHP Valencia.

## Deploy

La publicación en GitHub Pages se automatiza mediante el workflow `.github/workflows/static.yml`.

No es necesario subir los ficheros compilados al repositorio; basta con que el código fuente esté actualizado para que el despliegue se genere automáticamente.


## Uso en local

Comandos básicos para operar el website con [Jigsaw](https://jigsaw.tighten.com/):

```bash
# Modo local (salida en build_local/)
vendor/bin/jigsaw build

# Modo producción (salida en build_production/)
vendor/bin/jigsaw build production

# Servir el sitio en modo local (usa PHP built-in server)
vendor/bin/jigsaw serve
```

El comando `serve` recompila automáticamente cuando cambian los ficheros dentro de `source/`.


## CLI

Comandos útiles disponibles `bin/cli`.


### Requisitos

- PHP 8.1 o superior
- Dependencias instaladas: `composer install`


### Descargar eventos de Meetup

Descarga los datos JSON de los identificadores de eventos y guarda cada respuesta en un fichero:

```bash
bin/cli meetup:download-events \
  --events-file meetup_events.json \
  --output-dir meetup_events_data
```


### Generar archivos Markdown

Convierte los ficheros JSON descargados en entradas Markdown para la web:

```bash
bin/cli meetup:generate-markdown \
  --input-dir meetup_events_data \
  --output-dir source/_events
```

### Generar el boletín mensual de noticias

El boletín mensual se construye recopilando todas las ediciones del archivo de PHP Weekly que pertenezcan al mes en curso. El comando descarga cada publicación, fusiona las secciones relevantes y genera el fichero Markdown correspondiente en `source/_news`:

```bash
bin/cli news:generate-monthly \
  --output-dir source/_news \
  --archive-url https://www.phpweekly.com/archive.html
```

Puedes ajustar `--archive-url` si necesitas apuntar a otra página de archivo o a un mirror temporal.

## Automatización del boletín

El workflow `.github/workflows/monthly-news.yml` se ejecuta automáticamente el último día de cada mes (y admite ejecución manual con `workflow_dispatch`). Este proceso:

- instala las dependencias del proyecto
- ejecuta `bin/cli news:generate-monthly` para crear la entrada
- abre un Pull Request en borrador con los cambios detectados bajo una rama `news/boletin-YYYY-MM`

Con ello, basta con revisar e integrar el PR para que la nueva edición quede publicada tras el despliegue habitual de GitHub Pages.
