'use client';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance: Echo<'reverb'> | null = null;

export const getEcho = (): Echo<'reverb'> => {
  if (echoInstance) {
    return echoInstance;
  }

  if (typeof window === 'undefined') {
    throw new Error('Echo can only be initialised in the browser');
  }

  const w = window as Window & { Pusher?: typeof Pusher };
  w.Pusher = Pusher;

  const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost';
  const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY ?? '';
  const host = process.env.NEXT_PUBLIC_REVERB_HOST ?? 'localhost';
  const port = Number.parseInt(process.env.NEXT_PUBLIC_REVERB_PORT ?? '8080', 10);
  const scheme = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? 'http';

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${apiUrl}/broadcasting/auth`,
    auth: {
      headers: {
        Accept: 'application/json',
      },
    },
  });

  return echoInstance;
};
