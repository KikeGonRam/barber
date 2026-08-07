/**
 * Configuración base de Axios para toda la app.
 * Se expone en window.axios para poder usarlo desde cualquier script/vista sin
 * tener que importarlo de nuevo, y se marca cada petición como AJAX
 * (X-Requested-With) para que Laravel la trate como petición XHR (por ejemplo,
 * al responder con JSON en vez de redirigir en caso de error de validación).
 */
import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
