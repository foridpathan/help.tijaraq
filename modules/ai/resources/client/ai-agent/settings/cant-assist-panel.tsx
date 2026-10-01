import {AiAgentSettings} from '@ai/ai-agent/settings/ai-agent-settings';
import {PanelLayout} from '@ai/ai-agent/settings/panel-layout';
import {useSelectedAiAgent} from '@ai/ai-agent/use-selected-ai-agent';
import {AccordionItemProps} from '@common/ui/library/accordion/accordion';
import {FormTextField} from '@common/ui/library/forms/input-field/text-field/text-field';
import {Trans} from '@common/ui/library/i18n/trans';
import {FeedbackIcon} from '@common/ui/library/icons/material/Feedback';
import {useTrans} from '@ui/i18n/use-trans';
import {useForm} from 'react-hook-form';

export function CantAssistPanel(props: Partial<AccordionItemProps>) {
  const {trans} = useTrans();
  const selectedAgent = useSelectedAiAgent();
  const form = useForm<Partial<AiAgentSettings>>({
    defaultValues: {
      cantAssist: {
        instruction: selectedAgent.config.cantAssist?.instruction ?? '',
      },
    },
  });

  return (
    <PanelLayout
      {...props}
      label={<Trans message="If AI agent is unable to assist user" />}
      description={
        <Trans message="Choose what happens when AI agent is unable to help the customer" />
      }
      icon={<FeedbackIcon />}
      form={form}
    >
      <FormTextField
        name="cantAssist.instruction"
        label={<Trans message="Instruction (optional)" />}
        inputElementType="textarea"
        rows={2}
        className="mb-16"
        placeholder={trans({
          message:
            'say why you are unable to help and ask if you can help with something else.',
        })}
      />
    </PanelLayout>
  );
}
