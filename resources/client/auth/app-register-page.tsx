import {RegisterPage} from '@common/auth/ui/register-page';
import {useLocation} from 'react-router';

export function Component() {
  const {pathname} = useLocation();
  const isAgentRoute = pathname.includes('agents/join');
  return <RegisterPage inviteType={isAgentRoute ? 'agentInvite' : undefined} />;
}
