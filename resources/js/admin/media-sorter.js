/**
 * Alpine.data() de la grilla de miniaturas arrastrables (drag & drop) de la
 * Galería Multimedia del formulario de eventos.
 *
 * Vive en el bundle de Vite por la misma razón que event-location-map.js:
 * EventForm se monta vía AJAX dentro de EventsManagement, y un <script>
 * insertado así nunca se ejecuta — una función global definida en el propio
 * Blade quedaría indefinida y x-data="mediaSorter(...)" fallaría en
 * silencio, dejando la grilla de miniaturas sin reordenamiento ni botones
 * de eliminar funcionales.
 */
export default function mediaSorter(initialItems) {
    return {
        items: initialItems,
        dragIndex: null,

        onDragStart(index) {
            this.dragIndex = index;
        },

        onDragOver(index) {
            if (this.dragIndex === null || this.dragIndex === index) {
                return;
            }

            const movido = this.items.splice(this.dragIndex, 1)[0];
            this.items.splice(index, 0, movido);
            this.dragIndex = index;
        },

        onDragEnd() {
            if (this.dragIndex === null) {
                return;
            }

            this.dragIndex = null;
            // this.$wire (no "$wire" suelto): mismo motivo que en
            // event-location-map.js — aquí Alpine solo expone sus magics a
            // través de "this", al estar en un módulo JS, no en un atributo
            // Blade evaluado directamente por Alpine.
            this.$wire.reordenarMedios(this.items.map((item) => item.key));
        },
    };
}
