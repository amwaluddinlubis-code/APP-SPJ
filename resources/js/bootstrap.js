import axios from 'axios';
import './theme-profiles';
import './table-server-markers';
import './table-ui-standardization';
import './transaction-action-modal';
import './indonesian-date-input';
import './spj-purchase-date-validation';
import './spj-package-document-placement';
import './spj-package-manual-category';
import './spj-package-maintenance-links';
import './spj-package-workspace-ui';
import './spj-package-transaction-boundary';
import './spj-detail-input-validation';
import './spj-numbering-confirmation-modal';
import './legacy-action-icon-migrator';
import './action-icon-deduplicator';
import './sync-progress';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
