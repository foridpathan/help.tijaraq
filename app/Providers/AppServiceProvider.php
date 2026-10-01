<?php namespace App\Providers;

use App\Attributes\Models\CustomAttribute;
use App\Attributes\Policies\AttributePolicy;
use App\CannedReplies\Models\CannedReply;
use App\CannedReplies\Policies\CannedReplyPolicy;
use App\Contacts\Models\PageVisit;
use App\Conversations\Commands\DeleteTestConversationsCommand;
use App\Conversations\Email\Commands\ImportEmailsViaImap;
use App\Conversations\Events\ConversationCreated;
use App\Conversations\Events\ConversationMessageCreated;
use App\Conversations\Events\ConversationsAssignedToAgent;
use App\Conversations\Events\ConversationsUpdated;
use App\Conversations\Listeners\SendReplyCreatedNotif;
use App\Conversations\Listeners\SendTicketsAssignedNotif;
use App\Conversations\Models\Conversation;
use App\Conversations\Policies\ConversationFileEntryPolicy;
use App\Conversations\Policies\ConversationPolicy;
use App\Core\AppBootstrapData;
use App\Core\Commands\ResetDemoSiteCommand;
use App\Core\Listeners\DeleteUserRelations;
use App\Core\Modules;
use App\Core\UrlGenerator;
use App\HelpCenter\Models\HcArticle;
use App\HelpCenter\Models\HcCategory;
use App\HelpCenter\Models\SearchTerm;
use App\HelpCenter\Policies\HcArticlePolicy;
use App\Reports\Actions\GetAnalyticsHeaderData;
use App\Reports\Policies\HelpdeskReportPolicy;
use App\Team\Models\AgentInvite;
use App\Team\Models\Group;
use App\Team\Policies\GroupPolicy;
use App\Triggers\Models\Trigger;
use App\Triggers\Policies\TriggerPolicy;
use App\Triggers\TriggersCycle;
use Common\Admin\Analytics\Actions\GetAnalyticsHeaderDataAction;
use Common\Auth\Events\UserCreated;
use Common\Auth\Events\UsersDeleted;
use Common\Core\Bootstrap\BootstrapData;
use Common\Core\Contracts\AppUrlGenerator;
use Common\Tags\TaggableController;
use Common\Websockets\API\WebsocketAPI;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app->bind(BootstrapData::class, AppBootstrapData::class);

        // Policies
        Gate::policy(HcArticle::class, HcArticlePolicy::class);
        Gate::policy(HcCategory::class, HcArticlePolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(CustomAttribute::class, AttributePolicy::class);
        Gate::policy(CannedReply::class, CannedReplyPolicy::class);
        Gate::policy('ReportPolicy', HelpdeskReportPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Trigger::class, TriggerPolicy::class);
        Gate::policy(
            'conversationFileEntry',
            ConversationFileEntryPolicy::class,
        );

        Relation::enforceMorphMap([
            Conversation::MODEL_TYPE => Conversation::class,
            SearchTerm::MODEL_TYPE => SearchTerm::class,
            HcArticle::MODEL_TYPE => HcArticle::class,
            HcCategory::MODEL_TYPE => HcCategory::class,
            Group::MODEL_TYPE => Group::class,
            AgentInvite::MODEL_TYPE => AgentInvite::class,
            PageVisit::MODEL_TYPE => PageVisit::class,
        ]);

        $this->scheduleCommands();
        $this->registerEvents();

        // Create only one websocket API instance so API requests are made only once per request
        $this->app->singleton(WebsocketAPI::class, function (
            Application $app,
            array $options = [],
        ) {
            return new WebsocketAPI($options ?? []);
        });

        Modules::boot($this->app);
    }

    public function register(): void
    {
        $this->app->bind(AppUrlGenerator::class, UrlGenerator::class);

        $this->app->bind(
            GetAnalyticsHeaderDataAction::class,
            GetAnalyticsHeaderData::class,
        );

        Modules::register($this->app);
    }

    protected function scheduleCommands(): void
    {
        $this->commands([
            ResetDemoSiteCommand::class,
            ImportEmailsViaImap::class,
            DeleteTestConversationsCommand::class,
        ]);

        $this->app->booted(function () {
            if (!$this->app->runningInConsole()) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);

            // triggers
            $schedule
                ->call(function () {
                    (new TriggersCycle())->executeTimeBasedTriggers();
                })
                ->name('triggers:runTimeBased')
                ->hourly()
                ->withoutOverlapping(60);
        });
    }

    protected function registerEvents(): void
    {
        // User events
        Event::listen(UsersDeleted::class, DeleteUserRelations::class);
        Event::listen(UserCreated::class, function (UserCreated $e) {
            if ($e->user->isAgent()) {
                Group::findDefault()
                    ?->users()
                    ->syncWithoutDetaching([
                        $e->user->id => [
                            'created_at' => now(),
                        ],
                    ]);
            }
        });
        Event::listen(UsersDeleted::class, function (UsersDeleted $e) {
            foreach ($e->users as $user) {
                Group::findDefault()?->users()->detach($user);
            }
        });

        // Conversation events
        Event::listen(
            ConversationsAssignedToAgent::class,
            SendTicketsAssignedNotif::class,
        );
        Event::listen(
            ConversationMessageCreated::class,
            SendReplyCreatedNotif::class,
        );
        Event::listen(ConversationsUpdated::class, function (
            ConversationsUpdated $e,
        ) {
            // make sure "ConversationsUpdated" events fired by the cycle do not trigger another cycle,
            // if conversation is updated during the cycle, it will handle restarting itself
            if (TriggersCycle::$isRunning) {
                return false;
            }

            $cycle = new TriggersCycle();
            foreach ($e->conversationsAfterUpdate as $conversation) {
                if (
                    $conversation->assigned_to === Conversation::ASSIGNED_AGENT
                ) {
                    $cycle->runAgainstConversation(
                        $conversation,
                        $e->conversationsDataBeforeUpdate[$conversation->id],
                    );
                }
            }
        });
        Event::listen(ConversationCreated::class, function (
            ConversationCreated $e,
        ) {
            if (
                $e->conversation->assigned_to === Conversation::ASSIGNED_AGENT
            ) {
                (new TriggersCycle())->runAgainstConversation($e->conversation);
            }
        });

        $this->handleTaggableEvents();
        $this->handleConversationUpdatedCommand();

        // telescope
        if (
            $this->app->environment('local') &&
            class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)
        ) {
            $this->app->register(
                \Laravel\Telescope\TelescopeServiceProvider::class,
            );
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * TaggableController will not fire ConversationsUpdated event, need to do it manually here.
     */
    protected function handleTaggableEvents(): void
    {
        $tagEvent = null;
        TaggableController::$beforeTagChangeCallbacks[] = function (
            Collection $taggables,
        ) use (&$tagEvent) {
            if (
                $taggables->every(
                    fn($t) => $t->model_type === 'chat' ||
                        $t->model_type === 'ticket',
                )
            ) {
                $tagEvent = new ConversationsUpdated($taggables);
            }
        };
        TaggableController::$afterTagChangeCallbacks[] = function (
            Collection $taggables,
        ) use (&$tagEvent) {
            if ($tagEvent) {
                $tagEvent->dispatch($taggables);
            }
        };
    }

    /**
     * Broadcast only one ConversationsUpdated event before app terminates.
     */
    protected function handleConversationUpdatedCommand(): void
    {
        if ($this->app->runningInConsole()) {
            Event::listen(ArtisanStarting::class, function (
                ArtisanStarting $e,
            ) {
                app(Kernel::class)->whenCommandLifecycleIsLongerThan(
                    0,
                    function () {
                        ConversationsUpdated::broadcastLatest();
                    },
                );
            });
        } else {
            Event::listen(RequestHandled::class, function (RequestHandled $e) {
                ConversationsUpdated::broadcastLatest();
            });
        }
    }
}
