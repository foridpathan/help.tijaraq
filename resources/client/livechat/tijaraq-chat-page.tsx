import {apiClient} from '@common/http/query-client';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {FormEvent, useState} from 'react';
import {Link} from 'react-router';
import {chatRealtimeEnabled, useChatRealtime} from './use-chat-realtime';

type Conversation = {
  id: number;
  subject: string;
  status_category: number;
};
type Message = {id: number; body: string; author: string; created_at: string};

function plainText(html: string): string {
  return new DOMParser().parseFromString(html.replace(/<br\s*\/?\s*>/gi, '\n'), 'text/html').body.textContent ?? '';
}

export function CustomerChatPage() {
  return <ChatPage agent={false} />;
}

export function AgentChatPage() {
  return <ChatPage agent />;
}

function ChatPage({agent}: {agent: boolean}) {
  const queryClient = useQueryClient();
  const realtime = chatRealtimeEnabled();
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [draft, setDraft] = useState('');
  const [error, setError] = useState('');

  const list = useQuery({
    queryKey: ['tijaraq-chat', 'list', agent],
    queryFn: () => apiClient.get<{enabled: boolean; conversations: Conversation[]}>(
      `tijaraq-chat/conversations${agent ? '?agent=1' : ''}`,
    ).then(response => response.data),
    refetchInterval: realtime ? 60_000 : 30_000,
  });
  const currentId = selectedId ?? list.data?.conversations[0]?.id ?? null;
  useChatRealtime(agent, currentId);
  const thread = useQuery({
    queryKey: ['tijaraq-chat', 'thread', currentId],
    enabled: currentId !== null && currentId > 0,
    queryFn: () => apiClient.get<{conversation: Conversation; messages: Message[]}>(
      `tijaraq-chat/conversations/${currentId}`,
    ).then(response => response.data),
    refetchInterval: realtime ? 60_000 : 30_000,
  });
  const send = useMutation({
    mutationFn: async (message: string) => {
      if (currentId !== null && currentId > 0) {
        await apiClient.post(`tijaraq-chat/conversations/${currentId}/messages`, {message});
        return currentId;
      }
      const response = await apiClient.post<{conversation_id: number}>(
        'tijaraq-chat/conversations', {message},
      );
      return response.data.conversation_id;
    },
    onSuccess: id => {
      setSelectedId(id);
      setDraft('');
      setError('');
      queryClient.invalidateQueries({queryKey: ['tijaraq-chat']});
    },
    onError: () => setError('Message could not be sent. Please try again.'),
  });

  function submit(event: FormEvent) {
    event.preventDefault();
    if (draft.trim()) send.mutate(draft.trim());
  }

  return (
    <div className="container mx-auto max-w-6xl px-20 py-32">
      <div className="mb-24 flex items-center justify-between gap-16">
        <div>
          <h1 className="text-3xl font-semibold">TijaraQ Livechat</h1>
          <p className="mt-6 text-muted">{realtime ? 'Messages update in real time.' : 'Messages update automatically.'}</p>
        </div>
        <Link to={agent ? '/dashboard/conversations?viewId=all' : '/'} className="text-primary underline">
          {agent ? 'Open inbox' : 'Help center'}
        </Link>
      </div>
      <div className="grid min-h-[580px] overflow-hidden rounded-lg border bg md:grid-cols-[260px_1fr]">
        <aside className="border-b p-16 md:border-b-0 md:border-r">
          {!agent && (
            <button
              type="button"
              onClick={() => setSelectedId(0)}
              className="mb-16 w-full rounded bg-primary px-16 py-10 text-white"
              disabled={!list.data?.enabled}
            >
              New chat
            </button>
          )}
          {list.isLoading && <p>Loading chats...</p>}
          {list.data?.conversations.map(conversation => (
            <button
              key={conversation.id}
              type="button"
              onClick={() => setSelectedId(conversation.id)}
              className={`mb-8 block w-full rounded px-12 py-10 text-left ${currentId === conversation.id ? 'bg-primary/10' : 'hover:bg-alt'}`}
            >
              <span className="block truncate font-medium">{conversation.subject}</span>
              <span className="text-xs text-muted">Chat #{conversation.id}</span>
            </button>
          ))}
          {list.data?.conversations.length === 0 && <p className="text-sm text-muted">No chats yet.</p>}
        </aside>
        <section className="flex min-h-[580px] flex-col">
          {agent && currentId && currentId > 0 && (
            <div className="border-b px-20 py-10 text-sm">
              <Link className="text-primary underline" to={`/dashboard/ai-assistant?conversationId=${currentId}`}>
                Draft a reply with AI
              </Link>
            </div>
          )}
          <div className="flex-1 space-y-14 overflow-y-auto p-20">
            {currentId === 0 || currentId === null ? (
              <p className="text-muted">Start a conversation with the TijaraQ support team.</p>
            ) : thread.isLoading ? (
              <p>Loading messages...</p>
            ) : thread.isError ? (
              <p>Could not load this chat.</p>
            ) : thread.data?.messages.map(message => (
              <div key={message.id} className={`max-w-[85%] rounded-lg p-12 ${message.author === 'user' ? 'bg-primary/10' : 'bg-alt'}`}>
                <div className="mb-4 text-xs font-semibold">{message.author === 'user' ? 'Customer' : 'Support'}</div>
                <p className="whitespace-pre-wrap break-words">{plainText(message.body)}</p>
              </div>
            ))}
          </div>
          {!list.data?.enabled && <p className="px-20 text-danger">Livechat is disabled by an administrator.</p>}
          {error && <p className="px-20 text-danger" role="alert">{error}</p>}
          <form onSubmit={submit} className="flex gap-12 border-t p-16">
            <input
              value={draft}
              onChange={event => setDraft(event.target.value)}
              placeholder="Write a message..."
              aria-label="Message"
              maxLength={4000}
              disabled={!list.data?.enabled || send.isPending || (agent && !currentId)}
              className="min-w-0 flex-1 rounded border bg px-12 py-10"
            />
            <button type="submit" disabled={!draft.trim() || !list.data?.enabled || send.isPending || (agent && !currentId)} className="rounded bg-primary px-18 py-10 text-white disabled:opacity-50">
              Send
            </button>
          </form>
        </section>
      </div>
    </div>
  );
}
