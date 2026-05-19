window.App = window.App || { components: {}, views: {} };

window.App.views.Statistiche = {
    data() {
        return {
            riepilogo: null,
            topCo2: [],
            zoneCategorizzate: [],
            categorieByCodice: {},
            giorni: 7,
            limite: 10,
            loading: true,
            error: null
        };
    },
    async mounted() {
        await this.loadAll();
    },
    watch: {
        giorni() { this.loadTopCo2(); }
    },
    computed: {
        topCo2Labels() {
            return this.topCo2.map(z => z.nome_paese);
        },
        topCo2Datasets() {
            return [{
                label: 'gCO₂eq/kWh',
                data: this.topCo2.map(z => z.media_co2 != null ? Math.round(z.media_co2) : 0),
                backgroundColor: this.topCo2.map(z => {
                    const v = Number(z.media_co2);
                    if (isNaN(v)) return '#94a3b8';
                    if (v < 200) return '#16a34a';
                    if (v < 500) return '#eab308';
                    return '#dc2626';
                })
            }];
        }
    },
    methods: {
        async loadAll() {
            this.loading = true;
            this.error = null;
            try {
                const [riep, top, cat, categorie] = await Promise.all([
                    window.api.get('/statistiche/riepilogo'),
                    window.api.get('/statistiche/top-co2?limite=' + this.limite + '&giorni=' + this.giorni),
                    window.api.get('/statistiche/zone-categorizzate'),
                    window.api.get('/categorie')
                ]);
                this.riepilogo = riep;
                this.topCo2 = top.zone || [];
                this.zoneCategorizzate = cat || [];
                const map = {};
                for (const c of (categorie || [])) map[c.codice] = c;
                this.categorieByCodice = map;
            } catch (e) {
                this.error = (e.data && e.data.error) || e.message;
            } finally {
                this.loading = false;
            }
        },
        async loadTopCo2() {
            try {
                const top = await window.api.get('/statistiche/top-co2?limite=' + this.limite + '&giorni=' + this.giorni);
                this.topCo2 = top.zone || [];
            } catch (e) {
                console.error(e);
            }
        },
        catLabel(codice) {
            const c = this.categorieByCodice[codice];
            return c ? c.nome : codice;
        },
        catStyle(codice) {
            const c = this.categorieByCodice[codice];
            const col = (c && c.colore_hex) || '#64748b';
            return {
                background: col + '22',
                color: col,
                borderColor: col + '66'
            };
        }
    },
    template: `
        <div>
            <h1>Statistiche</h1>

            <p v-if="loading" class="muted">Caricamento…</p>
            <p v-else-if="error" class="error">{{ error }}</p>

            <template v-else>
                <div v-if="riepilogo" class="riepilogo-grid">
                    <div class="stat-card">
                        <p class="stat-label">Zone monitorate</p>
                        <p class="stat-value">{{ riepilogo.totale_zone }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Letture totali</p>
                        <p class="stat-value">{{ riepilogo.totale_letture }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">CO₂ media (24h)</p>
                        <p class="stat-value">
                            {{ riepilogo.media_co2_24h != null ? Math.round(riepilogo.media_co2_24h) : '—' }}
                        </p>
                        <p class="stat-unit">gCO₂eq/kWh</p>
                    </div>
                </div>

                <div class="card" style="margin-bottom:1rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.75rem; margin-bottom:1rem;">
                        <h2 style="margin:0;">Top zone per CO₂</h2>
                        <select class="input" style="width:auto; margin:0;" v-model.number="giorni">
                            <option :value="7">Ultimi 7 giorni</option>
                            <option :value="30">Ultimi 30 giorni</option>
                            <option :value="90">Ultimi 90 giorni</option>
                        </select>
                    </div>
                    <chart-card v-if="topCo2.length"
                                type="bar"
                                :labels="topCo2Labels"
                                :datasets="topCo2Datasets"></chart-card>
                    <p v-else class="muted">Nessun dato per il periodo selezionato.</p>
                </div>

                <div v-if="riepilogo && riepilogo.top3_green" class="cards-grid" style="margin-top:1rem;">
                    <div class="card">
                        <h3>Più green (7gg)</h3>
                        <ol style="padding-left:1.25rem; margin-top:.5rem;">
                            <li v-for="z in riepilogo.top3_green" :key="z.id">
                                {{ z.nome_paese }} —
                                <strong>{{ Math.round(z.media_co2) }}</strong> gCO₂/kWh
                            </li>
                        </ol>
                    </div>
                    <div class="card">
                        <h3>Più inquinanti (7gg)</h3>
                        <ol style="padding-left:1.25rem; margin-top:.5rem;">
                            <li v-for="z in riepilogo.top3_carbon_heavy" :key="z.id">
                                {{ z.nome_paese }} —
                                <strong>{{ Math.round(z.media_co2) }}</strong> gCO₂/kWh
                            </li>
                        </ol>
                    </div>
                </div>

                <div class="card" style="margin-top:1rem;">
                    <h2>Categorie per zona</h2>
                    <table class="storico-table">
                        <thead>
                            <tr>
                                <th>Paese</th>
                                <th>Categorie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="z in zoneCategorizzate" :key="z.zona_id">
                                <td>{{ z.nome_paese }}</td>
                                <td>
                                    <template v-if="z.categorie_codici">
                                        <span v-for="codice in z.categorie_codici.split(',')" :key="codice"
                                              class="badge category-badge"
                                              :style="catStyle(codice)">
                                            {{ catLabel(codice) }}
                                        </span>
                                    </template>
                                    <span v-else class="muted">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    `
};
