import {useWidgetBootstrapData} from '@livechat/widget/hooks/use-widget-bootstrap-data';
import {useWidgetLogoSrc} from '@livechat/widget/hooks/use-widget-logo-src';
import {Avatar, AvatarProps} from '@ui/avatar/avatar';

interface Props {
  size?: AvatarProps['size'];
  className?: string;
}
export function AiAgentAvatar({className, size}: Props) {
  const {aiAgent} = useWidgetBootstrapData();
  const logoSrc = useWidgetLogoSrc();
  const label = aiAgent?.name || 'AI assistant';
  const image = aiAgent?.image || logoSrc;

  return (
    <Avatar
      circle={false}
      fallback="initials"
      label={label}
      labelForBackground={label}
      src={image}
      className={className}
      size={size}
    />
  );
}
