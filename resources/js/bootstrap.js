import axios from 'axios';
import './table-server-markers';
import './table-ui-standardization';
import './spj-purchase-date-validation';
import './spj-package-document-placement';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
