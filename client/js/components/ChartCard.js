window.App = window.App || { components: {}, views: {} };

window.App.components.ChartCard = {
    props: {
        type: { type: String, default: 'bar' },
        labels: { type: Array, required: true },
        datasets: { type: Array, required: true },
        title: { type: String, default: '' }
    },
    data() {
        return { chart: null };
    },
    mounted() {
        this.create();
    },
    beforeUnmount() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    },
    watch: {
        labels() { this.update(); },
        datasets: { handler() { this.update(); }, deep: true },
        type() { this.create(); }
    },
    methods: {
        create() {
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
            this.chart = new Chart(this.$refs.canvas, {
                type: this.type,
                data: { labels: this.labels, datasets: this.datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: this.datasets.length > 1 || this.type === 'doughnut'
                        },
                        title: this.title
                            ? { display: true, text: this.title }
                            : { display: false }
                    },
                    scales: this.type === 'doughnut'
                        ? {}
                        : { y: { beginAtZero: true } }
                }
            });
        },
        update() {
            if (!this.chart) {
                this.create();
                return;
            }
            this.chart.data.labels = this.labels;
            this.chart.data.datasets = this.datasets;
            this.chart.update();
        }
    },
    template: `
        <div class="chart-card">
            <h3 v-if="title">{{ title }}</h3>
            <div class="chart-container"><canvas ref="canvas"></canvas></div>
        </div>
    `
};
