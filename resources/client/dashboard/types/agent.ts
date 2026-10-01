import {UpdateUserPageUser} from '@common/admin/users/update-user-page/update-user-page-user';
import {NormalizedModel} from '@ui/types/normalized-model';
import {User} from '@ui/types/user';

export type AgentSettings = {
  assignment_limit: number;
  accepts_conversations: 'yes' | 'no' | 'workingHours';
  working_hours: Record<string, {from: string; to: string}> | null;
};

export interface CompactAgent {
  id: number;
  name: string;
  email?: string;
  image: string;
  wasActiveRecently: boolean;
  acceptsConversations: boolean;
  activeAssignedConversationsCount: number;
  groups: {id: number; name: string}[];
}

export interface FullAgent extends UpdateUserPageUser {
  id: User['id'];
  name: User['name'];
  email: User['email'];
  image: User['image'];
  roles: User['roles'];
  banned_at: User['banned_at'];
  bans: User['bans'];
  agent_settings: AgentSettings;
  groups: NormalizedModel[];
}
