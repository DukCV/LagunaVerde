<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Media;
use App\Models\News;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NewsSeeder extends Seeder
{
    // ────────────────────────────────────────────────────────────────────
    //  Disco donde se almacenarán (o esperan) los archivos físicos.
    //  'public' → storage/app/public/  (accesible vía php artisan storage:link)
    //  Cambia a 's3' si usas almacenamiento externo.
    // ────────────────────────────────────────────────────────────────────
    private const DISK = 'public';

    // ════════════════════════════════════════════════════════════════════
    //  CATÁLOGO DE NOTICIAS DE PRUEBA
    //
    //  Cada elemento define UNA noticia con su metadato y sus archivos.
    //
    //  ESTRUCTURA DE ARCHIVOS:
    //    'cover'         → imagen de portada         (order = 0)
    //    'illustrations' → imágenes ilustrativas      (order = 1, 2 …)
    //    'documents'     → PDFs y otros adjuntos      (order = 100, 101 …)
    //
    //  Los 'path' son rutas RELATIVAS dentro del disco (DISK).
    //  Los archivos físicos se cargan de forma externa; este seeder
    //  sólo registra los metadatos en la tabla `media`.
    //
    //  ➕ PARA AGREGAR UNA NUEVA NOTICIA:
    //     1. Copia cualquier bloque completo (Noticia 1, 2 o 3).
    //     2. Pégalo debajo del último elemento del array (antes del ];).
    //     3. Actualiza title, category, paths y archivos.
    //     4. Ejecuta:  php artisan db:seed --class=NewsSeeder
    // ════════════════════════════════════════════════════════════════════
    private function newsData(): array
    {
        return [

            // ════════════════════════════════════════════════════════════
            //  NOTICIA 1
            // ════════════════════════════════════════════════════════════
            [
                // ── Datos de la noticia ───────────────────────────────
                'title'       => 'Laguna Verde inicia nueva temporada de monitoreo acuático',
                'summary'     => 'El equipo técnico arranca el ciclo 2025 para evaluar la '
                               . 'calidad del agua y la biodiversidad de la laguna.',
                'author_name' => 'Equipo Laguna Verde',
                'category'    => 'Medio Ambiente',   // debe existir en CategorySeeder (type=news)
                'published_at'=> now()->subDays(3),
                'status'      => 'published',
                'content'     => '<p>El equipo técnico de Laguna Verde arrancó oficialmente '
                               . 'el ciclo de monitoreo 2025, que incluye mediciones de pH, '
                               . 'oxígeno disuelto, temperatura y censos de fauna acuática.</p>'
                               . '<p>Esta temporada se incorporan nuevas sondas digitales que '
                               . 'permitirán lecturas en tiempo real desde la plataforma web.</p>'
                               . '<p>Los resultados serán publicados trimestralmente en este '
                               . 'sitio para consulta de la comunidad.</p>',

                // ── Imágenes ──────────────────────────────────────────
                //
                //  NOTA: Los archivos se cargan de forma EXTERNA.
                //  Los 'path' deben coincidir con la ruta donde
                //  el archivo esté disponible en el disco configurado.
                //  'size' es un valor estimado en bytes (referencia).
                //
                'cover' => [
                    'path'  => 'news/noticia-1/portada.jpg',
                    'mime'  => 'image/jpeg',
                    'size'  => 204800,   // ~200 KB
                    'title' => 'Vista aérea de Laguna Verde',
                    'alt'   => 'Vista aérea de la laguna con vegetación circundante',
                ],
                'illustrations' => [
                    [
                        'path'  => 'news/noticia-1/ilustracion-1.jpg',
                        'mime'  => 'image/jpeg',
                        'size'  => 153600,   // ~150 KB
                        'title' => 'Equipo de monitoreo en campo',
                        'alt'   => 'Técnicos tomando muestras de agua en la orilla de la laguna',
                    ],
                    [
                        'path'  => 'news/noticia-1/ilustracion-2.jpg',
                        'mime'  => 'image/jpeg',
                        'size'  => 112640,   // ~110 KB
                        'title' => 'Sonda digital de medición',
                        'alt'   => 'Detalle de la sonda digital sumergida en el agua',
                    ],
                ],
                'documents' => [],   // sin documentos adjuntos
            ],

            // ════════════════════════════════════════════════════════════
            //  NOTICIA 2
            // ════════════════════════════════════════════════════════════
            [
                'title'       => 'Voluntarios plantan 500 árboles nativos',
                'summary'     => 'Jornada de reforestación en la zona norte de la laguna.',
                'author_name' => 'Equipo Laguna Verde',
                'category'    => 'Comunidad',
                'published_at'=> now()->subDays(10),
                'status'      => 'published',
                'content'     => '<p>Más de 80 voluntarios participaron en la jornada...</p>',
                'cover' => [
                    'path'  => 'news/noticia-2/portada.jpg',
                    'mime'  => 'image/jpeg',
                    'size'  => 180000,
                    'title' => 'Voluntarios con árboles nativos',
                    'alt'   => 'Grupo de voluntarios con árboles listos para plantar',
                ],
                'illustrations' => [
                    [
                        'path'  => 'news/noticia-2/ilustracion-1.jpg',
                        'mime'  => 'image/jpeg',
                        'size'  => 140000,
                        'title' => 'Zona norte antes de la reforestación',
                        'alt'   => 'Paisaje árido de la zona norte de la laguna',
                    ],
                    [
                        'path'  => 'news/noticia-2/ilustracion-2.jpg',
                        'mime'  => 'image/jpeg',
                        'size'  => 130000,
                        'title' => 'Árbol nativo recién plantado',
                        'alt'   => 'Primer plano de un árbol nativo recién plantado',
                    ],
                ],
                'documents' => [],
            ],

            // ════════════════════════════════════════════════════════════
            //  NOTICIA 3
            // ════════════════════════════════════════════════════════════
            [
                'title'       => 'Informe Anual de Calidad del Agua 2025',
                'summary'     => 'Presentamos los resultados completos del monitoreo '
                               . 'anual: indicadores fisicoquímicos, biológicos y '
                               . 'comparativa con el ciclo anterior.',
                'author_name' => 'Área de Investigación',
                'category'    => 'Medio Ambiente',
                'published_at'=> now()->subDays(20),
                'status'      => 'published',
                'content'     => '<p>El Área de Investigación de Laguna Verde presenta '
                               . 'el informe anual correspondiente al ciclo 2025, que '
                               . 'documenta la evolución de los principales indicadores '
                               . 'de salud del ecosistema acuático.</p>'
                               . '<h2>Principales hallazgos</h2>'
                               . '<p>Los niveles de oxígeno disuelto se incrementaron un '
                               . '18 % respecto al año anterior, mientras que la turbidez '
                               . 'disminuyó un 22 %, evidencienciando una mejora sostenida '
                               . 'en la calidad del agua.</p>'
                               . '<p>El informe completo con todas las tablas y gráficas '
                               . 'está disponible para descarga en la sección de documentos.</p>',

                'cover' => [
                    'path'  => 'news/noticia-3/portada.jpg',
                    'mime'  => 'image/jpeg',
                    'size'  => 195000,
                    'title' => 'Muestra de agua bajo microscopio',
                    'alt'   => 'Vista microscópica de una muestra de agua de la laguna',
                ],

                'illustrations' => [],

                'documents' => [
                    [
                        'path'  => 'news/noticia-3/informe-calidad-agua-2025.pdf',
                        'mime'  => 'application/pdf',
                        'size'  => 2457600,
                        'title' => 'Informe Anual de Calidad del Agua 2025',
                        'alt'   => '',
                        'order' => 100,
                    ],
                ],
            ],

        ];
    }

    // ════════════════════════════════════════════════════════════════════
    //  ENTRY POINT
    // ════════════════════════════════════════════════════════════════════
    public function run(): void
    {
        $this->command->info('📰 Sembrando noticias...');

        foreach ($this->newsData() as $entry) {
            $this->seedNewsEntry($entry);
        }

        $this->command->info('  ✓ Noticias listas.');
    }

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Crea una noticia (si no existe) y registra su portada,
     * imágenes ilustrativas y documentos adjuntos.
     */
    private function seedNewsEntry(array $entry): void
    {
        // 1 ── Resolver categoría ──────────────────────────────────────────
        $category = Category::where('type', 'news')
                             ->where('name', $entry['category'])
                             ->first();

        if (! $category) {
            $this->command->warn(
                "  ⚠ Categoría \"{$entry['category']}\" no encontrada. "
                . "Omitiendo noticia: \"{$entry['title']}\""
            );
            return;
        }

        // 2 ── Crear noticia (idempotente por título) ──────────────────────
        [$news, $created] = $this->findOrCreateNews($entry, $category->id);

        if (! $created) {
            $this->command->line("  → Ya existe, se omiten archivos: \"{$news->title}\"");
            return;
        }

        $this->command->line("  + Noticia creada: \"{$news->title}\"");

        // 3 ── Imagen de portada (collection='cover', order=0) ──────────────
        $this->attachMedia($news, $entry['cover'], collection: 'cover', order: 0, label: 'portada');

        // 4 ── Imágenes ilustrativas al slider (collection='slider', order=1, 2…) ──
        foreach ($entry['illustrations'] as $index => $illustration) {
            $this->attachMedia(
                news      : $news,
                file      : $illustration,
                collection: 'slider',
                order     : $index + 1,
                label     : 'ilustración ' . ($index + 1)
            );
        }

        // 5 ── Documentos adjuntos (collection='document') ──────────────────────
        //      Usan el 'order' definido en el propio array del documento
        //      (convención: 100, 101, …) para que siempre queden después
        //      de las imágenes cuando se ordena por order ASC.
        foreach ($entry['documents'] ?? [] as $index => $document) {
            $order = $document['order'] ?? (100 + $index);
            $this->attachMedia(
                news      : $news,
                file      : $document,
                collection: 'document',
                order     : $order,
                label     : 'documento ' . ($index + 1)
            );
        }
    }

    /**
     * Busca o crea la noticia.
     * Devuelve [News, bool $wasCreated].
     *
     * @return array{0: News, 1: bool}
     */
    private function findOrCreateNews(array $entry, int $categoryId): array
    {
        $news = News::firstOrCreate(
            // ── Clave de búsqueda (único por título) ─────────────────
            ['title' => $entry['title']],
            // ── Valores a insertar si no existe ──────────────────────
            [
                'uuid'         => (string) Str::uuid(),
                'summary'      => $entry['summary']      ?? null,
                'author_name'  => $entry['author_name']  ?? null,
                'category_id'  => $categoryId,
                'published_at' => $entry['published_at'] ?? null,
                'status'       => $entry['status']       ?? 'draft',
                'content'      => $entry['content'],
            ]
        );

        return [$news, $news->wasRecentlyCreated];
    }

    /**
     * Inserta un registro en la tabla `media` asociado a la noticia dada.
     *
     * @param News   $news        Modelo de la noticia a la que se asocia el archivo.
     * @param array  $file        Metadatos del archivo (path, mime, size, title, alt).
     * @param string $collection  Colección del medio: 'cover' | 'slider' | 'document'.
     * @param int    $order       Posición de ordenamiento dentro del listado.
     * @param string $label       Etiqueta de depuración para el output del seeder.
     */
    private function attachMedia(News $news, array $file, string $collection, int $order, string $label): void
    {
        Media::firstOrCreate(
            [
                // getMorphClass() resuelve el alias correcto según el morphMap del AppServiceProvider.
                // Si hay enforceMorphMap(['news' => News::class]), retorna 'news' en lugar del FQCN.
                // Usar News::class directamente IGNORARÍA el morphMap y rompería las queries polimórficas.
                'mediable_type' => $news->getMorphClass(),
                'mediable_id'   => $news->id,
                'path'          => $file['path'],
            ],
            [
                // Colección que clasifica el tipo de archivo en el formulario de edición
                'collection' => $collection,
                'disk'       => self::DISK,
                'mime'       => $file['mime'],
                'size'       => $file['size'],
                'title'      => $file['title'] ?? null,
                'alt'        => $file['alt']   ?? null,
                'order'      => $order,
            ]
        );

        $this->command->line("     ↳ [{$label}] {$file['path']}");
    }
}

