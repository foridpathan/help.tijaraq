import {MobileCloseButton} from '@livechat/widget/mobile-close-button';
import {useWidgetStore} from '@livechat/widget/widget-store';
import clsx from 'clsx';
import {ReactNode} from 'react';

interface Props {
  label?: ReactNode;
  children?: ReactNode;
  start?: ReactNode;
  end?: ReactNode;
  className?: string;
}
export function WidgetScreenHeader({
  label,
  children,
  start,
  end,
  className,
}: Props) {
  const isMobile = useWidgetStore(s => s.isMobile);
  return (
    <div
      className={clsx(
        'flex flex-shrink-0 flex-col items-center justify-center overflow-hidden rounded-t-panel border-b bg px-8 py-10 text-main',
        !isMobile && 'rounded-t-panel',
        className,
      )}
    >
      <div className="flex w-full items-center justify-between gap-8">
        <div className="mr-auto flex-1">{start}</div>
        <div className="min-w-0 flex-auto overflow-hidden text-ellipsis whitespace-nowrap text-center text-lg font-semibold leading-[42px]">
          {label}
        </div>
        <div className="ml-auto flex flex-1 justify-end">
          {end}
          <MobileCloseButton />
        </div>
      </div>
      {children && <div className="mt-8 w-full">{children}</div>}
    </div>
  );
}
