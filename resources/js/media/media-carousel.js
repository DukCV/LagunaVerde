import { detenerMediosEnContenedor } from './video-guard';

/**
 * Alpine.data() del carrusel de imágenes/video. Lo usa directamente
 * image-gallery.blade.php (galería de la noticia) y lo extiende mediante
 * spread de objeto event-carousel.blade.php (carrusel del evento, que
 * además agrega su propio modal de pantalla completa).
 *
 * total()/current() son MÉTODOS, no getters: cuando event-carousel.blade.php
 * combina este objeto con sus propias propiedades vía
 * "{ ...mediaCarousel(items), modalAbierto: false, ... }", el spread de
 * objetos evalúa cualquier getter UNA sola vez en ese instante y congela
 * su valor (deja de ser reactivo). Con métodos en cambio solo se copia la
 * referencia a la función, así que cada llamada sigue leyendo el estado
 * (items/currentIndex) actual.
 */
export default function mediaCarousel(items) {
    return {
        items,
        currentIndex: 0,

        total()   { return this.items.length; },
        current() { return this.items[this.currentIndex]; },

        // Pausa cualquier <video>/<iframe> del carrusel ANTES de cambiar de
        // diapositiva. Se usa $root (no $el): $root siempre resuelve la
        // raíz x-data del componente sin importar qué botón disparó la
        // llamada (prev/next/dots), mientras que $el referenciaría ese
        // botón en concreto y no encontraría ningún <video> dentro de él.
        pausarVideos() {
            detenerMediosEnContenedor(this.$root);
        },

        prev() {
            this.pausarVideos();
            this.currentIndex = this.currentIndex === 0
                ? this.total() - 1
                : this.currentIndex - 1;
        },

        next() {
            this.pausarVideos();
            this.currentIndex = this.currentIndex === this.total() - 1
                ? 0
                : this.currentIndex + 1;
        },

        goTo(index) {
            // Sin esta guarda, hacer clic en el punto de la diapositiva ya
            // activa pausaría su video aunque no haya ninguna navegación real.
            if (index === this.currentIndex) {
                return;
            }

            this.pausarVideos();
            this.currentIndex = index;
        },
    };
}
