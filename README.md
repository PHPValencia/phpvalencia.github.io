# PHPValencia Website

Página web principal de PHP Valencia.


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
