import {getCustomEchoAuthEndpoint} from '@common/http/echo-custom-auth-endpoint';
import {setEchoSocketId} from '@common/http/get-echo-socket-id';
import {getBootstrapData} from '@ui/bootstrap-data/bootstrap-data-store';
import EchoType, {EchoOptions} from 'laravel-echo';

let globalEcho: Promise<EchoType<'reverb' | 'pusher'>> | null = null;

export async function bootEcho() {
  // deduplicate requests from different components
  if (globalEcho) {
    return await globalEcho;
  }

  globalEcho = new Promise(async (resolve, reject) => {
    const config = getBootstrapData().settings.broadcasting;
    if (!config || config.driver === 'log' || config.driver === 'null') {
      return;
    }

    const [{default: Echo}] = await Promise.all([
      import('laravel-echo'),
      import('pusher-js'),
    ]);

    const echoInstance = new Echo({
      ...getCredentials(),
      authEndpoint: getCustomEchoAuthEndpoint() ?? 'broadcasting/auth',
      csrfToken: getBootstrapData().csrf_token,
    });

    if ('pusher' in echoInstance.connector) {
      echoInstance.connector.pusher.connection.bind(
        'connected',
        function (e: any) {
          setEchoSocketId(e.socket_id);
          resolve(echoInstance as EchoType<'pusher' | 'reverb'>);
        },
      );
    }
  });
  return globalEcho;
}

function getCredentials() {
  const config = getBootstrapData().settings.broadcasting;
  switch (config?.driver) {
    case 'pusher':
      return {
        broadcaster: 'pusher',
        key: config.key,
        cluster: config.cluster,
        forceTLS: true,
      } as EchoOptions<'pusher'>;
    case 'reverb':
      return {
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: (config.scheme ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
      } as EchoOptions<'reverb'>;
    case 'ably':
      return {
        broadcaster: 'pusher',
        key: config.key,
        cluster: 'any',
        wsHost: 'realtime-pusher.ably.io',
        wsPort: 443,
        disableStats: true,
        encrypted: true,
      } as EchoOptions<'pusher'>;
    default:
      return {
        broadcaster: 'pusher',
      } as EchoOptions<'pusher'>;
  }
}
