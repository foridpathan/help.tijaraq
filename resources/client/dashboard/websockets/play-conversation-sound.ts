import {getFromLocalStorage} from '@ui/utils/hooks/local-storage';

export type ConversationSoundName =
  | 'message'
  | 'newVisitor'
  | 'incomingChat'
  | 'queuedVisitor';

const audioCache: Record<string, HTMLAudioElement> = {};

export function playConversationSound(
  sound: ConversationSoundName,
  key: 'dashboard' | 'widget',
) {
  const soundsDisabled = getFromLocalStorage(
    `${key}-chatSoundsDisabled`,
    false,
  );
  if (soundsDisabled) return null;
  const snakeCase = sound.replace(/([A-Z])/g, '-$1').toLowerCase();
  const audio = audioCache[snakeCase] ?? new Audio(`/sounds/${snakeCase}.mp3`);
  audioCache[snakeCase] = audio;
  audio.currentTime = 0;
  audio.volume = 0.4;

  // catch autoplay and permission errors
  try {
    audio.play();
  } catch (error) {
    //
  }
}
