import axios from 'axios';
import './table-server-markers';
import './table-ui-standardization';
import './spj-purchase-date-validation';
import './spj-package-document-placement';
import './spj-package-manual-category';
import './transaction-detail-ui';
import './transaction-detail-common-fields-layout';
import './transaction-detail-category-layout';
import './action-icon-deduplicator';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
