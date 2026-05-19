window.App = window.App || { components: {}, views: {} };

window.App.views.Mappa = {
    data() {
        return {
            zoneByIso: {},
            selectedZone: null,
            selectedReading: null,
            selectedReadingMeta: null,
            selectedMix: [],
            selectedNews: [],
            selectedCategorie: [],
            loadingDetail: false,
            saveError: null,
            saveSuccess: false,
            map: null,
            geoLayer: null
        };
    },
    async mounted() {
        await this.loadZone();
        this.initMap();
        await this.loadGeoJson();
    },
    beforeUnmount() {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    },
    methods: {
        async loadZone() {
            try {
                const zone = await window.api.get('/zone?con_ultima=1');
                this.zoneByIso = {};
                for (const z of zone) {
                    if (z.codice_iso) {
                        this.zoneByIso[z.codice_iso.toUpperCase()] = z;
                    }
                }
            } catch (e) {
                console.error('Errore caricamento zone', e);
            }
        },
        initMap() {
            this.map = L.map(this.$refs.mapContainer, {
                worldCopyJump: true,
                minZoom: 2
            }).setView([35, 10], 2);

            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; OpenStreetMap, &copy; CARTO',
                maxZoom: 18,
                subdomains: 'abcd'
            }).addTo(this.map);
        },
        async loadGeoJson() {
            try {
                const res = await fetch('assets/geojson/world-countries.geojson');
                const data = await res.json();
                this.geoLayer = L.geoJSON(data, {
                    style: f => this.styleFeature(f),
                    onEachFeature: (feature, layer) => {
                        const iso2 = this.featureIso2(feature);
                        const zona = iso2 ? this.zoneByIso[iso2] : null;
                        if (zona) {
                            layer.bindTooltip(zona.nome_paese, { sticky: true });
                        }
                        layer.on('click', () => this.selectFeature(feature));
                        layer.on('mouseover', e => e.target.setStyle({ weight: 1.5, fillOpacity: 0.85 }));
                        layer.on('mouseout', e => this.geoLayer.resetStyle(e.target));
                    }
                }).addTo(this.map);
            } catch (e) {
                console.error('Errore caricamento GeoJSON', e);
            }
        },
        featureIso2(feature) {
            const p = (feature && feature.properties) || {};
            const iso = p['ISO3166-1-Alpha-2'] || p.iso_a2 || p.ISO_A2 || p.postal;
            return (typeof iso === 'string' && iso.length === 2) ? iso.toUpperCase() : null;
        },
        styleFeature(feature) {
            const iso2 = this.featureIso2(feature);
            const zona = iso2 ? this.zoneByIso[iso2] : null;
            const co2 = zona && zona.ultima_carbon_intensity != null
                ? Number(zona.ultima_carbon_intensity)
                : null;

            let color = '#cbd5e1';
            if (co2 !== null && !isNaN(co2)) {
                if (co2 < 200) color = '#16a34a';
                else if (co2 < 500) color = '#eab308';
                else color = '#dc2626';
            }
            return {
                fillColor: color,
                color: '#94a3b8',
                weight: 0.5,
                fillOpacity: 0.6
            };
        },
        async selectFeature(feature) {
            const iso2 = this.featureIso2(feature);
            const nome = (feature.properties && (feature.properties.name || feature.properties.admin)) || iso2 || 'Paese';
            const zona = iso2 ? this.zoneByIso[iso2] : null;

            this.selectedReading = null;
            this.selectedReadingMeta = null;
            this.selectedMix = [];
            this.selectedNews = [];
            this.selectedCategorie = [];
            this.saveError = null;
            this.saveSuccess = false;

            if (!zona) {
                this.selectedZone = { nome_paese: nome, codice_iso: iso2, no_data: true };
                return;
            }

            this.selectedZone = zona;
            this.loadingDetail = true;
            try {
                const [live, news, cats] = await Promise.all([
                    window.api.get('/zone/' + zona.id + '/lettura-live').catch(() => null),
                    window.api.get('/zone/' + zona.id + '/news').catch(() => null),
                    window.api.get('/zone/' + zona.id + '/categorie').catch(() => [])
                ]);
                if (live) {
                    this.selectedReading = live.lettura;
                    this.selectedReadingMeta = { fonte: live.fonte, stale: live.stale };
                    this.selectedMix = live.mix || [];
                }
                if (news) {
                    this.selectedNews = news.news || [];
                }
                this.selectedCategorie = Array.isArray(cats) ? cats : [];
            } finally {
                this.loadingDetail = false;
            }
        },
        async addToFavorites() {
            if (!this.$root.currentUser) {
                window.location.hash = '#/login';
                return;
            }
            this.saveError = null;
            this.saveSuccess = false;
            try {
                await window.api.post('/zone-salvate', {
                    codice_zona: this.selectedZone.codice_zona
                });
                this.saveSuccess = true;
            } catch (e) {
                this.saveError = (e.data && e.data.error) || e.message;
            }
        },
        goToDetail() {
            if (this.selectedZone && this.selectedZone.id) {
                window.location.hash = '#/zone/' + this.selectedZone.id;
            }
        },
        round1(v) {
            const n = Number(v);
            return isNaN(n) ? '—' : n.toFixed(1);
        }
    },
    template: `
        <div class="mappa-layout">
            <div ref="mapContainer" class="mappa-canvas"></div>

            <aside class="mappa-sidebar">
                <div v-if="!selectedZone" class="card">
                    <h3>Esplora la mappa</h3>
                    <p class="muted">Clicca un paese per vedere intensita' di carbonio, mix energetico e notizie.</p>
                    <div style="margin-top: 1rem; font-size: .85rem; line-height: 1.8;">
                        <span class="legend-dot" style="background:#16a34a"></span> &lt; 200 gCO₂/kWh<br>
                        <span class="legend-dot" style="background:#eab308"></span> 200 – 500<br>
                        <span class="legend-dot" style="background:#dc2626"></span> &gt; 500<br>
                        <span class="legend-dot" style="background:#cbd5e1"></span> Dati non disponibili
                    </div>
                </div>

                <template v-else>
                    <div class="card">
                        <h3>{{ selectedZone.nome_paese }}</h3>

                        <div v-if="selectedCategorie.length > 0" style="margin:.5rem 0; display:flex; gap:.4rem; flex-wrap:wrap;">
                            <span v-for="c in selectedCategorie" :key="c.id"
                                  class="badge category-badge"
                                  :style="{ background: c.colore_hex + '22', color: c.colore_hex, borderColor: c.colore_hex + '66' }">
                                {{ c.nome }}
                            </span>
                        </div>

                        <p v-if="selectedZone.no_data" class="muted">Nessun dato disponibile per questo paese.</p>

                        <div v-else-if="loadingDetail" class="muted">Caricamento…</div>

                        <div v-else-if="selectedReading">
                            <p style="font-size:2rem; font-weight:700; margin:.5rem 0;">
                                {{ Math.round(selectedReading.carbon_intensity) }}
                                <span style="font-size:.9rem; font-weight:400; color:var(--secondario);">gCO₂eq/kWh</span>
                            </p>
                            <p class="muted" style="font-size:.85rem;">
                                Rinnovabile: <strong>{{ round1(selectedReading.renewable_pct) }}%</strong>
                                · Fossil-free: <strong>{{ round1(selectedReading.fossil_free_pct) }}%</strong>
                            </p>
                            <p v-if="selectedReadingMeta && selectedReadingMeta.stale" class="muted" style="font-size:.8rem; color:var(--errore);">
                                Dato non aggiornato (API esterna non disponibile)
                            </p>
                        </div>

                        <div v-if="!selectedZone.no_data" style="display:flex; gap:.5rem; margin-top:1rem; flex-wrap:wrap;">
                            <button class="btn btn-primary" @click="addToFavorites">Aggiungi ai preferiti</button>
                            <button class="btn" @click="goToDetail">Dettaglio</button>
                        </div>
                        <p v-if="saveError" class="error">{{ saveError }}</p>
                        <p v-if="saveSuccess" class="ok" style="margin-top:.5rem; font-size:.9rem;">Salvata nei preferiti.</p>
                    </div>

                    <div v-if="selectedMix.length > 0" class="card" style="margin-top:1rem;">
                        <h3>Mix energetico</h3>
                        <mix-bar v-for="m in selectedMix" :key="m.codice"
                                 :nome="m.nome" :percentuale="m.percentuale" :categoria="m.categoria">
                        </mix-bar>
                    </div>

                    <div v-if="selectedNews.length > 0" class="card" style="margin-top:1rem;">
                        <h3>Notizie</h3>
                        <news-card v-for="n in selectedNews.slice(0, 5)" :key="n.id" :news="n"></news-card>
                    </div>
                </template>
            </aside>
        </div>
    `
};
