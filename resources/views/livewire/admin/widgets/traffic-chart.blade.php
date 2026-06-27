<div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">

    {{-- Cabecera del widget con filtros de rango --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-1">Análisis de Tráfico</h2>
            <p class="text-sm text-gray-500">Visitas reales al sitio web</p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            {{-- Selector de rango temporal --}}
            <div class="flex bg-gray-100 rounded-lg p-1">
                @foreach(['dia' => 'Día', 'semana' => 'Semana', 'anio' => 'Año'] as $valor => $etiqueta)
                    <button
                        wire:click="cambiarRango('{{ $valor }}')"
                        class="px-3 py-1.5 text-sm rounded-md transition-colors {{ $rango === $valor ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}"
                        aria-pressed="{{ $rango === $valor ? 'true' : 'false' }}"
                    >
                        {{ $etiqueta }}
                    </button>
                @endforeach
            </div>

            {{-- Toggle de comparación con período anterior --}}
            <button
                wire:click="toggleComparar"
                class="px-3 py-1.5 text-sm rounded-lg border transition-colors {{ $comparar ? 'bg-blue-50 border-blue-600 text-blue-600' : 'border-gray-300 text-gray-600 hover:border-gray-400' }}"
                aria-pressed="{{ $comparar ? 'true' : 'false' }}"
            >
                Comparar período
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{-- Gráfico (Alpine.js + Chart.js)                                    --}}
    {{-- wire:key fuerza la recreación del componente Alpine cuando cambian --}}
    {{-- los filtros, lo que reinicializa Chart.js con datos frescos.       --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}
    <div
        wire:key="chart-{{ $rango }}-{{ $comparar ? '1' : '0' }}"
        wire:ignore
        x-data="{
            chart: null,
            datos: @js($datos),
            comparar: @js($comparar),

            init() {
                this.$nextTick(() => { this.renderChart(); });
            },

            renderChart() {
                if (this.chart) {
                    this.chart.destroy();
                    this.chart = null;
                }

                const ctx = this.$refs.canvas.getContext('2d');

                const datasets = [
                    {
                        label: 'Visitas actuales',
                        data: this.datos.visitas,
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59,130,246,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }
                ];

                if (this.comparar && this.datos.previas.some(v => v > 0)) {
                    datasets.push({
                        label: 'Período anterior',
                        data: this.datos.previas,
                        borderColor: '#9CA3AF',
                        backgroundColor: 'rgba(156,163,175,0.1)',
                        borderDash: [5, 5],
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                    });
                }

                this.chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: this.datos.labels,
                        datasets: datasets,
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: datasets.length > 1 },
                            tooltip: {
                                backgroundColor: '#fff',
                                titleColor: '#111827',
                                bodyColor: '#6B7280',
                                borderColor: '#E5E7EB',
                                borderWidth: 1,
                                padding: 12,
                            }
                        },
                        scales: {
                            x: {
                                grid: { color: '#F3F4F6' },
                                ticks: { color: '#6B7280', font: { size: 12 } }
                            },
                            y: {
                                grid: { color: '#F3F4F6' },
                                ticks: { color: '#6B7280', font: { size: 12 } },
                                beginAtZero: true,
                            }
                        }
                    }
                });
            }
        }"
        class="h-80"
    >
        <canvas x-ref="canvas" class="w-full h-full"></canvas>
    </div>

</div>
