import {apiClient} from '@common/http/query-client';
import {useMutation} from '@tanstack/react-query';
import {FormEvent, useState} from 'react';
import {Link, useSearchParams} from 'react-router';

export function Component() {
  const [searchParams] = useSearchParams();
  const [prompt, setPrompt] = useState('');
  const [conversationId, setConversationId] = useState(searchParams.get('conversationId') ?? '');
  const [answer, setAnswer] = useState('');
  const ask = useMutation({
    mutationFn: () => apiClient.post<{answer: string; provider: string; model: string}>(
      'tijaraq-ai/ask',
      {prompt, conversation_id: conversationId ? Number(conversationId) : undefined},
    ).then(response => response.data),
    onSuccess: data => setAnswer(data.answer),
  });

  function submit(event: FormEvent) {
    event.preventDefault();
    setAnswer('');
    ask.mutate();
  }

  return (
    <div className="dashboard-grid-content dashboard-rounded-panel p-24">
      <div className="mb-20 flex items-center justify-between">
        <h1 className="text-2xl font-semibold">TijaraQ AI assistant</h1>
        <Link to="/dashboard/conversations?viewId=all" className="text-primary underline">Back to inbox</Link>
      </div>
      <p className="mb-24 max-w-2xl text-muted">Ask for a draft reply or enter a conversation ID to include its latest messages. Review and edit the result before sending.</p>
      <form onSubmit={submit} className="max-w-3xl space-y-16">
        <label className="block">
          <span className="mb-6 block font-medium">Conversation ID (optional)</span>
          <input type="number" min="1" className="w-full rounded border bg px-12 py-10" value={conversationId} onChange={event => setConversationId(event.target.value)} />
        </label>
        <label className="block">
          <span className="mb-6 block font-medium">What should the assistant draft?</span>
          <textarea className="min-h-140 w-full rounded border bg p-12" value={prompt} maxLength={4000} required onChange={event => setPrompt(event.target.value)} placeholder="Draft a helpful reply to the customer..." />
        </label>
        <button type="submit" disabled={ask.isPending || !prompt.trim()} className="rounded bg-primary px-18 py-10 text-white disabled:opacity-50">{ask.isPending ? 'Generating...' : 'Generate draft'}</button>
      </form>
      {ask.isError && <p className="mt-16 text-danger" role="alert">AI is unavailable. Ask an admin to check the provider key and model.</p>}
      {answer && <div className="mt-28 max-w-3xl rounded border bg-alt p-20"><h2 className="mb-10 font-semibold">Draft reply</h2><p className="whitespace-pre-wrap">{answer}</p></div>}
    </div>
  );
}
