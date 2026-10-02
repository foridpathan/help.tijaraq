import {apiClient} from '@common/http/query-client';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';

export function Component() {
  const queryClient = useQueryClient();
  const settings = useQuery({
    queryKey: ['admin', 'tijaraq-chat'],
    queryFn: () => apiClient.get<{enabled: boolean}>('admin/tijaraq-chat').then(response => response.data),
  });
  const update = useMutation({
    mutationFn: (enabled: boolean) => apiClient.put('admin/tijaraq-chat', {enabled}),
    onSuccess: () => queryClient.invalidateQueries({queryKey: ['admin', 'tijaraq-chat']}),
  });

  return (
    <div className="dashboard-grid-content dashboard-rounded-panel p-24">
      <h1 className="mb-8 text-2xl font-semibold">TijaraQ Livechat</h1>
      <p className="mb-24 text-muted">Customers sign in and chat with your support team. Agents reply from Dashboard → Livechat or the conversation inbox.</p>
      {settings.isLoading ? <p>Loading...</p> : (
        <label className="flex items-center gap-12">
          <input
            type="checkbox"
            checked={!!settings.data?.enabled}
            onChange={event => update.mutate(event.target.checked)}
            disabled={update.isPending}
          />
          Enable Livechat
        </label>
      )}
      {update.isError && <p className="mt-12 text-danger" role="alert">Could not save Livechat settings.</p>}
      <p className="mt-24 text-sm text-muted">Customer link: /livechat · Agent link: /dashboard/livechat. Messages appear in the existing conversation inbox.</p>
    </div>
  );
}
