import {AttributeSelectorItem} from '@app/attributes/attribute-selector/attribute-selector-item';
import {CompactAttribute} from '@app/attributes/compact-attribute';
import {ConversationAttachment} from '@app/dashboard/types/conversation-attachment';

interface BaseItem {
  id: number;
  uuid: string;
  conversation_id: number;
  author: 'user' | 'agent' | 'system' | 'bot';
  created_at: string;
  user: {
    id: number;
    name: string | null;
    image: string | null | undefined;
  } | null;
}

export interface ConversationMessage extends BaseItem {
  type: 'message' | 'note';
  body: string;
  attachments: ConversationAttachment[];
  source: 'email' | null;
  data?: {
    preventTyping?: boolean;
    buttons?: MessageButton[];
  };
}

export interface PlaceholderConversationMessage
  extends Omit<ConversationMessage, 'id' | 'type'> {
  id: string | number;
  type: 'message' | 'note' | 'typing' | 'streaming';
  is_placeholder: true;
  is_streaming?: boolean;
}

export interface ConversationEvent extends Omit<BaseItem, 'author'> {
  author: 'system';
  type: 'event';
  body: {
    name:
      | 'customer.enteredEmail'
      | 'closed.inactivity'
      | 'closed.byAgent'
      | 'closed.byCustomer'
      | 'closed.byTrigger'
      | 'closed.byAiAgent'
      | 'customer.idle'
      | 'customer.leftChat'
      | 'customer.addedToQueue'
      | 'agent.leftChat'
      | 'agent.changed'
      | 'group.changed';
    oldAgent?: string;
    newAgent?: string;
    newGroup?: string;
    closedBy?: string;
    email?: string;
  };
}

export interface CardsMessage extends BaseItem {
  type: 'cards';
  body: {
    items: {
      title?: string;
      description?: string;
      image?: string;
      buttons: MessageButton[];
    }[];
  };
}

export type MessageButton = {
  name: string;
  actionType:
    | 'openUrl'
    | 'copyToClipboard'
    | 'goToNode'
    | 'openEmbed'
    | 'sendMessage'
    | 'setAttributes';
  actionValue: string;
  attributes?: (AttributeSelectorItem & {
    value: string;
  })[];
};

export interface CollectDetailsMessage extends Omit<BaseItem, 'author'> {
  author: 'system' | 'bot';
  type: 'collectDetailsForm';
  body: {
    submitted?: boolean;
    message?: string;
    attributeIds?: number[];
  };
}

export interface SubmittedFormDataMessage extends BaseItem {
  type: 'submittedFormData';
  body: {
    formType: 'preChat' | 'postChat' | 'collectDetails';
    attributes: {
      key: string;
      name: string;
      format: CompactAttribute['format'];
      value: unknown;
    }[];
  };
}

export type ConversationContentItem =
  | ConversationMessage
  | PlaceholderConversationMessage
  | ConversationEvent
  | SubmittedFormDataMessage
  | CollectDetailsMessage
  | CardsMessage;
