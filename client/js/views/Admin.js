window.App = window.App || { components: {}, views: {} };

window.App.views.Admin = {
    data() {
        return {
            users: [],
            stats: null,
            news: [],
            loading: true,
            error: null
        };
    },
    computed: {
        totalUsers() {
            if (!this.stats || !this.stats.utenti_per_ruolo) return 0;
            return this.stats.utenti_per_ruolo.reduce((acc, r) => acc + Number(r.totale), 0);
        },
        myId() {
            return this.$root.currentUser ? Number(this.$root.currentUser.id) : 0;
        }
    },
    async mounted() {
        await this.loadAll();
    },
    methods: {
        async loadAll() {
            this.loading = true;
            this.error = null;
            try {
                const [users, stats, news] = await Promise.all([
                    window.api.get('/users'),
                    window.api.get('/admin/stats'),
                    window.api.get('/admin/news?limit=50')
                ]);
                this.users = users;
                this.stats = stats;
                this.news = news;
            } catch (e) {
                if (e.status === 401) { window.location.hash = '#/login'; return; }
                if (e.status === 403) { window.location.hash = '#/'; return; }
                this.error = (e.data && e.data.error) || e.message;
            } finally {
                this.loading = false;
            }
        },
        async deleteUser(u) {
            if (!confirm('Eliminare l\'utente "' + u.username + '"? Verranno rimossi tutti i suoi commenti, preferiti e confronti.')) return;
            try {
                await window.api.del('/users/' + u.id);
                this.users = this.users.filter(x => x.id !== u.id);
            } catch (e) {
                alert((e.data && e.data.error) || 'Errore eliminazione');
            }
        },
        async deleteNews(n) {
            if (!confirm('Eliminare questa news dalla cache?')) return;
            try {
                await window.api.del('/admin/news/' + n.id);
                this.news = this.news.filter(x => x.id !== n.id);
                if (this.stats) this.stats.totale_news = Math.max(0, this.stats.totale_news - 1);
            } catch (e) {
                alert((e.data && e.data.error) || 'Errore eliminazione');
            }
        },
        formatDate(s) {
            if (!s) return '';
            const d = new Date(s);
            return isNaN(d) ? s : d.toLocaleString('it-IT');
        }
    },
    template: `
        <div>
            <h1>Pannello admin</h1>

            <p v-if="loading" class="muted">Caricamento…</p>
            <p v-else-if="error" class="error">{{ error }}</p>

            <template v-else>
                <div v-if="stats" class="riepilogo-grid">
                    <div class="stat-card">
                        <p class="stat-label">Utenti</p>
                        <p class="stat-value">{{ totalUsers }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Zone</p>
                        <p class="stat-value">{{ stats.totale_zone }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Letture</p>
                        <p class="stat-value">{{ stats.totale_letture }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Commenti</p>
                        <p class="stat-value">{{ stats.totale_commenti }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">News in cache</p>
                        <p class="stat-value">{{ stats.totale_news }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="stat-label">Confronti</p>
                        <p class="stat-value">{{ stats.totale_confronti }}</p>
                    </div>
                </div>

                <div class="card" style="margin-top:1rem;">
                    <h2>Utenti</h2>
                    <table class="storico-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Ruolo</th>
                                <th>Iscritto il</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="u in users" :key="u.id">
                                <td>{{ u.id }}</td>
                                <td>{{ u.username }}</td>
                                <td>{{ u.email }}</td>
                                <td><span class="badge">{{ u.ruolo }}</span></td>
                                <td>{{ formatDate(u.created_at) }}</td>
                                <td>
                                    <button v-if="Number(u.id) !== myId"
                                            class="btn btn-sm" @click="deleteUser(u)">Elimina</button>
                                    <span v-else class="muted" style="font-size:.8rem;">(tu)</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="card" style="margin-top:1rem;">
                    <h2>News in cache (ultime {{ news.length }})</h2>
                    <p v-if="news.length === 0" class="muted">Nessuna news in cache.</p>
                    <div v-for="n in news" :key="n.id" class="news-admin-row">
                        <div style="flex:1; min-width:0;">
                            <strong>{{ n.titolo || '(senza titolo)' }}</strong>
                            <p class="muted" style="font-size:.85rem; margin-top:.25rem;">
                                {{ n.codice_zona }} · {{ n.fonte_nome || '—' }} · {{ formatDate(n.recuperata_il) }}
                            </p>
                        </div>
                        <button class="btn btn-sm" @click="deleteNews(n)">Elimina</button>
                    </div>
                </div>
            </template>
        </div>
    `
};
