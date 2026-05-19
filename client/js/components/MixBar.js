window.App = window.App || { components: {}, views: {} };

window.App.components.MixBar = {
    props: {
        nome: { type: String, required: true },
        percentuale: { type: [Number, String], required: true },
        categoria: { type: String, default: 'altro' }
    },
    computed: {
        pct() {
            const n = Number(this.percentuale);
            return isNaN(n) ? 0 : Math.max(0, Math.min(100, n));
        },
        color() {
            const map = {
                rinnovabile: '#16a34a',
                fossile: '#dc2626',
                nucleare: '#8b5cf6',
                altro: '#94a3b8'
            };
            return map[this.categoria] || map.altro;
        }
    },
    template: `
        <div class="mix-bar">
            <div class="mix-bar-label">
                <span>{{ nome }}</span>
                <span class="muted">{{ pct.toFixed(1) }}%</span>
            </div>
            <div class="mix-bar-track">
                <div class="mix-bar-fill" :style="{ width: pct + '%', background: color }"></div>
            </div>
        </div>
    `
};
