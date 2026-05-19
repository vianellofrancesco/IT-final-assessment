window.App = window.App || { components: {}, views: {} };

window.App.views.Home = {
    template: `
        <div>
            <h1>GreenGrid</h1>
            <p v-if="$root.currentUser" class="muted">
                Bentornato, <strong>{{ $root.currentUser.username }}</strong>.
            </p>
            <p v-else class="muted">
                Monitora l'intensita' di carbonio e il mix energetico dei paesi.
                <a href="#/login">Accedi</a> o <a href="#/register">registrati</a> per salvare i tuoi paesi preferiti.
            </p>

            <div class="cards-grid">
                <a href="#/mappa" class="card">
                    <h3>Mappa</h3>
                    <p class="muted">Esplora i paesi e l'intensita' di carbonio in tempo reale.</p>
                </a>
                <a href="#/zone" class="card">
                    <h3>Le mie zone</h3>
                    <p class="muted">Le zone preferite con etichette e note personali.</p>
                </a>
                <a href="#/statistiche" class="card">
                    <h3>Statistiche</h3>
                    <p class="muted">Top paesi, classifiche, riepilogo globale.</p>
                </a>
                <a href="#/confronti" class="card">
                    <h3>Confronti</h3>
                    <p class="muted">Confronta piu' paesi tra loro su metriche e mix.</p>
                </a>
            </div>
        </div>
    `
};
