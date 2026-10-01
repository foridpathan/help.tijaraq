import {AdminDocsUrls} from '@app/admin/admin-config';
import {useImportEnvatoItems} from '@app/admin/settings/envato-settings/use-import-envato-items';
import {AdminSettings} from '@common/admin/settings/admin-settings';
import {AdminSettingsLayout} from '@common/admin/settings/layout/settings-layout';
import {SettingsPanel} from '@common/admin/settings/layout/settings-panel';
import {useAdminSettings} from '@common/admin/settings/requests/use-admin-settings';
import {Button} from '@ui/buttons/button';
import {LinkStyle} from '@ui/buttons/external-link';
import {FormSwitch} from '@ui/forms/toggle/switch';
import {Trans} from '@ui/i18n/trans';
import {useForm} from 'react-hook-form';
import {Link} from 'react-router';

export function Component() {
  const {data} = useAdminSettings();
  const form = useForm<AdminSettings>({
    defaultValues: {
      client: {
        envato: {
          enable: data.client.envato?.enable ?? false,
          require_purchase_code:
            data.client.envato?.require_purchase_code ?? false,
          active_support: data.client.envato?.active_support ?? false,
          filter_search: data.client.envato?.filter_search ?? false,
        },
      },
    },
  });

  return (
    <AdminSettingsLayout
      form={form}
      title={<Trans message="Envato" />}
      docsLink={AdminDocsUrls.settings.envato}
    >
      <EnvatoIntegrationPanel />
      <PurchaseCodePanel />
      <ActiveSupportPanel />
      <ImportItemsPanel />
    </AdminSettingsLayout>
  );
}

function EnvatoIntegrationPanel() {
  return (
    <SettingsPanel
      className="mb-24"
      title={<Trans message="Envato Integration" />}
      description={
        <Trans message="Enable Envato integration to allow customers to login with their Envato account and validate their support status." />
      }
    >
      <FormSwitch size="sm" name="client.envato.enable">
        <Trans message="Enable Envato integration" />
      </FormSwitch>
    </SettingsPanel>
  );
}

function PurchaseCodePanel() {
  return (
    <SettingsPanel
      className="mb-24"
      title={<Trans message="Purchase Code" />}
      description={
        <Trans message="Whether purchase code is required when creating a ticket." />
      }
    >
      <FormSwitch size="sm" name="client.envato.require_purchase_code">
        <Trans message="Require purchase code" />
      </FormSwitch>
    </SettingsPanel>
  );
}

function ActiveSupportPanel() {
  return (
    <SettingsPanel
      className="mb-24"
      title={<Trans message="Active Support" />}
      description={
        <Trans message="Whether only users with active support can create tickets." />
      }
    >
      <FormSwitch size="sm" name="client.envato.active_support">
        <Trans message="Envato active support" />
      </FormSwitch>
    </SettingsPanel>
  );
}

function ImportItemsPanel() {
  const importItems = useImportEnvatoItems();
  return (
    <SettingsPanel
      className="mb-24"
      title={<Trans message="Import Envato Items" />}
      description={
        <Trans
          message="Imported items can be attached to <a>category attribute</a> for support expiration validation, help center article filtering and other functionality."
          values={{
            a: text => (
              <Link
                className={LinkStyle}
                to="/admin/attributes"
                target="_blank"
              >
                {text}
              </Link>
            ),
          }}
        />
      }
    >
      <div className="mt-10">
        <Button
          variant="flat"
          color="primary"
          disabled={importItems.isPending}
          onClick={() => {
            importItems.mutate();
          }}
        >
          <Trans message="Import now" />
        </Button>
      </div>
    </SettingsPanel>
  );
}
