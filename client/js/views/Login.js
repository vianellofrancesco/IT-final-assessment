window.App = window.App || { components: {}, views: {} };

window.App.views.Login = {
    data() {
        return {
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
                const user = await window.api.post('/auth/login', {
                    email: this.email,
                    password: this.password
                });
                this.$root.currentUser = user;
                window.location.hash = '#/';
            } catch (e) {
                this.error = (e.data && e.data.error) || e.message || 'Errore di login';
            } finally {
                this.loading = false;
            }
        }
    },
    template: `
        <div class="card" style="max-width: 420px; margin: 2rem auto;">
            <h2>Accedi</h2>
            <form @submit.prevent="submit">
                <label for="email">Email</label>
                <input id="email" class="input" type="email" v-model="email" required autocomplete="email">

                <label for="password">Password</label>
                <input id="password" class="input" type="password" v-model="password" required autocomplete="current-password">

                <p v-if="error" class="error">{{ error }}</p>

                <button class="btn btn-primary" :disabled="loading" type="submit">
                    {{ loading ? '...' : 'Login' }}
                </button>
                <p class="muted" style="margin-top: 1rem;">
                    Non hai un account? <a href="#/register">Registrati</a>
                </p>
            </form>
        </div>
    `
};
