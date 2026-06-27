/**
 * Detiene cualquier <video> o <iframe> de video en reproducción dentro de
 * 'raiz' (un contenedor, document, o el propio elemento de medio).
 *
 * Por qué existe: x-show de Alpine solo alterna 'display: none' — el
 * <video> sigue montado y reproduciéndose en segundo plano salvo que se
 * pause explícitamente, y eso es exactamente lo que congela la página al
 * navegar un slider o cambiar de álbum/imagen con un video de fondo aún
 * activo. Esta función centraliza esa limpieza en un solo lugar para que
 * el slider, la galería, el modal de pantalla completa y la navegación de
 * Livewire (wire:navigate) usen siempre la misma lógica — cero duplicación.
 *
 * - <video>: .pause() + currentTime = 0, tal como exige un slider (al
 *   volver a esa diapositiva más adelante, el video arranca desde el
 *   principio en vez de seguir donde quedó).
 * - <iframe> (embeds de YouTube/Vimeo sin su SDK cargado): no expone
 *   .pause(), así que la única forma fiable de detener su reproducción sin
 *   peticiones nuevas al servidor es forzar su propia recarga reasignando
 *   el mismo 'src' que ya tiene — técnica estándar para iframes de origen
 *   cruzado.
 *
 * @param {Document|Element|null} raiz
 */
export function detenerMediosEnContenedor(raiz = document) {
    if (!raiz) {
        return;
    }

    const videos = raiz.matches?.('video')
        ? [raiz, ...raiz.querySelectorAll('video')]
        : raiz.querySelectorAll('video');

    videos.forEach((video) => {
        if (!video.paused) {
            video.pause();
        }

        try {
            video.currentTime = 0;
        } catch {
            // El navegador puede rechazar esto si el video aún no cargó su
            // metadata (readyState 0) — no hay nada que limpiar en ese caso.
        }
    });

    const iframes = raiz.matches?.('iframe')
        ? [raiz, ...raiz.querySelectorAll('iframe[src]')]
        : raiz.querySelectorAll('iframe[src]');

    iframes.forEach((iframe) => {
        const src = iframe.getAttribute('src');

        if (src) {
            iframe.setAttribute('src', src);
        }
    });
}
