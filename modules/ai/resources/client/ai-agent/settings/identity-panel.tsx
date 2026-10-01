import {AiAgentSettings} from '@ai/ai-agent/settings/ai-agent-settings';
import {PanelLayout} from '@ai/ai-agent/settings/panel-layout';
import {useSelectedAiAgent} from '@ai/ai-agent/use-selected-ai-agent';
import {UploadType} from '@app/site-config';
import {AccordionItemProps} from '@common/ui/library/accordion/accordion';
import {FormTextField} from '@common/ui/library/forms/input-field/text-field/text-field';
import {Trans} from '@common/ui/library/i18n/trans';
import {BadgeIcon} from '@common/ui/library/icons/material/Badge';
import {FormImageSelector} from '@common/uploads/components/image-selector';
import {FileUploadProvider} from '@common/uploads/uploader/file-upload-provider';
import {useForm} from 'react-hook-form';

export function IdentityPanel(props: Partial<AccordionItemProps>) {
  const selectedAgent = useSelectedAiAgent();
  const form = useForm<Partial<AiAgentSettings>>({
    defaultValues: {
      name: selectedAgent.config.name,
      image: selectedAgent.config.image,
    },
  });
  return (
    <PanelLayout
      {...props}
      label={<Trans message="Identity" />}
      description={<Trans message="Name and avatar" />}
      icon={<BadgeIcon />}
      form={form}
    >
      <FormTextField
        name="name"
        label={<Trans message="Name" />}
        description={<Trans message="The name your customers will see." />}
        required
        className="mb-16"
      />
      <FileUploadProvider>
        <FormImageSelector
          name="image"
          uploadType={UploadType.brandingImages}
          label={<Trans message="Avatar" />}
          className="max-w-400"
          showRemoveButton
          descriptionPosition="bottom"
          description={
            <Trans message="Use a JPG, PNG, or GIF smaller than 100KB. 50px by 50px works best." />
          }
        />
      </FileUploadProvider>
    </PanelLayout>
  );
}
