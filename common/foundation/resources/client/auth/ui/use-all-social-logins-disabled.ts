import {useSettings} from '@ui/settings/use-settings';

interface Props {
  isUsingInvite?: boolean;
}
export function useAllSocialLoginsDisabled(_: Props = {}): boolean {
  const {social} = useSettings();
  return (
    !social?.google?.enable &&
    !social?.facebook?.enable &&
    !social?.twitter?.enable
  );
}
