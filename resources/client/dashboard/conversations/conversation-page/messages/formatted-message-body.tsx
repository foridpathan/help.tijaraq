import {highlightAllCode} from '@common/text-editor/highlight/highlight-code';
import clsx from 'clsx';

export const formattedMessageBodyClassName =
  'prose prose-neutral max-w-none text-sm text-inherit [--tw-prose-bullets:inherit] [--tw-prose-counters:inherit] dark:prose-invert prose-li:my-4 prose-headings:text-sm prose-ul:pl-18 prose-ol:pl-18';

export const bodyClassNameWithoutParagraphSpacing =
  'prose prose-neutral max-w-none text-sm text-inherit [--tw-prose-bullets:inherit] [--tw-prose-counters:inherit] dark:prose-invert prose-li:m-0 prose-headings:text-sm prose-ul:pl-18 prose-ol:pl-18 prose-p:m-0 prose-img:m-0 prose-blockquote:border-l-on-primary';

interface Props {
  className?: string;
  children: string;
  isStreaming?: boolean;
  // If reply is by agent using bedesk reply composer, spaces will be via line breaks, no need for extra spacing. Chatbot will use html <p> tags to add spacing.
  addParagraphSpacing?: boolean;
}
export function FormattedMessageBody({
  className,
  children,
  isStreaming,
  addParagraphSpacing,
}: Props) {
  return (
    <div
      ref={el => {
        if (el && !isStreaming) {
          highlightAllCode(el);
        }
      }}
      className={clsx(
        addParagraphSpacing
          ? formattedMessageBodyClassName
          : bodyClassNameWithoutParagraphSpacing,
        className,
        isStreaming && 'streaming-message-body',
        'compact-scrollbar max-w-full overflow-x-auto',
      )}
      dangerouslySetInnerHTML={{__html: children}}
    />
  );
}
