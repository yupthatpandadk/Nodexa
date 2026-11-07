<?php

namespace Pterodactyl\Tests\Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Pterodactyl\Http\Controllers\Admin\SupportTicketController as AdminTickets;
use Pterodactyl\Http\Controllers\SupportTicketController as CustomerTickets;
use Pterodactyl\Models\Role;
use Pterodactyl\Models\SupportDepartment;
use Pterodactyl\Models\SupportSetting;
use Pterodactyl\Models\SupportTicket;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Nodexa\NodexaEventService;
use Pterodactyl\Services\Support\SupportAccess;
use Pterodactyl\Services\Support\TicketAutomation;
use Pterodactyl\Services\Support\TicketMailImporter;
use Pterodactyl\Services\Support\TicketNotificationService;
use Pterodactyl\Services\Support\TicketWorkflow;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SupportDeskTest extends TestCase
{
    private User $root;
    private User $customer;
    private TicketWorkflow $workflow;

    public function createApplication()
    {
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
        foreach (['views', 'sessions', 'cache'] as $dir) {
            $path = dirname(__DIR__, 2) . '/storage/framework/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('uuid')->nullable();
            $t->string('username');
            $t->string('email');
            $t->string('name_first')->nullable();
            $t->string('name_last')->nullable();
            $t->string('password')->nullable();
            $t->string('language')->default('en');
            $t->boolean('use_totp')->default(false);
            $t->boolean('gravatar')->default(false);
            $t->boolean('root_admin')->default(false);
            $t->timestamps();
        });
        Schema::create('servers', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('owner_id')->nullable();
            $t->string('name')->nullable();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug');
            $t->text('permissions');
            $t->timestamps();
        });
        Schema::create('role_user', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->unsignedInteger('user_id');
            $t->timestamps();
        });
        foreach (['2026_09_22_190000_create_nodexa_support_tickets.php', '2026_09_22_200000_expand_nodexa_support_tickets.php', '2026_09_22_210000_add_nodexa_ticket_settings.php', '2026_11_07_180000_expand_support_desk.php'] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
        $events = Mockery::mock(NodexaEventService::class);
        $events->shouldReceive('notify', 'emit')->zeroOrMoreTimes();
        $this->app->instance(NodexaEventService::class, $events);
        $notifications = Mockery::mock(TicketNotificationService::class);
        $notifications->shouldReceive('created', 'staffReply', 'customerReply', 'closed', 'escalated')->zeroOrMoreTimes();
        $this->app->instance(TicketNotificationService::class, $notifications);
        Storage::fake('local');
        $this->root = $this->user(true);
        $this->customer = $this->user();
        $this->workflow = $this->app->make(TicketWorkflow::class);
    }

    private function user(bool $root = false): User
    {
        $id = DB::table('users')->insertGetId(['uuid' => (string) Str::uuid(), 'username' => 'user' . Str::random(6), 'email' => Str::random(8) . '@example.com', 'root_admin' => $root, 'name_first' => 'Test', 'name_last' => 'User', 'created_at' => now(), 'updated_at' => now()]);
        return User::findOrFail($id);
    }

    private function ticket(array $data = [], ?User $customer = null): SupportTicket
    {
        return $this->workflow->create($customer ?? $this->customer, array_merge(['subject' => 'Help with server', 'department' => 'technical', 'priority' => 'normal', 'message' => 'Please help us with this server problem.'], $data));
    }

    private function request(User $user, array $data = []): Request
    {
        $r = Request::create('/', 'POST', $data);
        $r->setUserResolver(fn () => $user);
        $r->setLaravelSession($this->app['session']->driver());
        return $r;
    }

    private function staff(string $department = 'technical', array $permissions = ['tickets.view', 'tickets.manage']): User
    {
        $staff = $this->user();
        $role = Role::create(['name' => 'Support', 'slug' => Str::random(8), 'permissions' => $permissions]);
        $staff->roles()->attach($role->id);
        SupportDepartment::where('key', $department)->firstOrFail()->staff()->attach($staff->id);
        return $staff;
    }

    public function test_ticket_links_bind_by_numeric_id(): void
    {
        $ticket = $this->ticket();
        $this->assertSame('id', $ticket->getRouteKeyName());
        $this->assertStringEndsWith('/client/tickets/' . $ticket->id, route('store.client.tickets.show', $ticket));
        $this->assertSame($ticket->id, (new SupportTicket())->resolveRouteBinding($ticket->id)->id);
    }

    public function test_department_permissions_scope_lists_and_individual_tickets(): void
    {
        $technical = $this->ticket();
        $billing = $this->ticket(['department' => 'billing']);
        $staff = $this->staff();
        $access = $this->app->make(SupportAccess::class);
        $this->assertSame([$technical->id], $access->tickets($staff)->pluck('id')->all());
        $access->authorize($staff, $technical, true);
        $this->expectException(HttpException::class);
        $access->authorize($staff, $billing);
    }

    public function test_unassigned_department_staff_cannot_reply(): void
    {
        $staff = $this->staff('billing');
        $this->expectException(HttpException::class);
        $this->workflow->reply($this->ticket(), $staff, 'Not allowed', true);
    }

    public function test_view_only_staff_cannot_change_tickets(): void
    {
        $staff = $this->staff(permissions: ['tickets.view']);
        $this->expectException(HttpException::class);
        $this->workflow->reply($this->ticket(), $staff, 'Not allowed', true);
    }

    public function test_customer_cannot_read_someone_elses_ticket(): void
    {
        $this->expectException(HttpException::class);
        $this->app->make(CustomerTickets::class)->show($this->request($this->user()), $this->ticket());
    }

    public function test_notes_do_not_reset_sla_or_send_public_notifications(): void
    {
        $ticket = $this->ticket();
        $due = $ticket->due_at->toDateTimeString();
        $this->workflow->reply($ticket, $this->root, 'Staff-only diagnostic details', true, true, files: [UploadedFile::fake()->createWithContent('note.txt', 'secret')]);
        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertSame($due, $ticket->due_at->toDateTimeString());
        $this->assertNull($ticket->first_response_at);
        $message = $ticket->messages()->latest('id')->first();
        $this->assertTrue($message->internal);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->app->make(CustomerTickets::class)->attachment($this->request($this->customer), $ticket, $message->attachments()->first()->id);
    }

    public function test_staff_reply_pauses_sla_and_customer_reply_restarts_it(): void
    {
        $ticket = $this->ticket();
        $this->workflow->reply($ticket, $this->root, 'Please try again.', true);
        $ticket->refresh();
        $this->assertNull($ticket->due_at);
        $this->assertNotNull($ticket->first_response_at);
        $this->workflow->reply($ticket, $this->customer, 'Still not working');
        $ticket->refresh();
        $this->assertSame('customer_reply', $ticket->status);
        $this->assertNotNull($ticket->due_at);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_assignment_changes_preserve_existing_sla_deadline(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['due_at' => now()->subMinutes(1)]);
        $due = $ticket->due_at->toDateTimeString();
        $this->app['redirect']->setSession($this->app['session']->driver());
        $this->app->make(AdminTickets::class)->update($this->request($this->root, ['status' => 'open','priority' => 'normal','department' => 'technical','tags' => 'network']), $ticket);
        $this->assertSame($due, $ticket->fresh()->due_at->toDateTimeString());
    }

    public function test_auto_assignment_only_uses_department_members_with_manage_permission(): void
    {
        $this->root->update(['root_admin' => false]);
        $this->staff('billing');
        $this->staff(permissions: ['tickets.view']);
        $technical = $this->staff();
        $this->assertSame($technical->id, $this->ticket()->assigned_to);
    }

    public function test_merge_moves_messages_attachments_and_redirects_old_links(): void
    {
        $source = $this->ticket();
        $target = $this->ticket();
        $this->workflow->reply($source, $this->customer, 'Here is my log', files: [UploadedFile::fake()->createWithContent('server.txt', 'log details')]);
        $attachment = $source->attachments()->first();
        $this->workflow->merge($source, $target, $this->root);
        $this->assertSame($target->id, $source->fresh()->merged_into_id);
        $this->assertSame(3, $target->messages()->count());
        $this->assertSame(0, $source->messages()->count());
        $this->assertSame($target->id, $attachment->fresh()->ticket_id);
        Storage::disk('local')->assertExists($attachment->path);
        $response = $this->app->make(CustomerTickets::class)->show($this->request($this->customer), $source->fresh());
        $this->assertSame(route('store.client.tickets.show', $target), $response->getTargetUrl());
    }

    public function test_merge_rejects_tickets_belonging_to_different_customers(): void
    {
        $source = $this->ticket();
        $target = $this->ticket(customer: $this->user());
        try {
            $this->workflow->merge($source, $target, $this->root);
            $this->fail('Expected merge rejection');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertSame(1, $source->messages()->count());
            $this->assertNull($source->fresh()->merged_into_id);
        }
    }

    public function test_merge_rejects_self_and_closed_destinations(): void
    {
        $source = $this->ticket();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->workflow->merge($source, $source, $this->root);
    }

    public function test_escalation_runs_once_and_never_reduces_priority(): void
    {
        SupportDepartment::where('key', 'technical')->update(['escalation_minutes' => 5,'escalation_priority' => 'high']);
        $ticket = $this->ticket(['priority' => 'urgent']);
        $ticket->update(['due_at' => now()->subMinutes(10)]);
        $automation = $this->app->make(TicketAutomation::class);
        $this->assertSame(1, $automation->run()['escalated']);
        $this->assertSame(0, $automation->run()['escalated']);
        $this->assertSame('urgent', $ticket->fresh()->priority);
        $this->assertSame(1, $ticket->events()->where('event', 'escalated')->count());
    }

    public function test_auto_close_only_affects_inactive_answered_tickets(): void
    {
        SupportDepartment::where('key', 'technical')->update(['auto_close_hours' => 24]);
        $waiting = $this->ticket();
        $open = $this->ticket();
        $reply = $this->ticket();
        $this->workflow->reply($waiting, $this->root, 'We have fixed this.', true);
        $waiting->refresh()->update(['last_staff_reply_at' => now()->subHours(48),'last_customer_reply_at' => now()->subHours(49)]);
        $this->workflow->reply($reply, $this->root, 'We have fixed this.', true);
        $this->workflow->reply($reply, $this->customer, 'Still broken');
        $result = $this->app->make(TicketAutomation::class)->run();
        $this->assertSame(1, $result['closed']);
        $this->assertSame('closed', $waiting->fresh()->status);
        $this->assertSame('open', $open->fresh()->status);
        $this->assertSame('customer_reply', $reply->fresh()->status);
    }

    public function test_department_can_prevent_customer_closure(): void
    {
        $ticket = $this->ticket();
        SupportDepartment::where('key', 'technical')->update(['prevent_client_close' => true]);
        $this->expectException(HttpException::class);
        $this->app->make(CustomerTickets::class)->close($this->request($this->customer), $ticket);
    }

    public function test_mail_import_is_idempotent_and_checks_private_address_and_sender(): void
    {
        config(['support.mail.address' => 'support@example.com']);
        $ticket = $this->ticket();
        $importer = $this->app->make(TicketMailImporter::class);
        $to = ['support+' . $ticket->reply_token . '@example.com'];
        $this->assertSame('rejected', $importer->import(hash('sha256', 'wrong-sender'), 'other@example.com', $to, 'Unauthorized reply'));
        $this->assertSame('rejected', $importer->import(hash('sha256', 'no-token'), $this->customer->email, ['support@example.com'], 'Missing token'));
        $key = hash('sha256', 'valid-mail');
        $this->assertSame('imported', $importer->import($key, $this->customer->email, $to, "Here is my update\n\n> --- Reply above this line ---\n> Previous message"));
        $this->assertSame('duplicate', $importer->import($key, $this->customer->email, $to, 'Same reply again'));
        $this->assertSame(2, $ticket->messages()->count());
        $this->assertSame('Here is my update', $ticket->messages()->latest('id')->value('message'));
    }

    public function test_mail_follows_merge_chain_and_reopens_closed_tickets_when_allowed(): void
    {
        config(['support.mail.address' => 'support@example.com']);
        $source = $this->ticket();
        $target = $this->ticket();
        $this->workflow->merge($source, $target, $this->root);
        $this->workflow->changeStatus($target, $this->root, 'closed', 'Resolved');
        $result = $this->app->make(TicketMailImporter::class)->import(hash('sha256', 'old-address'), $this->customer->email, ['support+'.$source->reply_token.'@example.com'], 'Replying to old notification');
        $this->assertSame('imported', $result);
        $this->assertSame('customer_reply', $target->fresh()->status);
        $this->assertSame(3, $target->messages()->count());
    }

    public function test_mail_cannot_reopen_when_disabled_and_rejects_executable_attachments(): void
    {
        config(['support.mail.address' => 'support@example.com']);
        $ticket = $this->ticket();
        $to = ['support+'.$ticket->reply_token.'@example.com'];
        $this->workflow->changeStatus($ticket, $this->root, 'closed', 'Resolved');
        SupportSetting::put('allow_reopen', '0');
        $importer = $this->app->make(TicketMailImporter::class);
        $this->assertSame('rejected', $importer->import(hash('sha256', 'closed-mail'), $this->customer->email, $to, 'New question'));
        $this->workflow->changeStatus($ticket, $this->root, 'open', 'Reopened');
        $this->assertSame('rejected', $importer->import(hash('sha256', 'bad-file'), $this->customer->email, $to, 'New question', [UploadedFile::fake()->createWithContent('bad.php', '<?php echo "test";')]));
        $this->assertSame(1, $ticket->messages()->count());
    }

    public function test_customer_can_only_rate_public_staff_replies(): void
    {
        $ticket = $this->ticket();
        $this->workflow->reply($ticket, $this->root, 'Secret note', true, true);
        $note = $ticket->messages()->latest('id')->first();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->app->make(CustomerTickets::class)->rateReply($this->request($this->customer, ['rating' => 5]), $ticket, $note->id);
    }

    public function test_support_staff_cannot_grant_department_access_or_change_global_settings(): void
    {
        $staff = $this->staff();
        $this->expectException(HttpException::class);
        $this->app->make(AdminTickets::class)->saveDepartment($this->request($staff));
    }

    public function test_outgoing_mail_contains_public_reply_and_private_reply_to_only(): void
    {
        config(['support.mail.enabled' => true,'support.mail.address' => 'support@example.com','mail.default' => 'array']);
        $ticket = $this->ticket();
        $this->workflow->reply($ticket, $this->root, 'This is the public answer', true);
        $this->workflow->reply($ticket, $this->root, 'INTERNAL SECRET', true, true);
        $mailer = Mail::mailer('array');
        $mailer->getSymfonyTransport()->flush();
        (new TicketNotificationService())->staffReply($ticket);
        $sent = $mailer->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('support+'.$ticket->reply_token.'@example.com', $sent->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString('This is the public answer', $sent->getHtmlBody());
        $this->assertStringNotContainsString('INTERNAL SECRET', $sent->getHtmlBody());
    }
    public function test_customer_pages_render_and_keep_internal_content_hidden(): void
    {
        $this->withoutMiddleware([\Pterodactyl\Http\Middleware\SiteAccessGate::class, \Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication::class]);
        $ticket = $this->ticket();
        $this->workflow->reply($ticket, $this->root, 'SECRET INTERNAL NOTE', true, true);
        $this->actingAs($this->customer);
        $this->get('/client/tickets/'.$ticket->id)->assertOk()->assertSee('Help with server')->assertDontSee('SECRET INTERNAL NOTE');
        $this->get('/client/tickets')->assertOk()->assertSee($ticket->ticket_number);
        $this->get('/client/tickets/new')->assertOk()->assertSee('Hvordan kan vi hjælpe?');
    }

    public function test_admin_pages_render_with_settings_and_predefined_reply_picker(): void
    {
        $this->withoutMiddleware([\Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication::class, \Pterodactyl\Http\Middleware\NodexaAdminAudit::class]);
        $ticket = $this->ticket();
        $this->actingAs($this->root);
        $this->get('/admin/tickets/'.$ticket->id)->assertOk()->assertSee('Predefined reply')->assertSee('Activity log');
        $this->get('/admin/tickets')->assertOk()->assertSee('Needs a reply');
        $this->get('/admin/tickets/settings')->assertOk()->assertSee('Recent mail imports');
    }

    public function test_http_routes_reject_foreign_tickets_and_department_access(): void
    {
        $this->withoutMiddleware([\Pterodactyl\Http\Middleware\SiteAccessGate::class, \Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication::class, \Pterodactyl\Http\Middleware\NodexaAdminAudit::class]);
        $ticket = $this->ticket();
        $staff = $this->staff('billing');
        $this->actingAs($this->user())->get('/client/tickets/'.$ticket->id)->assertForbidden();
        $this->actingAs($staff)->get('/admin/tickets/'.$ticket->id)->assertForbidden();
        $this->actingAs($staff)->get('/admin/tickets/settings')->assertForbidden();
    }

    public function test_new_migration_rolls_back_without_deleting_existing_conversations(): void
    {
        $ticket = $this->ticket();
        (require database_path('migrations/2026_11_07_180000_expand_support_desk.php'))->down();
        $this->assertTrue(Schema::hasTable('nodexa_support_tickets'));
        $this->assertSame(1, DB::table('nodexa_support_ticket_messages')->where('ticket_id', $ticket->id)->count());
        $this->assertFalse(Schema::hasColumn('nodexa_support_tickets', 'merged_into_id'));
    }

}
