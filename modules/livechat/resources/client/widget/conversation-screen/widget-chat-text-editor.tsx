import {EmojiPickerButton} from '@app/reply-composer/emoji-picker-button';
import {useSettingsPreviewMode} from '@common/admin/settings/preview/use-settings-preview-mode';
import {useAuth} from '@common/auth/use-auth';
import {FileEntry} from '@common/uploads/file-entry';
import {useFileUploadStore} from '@common/uploads/uploader/file-upload-provider';
import {ChatUploadFileButton} from '@livechat/widget/chat/text-editor/chat-upload-file-button';
import {IconButton} from '@ui/buttons/icon-button';
import {Trans} from '@ui/i18n/trans';
import {useTrans} from '@ui/i18n/use-trans';
import {SendIcon} from '@ui/icons/material/Send';
import {useSettings} from '@ui/settings/use-settings';
import {Tooltip} from '@ui/tooltip/tooltip';
import {useRef, useState} from 'react';

export interface WidgetChatTextEditorPayload {
  body: string;
  attachments: FileEntry[];
}

interface Props {
  isPending: boolean;
  onSubmit: (data: WidgetChatTextEditorPayload) => void;
}
export function WidgetChatTextEditor({isPending, onSubmit}: Props) {
  const {hasPermission} = useAuth();
  const uploadsDisabled = !hasPermission('files.create');
  const {isInsideSettingsPreview} = useSettingsPreviewMode();
  const {chatWidget} = useSettings();
  const getCompletedUploads = useFileUploadStore(
    s => s.getCompletedFileEntries,
  );
  const clearInactiveUploads = useFileUploadStore(s => s.clearInactive);
  const inputContainerRef = useRef<HTMLDivElement>(null);
  const formRef = useRef<HTMLFormElement>(null);
  const [value, setValue] = useState('');
  const {trans} = useTrans();

  const handleSubmit = async () => {
    if (isPending) return;
    if (!isInsideSettingsPreview) {
      onSubmit({
        body: value,
        attachments: getCompletedUploads(),
      });

      setValue('');
      clearInactiveUploads();
    } else {
      setValue('');
      clearInactiveUploads();
    }
  };

  return (
    <form
      ref={formRef}
      className="m-0 flex-shrink-0"
      onSubmit={e => {
        e.stopPropagation();
        e.preventDefault();
        handleSubmit();
      }}
    >
      <div
        ref={inputContainerRef}
        className="relative overflow-hidden rounded-[24px] bg-elevated shadow-[rgba(0,0,0,0.2)_0px_0px_4px] transition-shadow focus-within:ring dark:shadow-[rgba(255,255,255,0.2)_0px_0px_4px]"
      >
        <div className="relative max-h-[6em] min-h-48 min-w-0 flex-auto">
          <textarea
            required
            className="compact-scrollbar absolute inset-0 max-h-inherit resize-none border-none bg-transparent py-14 pl-14 pr-96 text-sm text outline-none"
            value={value}
            rows={1}
            onChange={e => setValue(e.target.value)}
            placeholder={trans({
              message:
                chatWidget?.inputPlaceholder ?? 'Enter your message here...',
            })}
            onKeyDown={e => {
              if (e.key === 'Enter') {
                e.preventDefault();
                handleSubmit();
              }
            }}
          />
          <div className="invisible max-h-inherit whitespace-pre-line py-14 pl-14 pr-96 text-sm">
            {value}
          </div>
        </div>
        <div className="absolute bottom-0 right-0 top-0 flex items-end px-6 pb-6">
          <EmojiPickerButton
            onSelected={emoji => setValue(value + emoji)}
            className="text-muted"
            size="sm"
          />
          {!uploadsDisabled && (
            <ChatUploadFileButton
              inputContainerRef={inputContainerRef}
              className="text-muted"
              size="sm"
              disabled={isInsideSettingsPreview}
            />
          )}
          <Tooltip label={<Trans message="Submit" />}>
            <IconButton
              disabled={isPending || isInsideSettingsPreview}
              type="submit"
              size="sm"
              color="primary"
            >
              <SendIcon />
            </IconButton>
          </Tooltip>
        </div>
      </div>
    </form>
  );
}
