window.App = window.App || { components: {}, views: {} };

window.App.views.ZonaDettaglio = {
    data() {
        return {
            zona: null,
            ultimaLettura: null,
            mix: [],
            categorie: [],
            letture: [],
            news: [],
            loading: true,
            error: null
        };
    },
    computed: {
        zonaId() {
            return Number(this.$root.currentRoute.params.id) || 0;
        }
    },
    async mounted() {
        await this.load();
    },
    watch: {
        zonaId() { this.load(); }
    },
    methods: {
        async load() {
            if (!this.zonaId) {
                this.error = 'Zona non specificata';
                this.loading = false;
                return;
            }
            this.loading = true;
            this.error = null;
            try {
                const [info, storico, news] = await Promise.all([
                    window.api.get('/zone/' + this.zonaId),
                    window.api.get('/zone/' + this.zonaId + '/letture-storiche?giorni=7').catch(() => ({ letture: [] })),
                    window.api.get('/zone/' + this.zonaId + '/news').catch(() => ({ news: [] }))
                ]);
                this.zona = info.zona;
                this.ultimaLettura = info.ultima_lettura;
                this.mix = info.mix || [];
                this.categorie = info.categorie || [];
                this.letture = storico.letture || [];
                this.news = news.news || [];
            } catch (e) {
                this.error = (e.data && e.data.error) || e.message;
            } finally {
                this.loading = false;
            }
        },
        formatDate(s) {
            if (!s) return '';
            const d = new Date(s);
            return isNaN(d) ? s : d.toLocaleString('it-IT');
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

            <template v-else-if="zona">
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
                        <div>
                            <h1>{{ zona.nome_paese }}</h1>
                            <p class="muted">Codice: {{ zona.codice_zona }}</p>
                            <div v-if="categorie.length > 0" style="margin-top:.75rem; display:flex; gap:.4rem; flex-wrap:wrap;">
                                <span v-for="c in categorie" :key="c.id" class="badge category-badge"
                                      :style="{ background: c.colore_hex + '22', color: c.colore_hex, borderColor: c.colore_hex + '66' }">
                                    {{ c.nome }}
                                </span>
                            </div>
                        </div>
                        <div v-if="ultimaLettura" style="text-align:right;">
                            <p style="font-size:2.5rem; font-weight:700; line-height:1;">
                                {{ Math.round(ultimaLettura.carbon_intensity) }}
                            </p>
                            <p class="muted">gCO₂eq/kWh</p>
                            <p class="muted" style="font-size:.8rem;">
                                {{ formatDate(ultimaLettura.rilevata_il) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="mix.length > 0" class="card" style="margin-top:1rem;">
                    <h2>Mix energetico</h2>
                    <mix-bar v-for="m in mix" :key="m.codice"
                             :nome="m.nome" :percentuale="m.percentuale" :categoria="m.categoria">
                    </mix-bar>
                </div>

                <div v-if="letture.length > 0" class="card" style="margin-top:1rem;">
                    <h2>Storico ultimi 7 giorni</h2>
                    <table class="storico-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>CO₂ (gCO₂/kWh)</th>
                                <th>Rinnovabile</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="l in letture" :key="l.id">
                                <td>{{ formatDate(l.rilevata_il) }}</td>
                                <td>{{ Math.round(l.carbon_intensity) }}</td>
                                <td>{{ round1(l.renewable_pct) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="news.length > 0" class="card" style="margin-top:1rem;">
                    <h2>Notizie</h2>
                    <news-card v-for="n in news.slice(0, 8)" :key="n.id" :news="n"></news-card>
                </div>

                <div class="card" style="margin-top:1rem;">
                    <h2>Commenti</h2>
                    <comment-list :zona-id="zonaId"></comment-list>
                </div>
            </template>
        </div>
    `
};
