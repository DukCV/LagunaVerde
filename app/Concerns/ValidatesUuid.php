<?php

namespace App\Concerns;

/**
 * Trait para centralizar la validación de UUIDs.
 *
 * RESPONSABILIDAD:
 *  - Proveer un método único, seguro y DRY (Don't Repeat Yourself) para
 *    validar UUIDs v4/RFC-4122 antes de usarlos en consultas a la BD.
 *
 * SEGURIDAD:
 *  - Evita que strings maliciosos o malformados lleguen al motor de base de datos.
 *  - Sirve como primera capa de defensa (Defensa en Profundidad).
 */
trait ValidatesUuid
{
    /**
     * Patrón regex para validar un UUID estándar (RFC-4122).
     */
    protected const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Verifica si el string proporcionado tiene el formato de un UUID válido.
     */
    protected function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match(self::UUID_PATTERN, $uuid);
    }
}
