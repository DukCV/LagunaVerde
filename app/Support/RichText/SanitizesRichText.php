<?php

namespace App\Support\RichText;

/**
 * Sanitización de HTML generado por el editor Trix (defensa en profundidad).
 *
 * Trix ya sanitiza en el cliente; este trait es la segunda línea de defensa
 * en el servidor. Usa DOMDocument para recorrer el árbol real, evitando
 * falsos positivos de regex con HTML anidado o mal formado.
 *
 * Etiquetas prohibidas: script, iframe, form, style, meta, link,
 *   object, embed, applet, base, svg, math.
 * Atributos prohibidos: todos los event handlers (on*),
 *   href/src/action con protocolo 'javascript:'.
 *
 * Extraído de NewsFormService — usado idénticamente por AdminEventsFormService.
 */
trait SanitizesRichText
{
    private function sanitizarContenido(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        // Cabecera BOM para que DOMDocument interprete correctamente UTF-8
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $this->limpiarNodo($dom);

        // Reconstruir HTML sin los wrappers <html>/<body> que añade DOMDocument
        $resultado = '';
        foreach ($dom->childNodes as $nodo) {
            $resultado .= $dom->saveHTML($nodo);
        }

        return trim($resultado);
    }

    /**
     * Recorre recursivamente el árbol DOM y elimina nodos/atributos peligrosos.
     */
    private function limpiarNodo(\DOMNode $nodo): void
    {
        // Etiquetas cuyo contenido debe eliminarse completamente
        static $etiquetasProhibidas = [
            'script', 'style', 'iframe', 'form', 'input', 'button',
            'select', 'textarea', 'meta', 'link', 'object', 'embed',
            'applet', 'base', 'svg', 'math',
        ];

        $nodosAEliminar = [];

        foreach ($nodo->childNodes as $hijo) {
            if (! $hijo instanceof \DOMElement) {
                continue;
            }

            // Eliminar etiquetas prohibidas con todo su contenido
            if (in_array(strtolower($hijo->tagName), $etiquetasProhibidas, strict: true)) {
                $nodosAEliminar[] = $hijo;
                continue;
            }

            // Limpiar atributos de eventos y URIs con javascript:
            $atributosAEliminar = [];
            foreach ($hijo->attributes as $atributo) {
                $nombre = strtolower($atributo->name);
                $valor  = strtolower(trim($atributo->value));

                // Eliminar todos los event handlers (onclick, onerror, etc.)
                if (str_starts_with($nombre, 'on')) {
                    $atributosAEliminar[] = $atributo->name;
                    continue;
                }

                // Eliminar URIs con protocolo javascript: en href, src, action, data
                if (in_array($nombre, ['href', 'src', 'action', 'data'], strict: true)
                    && preg_match('/^\s*javascript\s*:/i', $valor)) {
                    $atributosAEliminar[] = $atributo->name;
                }
            }

            foreach ($atributosAEliminar as $nombre) {
                $hijo->removeAttribute($nombre);
            }

            // Recursión en nodos hijos antes de procesarlos
            $this->limpiarNodo($hijo);
        }

        // Eliminar los nodos marcados DESPUÉS del loop para no invalidar el iterador
        foreach ($nodosAEliminar as $eliminar) {
            $nodo->removeChild($eliminar);
        }
    }
}
