window.App = window.App || { components: {}, views: {} };

window.App.components.NewsCard = {
    props: {
        news: { type: Object, required: true }
    },
    computed: {
        date() {
            if (!this.news.pubblicata_il) return '';
            const d = new Date(this.news.pubblicata_il);
            return isNaN(d) ? this.news.pubblicata_il : d.toLocaleDateString('it-IT');
        }
    },
    template: `
        <a :href="news.url" target="_blank" rel="noopener noreferrer" class="news-card">
            <div class="news-card-title">{{ news.titolo || '(senza titolo)' }}</div>
            <div class="news-card-meta">
                <span>{{ news.fonte_nome || 'Fonte sconosciuta' }}</span>
                <span v-if="date"> · {{ date }}</span>
            </div>
        </a>
    `
};
