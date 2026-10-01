import {CompactAttribute} from '@app/attributes/compact-attribute';
import {PageVisit} from '@app/dashboard/conversations/conversation-page/details-sidebar/page-visists-panel';
import {PaginatedBackendResponse} from '@common/http/backend-response/pagination-response';
import {EnvatoPurchaseCode} from '@envato/envato-purchase-code';
import {ConversationSummary} from '../../../modules/ai/resources/client/conversation-summary-panel/conversation-summary';

export const AGENT_PERMISSION = 'tickets.update';
export const TICKET_MODEL_TYPE = 'ticket';
export const CONVERSATION_MODEL_TYPE = 'conversation';

export interface CustomerSession {
  id: number;
  ip_address: string;
  platform: string;
  browser: string;
  device: string;
}

export interface FullConversationResponse {
  conversation: {
    id: number;
    model_type: 'conversation';
    type: 'ticket' | 'chat';
    status_id: number;
    status_category: number;
    subject?: string;
    assignee?: {id: number; name: string; image: string};
    group?: {id: number; name: string};
    rating?: boolean | null;
    created_at?: string;
    updated_at?: string;
    channel?: 'email' | 'widget' | 'website';
  };
  user: {
    id: number;
    name?: string;
    email?: string;
    country?: string;
    city?: string;
    timezone?: string;
    tags: string[];
    banned_at: string | null;
    bans: {
      id: number;
      expired_at: string;
      comment: string;
    }[];
  };
  visits: PaginatedBackendResponse<PageVisit>;
  summary: ConversationSummary | null;
  session: CustomerSession | null;
  attributes: CompactAttribute[];
  envatoPurchaseCodes: EnvatoPurchaseCode[];
  tags: ConversationTag[];
}

export interface ConversationTag {
  id: number;
  name: string;
}

export interface ConversationListItemType {
  id: number;
  subject: string | null;
  latest_message: {
    body: string;
    author: 'user' | 'agent' | 'system' | 'bot';
    user: {
      id: number;
      name: string | null;
      image: string | null;
    };
    created_at: string;
  } | null;
  status_category: number;
  status_label: string;
  customer_status_label?: string;
  type: 'ticket' | 'chat';
  channel: 'email' | 'widget' | 'website';
  priority: number;
  updated_at: string | null;
  created_at: string | null;
  closed_at: string | null;
  assigned_at: string | null;
  closed_by: string | null;
  group: {id: number; name: string} | null;
  assignee: {
    id: number;
    name: string | null;
    image: string | null;
    email: string | null;
  } | null;
  user: {
    id: number;
    name: string | null;
    image: string | null;
    email: string | null;
  };
  tags: ConversationTag[];
  attributes?: Record<string, unknown>;
}
