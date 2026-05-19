window.App = window.App || { components: {}, views: {} };

window.App.views.ZoneSalvate = {
    data() {
        return {
            preferiti: [],
            zoneDisponibili: [],
            loading: true,
            error: null,

            addOpen: false,
            addCodice: '',
            addEtichetta: '',
            addNote: '',
            addError: null,
            addPosting: false,

            editTarget: null,
            editEtichetta: '',
            editNote: '',
            editError: null,
            editPosting: false
        };
    },
    async mounted() {
        await this.loadPreferiti();
    },
    methods: {
        async loadPreferiti() {
            this.loading = true;
            this.error = null;
            try {
                this.preferiti = await window.api.get('/zone-salvate');
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
        async openAdd() {
            this.addOpen = true;
            this.addError = null;
            this.addCodice = '';
            this.addEtichetta = '';
            this.addNote = '';
            if (this.zoneDisponibili.length === 0) {
                try {
                    this.zoneDisponibili = await window.api.get('/zone');
                } catch (e) {
                    this.addError = 'Impossibile caricare la lista zone';
                }
            }
        },
        closeAdd() { this.addOpen = false; },
        async submitAdd() {
            if (!this.addCodice) return;
            this.addPosting = true;
            this.addError = null;
            try {
                await window.api.post('/zone-salvate', {
                    codice_zona: this.addCodice,
                    etichetta: this.addEtichetta || null,
                    note: this.addNote || null
                });
                this.closeAdd();
                await this.loadPreferiti();
            } catch (e) {
                this.addError = (e.data && e.data.error) || e.message;
            } finally {
                this.addPosting = false;
            }
        },
        openEdit(p) {
            this.editTarget = p;
            this.editEtichetta = p.etichetta || '';
            this.editNote = p.note || '';
            this.editError = null;
        },
        closeEdit() { this.editTarget = null; },
        async submitEdit() {
            if (!this.editTarget) return;
            this.editPosting = true;
            this.editError = null;
            try {
                await window.api.patch('/zone-salvate/' + this.editTarget.zona_id, {
                    etichetta: this.editEtichetta || null,
                    note: this.editNote || null
                });
                this.closeEdit();
                await this.loadPreferiti();
            } catch (e) {
                this.editError = (e.data && e.data.error) || e.message;
            } finally {
                this.editPosting = false;
            }
        },
        async deletePreferito(p) {
            if (!confirm('Rimuovere ' + p.nome_paese + ' dai preferiti?')) return;
            try {
                await window.api.del('/zone-salvate/' + p.zona_id);
                this.preferiti = this.preferiti.filter(x => x.zona_id !== p.zona_id);
            } catch (e) {
                alert((e.data && e.data.error) || 'Errore eliminazione');
            }
        },
        goToDetail(p) {
            window.location.hash = '#/zone/' + p.zona_id;
        }
    },
    template: `
        <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                <h1>Le mie zone</h1>
                <button class="btn btn-primary" @click="openAdd">Aggiungi zona</button>
            </div>

            <p v-if="loading" class="muted">Caricamento…</p>
            <p v-else-if="error" class="error">{{ error }}</p>
            <p v-else-if="preferiti.length === 0" class="muted">
                Non hai ancora salvato nessuna zona. Esplora la <a href="#/mappa">mappa</a> o aggiungile manualmente.
            </p>

            <div class="cards-grid">
                <div v-for="p in preferiti" :key="p.zona_id" class="card preferito-card">
                    <h3>{{ p.nome_paese }}</h3>
                    <p v-if="p.etichetta" style="font-weight:500;">{{ p.etichetta }}</p>
                    <p v-if="p.note" class="muted" style="white-space:pre-wrap;">{{ p.note }}</p>
                    <p v-if="p.ultima_carbon_intensity != null" style="margin-top:.5rem;">
                        <strong>{{ Math.round(p.ultima_carbon_intensity) }}</strong>
                        <span class="muted">gCO₂eq/kWh</span>
                    </p>
                    <div style="display:flex; gap:.5rem; margin-top:1rem; flex-wrap:wrap;">
                        <button class="btn btn-sm" @click="goToDetail(p)">Dettaglio</button>
                        <button class="btn btn-sm" @click="openEdit(p)">Modifica</button>
                        <button class="btn btn-sm" @click="deletePreferito(p)">Elimina</button>
                    </div>
                </div>
            </div>

            <div v-if="addOpen" class="modal-overlay" @click.self="closeAdd">
                <div class="modal-content">
                    <h2>Aggiungi una zona</h2>
                    <form @submit.prevent="submitAdd">
                        <label for="add-codice">Zona</label>
                        <select id="add-codice" class="input" v-model="addCodice" required>
                            <option value="">— Scegli un paese —</option>
                            <option v-for="z in zoneDisponibili" :key="z.id" :value="z.codice_zona">
                                {{ z.nome_paese }} ({{ z.codice_zona }})
                            </option>
                        </select>

                        <label for="add-etichetta">Etichetta (opzionale)</label>
                        <input id="add-etichetta" class="input" type="text" v-model="addEtichetta" maxlength="80">

                        <label for="add-note">Note (opzionale)</label>
                        <textarea id="add-note" class="input" v-model="addNote" rows="3"></textarea>

                        <p v-if="addError" class="error">{{ addError }}</p>
                        <div style="display:flex; gap:.5rem;">
                            <button class="btn btn-primary" :disabled="addPosting" type="submit">
                                {{ addPosting ? 'Salvataggio…' : 'Aggiungi' }}
                            </button>
                            <button class="btn" type="button" @click="closeAdd">Annulla</button>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="editTarget" class="modal-overlay" @click.self="closeEdit">
                <div class="modal-content">
                    <h2>Modifica {{ editTarget.nome_paese }}</h2>
                    <form @submit.prevent="submitEdit">
                        <label for="edit-etichetta">Etichetta</label>
                        <input id="edit-etichetta" class="input" type="text" v-model="editEtichetta" maxlength="80">

                        <label for="edit-note">Note</label>
                        <textarea id="edit-note" class="input" v-model="editNote" rows="3"></textarea>

                        <p v-if="editError" class="error">{{ editError }}</p>
                        <div style="display:flex; gap:.5rem;">
                            <button class="btn btn-primary" :disabled="editPosting" type="submit">
                                {{ editPosting ? 'Salvataggio…' : 'Salva' }}
                            </button>
                            <button class="btn" type="button" @click="closeEdit">Annulla</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `
};
