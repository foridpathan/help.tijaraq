<?php

namespace Database\Seeders;

use App\Attributes\Models\CustomAttribute;
use App\Conversations\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InternalAttributesSeeder extends Seeder
{
    public function run(): void
    {
        if (!$this->getAttribute('email', 'user')) {
            CustomAttribute::create([
                'name' => 'Email',
                'key' => 'email',
                'format' => 'email',
                'permission' => 'userCanView',
                'type' => User::MODEL_TYPE,
                'required' => true,
                'internal' => true,
                'materialized' => true,
            ]);
        }

        if (!$this->getAttribute('name', 'user')) {
            CustomAttribute::create([
                'name' => 'Name',
                'key' => 'name',
                'format' => 'text',
                'permission' => 'userCanEdit',
                'type' => User::MODEL_TYPE,
                'required' => false,
                'internal' => true,
                'materialized' => true,
            ]);
        }

        if (!$this->getAttribute('language', 'user')) {
            CustomAttribute::create([
                'name' => 'Language',
                'key' => 'language',
                'format' => 'dropdown',
                'permission' => 'userCanEdit',
                'type' => User::MODEL_TYPE,
                'required' => false,
                'internal' => true,
                'materialized' => true,
            ]);
        }

        if (!$this->getAttribute('timezone', 'user')) {
            CustomAttribute::create([
                'name' => 'Timezone',
                'key' => 'timezone',
                'format' => 'dropdown',
                'permission' => 'userCanEdit',
                'type' => User::MODEL_TYPE,
                'required' => false,
                'internal' => true,
                'materialized' => true,
            ]);
        }

        if (!$this->getAttribute('country', 'user')) {
            CustomAttribute::create([
                'name' => 'Country',
                'key' => 'country',
                'format' => 'dropdown',
                'permission' => 'userCanEdit',
                'type' => User::MODEL_TYPE,
                'required' => false,
                'internal' => true,
                'materialized' => true,
            ]);
        }

        if (!$this->getAttribute('group_id', 'conversation')) {
            CustomAttribute::create([
                'name' => 'Department',
                'format' => 'dropdown',
                'key' => 'group_id',
                'permission' => 'agentOnly',
                'type' => Conversation::MODEL_TYPE,
                'required' => false,
                'internal' => true,
                'materialized' => true,
            ]);
        }

        if (!$this->getAttribute('rating', 'conversation')) {
            CustomAttribute::create([
                'name' => 'Rating',
                'format' => 'rating',
                'key' => 'rating',
                'permission' => 'userCanEdit',
                'type' => Conversation::MODEL_TYPE,
                'required' => false,
                'internal' => true,
                'materialized' => true,
            ]);
        }

        if (!$this->getAttribute('category', 'conversation')) {
            CustomAttribute::create([
                'name' => 'Category',
                'customer_name' => 'Hi, what can we help you with?',
                'format' => 'dropdown',
                'key' => 'category',
                'permission' => 'userCanEdit',
                'type' => Conversation::MODEL_TYPE,
                'required' => false,
                'internal' => true,
                'config' => [
                    'options' => [
                        ['value' => 'billing', 'label' => 'Billing'],
                        ['value' => 'support', 'label' => 'Support'],
                        ['value' => 'other', 'label' => 'Other'],
                    ],
                ],
            ]);
        }

        if (!$this->getAttribute('subject', 'conversation')) {
            CustomAttribute::create([
                'name' => 'Subject',
                'customer_name' =>
                    'In a few words, tell us what your enquiry is about',
                'format' => 'text',
                'key' => 'subject',
                'permission' => 'userCanEdit',
                'type' => Conversation::MODEL_TYPE,
                'required' => true,
                'internal' => true,
                'materialized' => true,
            ]);
        }

        $subject =
            $this->getAttribute('subject', 'conversation') ??
            CustomAttribute::create([
                'name' => 'Subject',
                'customer_name' =>
                    'In a few words, tell us what your enquiry is about',
                'format' => 'text',
                'key' => 'subject',
                'permission' => 'userCanEdit',
                'type' => Conversation::MODEL_TYPE,
                'required' => true,
                'internal' => true,
                'materialized' => true,
            ]);

        $description =
            $this->getAttribute('description', 'conversation') ??
            CustomAttribute::create([
                'name' => 'Description',
                'customer_name' => 'Provide a detailed description',
                'format' => 'multiLineText',
                'key' => 'description',
                'permission' => 'userCanEdit',
                'type' => Conversation::MODEL_TYPE,
                'required' => true,
                'internal' => true,
                'materialized' => true,
            ]);

        // add locked attributes to new ticket page config
        $old = DB::table('settings')
            ->where('name', 'hc.newTicket.appearance')
            ->first();
        if ($old) {
            $new = json_decode($old->value, true);
            $new['attributeIds'] = $new['attributeIds'] ?? [];

            if (
                in_array($subject->id, $new['attributeIds']) &&
                in_array($description->id, $new['attributeIds'])
            ) {
                return;
            }

            $new['attributeIds'] = array_unique(
                array_merge(
                    [$subject->id, $description->id],
                    $new['attributeIds'] ?? [],
                ),
            );
            DB::table('settings')
                ->where('name', 'hc.newTicket.appearance')
                ->update(['value' => json_encode($new)]);
        }
    }

    protected function getAttribute(
        string $key,
        string $type,
    ): CustomAttribute|null {
        return CustomAttribute::withoutGlobalScopes()
            ->where('key', $key)
            ->where('type', $type)
            ->first();
    }
}
