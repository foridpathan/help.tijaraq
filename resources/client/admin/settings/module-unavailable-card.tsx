import {SettingsMobileNav} from '@common/admin/settings/layout/settings-layout';
import {DatatablePageHeaderBar} from '@common/datatable/page/datatable-page-with-header-layout';
import {useIsMobileMediaQuery} from '@ui/utils/hooks/is-mobile-media-query';
import {ReactElement, ReactNode} from 'react';

type Props = {
  title: ReactNode;
  description: ReactNode;
  icon: ReactElement;
};

export function ModuleUnavailableCard({title, description, icon}: Props) {
  const isMobile = useIsMobileMediaQuery();

  return (
    <div className="dashboard-grid-content dashboard-rounded-panel relative flex flex-auto flex-col">
      <DatatablePageHeaderBar
        showSidebarToggleButton={!!isMobile}
        title={title}
        rightContent={isMobile && <SettingsMobileNav />}
      />
      <div className="p-24">
        <div className="max-w-680 rounded-panel border p-24 shadow">
          <div className="mb-24">{icon}</div>
          <div className="mb-10 text-base font-medium">{title}</div>
          <div className="text-sm text-muted">{description}</div>
        </div>
      </div>
    </div>
  );
}
