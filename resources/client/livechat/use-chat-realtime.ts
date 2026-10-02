import {echoStore} from '@app/dashboard/websockets/echo-store';
import {useAuth} from '@common/auth/use-auth';
import {useQueryClient} from '@tanstack/react-query';
import {getBootstrapData} from '@ui/bootstrap-data/bootstrap-data-store';
import {useEffect} from 'react';

type ConversationEvent = {
  conversationId: number;
  kind: 'created' | 'message';
};

export function chatRealtimeEnabled(): boolean {
  const broadcasting = getBootstrapData().settings.broadcasting;
  return !!broadcasting?.key &&
    ['pusher', 'reverb', 'ably'].includes(broadcasting.driver ?? '');
}

export function useChatRealtime(agent: boolean, currentId: number | null) {
  const queryClient = useQueryClient();
  const {user} = useAuth();
  const userId = user?.id;

  useEffect(() => {
    if (!chatRealtimeEnabled() || !userId) return;

    let timer: ReturnType<typeof setTimeout> | undefined;
    let refreshList = false;
    let refreshThread = false;
    const unsubscribe = echoStore().listen<ConversationEvent>({
      channel: agent ? 'tijaraq-chat-agents' : `tijaraq-chat-user.${userId}`,
      type: 'private',
      events: ['chat.changed'],
      callback: event => {
        refreshList ||= event.kind === 'created';
        refreshThread ||= event.conversationId === currentId;
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => {
          if (refreshList) {
            queryClient.invalidateQueries({queryKey: ['tijaraq-chat', 'list', agent]});
          }
          if (refreshThread && currentId) {
            queryClient.invalidateQueries({queryKey: ['tijaraq-chat', 'thread', currentId]});
          }
          refreshList = false;
          refreshThread = false;
        }, 250);
      },
    });

    return () => {
      unsubscribe();
      if (timer) clearTimeout(timer);
    };
  }, [agent, currentId, queryClient, userId]);
}
