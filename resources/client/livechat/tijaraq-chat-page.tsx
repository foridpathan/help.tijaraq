import {apiClient} from '@common/http/query-client';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {FormEvent, ReactNode, useEffect, useRef, useState} from 'react';
import {Link} from 'react-router';
import {chatRealtimeEnabled, useChatRealtime} from './use-chat-realtime';
import './tijaraq-chat-page.css';

type Conversation = {
  id: number;
  subject: string;
  status_category: number;
  created_at: string;
  updated_at: string;
  user?: {id: number; name: string | null} | null;
  latest_message?: {body: string; author: string; created_at: string} | null;
};
type Message = {id: number; body: string; author: string; created_at: string};

type IconName = 'search' | 'plus' | 'send' | 'arrow' | 'info' | 'close' | 'sparkle' | 'message';
const iconPaths: Record<IconName, ReactNode> = {
  search: <><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></>,
  plus: <path d="M12 5v14M5 12h14"/>,
  send: <><path d="m21 3-8.2 18-3.3-7.5L2 10.2 21 3Z"/><path d="M9.5 13.5 21 3"/></>,
  arrow: <path d="m15 18-6-6 6-6"/>,
  info: <><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></>,
  close: <path d="M18 6 6 18M6 6l12 12"/>,
  sparkle: <><path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3ZM19 17l.7 1.3L21 19l-1.3.7L19 21l-.7-1.3L17 19l1.3-.7L19 17Z"/></>,
  message: <path d="M20 11.5a8 8 0 0 1-8 8 8.5 8.5 0 0 1-4-.9L3 20l1.4-4.5A8 8 0 1 1 20 11.5Z"/>,
};
function Icon({name, size = 20}: {name: IconName; size?: number}) {
  return <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{iconPaths[name]}</svg>;
}
function plainText(html: string): string {
  return new DOMParser().parseFromString(html.replace(/<br\s*\/?\s*>/gi, '\n'), 'text/html').body.textContent ?? '';
}
function shortTime(date?: string) {
  if (!date) return '';
  const value = new Date(date);
  if (Number.isNaN(value.getTime())) return '';
  const now = new Date();
  if (value.toDateString() === now.toDateString()) return value.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
  return value.toLocaleDateString([], {month: 'short', day: 'numeric'});
}
function initials(name: string) {
  return name.split(/\s+/).slice(0, 2).map(part => part.charAt(0)).join('').toUpperCase() || 'C';
}
function statusLabel(category: number) {
  return category <= 4 ? 'Closed' : category === 5 ? 'Pending' : 'Open';
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
  const [mobileThreadOpen, setMobileThreadOpen] = useState(false);
  const [detailsOpen, setDetailsOpen] = useState(true);
  const [search, setSearch] = useState('');
  const [draft, setDraft] = useState('');
  const [error, setError] = useState('');
  const endRef = useRef<HTMLDivElement>(null);

  const list = useQuery({
    queryKey: ['tijaraq-chat', 'list', agent],
    queryFn: () => apiClient.get<{enabled: boolean; conversations: Conversation[]}>(
      `tijaraq-chat/conversations${agent ? '?agent=1' : ''}`,
    ).then(response => response.data),
    refetchInterval: realtime ? 60_000 : 30_000,
  });
  const currentId = selectedId ?? list.data?.conversations[0]?.id ?? null;
  const selected = list.data?.conversations.find(conversation => conversation.id === currentId);
  useChatRealtime(agent, currentId);
  const thread = useQuery({
    queryKey: ['tijaraq-chat', 'thread', currentId],
    enabled: currentId !== null && currentId > 0,
    queryFn: () => apiClient.get<{conversation: Conversation; messages: Message[]}>(
      `tijaraq-chat/conversations/${currentId}`,
    ).then(response => response.data),
    refetchInterval: realtime ? 60_000 : 30_000,
  });
  const conversation = thread.data?.conversation ?? selected;
  const title = currentId === null ? 'Select a conversation' : currentId === 0 ? 'New conversation' : agent
    ? conversation?.user?.name || `Customer #${conversation?.user?.id ?? ''}`
    : 'TijaraQ Support';
  const closed = !!conversation && conversation.status_category <= 3;
  const filtered = list.data?.conversations.filter(item => {
    const term = search.trim().toLowerCase();
    return !term || item.subject?.toLowerCase().includes(term) || item.user?.name?.toLowerCase().includes(term) || String(item.id).includes(term);
  }) ?? [];

  useEffect(() => {
    endRef.current?.scrollIntoView({behavior: 'smooth', block: 'end'});
  }, [currentId, thread.data?.messages.length]);

  const send = useMutation({
    mutationFn: async (message: string) => {
      if (currentId !== null && currentId > 0) {
        await apiClient.post(`tijaraq-chat/conversations/${currentId}/messages`, {message});
        return currentId;
      }
      const response = await apiClient.post<{conversation_id: number}>('tijaraq-chat/conversations', {message});
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
  const canSend = !!list.data?.enabled && !send.isPending && !closed && (!agent || !!currentId);
  function submit(event: FormEvent) {
    event.preventDefault();
    if (draft.trim() && canSend) send.mutate(draft.trim());
  }
  function select(id: number) {
    setSelectedId(id);
    setMobileThreadOpen(true);
    setError('');
    setDraft('');
  }

  return <div className={`tq-chat-page ${mobileThreadOpen ? 'tq-chat-page--thread' : ''}`}>
    <div className="tq-chat-heading">
      <div>
        <span className="tq-chat-eyebrow"><span className="tq-chat-live-dot"/> {agent ? 'SUPPORT WORKSPACE' : 'CUSTOMER SUPPORT'}</span>
        <h1>{agent ? 'Live chat' : 'Messages'}</h1>
        <p>{agent ? 'Connect with customers and keep every conversation moving.' : 'A direct line to our support team, whenever you need us.'}</p>
      </div>
      <Link className="tq-chat-heading-link" to={agent ? '/dashboard/conversations?viewId=all' : '/'}>{agent ? 'Open inbox' : 'Help center'} <span aria-hidden="true">↗</span></Link>
    </div>
    <div className={`tq-chat-shell ${detailsOpen ? '' : 'tq-chat-shell--no-details'}`}>
      <aside className="tq-chat-list-panel" aria-label="Conversations">
        <div className="tq-chat-list-header">
          <div className="tq-chat-list-title"><h2>Conversations</h2><span>{list.data?.conversations.length ?? 0}</span></div>
          {!agent && <button type="button" className="tq-chat-new" onClick={() => select(0)} disabled={!list.data?.enabled} aria-label="New conversation" title="New conversation"><Icon name="plus" size={20}/></button>}
        </div>
        <label className="tq-chat-search"><Icon name="search" size={18}/><input value={search} onChange={event => setSearch(event.target.value)} placeholder="Search conversations" aria-label="Search conversations"/></label>
        <div className="tq-chat-list-scroll">
          {!agent && <button type="button" className={`tq-chat-list-item tq-chat-start ${currentId === 0 ? 'is-active' : ''}`} onClick={() => select(0)} disabled={!list.data?.enabled}>
            <span className="tq-chat-avatar tq-chat-avatar--new"><Icon name="plus" size={22}/></span><span className="tq-chat-item-copy"><strong>Start a new chat</strong><small>Ask us anything</small></span>
          </button>}
          {list.isLoading && <p className="tq-chat-list-note">Loading conversations...</p>}
          {list.isError && <p className="tq-chat-list-note">Could not load conversations.</p>}
          {filtered.map(item => {
            const name = agent ? item.user?.name || `Customer #${item.user?.id ?? item.id}` : 'TijaraQ Support';
            return <button key={item.id} type="button" className={`tq-chat-list-item ${currentId === item.id ? 'is-active' : ''}`} onClick={() => select(item.id)} aria-current={currentId === item.id ? 'true' : undefined}>
              <span className="tq-chat-avatar">{initials(name)}</span>
              <span className="tq-chat-item-copy"><span className="tq-chat-item-top"><strong>{name}</strong><time>{shortTime(item.latest_message?.created_at || item.updated_at)}</time></span><small>{item.latest_message ? plainText(item.latest_message.body) : item.subject}</small></span>
            </button>;
          })}
          {!list.isLoading && !list.isError && filtered.length === 0 && <p className="tq-chat-list-note">{search ? 'No matching conversations.' : agent ? 'No conversations yet.' : 'Your conversations will appear here.'}</p>}
        </div>
        <div className="tq-chat-list-footer"><span className="tq-chat-live-dot"/>{realtime ? 'Live updates on' : 'Updates automatically'}</div>
      </aside>

      <main className="tq-chat-thread">
        <header className="tq-chat-thread-header">
          <button className="tq-chat-back" type="button" onClick={() => setMobileThreadOpen(false)} aria-label="Back to conversations"><Icon name="arrow"/></button>
          <span className="tq-chat-avatar tq-chat-avatar--header">{currentId === 0 ? <Icon name="message"/> : initials(title)}</span>
          <div className="tq-chat-thread-heading"><h2>{title}</h2><p>{currentId === 0 ? 'We are here to help' : conversation ? `${statusLabel(conversation.status_category)} · Chat #${conversation.id}` : 'Select a conversation'}</p></div>
          <button type="button" className={`tq-chat-icon-button ${detailsOpen ? 'is-selected' : ''}`} onClick={() => setDetailsOpen(!detailsOpen)} aria-label={detailsOpen ? 'Hide conversation details' : 'Show conversation details'} title="Conversation details"><Icon name="info"/></button>
        </header>
        <div className="tq-chat-messages" aria-live="polite">
          {currentId === 0 || currentId === null ? <div className="tq-chat-empty"><span className="tq-chat-empty-icon"><Icon name="message" size={30}/></span><h3>{agent ? 'Choose a conversation' : 'How can we help?'}</h3><p>{agent ? 'Select a chat on the left to read and reply.' : 'Send your first message and our team will get back to you.'}</p></div>
          : thread.isLoading ? <p className="tq-chat-center-note">Loading messages...</p>
          : thread.isError ? <p className="tq-chat-center-note">Could not load this chat.</p>
          : <div className="tq-chat-message-stack">
              <div className="tq-chat-date-divider"><span>Conversation</span></div>
              {thread.data?.messages.map(message => {
                const own = agent ? message.author === 'agent' : message.author === 'user';
                const author = message.author === 'user' ? (agent ? title : 'You') : (agent ? 'You' : 'TijaraQ Support');
                return <div key={message.id} className={`tq-chat-message-row ${own ? 'is-own' : ''}`}>
                  {!own && <span className="tq-chat-avatar tq-chat-avatar--message">{initials(author)}</span>}
                  <div className="tq-chat-message-wrap"><div className="tq-chat-bubble"><p>{plainText(message.body)}</p></div><div className="tq-chat-message-meta"><span>{author}</span><time dateTime={message.created_at}>{shortTime(message.created_at)}</time></div></div>
                </div>;
              })}
              <div ref={endRef}/>
            </div>}
        </div>
        <div className="tq-chat-compose-area">
          {!list.data?.enabled && <p className="tq-chat-alert">Live chat is currently unavailable.</p>}
          {closed && <p className="tq-chat-alert">This conversation is closed.</p>}
          {error && <p className="tq-chat-alert" role="alert">{error}</p>}
          <form onSubmit={submit} className="tq-chat-composer">
            <input value={draft} onChange={event => setDraft(event.target.value)} placeholder={agent ? 'Write a reply...' : 'Type your message...'} aria-label="Message" maxLength={4000} disabled={!canSend}/>
            <button type="submit" disabled={!draft.trim() || !canSend} aria-label="Send message" title="Send message"><Icon name="send" size={20}/></button>
          </form>
          <span className="tq-chat-compose-hint">Press Enter to send</span>
        </div>
      </main>

      {detailsOpen && <aside className="tq-chat-details" aria-label="Conversation details">
        <div className="tq-chat-details-header"><h2>Chat details</h2><button type="button" onClick={() => setDetailsOpen(false)} aria-label="Close details"><Icon name="close" size={18}/></button></div>
        <div className="tq-chat-details-scroll">
          <div className="tq-chat-profile"><span className="tq-chat-avatar tq-chat-avatar--profile">{initials(title)}</span><h3>{title}</h3><p>{agent ? 'Customer conversation' : 'Customer support'}</p></div>
          <div className="tq-chat-detail-section"><h4>Conversation</h4><div className="tq-chat-detail-row"><span>Status</span><strong className={`tq-chat-status ${conversation?.status_category && conversation.status_category <= 4 ? 'is-closed' : ''}`}><i/>{conversation ? statusLabel(conversation.status_category) : 'New'}</strong></div><div className="tq-chat-detail-row"><span>Chat ID</span><strong>{conversation ? `#${conversation.id}` : '—'}</strong></div><div className="tq-chat-detail-row"><span>Started</span><strong>{conversation?.created_at ? new Date(conversation.created_at).toLocaleDateString([], {month: 'short', day: 'numeric', year: 'numeric'}) : '—'}</strong></div></div>
          {conversation?.subject && <div className="tq-chat-detail-section"><h4>Topic</h4><p className="tq-chat-topic">{conversation.subject}</p></div>}
          {agent && currentId && currentId > 0 && <Link className="tq-chat-ai-link" to={`/dashboard/ai-assistant?conversationId=${currentId}`}><span><Icon name="sparkle" size={18}/></span><span><strong>Draft with AI</strong><small>Get help writing a reply</small></span><span aria-hidden="true">↗</span></Link>}
          <div className="tq-chat-help-card"><span className="tq-chat-help-icon"><Icon name="message" size={20}/></span><strong>{agent ? 'Need more tools?' : 'We’re here to help'}</strong><p>{agent ? 'Open the full inbox to manage this conversation.' : 'Our team will reply here as soon as possible.'}</p>{agent && <Link to="/dashboard/conversations?viewId=all">Go to inbox →</Link>}</div>
        </div>
      </aside>}
    </div>
  </div>;
}
