<?php

namespace App\Support\Maps;

/**
 * Construye URLs de Google Maps (iframe embebido y enlace "abrir en Maps")
 * a partir de un texto de ubicación en lenguaje libre (sin geocodificación
 * ni coordenadas — Google Maps resuelve la búsqueda del lado del cliente).
 *
 * ÚNICA FUENTE DE VERDAD: tanto la vista previa en vivo del formulario admin
 * (LocationForm/_location-modality.blade.php) como la página pública del
 * evento (EventDetailDto/event-detail-page.blade.php) reutilizan este
 * helper a través de <x-google-maps-embed> — evita duplicar la construcción
 * de la URL y el urlencode() en varios archivos.
 *
 * SEGURIDAD:
 *  - urlencode() neutraliza cualquier carácter capaz de romper el atributo
 *    HTML o inyectar markup (<, >, ", ', &) antes de interpolarse en el
 *    src del iframe. La vista aplica además {{ }} (htmlspecialchars) sobre
 *    el resultado — doble capa, sin lógica de escape adicional aquí.
 *  - No se realiza ninguna llamada saliente: solo se construye una URL.
 */
final class GoogleMapsEmbed
{
    private const EMBED_URL  = 'https://maps.google.com/maps';
    private const SEARCH_URL = 'https://maps.google.com/';

    /** URL del iframe embebido (output=embed), o null si no hay ubicación. */
    public static function embedUrl(?string $location, int $zoom = 15): ?string
    {
        $direccion = trim((string) $location);

        if ($direccion === '') {
            return null;
        }

        return self::EMBED_URL . '?q=' . urlencode($direccion) . '&z=' . $zoom . '&output=embed';
    }

    /** URL para abrir la ubicación en una pestaña de Google Maps, o null si no hay ubicación. */
    public static function searchUrl(?string $location): ?string
    {
        $direccion = trim((string) $location);

        if ($direccion === '') {
            return null;
        }

        return self::SEARCH_URL . '?q=' . urlencode($direccion);
    }
}
