<?php

namespace App\Conversations\Customer\Controllers;

use App\Attributes\Models\CustomAttribute;
use Common\Core\BaseController;
use Illuminate\Support\Facades\Auth;

class CustomerNewTicketPageDataController extends BaseController
{
    public function __invoke()
    {
        $isAgent = Auth::user()?->isAgent();

        $config = settings('hc.newTicket.appearance');
        $config['attributeIds'] = array_values($config['attributeIds'] ?? []);
        $attributeIds = $config['attributeIds'];

        $attributes = CustomAttribute::query()
            ->where('type', 'conversation')
            // when loading attributes for customer, only show attributes that customers can edit
            ->when(
                !$isAgent,
                fn($q) => $q->where(
                    'permission',
                    CustomAttribute::PERMISSION_USER_CAN_EDIT,
                ),
            )
            // for appearance editor will need all attributes for live preview
            ->when(
                !request('loadAllAttributes') || !$isAgent,
                fn($q) => $q->whereIn('id', $attributeIds),
            )
            ->get()
            ->map(
                fn(CustomAttribute $attribute) => $attribute->toCompactArray(
                    'customer',
                ),
            );

        $categoryAttribute = $attributes->first(
            fn($attribute) => $attribute['key'] === 'category',
        );

        $response = [
            'attributes' => $attributes->values(),
            'config' => $config,
        ];

        $response['customerEmail'] =
            Auth::user()?->email ?? Auth::user()?->secondaryEmail?->address;
        $response['customerHasVerifiedEmail'] = !!Auth::user()
            ?->email_verified_at;

        return $this->success($response);
    }
}
