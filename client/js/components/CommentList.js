window.App = window.App || { components: {}, views: {} };

window.App.components.CommentList = {
    props: {
        zonaId: { type: [Number, String], required: true }
    },
    data() {
        return {
            commenti: [],
            loading: false,
            done: false,
            offset: 0,
            limit: 20,

            newText: '',
            newError: null,
            posting: false,

            editId: null,
            editText: '',
            editError: null,
            editPosting: false
        };
    },
    async mounted() {
        await this.load(true);
    },
    watch: {
        zonaId() { this.load(true); }
    },
    methods: {
        async load(reset) {
            if (this.loading) return;
            this.loading = true;
            try {
                if (reset) {
                    this.commenti = [];
                    this.offset = 0;
                    this.done = false;
                }
                const url = '/zone/' + Number(this.zonaId) + '/commenti?limit=' + this.limit + '&offset=' + this.offset;
                const data = await window.api.get(url);
                const batch = (data && data.commenti) || [];
                this.commenti.push(...batch);
                if (batch.length < this.limit) {
                    this.done = true;
                } else {
                    this.offset += this.limit;
                }
            } catch (e) {
                console.error('Errore commenti', e);
            } finally {
                this.loading = false;
            }
        },
        async submitNew() {
            const text = this.newText.trim();
            if (!text) return;
            this.posting = true;
            this.newError = null;
            try {
                const c = await window.api.post('/commenti', {
                    zona_id: Number(this.zonaId),
                    testo: text
                });
                this.commenti.unshift(c);
                this.newText = '';
            } catch (e) {
                this.newError = (e.data && e.data.error) || e.message;
            } finally {
                this.posting = false;
            }
        },
        startEdit(c) {
            this.editId = c.id;
            this.editText = c.testo;
            this.editError = null;
        },
        cancelEdit() {
            this.editId = null;
            this.editText = '';
        },
        async saveEdit(c) {
            const text = this.editText.trim();
            if (!text) return;
            this.editPosting = true;
            this.editError = null;
            try {
                const updated = await window.api.patch('/commenti/' + c.id, { testo: text });
                const idx = this.commenti.findIndex(x => x.id === c.id);
                if (idx !== -1) this.commenti[idx] = updated;
                this.cancelEdit();
            } catch (e) {
                this.editError = (e.data && e.data.error) || e.message;
            } finally {
                this.editPosting = false;
            }
        },
        async deleteCommento(c) {
            if (!confirm('Eliminare questo commento?')) return;
            try {
                await window.api.del('/commenti/' + c.id);
                this.commenti = this.commenti.filter(x => x.id !== c.id);
            } catch (e) {
                alert((e.data && e.data.error) || 'Errore eliminazione');
            }
        },
        canEdit(c) {
            const me = this.$root.currentUser;
            if (!me) return false;
            return Number(c.utente_id) === Number(me.id) || me.ruolo === 'admin';
        },
        formatDate(s) {
            if (!s) return '';
            const d = new Date(s);
            return isNaN(d) ? s : d.toLocaleString('it-IT');
        }
    },
    template: `
        <div>
            <div v-if="$root.currentUser" class="comment-form">
                <form @submit.prevent="submitNew">
                    <textarea class="input" v-model="newText" rows="3" maxlength="2000"
                              placeholder="Scrivi un commento..." required></textarea>
                    <p v-if="newError" class="error">{{ newError }}</p>
                    <button class="btn btn-primary" :disabled="posting" type="submit">
                        {{ posting ? 'Invio…' : 'Pubblica' }}
                    </button>
                </form>
            </div>
            <p v-else class="muted" style="margin-bottom:1rem;">
                <a href="#/login">Accedi</a> per lasciare un commento.
            </p>

            <p v-if="commenti.length === 0 && !loading" class="muted">
                Nessun commento ancora.
            </p>

            <div v-for="c in commenti" :key="c.id" class="comment-item">
                <div class="comment-meta">
                    <strong>{{ c.username }}</strong>
                    <span class="muted"> · {{ formatDate(c.creato_il) }}</span>
                    <span v-if="c.modificato_il" class="muted" style="font-size:.8rem;"> (modificato)</span>
                </div>
                <div v-if="editId === c.id">
                    <textarea class="input" v-model="editText" rows="3" maxlength="2000"></textarea>
                    <p v-if="editError" class="error">{{ editError }}</p>
                    <div class="comment-actions">
                        <button class="btn btn-primary btn-sm" :disabled="editPosting" @click="saveEdit(c)">Salva</button>
                        <button class="btn btn-sm" @click="cancelEdit">Annulla</button>
                    </div>
                </div>
                <div v-else>
                    <p class="comment-text">{{ c.testo }}</p>
                    <div v-if="canEdit(c)" class="comment-actions">
                        <button class="btn btn-sm" @click="startEdit(c)">Modifica</button>
                        <button class="btn btn-sm" @click="deleteCommento(c)">Elimina</button>
                    </div>
                </div>
            </div>

            <div v-if="!done && commenti.length > 0" style="text-align:center; margin-top:1rem;">
                <button class="btn" :disabled="loading" @click="load(false)">
                    {{ loading ? '…' : 'Carica altri' }}
                </button>
            </div>
        </div>
    `
};
