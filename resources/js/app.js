import './bootstrap';
import '../css/forms-standardization.css';
import '../css/dark-theme-refinement.css';
import '../css/page-header-unified.css';
import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';
import Chart from 'chart.js/auto';

if (!window.Alpine) {
    if (!Object.prototype.hasOwnProperty.call(Alpine, '$persist')) {
        Alpine.plugin(persist);
    }

    window.Alpine = Alpine;
    Alpine.start();
}

const chartDataElement = document.getElementById('dashboard-chart-data');

if (chartDataElement) {
    const data = JSON.parse(chartDataElement.textContent);
    const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
