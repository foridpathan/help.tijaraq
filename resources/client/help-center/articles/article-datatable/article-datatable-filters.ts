import {
  BackendFilter,
  FilterChipFieldControl,
  FilterControlType,
  FilterOperator,
} from '@common/datatable/filters/backend-filter';
import {
  createdAtFilter,
  updatedAtFilter,
} from '@common/datatable/filters/timestamp-filters';
import {message} from '@ui/i18n/message';
import {USER_MODEL} from '@ui/types/user';

export function tagsFilter(
  options?: Partial<BackendFilter<FilterChipFieldControl>>,
): BackendFilter<FilterChipFieldControl> {
  return {
    key: 'tags',
    label: message('Tags'),
    defaultOperator: FilterOperator.has,
    operators: [FilterOperator.has],
    control: {
      type: FilterControlType.ChipField,
      autocompleteEndpoint: 'helpdesk/normalized-models/tags',
      placeholder: message('Find tag'),
      defaultValue: [],
    },
    ...options,
  };
}

export const ArticleDatatableFilters: BackendFilter[] = [
  {
    key: 'draft',
    label: message('Status'),
    defaultOperator: FilterOperator.eq,
    control: {
      type: FilterControlType.Select,
      options: [
        {key: '01', value: '', label: message('All')},
        {key: '02', value: false, label: message('Published')},
        {key: '03', value: true, label: message('Draft')},
      ],
      defaultValue: '01',
    },
  },
  {
    key: 'author_id',
    label: message('Author'),
    description: message('User this article was created by'),
    defaultOperator: FilterOperator.eq,
    control: {
      type: FilterControlType.SelectModel,
      model: USER_MODEL,
    },
  },
  tagsFilter(),
  {
    key: 'used_by_ai_agent',
    label: message('AI Agent status'),
    defaultOperator: FilterOperator.eq,
    control: {
      type: FilterControlType.Select,
      options: [
        {key: '01', value: true, label: message('Used by AI agent')},
        {key: '02', value: false, label: message('Not used by AI agent')},
      ],
      defaultValue: '01',
    },
  },
  createdAtFilter({
    description: message('Date article was created'),
  }),
  updatedAtFilter({
    description: message('Date article was last updated'),
  }),
];
