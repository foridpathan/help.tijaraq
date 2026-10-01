<?php namespace App\Models;

use App\Attributes\Models\CustomAttribute;
use App\Attributes\Traits\HasCustomAttributes;
use App\CannedReplies\Models\CannedReply;
use App\Contacts\Models\UserDetails;
use App\Contacts\Traits\CanHaveSecondaryEmails;
use App\Conversations\Models\Conversation;
use App\Team\Traits\CanBeAgent;
use Common\Auth\BaseUser;
use Common\Tags\Tag;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use App\Contacts\Models\PageVisit;

class User extends BaseUser
{
    use HasApiTokens, CanBeAgent, CanHaveSecondaryEmails, HasCustomAttributes {
        updateCustomAttributes as parentUpdateAttributes;
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function pageVisits(): HasMany
    {
        return $this->hasMany(PageVisit::class);
    }

    public function latestPageVisit(): HasOne
    {
        return $this->hasOne(PageVisit::class)->orderBy('created_at', 'desc');
    }

    public function updateCustomAttributes(
        array|Collection $values,
        $permission = CustomAttribute::PERMISSION_USER_CAN_EDIT,
    ): void {
        // we don't want to allow user to manually change or specify their primary email here,
        // because this email address is not verified
        $email = Arr::pull($values, 'email');
        if ($email) {
            $this->secondaryEmails()->updateOrCreate(['address' => $email]);
        }

        $this->parentUpdateAttributes($values, $permission);
    }

    public function details(): HasOne
    {
        return $this->hasOne(UserDetails::class);
    }

    public function cannedReplies(): HasMany
    {
        return $this->hasMany(CannedReply::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function makeSearchableUsing(Collection $models)
    {
        return parent::makeSearchableUsing($models)
            ->load([
                'customAttributes' => fn($builder) => $builder->where(
                    'type',
                    'user',
                ),
            ])
            ->loadCount('pageVisits');
    }

    protected function makeAllSearchableUsing($query)
    {
        return parent::makeAllSearchableUsing($query)
            ->with([
                'customAttributes' => fn($builder) => $builder->where(
                    'type',
                    'user',
                ),
            ])
            ->withCount('pageVisits');
    }

    public static function filterableFields(): array
    {
        $fields = parent::filterableFields();
        $fields[] = 'page_visits_count';
        $fields[] = 'is_returning';
        $fields[] = 'ca_*'; // all attributes
        return $fields;
    }

    public function toSearchableArray(): array
    {
        $data = parent::toSearchableArray();
        $data['page_visits_count'] = $this->page_visits_count ?? 0;
        $data['is_returning'] = $this->page_visits_count > 1;

        if (
            $this->relationLoaded('customAttributes') &&
            !$this->customAttributes->isEmpty()
        ) {
            $this->customAttributes()->each(
                fn($attribute) => ($data["ca_$attribute->key"] =
                    $attribute->pivot->value),
            );
        }

        return $data;
    }
}
