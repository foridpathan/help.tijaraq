import {apiClient} from '@common/http/query-client';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {FormEvent, useEffect, useState} from 'react';

type ProviderName = 'openai' | 'anthropic' | 'gemini' | 'openrouter';
type Settings = {
  provider: ProviderName;
  providers: Record<ProviderName, {configured: boolean; model: string}>;
};

export function Component() {
  const queryClient = useQueryClient();
  const settings = useQuery({
    queryKey: ['admin', 'tijaraq-ai'],
    queryFn: () => apiClient.get<{settings: Settings}>('admin/tijaraq-ai').then(response => response.data.settings),
  });
  const [provider, setProvider] = useState<ProviderName>('openai');
  const [model, setModel] = useState('gpt-4o-mini');
  const [apiKey, setApiKey] = useState('');
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    if (settings.data) {
      setProvider(settings.data.provider);
      setModel(settings.data.providers[settings.data.provider]?.model ?? '');
    }
  }, [settings.data]);

  const update = useMutation({
    mutationFn: () => apiClient.put('admin/tijaraq-ai', {provider, model, api_key: apiKey || undefined}),
    onSuccess: () => {
      setApiKey('');
      setSaved(true);
      queryClient.invalidateQueries({queryKey: ['admin', 'tijaraq-ai']});
    },
  });

  function submit(event: FormEvent) {
    event.preventDefault();
    setSaved(false);
    update.mutate();
  }

  return (
    <div className="dashboard-grid-content dashboard-rounded-panel p-24">
      <h1 className="mb-8 text-2xl font-semibold">TijaraQ AI assistant</h1>
      <p className="mb-24 text-muted">Choose a provider and model. API keys stay on the server and are never shown again. Agents review drafts before sending them.</p>
      <form onSubmit={submit} className="max-w-xl space-y-18">
        <label className="block">
          <span className="mb-6 block font-medium">Provider</span>
          <select
            className="w-full rounded border bg px-12 py-10"
            value={provider}
            onChange={event => {
              const next = event.target.value as ProviderName;
              setProvider(next);
              setModel(settings.data?.providers[next]?.model ?? '');
              setSaved(false);
            }}
          >
            {(['openai', 'anthropic', 'gemini', 'openrouter'] as ProviderName[]).map(name => (
              <option key={name} value={name}>{name} {settings.data?.providers[name]?.configured ? '✓ configured' : '— key needed'}</option>
            ))}
          </select>
        </label>
        <label className="block">
          <span className="mb-6 block font-medium">Model</span>
          <input className="w-full rounded border bg px-12 py-10" value={model} maxLength={100} required onChange={event => setModel(event.target.value)} />
        </label>
        <label className="block">
          <span className="mb-6 block font-medium">API key</span>
          <input className="w-full rounded border bg px-12 py-10" type="password" value={apiKey} autoComplete="off" placeholder={settings.data?.providers[provider]?.configured ? 'Leave blank to keep current key' : 'Enter provider API key'} onChange={event => setApiKey(event.target.value)} />
        </label>
        <button className="rounded bg-primary px-18 py-10 text-white disabled:opacity-50" type="submit" disabled={update.isPending || !model.trim()}>Save AI settings</button>
        {saved && <p className="text-positive">Saved. Agents can use Dashboard → AI assistant.</p>}
        {update.isError && <p className="text-danger" role="alert">Could not save AI settings.</p>}
      </form>
    </div>
  );
}
