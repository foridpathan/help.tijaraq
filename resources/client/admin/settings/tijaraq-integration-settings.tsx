import {apiClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {SettingsPanel} from '@common/admin/settings/layout/settings-panel';
import {SectionHelper} from '@common/ui/other/section-helper';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {Button} from '@ui/buttons/button';
import {TextField} from '@ui/forms/input-field/text-field/text-field';
import {Checkbox} from '@ui/forms/toggle/checkbox';
import {Switch} from '@ui/forms/toggle/switch';
import {message} from '@ui/i18n/message';
import {Trans} from '@ui/i18n/trans';
import {toast} from '@ui/toast/toast';
import useClipboard from '@ui/utils/hooks/use-clipboard';
import {ReactNode, useEffect, useState} from 'react';

interface Status {
  enabled: boolean;
  api_key_ids: string[];
  webhook_url: string | null;
  webhook_secret_set: boolean;
  sso_issuer: string;
  sso_audience: string;
  sso_kids: string[];
  sso_public_keys_path: string;
  allowed_ips: string;
  rate_limit_per_tenant: number;
  ready: boolean;
  checks: Record<string, boolean>;
  defaults: {helpdesk_url: string; main_app_url: string};
}

interface GenerateResult {
  main_app: Record<string, string>;
  private_key: {kid: string; pem: string} | null;
  generated: string[];
  notes: string[];
  status: Status;
}

type Part = 'api_key' | 'webhook_secret' | 'sso_key';

const endpoint = 'admin/tijaraq-integration';
const queryKey = ['admin', 'tijaraq-integration'];

function useStatus() {
  return useQuery({
    queryKey,
    queryFn: () =>
      apiClient
        .get<{status: Status}>(endpoint)
        .then(response => response.data.status),
  });
}

export function Component() {
  const {data: status} = useStatus();

  return (
    <div className="dashboard-grid-content dashboard-rounded-panel relative flex flex-auto flex-col">
      <div className="border-b px-24 py-16 text-lg font-medium">
        <Trans message="TijaraQ integration" />
      </div>
      <div className="flex-auto overflow-y-auto">
        <div className="mx-auto p-12 @container/settings-form md:p-24 lg:max-w-[1440px]">
          {status ? (
            <div className="space-y-24">
              <StatusSection status={status} />
              <GenerateSection status={status} />
              <SettingsSection status={status} />
            </div>
          ) : (
            <Trans message="Loading..." />
          )}
        </div>
      </div>
    </div>
  );
}

function StatusSection({status}: {status: Status}) {
  const labels: [string, ReactNode][] = [
    ['enabled', <Trans key="e" message="Integration enabled" />],
    ['api_key', <Trans key="a" message="API key configured" />],
    ['webhook_secret', <Trans key="w" message="Webhook secret configured" />],
    ['webhook_url', <Trans key="u" message="Webhook URL configured" />],
    ['sso_public_key', <Trans key="s" message="SSO public key installed" />],
    ['env_writable', <Trans key="f" message=".env file is writable" />],
  ];

  return (
    <SettingsPanel
      layout="vertical"
      title={<Trans message="Status" />}
      description={
        <Trans message="Everything below must be green for the main app to connect. Secrets are kept in the .env file, never in the database, and are never shown again after they are generated." />
      }
    >
      <ul className="space-y-8 text-sm">
        {labels.map(([key, label]) => (
          <li key={key} className="flex items-center gap-8">
            <span
              className={
                status.checks[key] ? 'text-positive' : 'text-danger'
              }
            >
              {status.checks[key] ? '✓' : '✗'}
            </span>
            {label}
          </li>
        ))}
      </ul>
      {status.api_key_ids.length > 0 && (
        <div className="mt-16 text-sm text-muted">
          <Trans message="API key ids" />: {status.api_key_ids.join(', ')}
          {' · '}
          <Trans message="SSO key ids" />: {status.sso_kids.join(', ') || '-'}
        </div>
      )}
    </SettingsPanel>
  );
}

function GenerateSection({status}: {status: Status}) {
  const queryClient = useQueryClient();
  const [mainAppUrl, setMainAppUrl] = useState(
    status.sso_issuer || status.defaults.main_app_url,
  );
  const [helpdeskUrl, setHelpdeskUrl] = useState(
    status.sso_audience || status.defaults.helpdesk_url,
  );
  const [parts, setParts] = useState<Record<Part, boolean>>({
    api_key: true,
    webhook_secret: true,
    sso_key: true,
  });
  const [keepOld, setKeepOld] = useState(false);
  const [result, setResult] = useState<GenerateResult | null>(null);

  const generate = useMutation({
    mutationFn: () =>
      apiClient
        .post<GenerateResult>(`${endpoint}/generate`, {
          parts: (Object.keys(parts) as Part[]).filter(p => parts[p]),
          main_app_url: mainAppUrl,
          helpdesk_url: helpdeskUrl,
          keep_old_api_key: keepOld,
          enable: true,
        })
        .then(response => response.data),
    onSuccess: data => {
      setResult(data);
      queryClient.setQueryData(queryKey, data.status);
      toast(message('Generated and saved. Copy the main app values now.'));
    },
    onError: err => showHttpErrorToast(err),
  });

  const hasExistingKey = status.api_key_ids.length > 0;
  const nothingSelected = !Object.values(parts).some(Boolean);

  return (
    <SettingsPanel
      layout="vertical"
      title={<Trans message="Generate keys and connection details" />}
      description={
        <Trans message="Creates the API key, webhook secret and SSO key pair, saves the helpdesk side to the .env file and gives you the matching values for the main app." />
      }
    >
      <div className="grid max-w-[720px] gap-16">
        <TextField
          size="sm"
          label={<Trans message="Main app URL" />}
          description={
            <Trans message="Where app.tijaraq.com runs. Used for the webhook URL and the SSO issuer." />
          }
          value={mainAppUrl}
          onChange={e => setMainAppUrl(e.target.value)}
          placeholder="https://app.tijaraq.com"
        />
        <TextField
          size="sm"
          label={<Trans message="Helpdesk URL" />}
          description={
            <Trans message="Public URL of this helpdesk. Used as the SSO audience." />
          }
          value={helpdeskUrl}
          onChange={e => setHelpdeskUrl(e.target.value)}
          placeholder="https://help.tijaraq.com"
        />
        <div className="space-y-6">
          <Checkbox
            checked={parts.api_key}
            onChange={e => setParts({...parts, api_key: e.target.checked})}
          >
            <Trans message="API key (main app to helpdesk requests)" />
          </Checkbox>
          <Checkbox
            checked={parts.webhook_secret}
            onChange={e =>
              setParts({...parts, webhook_secret: e.target.checked})
            }
          >
            <Trans message="Webhook secret (helpdesk to main app events)" />
          </Checkbox>
          <Checkbox
            checked={parts.sso_key}
            onChange={e => setParts({...parts, sso_key: e.target.checked})}
          >
            <Trans message="SSO key pair (merchant sign-in)" />
          </Checkbox>
        </div>
        {hasExistingKey && parts.api_key && (
          <Switch checked={keepOld} onChange={e => setKeepOld(e.target.checked)}>
            <Trans message="Key rotation: keep the old API key working until the main app switches" />
          </Switch>
        )}
        {hasExistingKey && !keepOld && parts.api_key && (
          <SectionHelper
            color="warning"
            description={
              <Trans message="The current API key stops working as soon as you generate. Update the main app straight away, or turn on key rotation." />
            }
          />
        )}
        <div>
          <Button
            variant="flat"
            color="primary"
            disabled={generate.isPending || nothingSelected}
            onClick={() => generate.mutate()}
          >
            <Trans message="Generate and save" />
          </Button>
        </div>
      </div>

      {result && <ResultBlock result={result} />}
    </SettingsPanel>
  );
}

function ResultBlock({result}: {result: GenerateResult}) {
  const envBlock = Object.entries(result.main_app)
    .map(([key, value]) => `${key}=${value}`)
    .join('\n');

  return (
    <div className="mt-24 max-w-[720px] space-y-16">
      <SectionHelper
        color="positive"
        title={<Trans message="Saved on the helpdesk" />}
        description={
          <Trans message="These values are shown only once. Put them in the main app now." />
        }
      />
      <CopyField
        label={<Trans message="Main app .env" />}
        value={envBlock}
        rows={Object.keys(result.main_app).length + 1}
      />
      {result.private_key && (
        <div>
          <CopyField
            label={
              <Trans
                message="SSO private key (save as storage/app/private/helpdesk-sso/:kid.pem in the main app)"
                values={{kid: result.private_key.kid}}
              />
            }
            value={result.private_key.pem}
            rows={8}
          />
          <Button
            className="mt-8"
            variant="outline"
            size="xs"
            onClick={() =>
              downloadText(
                `${result.private_key!.kid}.pem`,
                result.private_key!.pem,
              )
            }
          >
            <Trans message="Download .pem file" />
          </Button>
        </div>
      )}
      {result.notes.map(note => (
        <div key={note} className="text-sm text-muted">
          {note}
        </div>
      ))}
    </div>
  );
}

function CopyField({
  label,
  value,
  rows,
}: {
  label: ReactNode;
  value: string;
  rows: number;
}) {
  const [copied, copy] = useClipboard(value);

  return (
    <div>
      <TextField
        size="xs"
        label={label}
        inputElementType="textarea"
        rows={rows}
        value={value}
        readOnly
        onFocus={e => e.target.select()}
      />
      <Button className="mt-8" variant="outline" size="xs" onClick={copy}>
        {copied ? <Trans message="Copied" /> : <Trans message="Copy" />}
      </Button>
    </div>
  );
}

function downloadText(fileName: string, text: string) {
  const url = URL.createObjectURL(new Blob([text], {type: 'text/plain'}));
  const link = document.createElement('a');
  link.href = url;
  link.download = fileName;
  link.click();
  URL.revokeObjectURL(url);
}

function SettingsSection({status}: {status: Status}) {
  const queryClient = useQueryClient();
  const [enabled, setEnabled] = useState(status.enabled);
  const [webhookUrl, setWebhookUrl] = useState(status.webhook_url ?? '');
  const [allowedIps, setAllowedIps] = useState(status.allowed_ips);
  const [rateLimit, setRateLimit] = useState(
    String(status.rate_limit_per_tenant),
  );

  useEffect(() => {
    setEnabled(status.enabled);
    setWebhookUrl(status.webhook_url ?? '');
    setAllowedIps(status.allowed_ips);
    setRateLimit(String(status.rate_limit_per_tenant));
  }, [status]);

  const save = useMutation({
    mutationFn: () =>
      apiClient
        .put<{status: Status}>(endpoint, {
          enabled,
          webhook_url: webhookUrl,
          allowed_ips: allowedIps,
          rate_limit_per_tenant: Number(rateLimit),
        })
        .then(response => response.data.status),
    onSuccess: data => {
      queryClient.setQueryData(queryKey, data);
      toast(message('Settings saved'));
    },
    onError: err => showHttpErrorToast(err),
  });

  return (
    <SettingsPanel
      layout="vertical"
      title={<Trans message="Connection settings" />}
      description={
        <Trans message="Non-secret options. Saved to the .env file." />
      }
    >
      <div className="grid max-w-[720px] gap-16">
        <Switch checked={enabled} onChange={e => setEnabled(e.target.checked)}>
          <Trans message="Enable the TijaraQ integration" />
        </Switch>
        <TextField
          size="sm"
          label={<Trans message="Webhook URL" />}
          value={webhookUrl}
          onChange={e => setWebhookUrl(e.target.value)}
          placeholder="https://app.tijaraq.com/webhooks/helpdesk"
        />
        <TextField
          size="sm"
          label={<Trans message="Allowed IP addresses" />}
          description={
            <Trans message="Optional, comma separated IPs or CIDR ranges. Leave empty to allow any address." />
          }
          value={allowedIps}
          onChange={e => setAllowedIps(e.target.value)}
        />
        <TextField
          size="sm"
          type="number"
          label={<Trans message="Requests per minute per company" />}
          value={rateLimit}
          onChange={e => setRateLimit(e.target.value)}
        />
        <div>
          <Button
            variant="flat"
            color="primary"
            disabled={save.isPending}
            onClick={() => save.mutate()}
          >
            <Trans message="Save" />
          </Button>
        </div>
      </div>
    </SettingsPanel>
  );
}
