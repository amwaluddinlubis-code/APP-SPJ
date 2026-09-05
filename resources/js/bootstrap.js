import axios from 'axios';
import './table-server-markers';
import './table-ui-standardization';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
