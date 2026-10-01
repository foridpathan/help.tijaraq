import {ConversationCategoryAttribute} from '@app/attributes/compact-attribute';
import {Item} from '@ui/forms/listbox/item';
import {FormSelect} from '@ui/forms/select/select';
import {Trans} from '@ui/i18n/trans';

interface Props {
  attribute: ConversationCategoryAttribute;
}
export function CategoryField({attribute}: Props) {
  const categories = attribute.config?.options
    ? attribute.config.options.filter(option => !option.agentOnly)
    : [];

  return (
    <FormSelect
      required
      name="attributes.category"
      selectionMode="single"
      className="mb-24"
      label={<Trans message={attribute.name} />}
    >
      {categories.map(category => (
        <Item key={category.value} value={category.value}>
          {category.label}
        </Item>
      ))}
    </FormSelect>
  );
}
