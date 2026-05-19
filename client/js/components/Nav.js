window.App = window.App || { components: {}, views: {} };

window.App.components.Nav = {
    data() {
        return { menuOpen: false };
    },
    methods: {
        closeMenu() { this.menuOpen = false; },
        toggleMenu() { this.menuOpen = !this.menuOpen; }
    },
    template: `
        <nav class="app-nav">
            <a href="#/" class="brand" @click="closeMenu">GreenGrid</a>
            <button class="btn nav-hamburger" type="button" @click="toggleMenu" aria-label="Menu">
                {{ menuOpen ? 'Chiudi' : 'Menu' }}
            </button>
            <div class="nav-links" :class="{ open: menuOpen }">
                <a href="#/mappa" @click="closeMenu">Mappa</a>
                <a href="#/statistiche" @click="closeMenu">Statistiche</a>
                <a v-if="$root.currentUser" href="#/zone" @click="closeMenu">Le mie zone</a>
                <a v-if="$root.currentUser" href="#/confronti" @click="closeMenu">Confronti</a>
                <a v-if="$root.currentUser && $root.currentUser.ruolo === 'admin'"
                   href="#/admin" @click="closeMenu">Admin</a>
            </div>
            <div class="nav-user">
                <template v-if="$root.currentUser">
                    <a href="#/settings" class="btn btn-sm" @click="closeMenu">Opzioni</a>
                    <span class="badge">{{ $root.currentUser.username }}</span>
                    <button class="btn" type="button" @click="$root.logout()">Logout</button>
                </template>
                <template v-else>
                    <a href="#/login" class="btn" @click="closeMenu">Login</a>
                    <a href="#/register" class="btn btn-primary" @click="closeMenu">Registrati</a>
                </template>
            </div>
        </nav>
    `
};
