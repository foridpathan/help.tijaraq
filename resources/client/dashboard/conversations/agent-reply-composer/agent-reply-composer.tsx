import {replaceVariables} from '@app/attributes/attribute-selector/replace-variables';
import {ConversationCategoryAttribute} from '@app/attributes/compact-attribute';
import {FullConversationResponse} from '@app/dashboard/conversation';
import {useAgentReplyComposerStore} from '@app/dashboard/conversations/agent-reply-composer/agent-reply-composer-store';
import {ArticleSearchButton} from '@app/dashboard/conversations/agent-reply-composer/article-search-button';
import {InsertCannedReplyButton} from '@app/dashboard/conversations/agent-reply-composer/insert-canned-reply-button';
import {MessageTypeSelector} from '@app/dashboard/conversations/agent-reply-composer/message-type-selector';
import {SubmitReplyButtons} from '@app/dashboard/conversations/agent-reply-composer/submit-reply-buttons';
import {useSubmitAgentReply} from '@app/dashboard/conversations/agent-reply-composer/use-submit-agent-reply';
import {ReplyComposerEmojiPickerButton} from '@app/reply-composer/emoji-picker-button';
import {EnhanceTextWithAiButton} from '@app/reply-composer/enhance-text-with-ai-button';
import {InsertInlineImageButton} from '@app/reply-composer/insert-inline-image-button';
import {ReplyComposerAttachments} from '@app/reply-composer/reply-composer-attachments';
import ReplyComposerContainer from '@app/reply-composer/reply-composer-container';
import {ReplyComposerDropTargetMask} from '@app/reply-composer/reply-composer-drop-target';
import {ReplyComposerFooter} from '@app/reply-composer/reply-composer-footer';
import {UploadAttachmentsButton} from '@app/reply-composer/upload-attachments-button';
import {UploadType} from '@app/site-config';
import {useAuth} from '@common/auth/use-auth';
import {FloatingToolbar} from '@common/text-editor/floating-toolbar';
import {useCurrentTextEditor} from '@common/text-editor/tiptap-editor-context';
import {TextEditorApi} from '@common/text-editor/tiptap-editor-provider';
import {Chip} from '@ui/forms/input-field/chip-field/chip';
import {ChipList} from '@ui/forms/input-field/chip-field/chip-list';
import clsx from 'clsx';
import {useRef} from 'react';

export interface Props {
  data: FullConversationResponse;
}
export function AgentReplyComposer({data}: Props) {
  const {hasPermission} = useAuth();
  const uploadsDisabled = !hasPermission('files.create');
  const editorApiRef = useRef<TextEditorApi>(null);
  const updateDraft = useAgentReplyComposerStore(s => s.updateDraft);
  const submitReply = useSubmitAgentReply(data.conversation);
  const draft = useAgentReplyComposerStore(s => s.draft);
  const messageType = useAgentReplyComposerStore(s => s.messageType);
  const addAttachment = useAgentReplyComposerStore(s => s.addAttachment);
  const removeAttachment = useAgentReplyComposerStore(s => s.removeAttachment);

  const handleSubmit = () => {
    submitReply.mutate(undefined, {
      onSuccess: () => editorApiRef.current?.clearContents(),
    });
  };

  return (
    <form
      onSubmit={e => {
        e.preventDefault();
        handleSubmit();
      }}
    >
      <ReplyComposerDropTargetMask
        isDisabled={uploadsDisabled}
        onUpload={addAttachment}
      >
        <ReplyComposerContainer
          ref={editorApiRef}
          initialContent={draft.body}
          height="max-h-[50vh] min-h-[180px]"
          onChange={value => updateDraft({body: value})}
          className={clsx(
            'mx-16 my-16',
            messageType === 'note' && 'bg-warning/15',
          )}
          header={<MessageTypeSelector />}
          submitToClosestForm
        >
          <FloatingToolbar />
          <ReplyComposerAttachments
            className="mb-2 mt-24 px-12"
            attachments={draft.attachments}
            onRemove={removeAttachment}
          />
          <TagList />
          <ReplyComposerFooter
            submitButtons={
              <SubmitReplyButtons conversation={data.conversation} />
            }
          >
            <CannedReplyButton data={data} />
            <ReplyComposerEmojiPickerButton />
            <ConversationArticleSearchButton data={data} />
            {!uploadsDisabled && (
              <UploadAttachmentsButton onUpload={addAttachment} />
            )}
            {!uploadsDisabled && (
              <InsertInlineImageButton
                uploadType={UploadType.conversationImages}
              />
            )}
            <EnhanceTextWithAiButton disabled={!draft.body.length} />
          </ReplyComposerFooter>
        </ReplyComposerContainer>
      </ReplyComposerDropTargetMask>
    </form>
  );
}

export function CannedReplyButton({data}: Props) {
  const editor = useCurrentTextEditor();
  const draft = useAgentReplyComposerStore(s => s.draft);
  const updateDraft = useAgentReplyComposerStore(s => s.updateDraft);

  return (
    <InsertCannedReplyButton
      onSelected={reply => {
        editor?.commands.insertContent(replaceVariables(reply.body, data));
        setTimeout(() => {
          editor?.commands.focus();
        }, 170);
        updateDraft(reply);
      }}
      getInitialData={() => ({
        body: draft.body,
        attachments: draft.attachments,
        tags: draft.tags,
      })}
    />
  );
}

function TagList() {
  const tags = useAgentReplyComposerStore(s => s.draft.tags);
  const removeTag = useAgentReplyComposerStore(s => s.removeTag);

  if (!tags?.length) {
    return null;
  }

  return (
    <ChipList size="xs" className="mb-2 mt-12 px-12">
      {tags.map(tag => (
        <Chip key={tag.id} onRemove={() => removeTag(tag)}>
          {tag.name}
        </Chip>
      ))}
    </ChipList>
  );
}

function ConversationArticleSearchButton({data}: Props) {
  // if conversation has category attributes, get help center categories attached
  const category = data.attributes?.find(c => c.key === 'category') as
    | (ConversationCategoryAttribute & {value?: string})
    | null;
  const hcCategoryId = category?.value
    ? category.config?.options?.find(o => o.value === category.value)
        ?.hcCategories?.[0]
    : undefined;

  return (
    <ArticleSearchButton
      categoryIds={hcCategoryId ? [hcCategoryId] : undefined}
    />
  );
}
