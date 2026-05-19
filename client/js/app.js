window.API_BASE = '/progetto-finale/server/api';

window.api = {
    async request(method, path, body) {
        const opts = {
            method,
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        };
        if (body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const r = await fetch(window.API_BASE + path, opts);
        let data = null;
        try { data = await r.json(); } catch (_) {}
        if (!r.ok) {
            const e = new Error((data && data.error) || ('HTTP ' + r.status));
            e.status = r.status;
            e.data = data;
            throw e;
        }
        return data;
    },
    get: (p) => window.api.request('GET', p),
    post: (p, b) => window.api.request('POST', p, b),
    put: (p, b) => window.api.request('PUT', p, b),
    patch: (p, b) => window.api.request('PATCH', p, b),
    del: (p) => window.api.request('DELETE', p)
};

window.AppRouter = {
    resolve(hash) {
        const path = (hash || '').replace(/^#/, '') || '/';
        const seg = path.split('/').filter(Boolean);
        if (seg.length === 0) return { name: 'home', params: {} };
        if (seg[0] === 'login') return { name: 'login', params: {} };
        if (seg[0] === 'register') return { name: 'register', params: {} };
        if (seg[0] === 'mappa') return { name: 'mappa', params: {} };
        if (seg[0] === 'statistiche') return { name: 'statistiche', params: {} };
        if (seg[0] === 'admin') return { name: 'admin', params: {} };
        if (seg[0] === 'settings') return { name: 'settings', params: {} };
        if (seg[0] === 'confronti') {
            if (seg.length === 1) return { name: 'confronti', params: {} };
            return { name: 'confrontoDettaglio', params: { id: seg[1] } };
        }
        if (seg[0] === 'zone') {
            if (seg.length === 1) return { name: 'zoneSalvate', params: {} };
            return { name: 'zonaDettaglio', params: { id: seg[1] } };
        }
        return { name: 'home', params: {} };
    }
};

const app = Vue.createApp({
    data() {
        return {
            currentRoute: { name: 'home', params: {} },
            currentUser: null,
            ready: false,
            tema: 'chiaro'
        };
    },
    computed: {
        currentView() {
            const map = {
                home: 'view-home',
                login: 'view-login',
                register: 'view-register',
                mappa: 'view-mappa',
                zoneSalvate: 'view-zone-salvate',
                zonaDettaglio: 'view-zona-dettaglio',
                statistiche: 'view-statistiche',
                confronti: 'view-confronti',
                confrontoDettaglio: 'view-confronto-dettaglio',
                settings: 'view-settings',
                admin: 'view-admin'
            };
            return map[this.currentRoute.name] || 'view-home';
        }
    },
    async mounted() {
        try {
            const t = localStorage.getItem('tema');
            if (t === 'chiaro' || t === 'scuro') this.tema = t;
        } catch (_) {}
        this.applicaTema();

        this.updateRoute();
        window.addEventListener('hashchange', () => this.updateRoute());

        try {
            this.currentUser = await window.api.get('/auth/me');
        } catch (_) {
            this.currentUser = null;
        }
        this.ready = true;
    },
    methods: {
        updateRoute() {
            this.currentRoute = window.AppRouter.resolve(window.location.hash);
        },
        applicaTema() {
            document.documentElement.setAttribute('data-theme', this.tema);
        },
        impostaTema(nuovoTema) {
            if (nuovoTema !== 'chiaro' && nuovoTema !== 'scuro') return;
            this.tema = nuovoTema;
            try { localStorage.setItem('tema', nuovoTema); } catch (_) {}
            this.applicaTema();
        },
        async logout() {
            try { await window.api.post('/auth/logout'); } catch (_) {}
            this.currentUser = null;
            window.location.hash = '#/';
        }
    },
    template: `
        <div class="app-layout">
            <app-nav></app-nav>
            <main class="app-main">
                <div class="container">
                    <component :is="currentView"></component>
                </div>
            </main>
        </div>
    `
});

app.component('app-nav', window.App.components.Nav);
app.component('mix-bar', window.App.components.MixBar);
app.component('news-card', window.App.components.NewsCard);
app.component('comment-list', window.App.components.CommentList);
app.component('chart-card', window.App.components.ChartCard);

app.component('view-home', window.App.views.Home);
app.component('view-login', window.App.views.Login);
app.component('view-register', window.App.views.Register);
app.component('view-mappa', window.App.views.Mappa);
app.component('view-zone-salvate', window.App.views.ZoneSalvate);
app.component('view-zona-dettaglio', window.App.views.ZonaDettaglio);
app.component('view-statistiche', window.App.views.Statistiche);
app.component('view-confronti', window.App.views.Confronti);
app.component('view-confronto-dettaglio', window.App.views.ConfrontoDettaglio);
app.component('view-settings', window.App.views.Settings);
app.component('view-admin', window.App.views.Admin);

app.mount('#app');
