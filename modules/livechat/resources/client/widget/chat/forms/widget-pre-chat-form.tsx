import {useSettingsPreviewMode} from '@common/admin/settings/preview/use-settings-preview-mode';
import {
  WidgetChatForm,
  WidgetChatFormValue,
} from '@livechat/widget/chat/forms/widget-chat-form';
import {Trans} from '@ui/i18n/trans';
import {useSettings} from '@ui/settings/use-settings';

interface Props {
  isPending?: boolean;
  onSubmit?: (values: WidgetChatFormValue) => void;
}
export function WidgetPreChatForm({onSubmit, isPending}: Props) {
  const {isInsideSettingsPreview: isAppearanceEditorActive} =
    useSettingsPreviewMode();
  const {chatWidget} = useSettings();
  const fields = chatWidget?.forms?.preChat?.attributes;
  if (!fields?.length) {
    return null;
  }

  return (
    <div className="animated-chat-message">
      <WidgetChatForm
        attributeIds={fields}
        information={chatWidget?.forms?.preChat?.information}
        onSubmit={values => {
          if (isAppearanceEditorActive || !onSubmit) return;
          onSubmit(values);
        }}
        submitButtonLabel={<Trans message="Start the chat" />}
        isPending={isPending ?? false}
      />
    </div>
  );
}
