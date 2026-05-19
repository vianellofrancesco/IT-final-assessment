window.App = window.App || { components: {}, views: {} };

window.App.views.Confronti = {
    data() {
        return {
            confronti: [],
            zone: [],
            loading: true,
            error: null,

            modaleAperta: false,
            formNome: '',
            formDescrizione: '',
            formZoneIds: [],
            formErrore: null,
            formInvio: false
        };
    },
    async mounted() {
        await this.caricaElenco();
    },
    methods: {
        async caricaElenco() {
            this.loading = true;
            this.error = null;
            try {
                this.confronti = await window.api.get('/confronti');
            } catch (e) {
                if (e.status === 401) {
                    window.location.hash = '#/login';
                    return;
                }
                this.error = (e.data && e.data.error) || e.message;
            } finally {
                this.loading = false;
            }
        },
        async apriModale() {
            this.modaleAperta = true;
            this.formErrore = null;
            this.formNome = '';
            this.formDescrizione = '';
            this.formZoneIds = [];
            if (this.zone.length === 0) {
                try {
                    this.zone = await window.api.get('/zone');
                } catch (e) {
                    this.formErrore = 'Impossibile caricare la lista zone';
                }
            }
        },
        chiudiModale() { this.modaleAperta = false; },
        async creaConfronto() {
            if (!this.formNome.trim() || this.formZoneIds.length === 0) return;
            this.formInvio = true;
            this.formErrore = null;
            try {
                const creato = await window.api.post('/confronti', {
                    nome: this.formNome.trim(),
                    descrizione: this.formDescrizione || null,
                    zone_ids: this.formZoneIds.map(id => Number(id))
                });
                this.chiudiModale();
                if (creato && creato.id) {
                    window.location.hash = '#/confronti/' + creato.id;
                } else {
                    await this.caricaElenco();
                }
            } catch (e) {
                this.formErrore = (e.data && e.data.error) || e.message;
            } finally {
                this.formInvio = false;
            }
        },
        formatDate(s) {
            if (!s) return '';
            const d = new Date(s);
            return isNaN(d) ? s : d.toLocaleDateString('it-IT');
        }
    },
    template: `
        <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                <h1>I miei confronti</h1>
                <button class="btn btn-primary" @click="apriModale">Nuovo confronto</button>
            </div>

            <p v-if="loading" class="muted">Caricamento...</p>
            <p v-else-if="error" class="error">{{ error }}</p>
            <p v-else-if="confronti.length === 0" class="muted">
                Non hai ancora creato nessun confronto.
            </p>

            <div class="cards-grid">
                <div v-for="c in confronti" :key="c.id" class="card">
                    <h3>{{ c.nome }}</h3>
                    <p v-if="c.descrizione" class="muted" style="white-space:pre-wrap;">{{ c.descrizione }}</p>
                    <p class="muted" style="font-size:.85rem; margin-top:.5rem;">
                        {{ c.numero_zone }} zone &middot; {{ formatDate(c.creato_il) }}
                    </p>
                    <a :href="'#/confronti/' + c.id" class="btn btn-primary btn-sm" style="margin-top:1rem;">
                        Apri
                    </a>
                </div>
            </div>

            <div v-if="modaleAperta" class="modal-overlay" @click.self="chiudiModale">
                <div class="modal-content">
                    <h2>Nuovo confronto</h2>
                    <form @submit.prevent="creaConfronto">
                        <label for="conf-nome">Nome</label>
                        <input id="conf-nome" class="input" type="text" v-model="formNome"
                               required maxlength="100">

                        <label for="conf-desc">Descrizione</label>
                        <textarea id="conf-desc" class="input" v-model="formDescrizione" rows="2"></textarea>

                        <label>Zone da confrontare</label>
                        <div class="zone-checkbox-list">
                            <label v-for="z in zone" :key="z.id" class="zone-checkbox">
                                <input type="checkbox" :value="z.id" v-model="formZoneIds">
                                {{ z.nome_paese }}
                            </label>
                        </div>
                        <p class="muted" style="font-size:.85rem; margin-top:-.5rem;">
                            {{ formZoneIds.length }} selezionate (min 1, max 20)
                        </p>

                        <p v-if="formErrore" class="error">{{ formErrore }}</p>
                        <div style="display:flex; gap:.5rem;">
                            <button class="btn btn-primary"
                                    :disabled="formInvio || formZoneIds.length === 0"
                                    type="submit">
                                {{ formInvio ? 'Creazione...' : 'Crea' }}
                            </button>
                            <button class="btn" type="button" @click="chiudiModale">Annulla</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `
};
