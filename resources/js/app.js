// Punto de entrada de Vite. El editor de contenido (Trix) no requiere
// JS propio: <x-rich-text::trix wire:model="..."> ya gestiona la
// sincronización con Livewire mediante Alpine vía atributos en el Blade.

import mediaSorter from './admin/media-sorter';
import mediaCarousel from './media/media-carousel';
import { detenerMediosEnContenedor } from './media/video-guard';

// Se registra como Alpine.data() — NO como función global suelta en un
// <script> dentro de un Blade de Livewire — porque EventForm se monta vía
// AJAX dentro de EventsManagement (wire:click="crearEvento"/"editarEvento").
// Cualquier <script> insertado así por Livewire jamás se ejecuta (protección
// del navegador contra scripts inyectados dinámicamente), así que una
// función global definida en el propio Blade quedaría indefinida y Alpine
// fallaría en silencio al evaluar su x-data. Este archivo, en cambio, se
// carga una sola vez de forma normal en el <head> del layout raíz — por eso
// es el lugar correcto para definir este componente reutilizable.
document.addEventListener('alpine:init', () => {
    Alpine.data('mediaSorter', mediaSorter);
    Alpine.data('mediaCarousel', mediaCarousel);
});

// ────────────────────────────────────────────────────────────────────────
// Limpieza global de video/audio en segundo plano (sliders, galería
// multimedia, modales) — registrada UNA sola vez aquí en vez de repetirla
// por componente, porque debe cubrir TODA la página, no solo el carrusel
// donde el usuario hizo clic.
// ────────────────────────────────────────────────────────────────────────

// 'wire:navigate' reemplaza el DOM de la página sin recarga completa (SPA).
// Sin esto, un <video> o <iframe> que seguía reproduciéndose en la página
// de origen queda huérfano sonando de fondo durante y después del cambio
// de página — justo el "freeze" global reportado en producción.
document.addEventListener('livewire:navigating', () => {
    detenerMediosEnContenedor(document);
});

// Defensa adicional para re-renders parciales (sin navegación completa):
// si un morph de Livewire va a eliminar un nodo que todavía contiene un
// <video>/<iframe> en reproducción, se detiene justo antes de borrarlo.
document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.removing', ({ el }) => {
        detenerMediosEnContenedor(el);
    });
});
