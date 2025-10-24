import axios from "axios";
import Alpine from "alpinejs";
import tableOfContents from './components/table-of-contents.js';

window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

window.Alpine = Alpine;

// Register Alpine components
Alpine.data('tableOfContents', tableOfContents);

Alpine.start();
