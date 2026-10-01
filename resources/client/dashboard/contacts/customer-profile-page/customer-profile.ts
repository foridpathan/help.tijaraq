import {CustomerSession} from '@app/dashboard/conversation';
import {CompactAttribute} from '@app/attributes/compact-attribute';

export interface CustomerProfile {
  id: number;
  name: string;
  email: string;
  image: string | null;
  created_at: string;
  updated_at: string;
  last_active_at: string | null;
  was_active_recently: boolean;
  banned_at: string | null;
  ban_reason: string | null;
  timezone: string;
  country: string | null;
  city: string | null;
  language: string | null;
  tags: string[];
  notes: string | null;
  emails: string[];
  attributes: CompactAttribute[];
  session: CustomerSession | null;
}
