window.App = window.App || { components: {}, views: {} };

const TREND_COLORS = [
    '#16a34a', '#dc2626', '#3b82f6', '#eab308', '#8b5cf6',
    '#ec4899', '#06b6d4', '#f97316', '#a16207', '#0ea5e9'
];

window.App.views.ConfrontoDettaglio = {
    data() {
        return {
            confronto: null,
            zoneList: [],
            trendByZona: {},
            loading: true,
            error: null,
            deleting: false
        };
    },
    computed: {
        confrontoId() {
            return Number(this.$root.currentRoute.params.id) || 0;
        },
        co2Labels() {
            return this.zoneList.map(z => z.nome_paese);
        },
        co2Datasets() {
            return [{
                label: 'CO₂ media 7gg (gCO₂eq/kWh)',
                data: this.zoneList.map(z => z.media_co2_7d != null ? Math.round(z.media_co2_7d) : 0),
                backgroundColor: this.zoneList.map(z => {
                    const v = Number(z.media_co2_7d);
                    if (isNaN(v)) return '#94a3b8';
                    if (v < 200) return '#16a34a';
                    if (v < 500) return '#eab308';
                    return '#dc2626';
                })
            }];
        },
        trendChart() {
            const allTs = new Set();
            for (const id in this.trendByZona) {
                this.trendByZona[id].forEach(l => allTs.add(l.rilevata_il));
            }
            const sorted = [...allTs].sort();
            const labels = sorted.map(t => {
                const d = new Date(t);
                return isNaN(d) ? t : d.toLocaleString('it-IT', {
                    day: '2-digit', month: '2-digit',
                    hour: '2-digit', minute: '2-digit'
                });
            });
            const datasets = this.zoneList.map((z, i) => {
                const map = new Map();
                (this.trendByZona[z.zona_id] || []).forEach(l => {
                    map.set(l.rilevata_il, Number(l.carbon_intensity));
                });
                const color = TREND_COLORS[i % TREND_COLORS.length];
                return {
                    label: z.nome_paese,
                    data: sorted.map(t => map.has(t) ? map.get(t) : null),
                    borderColor: color,
                    backgroundColor: color + '33',
                    tension: 0.2,
                    spanGaps: true,
                    pointRadius: 2
                };
            });
            return { labels, datasets };
        }
    },
    async mounted() {
        await this.load();
    },
    watch: {
        confrontoId() { this.load(); }
    },
    methods: {
        async load() {
            if (!this.confrontoId) {
                this.error = 'Confronto non specificato';
                this.loading = false;
                return;
            }
            this.loading = true;
            this.error = null;
            try {
                const data = await window.api.get('/confronti/' + this.confrontoId);
                this.confronto = data.confronto;
                this.zoneList = data.zone || [];

                const trends = await Promise.all(
                    this.zoneList.map(z =>
                        window.api.get('/zone/' + z.zona_id + '/letture-storiche?giorni=7')
                            .then(r => ({ zona_id: z.zona_id, letture: r.letture || [] }))
                            .catch(() => ({ zona_id: z.zona_id, letture: [] }))
                    )
                );
                const map = {};
                for (const t of trends) map[t.zona_id] = t.letture;
                this.trendByZona = map;
            } catch (e) {
                if (e.status === 401) {
                    window.location.hash = '#/login';
                    return;
                }
                if (e.status === 404) {
                    this.error = 'Confronto non trovato';
                } else {
                    this.error = (e.data && e.data.error) || e.message;
                }
            } finally {
                this.loading = false;
            }
        },
        async deleteConfronto() {
            if (!confirm('Eliminare definitivamente questo confronto?')) return;
            this.deleting = true;
            try {
                await window.api.del('/confronti/' + this.confrontoId);
                window.location.hash = '#/confronti';
            } catch (e) {
                alert((e.data && e.data.error) || 'Errore eliminazione');
            } finally {
                this.deleting = false;
            }
        },
        round1(v) {
            const n = Number(v);
            return isNaN(n) ? '—' : n.toFixed(1);
        }
    },
    template: `
        <div>
            <p v-if="loading" class="muted">Caricamento…</p>
            <p v-else-if="error" class="error">{{ error }}</p>

            <template v-else-if="confronto">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h1>{{ confronto.nome }}</h1>
                        <p v-if="confronto.descrizione" class="muted" style="white-space:pre-wrap;">
                            {{ confronto.descrizione }}
                        </p>
                    </div>
                    <button class="btn" :disabled="deleting" @click="deleteConfronto">
                        {{ deleting ? 'Eliminazione…' : 'Elimina confronto' }}
                    </button>
                </div>

                <div class="cards-grid">
                    <div v-for="z in zoneList" :key="z.zona_id" class="card">
                        <h3>{{ z.nome_paese }}</h3>
                        <p>
                            <strong style="font-size:1.5rem;">
                                {{ z.ultima_co2 != null ? Math.round(z.ultima_co2) : '—' }}
                            </strong>
                            <span class="muted">gCO₂/kWh attuale</span>
                        </p>
                        <p class="muted" style="font-size:.9rem;">
                            Media 7gg:
                            <strong>{{ z.media_co2_7d != null ? Math.round(z.media_co2_7d) : '—' }}</strong>
                            gCO₂/kWh
                        </p>
                        <p class="muted" style="font-size:.9rem;">
                            Rinnovabile media: <strong>{{ round1(z.media_renew_7d) }}%</strong>
                        </p>
                        <a :href="'#/zone/' + z.zona_id" class="btn btn-sm" style="margin-top:.75rem;">
                            Dettaglio zona
                        </a>
                    </div>
                </div>

                <div class="card" style="margin-top:1rem;">
                    <h2>CO₂ media (7gg)</h2>
                    <chart-card v-if="zoneList.length"
                                type="bar"
                                :labels="co2Labels"
                                :datasets="co2Datasets"></chart-card>
                </div>

                <div class="card" style="margin-top:1rem;">
                    <h2>Trend ultimi 7 giorni</h2>
                    <chart-card v-if="trendChart.labels.length"
                                type="line"
                                :labels="trendChart.labels"
                                :datasets="trendChart.datasets"></chart-card>
                    <p v-else class="muted">Nessuna lettura storica disponibile.</p>
                </div>
            </template>
        </div>
    `
};
