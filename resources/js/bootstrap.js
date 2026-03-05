import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.Pusher = Pusher;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const reverbScheme = import.meta.env.VITE_REVERB_SCHEME
	?? window.location.protocol.replace(':', '');

const reverbHost = import.meta.env.VITE_REVERB_HOST
	?? window.location.hostname;

const reverbPort = Number(
	import.meta.env.VITE_REVERB_PORT
		?? (reverbScheme === 'https' ? 443 : 80),
);

const useTls = reverbScheme === 'https';

window.Echo = new Echo({
	broadcaster: 'reverb',
	key: import.meta.env.VITE_REVERB_APP_KEY,
	wsHost: reverbHost,
	wsPort: reverbPort,
	wssPort: reverbPort,
	forceTLS: useTls,
	enabledTransports: useTls ? ['wss'] : ['ws'],
});
