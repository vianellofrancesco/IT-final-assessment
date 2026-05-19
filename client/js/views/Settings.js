window.App = window.App || { components: {}, views: {} };

window.App.views.Settings = {
    data() {
        return {
            username: '',
            email: '',
            profiloErrore: null,
            profiloOk: false,
            profiloInvio: false,

            passwordVecchia: '',
            passwordNuova: '',
            passwordErrore: null,
            passwordOk: false,
            passwordInvio: false
        };
    },
    async mounted() {
        try {
            const me = await window.api.get('/auth/me');
            this.username = me.username;
            this.email = me.email;
            this.$root.currentUser = me;
        } catch (e) {
            if (e.status === 401) {
                window.location.hash = '#/login';
            }
        }
    },
    methods: {
        impostaTema(t) { this.$root.impostaTema(t); },
        async salvaProfilo() {
            this.profiloErrore = null;
            this.profiloOk = false;
            this.profiloInvio = true;
            try {
                const me = await window.api.put('/users/me', {
                    username: this.username,
                    email: this.email
                });
                this.$root.currentUser = me;
                this.profiloOk = true;
            } catch (e) {
                this.profiloErrore = (e.data && e.data.error) || e.message;
            } finally {
                this.profiloInvio = false;
            }
        },
        async cambiaPassword() {
            this.passwordErrore = null;
            this.passwordOk = false;
            this.passwordInvio = true;
            try {
                await window.api.patch('/users/me/password', {
                    vecchia_password: this.passwordVecchia,
                    nuova_password: this.passwordNuova
                });
                this.passwordOk = true;
                this.passwordVecchia = '';
                this.passwordNuova = '';
            } catch (e) {
                this.passwordErrore = (e.data && e.data.error) || e.message;
            } finally {
                this.passwordInvio = false;
            }
        }
    },
    template: `
        <div>
            <h1>Impostazioni</h1>

            <div class="card" style="margin-top:1rem;">
                <h2>Aspetto</h2>
                <p class="muted" style="margin-bottom:.5rem; font-size:.9rem;">Tema</p>
                <div class="choice-group">
                    <button class="btn" :class="{ 'btn-primary': $root.tema === 'chiaro' }"
                            @click="impostaTema('chiaro')">Chiaro</button>
                    <button class="btn" :class="{ 'btn-primary': $root.tema === 'scuro' }"
                            @click="impostaTema('scuro')">Scuro</button>
                </div>
            </div>

            <div class="card" style="margin-top:1rem;">
                <h2>Profilo</h2>
                <form @submit.prevent="salvaProfilo">
                    <label for="set-username">Username</label>
                    <input id="set-username" class="input" type="text" v-model="username"
                           required pattern="[A-Za-z0-9_]+" minlength="3" maxlength="50">

                    <label for="set-email">Email</label>
                    <input id="set-email" class="input" type="email" v-model="email" required>

                    <p v-if="profiloErrore" class="error">{{ profiloErrore }}</p>
                    <p v-if="profiloOk" class="ok" style="margin:.5rem 0 1rem;">Profilo aggiornato.</p>

                    <button class="btn btn-primary" :disabled="profiloInvio" type="submit">
                        {{ profiloInvio ? 'Invio...' : 'Salva profilo' }}
                    </button>
                </form>
            </div>

            <div class="card" style="margin-top:1rem;">
                <h2>Password</h2>
                <form @submit.prevent="cambiaPassword">
                    <label for="set-vecchia">Password attuale</label>
                    <input id="set-vecchia" class="input" type="password" v-model="passwordVecchia"
                           required autocomplete="current-password">

                    <label for="set-nuova">Nuova password</label>
                    <input id="set-nuova" class="input" type="password" v-model="passwordNuova"
                           required autocomplete="new-password">

                    <p v-if="passwordErrore" class="error">{{ passwordErrore }}</p>
                    <p v-if="passwordOk" class="ok" style="margin:.5rem 0 1rem;">Password aggiornata.</p>

                    <button class="btn btn-primary" :disabled="passwordInvio" type="submit">
                        {{ passwordInvio ? 'Invio...' : 'Cambia password' }}
                    </button>
                </form>
            </div>
        </div>
    `
};
