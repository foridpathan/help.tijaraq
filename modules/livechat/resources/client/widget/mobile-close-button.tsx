import {useIsWidgetInline} from '@livechat/widget/hooks/use-is-widget-inline';
import {useWidgetStore, widgetStore} from '@livechat/widget/widget-store';
import {IconButton} from '@ui/buttons/icon-button';
import {CloseIcon} from '@ui/icons/material/Close';

export function MobileCloseButton() {
  const isMobile = useWidgetStore(s => s.isMobile);
  const {isInline} = useIsWidgetInline();

  if (!isMobile || isInline) return null;

  return (
    <IconButton onClick={() => widgetStore().setWidgetState('closed')}>
      <CloseIcon />
    </IconButton>
  );
}
