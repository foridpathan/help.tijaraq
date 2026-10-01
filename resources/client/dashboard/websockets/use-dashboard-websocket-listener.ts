import {addAnimatingMessage} from '@app/dashboard/conversations/conversation-page/messages/animating-messages';
import {helpdeskChannel} from '@app/dashboard/helpdesk-channel';
import {helpdeskQueries} from '@app/dashboard/helpdesk-queries';
import {dashboardChatUpdatesNotifier} from '@app/dashboard/websockets/dashboard-websocket-updates-notifier';
import {echoStore} from '@app/dashboard/websockets/echo-store';
import {playConversationSound} from '@app/dashboard/websockets/play-conversation-sound';
import {WebsocketConversationEvent} from '@app/dashboard/websockets/websocket-conversation-event';
import {useAuth} from '@common/auth/use-auth';
import {queryClient} from '@common/http/query-client';
import {useEffect, useRef} from 'react';
import {useDebouncedCallback} from 'use-debounce';

export function useDashboardWebsocketListener() {
  const {user} = useAuth();
  const currentUserId = user?.id ?? null;

  // make sure there are no duplicate requests if multiple similar
  // events are fired in a short time by debouncing handlers
  const invalidateConversationsQueries = useDebouncedCallback(
    () => {
      queryClient.invalidateQueries({
        queryKey: helpdeskQueries.conversations.invalidateKey,
      });
    },
    3000,
    {leading: true},
  );

  const invalidateAgentsQueries = useDebouncedCallback(
    () => {
      queryClient.invalidateQueries({
        queryKey: helpdeskQueries.agents.invalidateKey,
      });
    },
    3000,
    {leading: true},
  );

  const handleUserCreatedEvent = useDebouncedCallback(
    () => {
      queryClient.invalidateQueries({
        queryKey: helpdeskQueries.customers.invalidateKey,
      });
      playConversationSound('newVisitor', 'dashboard');
    },
    10000,
    {leading: true},
  );

  const invalidatePageVisitQueries = useDebouncedCallback(e => {
    queryClient.invalidateQueries({
      queryKey: helpdeskQueries.pageVisits.invalidateKey,
    });
  }, 500);

  useListener<WebsocketConversationEvent>(
    [
      helpdeskChannel.events.conversations.created,
      helpdeskChannel.events.conversations.updated,
      helpdeskChannel.events.conversations.newMessage,
    ],
    async e => {
      if (e.messageUuid) {
        addAnimatingMessage(e.messageUuid);
      }

      // don't notify of events in conversations handled by ai agent or flows
      if (e.conversations.every(c => c.assigned_to === 'bot')) {
        return;
      }

      // invalidate queries when conversation is created or updated or
      // when there's a new message for conversation assigned to this agent
      if (
        e.event !== helpdeskChannel.events.conversations.newMessage ||
        e.conversations.every(c => c.assignee_id === currentUserId)
      ) {
        invalidateConversationsQueries();
      }

      dashboardChatUpdatesNotifier.handleEvent(e);
    },
  );

  // invalidate agent queries when any relevant event occurs
  useListener([helpdeskChannel.events.agents.updated], invalidateAgentsQueries);

  // invalidate customer queries when any relevant event occurs
  useListener([helpdeskChannel.events.users.created], handleUserCreatedEvent);

  // invalidate visits queries when any relevant event occurs
  useListener(
    [helpdeskChannel.events.users.pageVisitCreated],
    invalidatePageVisitQueries,
  );
}

function useListener<T>(events: string[], callback: (e: T) => void) {
  const callbackRef = useRef(callback);
  callbackRef.current = callback;

  useEffect(() => {
    return echoStore().listen<WebsocketConversationEvent>({
      channel: helpdeskChannel.name,
      events,
      type: 'presence',
      callback: e => {
        callbackRef.current(e as T);
      },
    });
    // events are ignored on purpose, they will never change
  }, []);
}
