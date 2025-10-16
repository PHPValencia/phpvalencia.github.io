# PHPValencia Website

Página web principal de PHP Valencia.

## Utilidades CLI

Todos los comandos de automatización del proyecto están disponibles a través de la aplicación Symfony Console situada en `bin/cli`.

### Descargar eventos de Meetup

Descarga los datos JSON de los identificadores de eventos configurados y los guarda en un directorio:

```bash
bin/cli meetup:download-events \
  --events-file meetup_events.json \
  --output-dir meetup_events_data
```

- `--events-file` debe apuntar a un array JSON con los identificadores de eventos de Meetup que se quieren descargar.
- `--output-dir` es la carpeta donde se almacenarán los ficheros JSON (se creará si no existe).

### Generar archivos Markdown

Convierte los ficheros JSON descargados en entradas Markdown para la web:

```bash
bin/cli meetup:generate-markdown \
  --input-dir meetup_events_data \
  --output-dir source/_events
```

- `--input-dir` debe señalar al directorio que contiene los ficheros JSON de los eventos.
- `--output-dir` es el directorio donde se generarán los ficheros Markdown.
