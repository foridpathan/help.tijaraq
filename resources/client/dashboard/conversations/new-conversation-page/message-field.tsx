import {ArticleSearchButton} from '@app/dashboard/conversations/agent-reply-composer/article-search-button';
import {InsertCannedReplyButton} from '@app/dashboard/conversations/agent-reply-composer/insert-canned-reply-button';
import {NewConversationPayload} from '@app/dashboard/conversations/new-conversation-page/new-conversation-payload';
import {ConversationAttachment} from '@app/dashboard/types/conversation-attachment';
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
import {FileUploadProvider} from '@common/uploads/uploader/file-upload-provider';
import {ReactNode} from 'react';
import {useFormContext, useWatch} from 'react-hook-form';

interface Props {
  errorMessage?: ReactNode;
}
export function MessageField({errorMessage}: Props) {
  const {hasPermission} = useAuth();
  const uploadsDisabled = !hasPermission('files.create');

  const form = useFormContext<NewConversationPayload>();
  const attachments = useWatch<NewConversationPayload, 'message.attachments'>({
    name: 'message.attachments',
  });

  const handleUpload = (attachment: ConversationAttachment) => {
    form.setValue('message.attachments', [attachment, ...attachments], {
      shouldDirty: true,
    });
  };

  return (
    <FileUploadProvider>
      <ReplyComposerDropTargetMask
        isDisabled={uploadsDisabled}
        onUpload={handleUpload}
      >
        <ReplyComposerContainer
          autoFocus
          submitToClosestForm
          onChange={value =>
            form.setValue('message.body', value, {shouldDirty: true})
          }
        >
          <FloatingToolbar />
          <ReplyComposerFooter>
            <CannedReplyButton />
            <ArticleSearchButton />
            <ReplyComposerEmojiPickerButton />
            {!uploadsDisabled && (
              <UploadAttachmentsButton onUpload={handleUpload} />
            )}
            {!uploadsDisabled && (
              <InsertInlineImageButton
                uploadType={UploadType.conversationImages}
              />
            )}
            <EnhanceTextWithAiButton />
          </ReplyComposerFooter>
        </ReplyComposerContainer>
      </ReplyComposerDropTargetMask>
      {errorMessage}
      <ReplyComposerAttachments
        className="mt-12"
        attachments={attachments}
        onRemove={attachment => {
          form.setValue(
            'message.attachments',
            attachments.filter(a => a.id !== attachment.id),
            {shouldDirty: true},
          );
        }}
      />
    </FileUploadProvider>
  );
}

function CannedReplyButton() {
  const editor = useCurrentTextEditor();
  const form = useFormContext<NewConversationPayload>();
  return (
    <InsertCannedReplyButton
      onSelected={reply => {
        editor?.commands.insertContent(reply.body);
        if (reply.attachments.length) {
          form.setValue('message.attachments', reply.attachments, {
            shouldDirty: true,
          });
        }
      }}
    />
  );
}
