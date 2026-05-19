window.App = window.App || { components: {}, views: {} };

window.App.views.Register = {
    data() {
        return {
            username: '',
            email: '',
            password: '',
            error: null,
            loading: false
        };
    },
    methods: {
        async submit() {
            this.error = null;
            this.loading = true;
            try {
                const user = await window.api.post('/auth/register', {
                    username: this.username,
                    email: this.email,
                    password: this.password
                });
                this.$root.currentUser = user;
                window.location.hash = '#/';
            } catch (e) {
                this.error = (e.data && e.data.error) || e.message || 'Errore di registrazione';
            } finally {
                this.loading = false;
            }
        }
    },
    template: `
        <div class="card" style="max-width: 420px; margin: 2rem auto;">
            <h2>Crea un account</h2>
            <form @submit.prevent="submit">
                <label for="username">Username</label>
                <input id="username" class="input" type="text" v-model="username" required
                       minlength="3" maxlength="50" pattern="[A-Za-z0-9_]+"
                       autocomplete="username"
                       title="3-50 caratteri tra lettere, numeri e underscore">

                <label for="email">Email</label>
                <input id="email" class="input" type="email" v-model="email" required autocomplete="email">

                <label for="password">Password</label>
                <input id="password" class="input" type="password" v-model="password" required
                       autocomplete="new-password">

                <p v-if="error" class="error">{{ error }}</p>

                <button class="btn btn-primary" :disabled="loading" type="submit">
                    {{ loading ? '...' : 'Registrati' }}
                </button>
                <p class="muted" style="margin-top: 1rem;">
                    Gia' registrato? <a href="#/login">Accedi</a>
                </p>
            </form>
        </div>
    `
};
