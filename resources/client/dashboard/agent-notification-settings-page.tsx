import {DatatablePageHeaderBar} from '@common/datatable/page/datatable-page-with-header-layout';
import {NotificationSettings} from '@common/notifications/subscriptions/notification-settings-page';
import {Trans} from '@ui/i18n/trans';

export function Component() {
  return (
    <div className="flex h-full flex-col">
      <DatatablePageHeaderBar showSidebarToggleButton>
        <Trans message="Your notification preferences" />
      </DatatablePageHeaderBar>
      <div className="flex-auto overflow-y-auto">
        <div className="container mx-auto px-12 py-44 md:px-24">
          <NotificationSettings />
        </div>
      </div>
    </div>
  );
}
