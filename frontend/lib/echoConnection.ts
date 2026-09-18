import Echo from 'laravel-echo';
import type { EchoConnectionState } from './ticketQueueTrust';

export type { EchoConnectionState };

type ConnectionEvent = {
  current?: string;
};

const toState = (current: string | undefined): EchoConnectionState => {
  if (current === 'connected') {
    return 'live';
  }
  if (current === 'connecting' || current === 'unavailable') {
    return 'reconnecting';
  }
  return 'offline';
};

type PusherLike = {
  connection?: {
    state?: string;
    bind: (event: string, handler: (payload: ConnectionEvent) => void) => void;
    unbind: (event: string, handler: (payload: ConnectionEvent) => void) => void;
  };
};

export const subscribeEchoConnection = (
  echo: Echo<'reverb'>,
  onChange: (state: EchoConnectionState) => void,
): (() => void) => {
  const connector = echo.connector as { pusher?: PusherLike };
  const connection = connector.pusher?.connection;
  if (!connection) {
    onChange('offline');
    return () => undefined;
  }

  const handler = (payload: ConnectionEvent): void => {
    onChange(toState(payload.current ?? connection.state));
  };

  onChange(toState(connection.state));
  connection.bind('state_change', handler);

  return () => {
    connection.unbind('state_change', handler);
  };
};
