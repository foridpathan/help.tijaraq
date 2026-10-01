import {helpdeskQueries} from '@app/dashboard/helpdesk-queries';
import {onFormQueryError} from '@common/errors/on-form-query-error';
import {apiClient} from '@common/http/query-client';
import {SectionHelper} from '@common/ui/other/section-helper';
import {useMutation, useQuery} from '@tanstack/react-query';
import {Button} from '@ui/buttons/button';
import {Form} from '@ui/forms/form';
import {ChipValue} from '@ui/forms/input-field/chip-field/chip-field';
import {FormChipField} from '@ui/forms/input-field/chip-field/form-chip-field';
import {Item} from '@ui/forms/listbox/item';
import {message} from '@ui/i18n/message';
import {Trans} from '@ui/i18n/trans';
import {useTrans} from '@ui/i18n/use-trans';
import {KeyboardArrowDownIcon} from '@ui/icons/material/KeyboardArrowDown';
import {Dialog} from '@ui/overlays/dialog/dialog';
import {DialogBody} from '@ui/overlays/dialog/dialog-body';
import {useDialogContext} from '@ui/overlays/dialog/dialog-context';
import {DialogFooter} from '@ui/overlays/dialog/dialog-footer';
import {DialogHeader} from '@ui/overlays/dialog/dialog-header';
import {DialogTrigger} from '@ui/overlays/dialog/dialog-trigger';
import {toast} from '@ui/toast/toast';
import {useState} from 'react';
import {useForm} from 'react-hook-form';

type Props = {
  selectedIds: (number | string)[];
  taggableType: string;
  onTagsSynced: () => void;
};
export function SyncTagsButton({
  selectedIds,
  taggableType,
  onTagsSynced,
}: Props) {
  return (
    <DialogTrigger type="modal">
      <Button variant="outline" endIcon={<KeyboardArrowDownIcon />}>
        <Trans message="Manage tags" />
      </Button>
      <AddTagsDialog
        selectedIds={selectedIds}
        taggableType={taggableType}
        onTagsSynced={onTagsSynced}
      />
    </DialogTrigger>
  );
}

function AddTagsDialog({selectedIds, taggableType, onTagsSynced}: Props) {
  const {close, formId} = useDialogContext();
  const [searchQuery, setSearchQuery] = useState('');
  const {data} = useQuery(helpdeskQueries.tags.index(searchQuery));
  const allTags = data?.tags || [];
  const {trans} = useTrans();

  const form = useForm<{tags: ChipValue[]}>({
    defaultValues: {
      tags: [],
    },
  });

  const syncTags = useMutation({
    mutationFn: (tags: ChipValue[]) => {
      return apiClient.post('hc/taggables/sync-tags', {
        taggableIds: selectedIds,
        taggableType: taggableType,
        tags: tags.map(tag => tag.name),
      });
    },
    onSuccess: () => {
      toast(message('Tags synced successfully'));
      onTagsSynced();
    },
    onError: r => onFormQueryError(r, form),
  });

  return (
    <Dialog size="lg">
      <DialogHeader>
        <Trans message="Manage tags" />
      </DialogHeader>
      <DialogBody>
        <Form
          id={formId}
          form={form}
          onSubmit={value => syncTags.mutate(value.tags)}
          onBeforeSubmit={() => {
            form.clearErrors();
          }}
        >
          <FormChipField
            name="tags"
            placeholder={trans({
              message: 'Type to search or create a new tag...',
            })}
            background="bg"
            label={<Trans message="Select tags to apply" />}
            chipSize="sm"
            suggestions={allTags}
            inputValue={searchQuery}
            onInputValueChange={setSearchQuery}
          >
            {tag => (
              <Item value={tag.name} key={tag.name}>
                <Trans message={tag.name} />
              </Item>
            )}
          </FormChipField>
        </Form>
        <SectionHelper
          className="mt-24"
          color="warning"
          size="sm"
          description={
            <Trans message="This will attach selected tags and detach any tags that are not in the list." />
          }
        />
      </DialogBody>
      <DialogFooter>
        <Button onClick={() => close()}>
          <Trans message="Close" />
        </Button>
        <Button
          type="submit"
          variant="flat"
          color="primary"
          form={formId}
          disabled={syncTags.isPending}
        >
          <Trans message="Save" />
        </Button>
      </DialogFooter>
    </Dialog>
  );
}
