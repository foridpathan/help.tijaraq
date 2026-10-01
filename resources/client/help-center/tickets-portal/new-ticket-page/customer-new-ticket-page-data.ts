import {CompactAttribute} from '@app/attributes/compact-attribute';

export interface CustomerNewTicketPageData {
  attributes: CompactAttribute[];
  config: {
    title: string;
    submitButtonText: string;
    sidebarTitle: string;
    sidebarTips: {title: string; content: string}[];
    attributeIds?: number[];
  };
  customerEmail?: string;
  customerHasVerifiedEmail?: boolean;
}
