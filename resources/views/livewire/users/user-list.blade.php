<?php

use Livewire\Component;
use App\Enums\Backoffice\BackofficeRole;
use App\Livewire\Concerns\UsesBackofficeEnums;
use App\Livewire\Concerns\WithApiCursorPagination;
use App\Services\Api\UsesBackofficeApi;
use App\Support\BackofficeEnums;
use App\Support\BackofficeEnumSets;

new class extends Component
{
    use WithApiCursorPagination;
    use UsesBackofficeApi;
    use UsesBackofficeEnums;

    public ?array $selected = null;
    public bool $showCreateModal = false;
    public bool $showElevateModal = false;
    public bool $showCloseConfirm = false;
    public array $newUser = ['email' => '', 'fullName' => '', 'password' => '', 'role' => 'OPERATOR'];
    public string $targetRole = 'SUPERVISOR';
    public string $notification = '';
    public string $notificationType = 'success';

    public function selectRow(string $id): void
    {
        $this->selected = collect($this->api()->backofficeUsers())->firstWhere('id', $id);
        $this->resetActionState();

        if ($this->selected) {
            $this->targetRole = $this->defaultTargetRole((string) ($this->selected['role'] ?? ''));
        }
    }

    public function closeDrawer(): void
    {
        $this->selected = null;
        $this->resetActionState();
    }

    public function openCreateModal(): void
    {
        $this->notification = '';
        $this->notificationType = 'success';
        $this->showCreateModal = true;
    }

    public function openElevateModal(): void
    {
        if (! $this->selected) {
            return;
        }

        if (in_array((string) ($this->selected['role'] ?? ''), ['ADMIN', 'SUPER_ADMIN'], true)) {
            return;
        }

        $this->showCloseConfirm = false;
        $this->showElevateModal = true;
        $this->targetRole = $this->defaultTargetRole((string) ($this->selected['role'] ?? ''));
    }

    public function confirmClose(): void
    {
        $this->showElevateModal = false;
        $this->showCloseConfirm = true;
    }

    public function createUser(): void
    {
        $this->validate([
            'newUser.email'    => 'required|email',
            'newUser.fullName' => 'required|string|max:255',
            'newUser.password' => 'required|min:8|max:100',
            'newUser.role'     => 'required|' . BackofficeEnums::validationRule(BackofficeRole::class, BackofficeEnumSets::manageableBackofficeRoles()),
        ]);
        $this->api()->createBackofficeUser([
            'email' => trim($this->newUser['email']),
            'password' => $this->newUser['password'],
            'fullName' => trim($this->newUser['fullName']),
            'role' => $this->newUser['role'],
        ]);
        $this->notification = 'Backoffice user created successfully.';
        $this->notificationType = 'success';
        $this->showCreateModal = false;
        $this->newUser = ['email' => '', 'fullName' => '', 'password' => '', 'role' => 'OPERATOR'];
    }

    public function suspendUser(): void
    {
        $this->api()->suspendBackofficeUser($this->selected['id']);
        $this->notification = 'User suspended.';
        $this->notificationType = 'success';
        $this->closeDrawer();
    }

    public function reactivateUser(): void
    {
        $this->api()->reactivateBackofficeUser($this->selected['id']);
        $this->notification = 'User reactivated.';
        $this->notificationType = 'success';
        $this->closeDrawer();
    }

    public function closeUser(): void
    {
        if (! $this->selected) {
            return;
        }

        $this->api()->closeBackofficeUser($this->selected['id']);
        $this->notification = 'User closed.';
        $this->notificationType = 'success';
        $this->closeDrawer();
    }

    public function submitRoleElevation(): void
    {
        if (! $this->selected) {
            return;
        }

        $this->validate([
            'targetRole' => 'required|' . BackofficeEnums::validationRule(BackofficeRole::class, BackofficeEnumSets::manageableBackofficeRoles()),
        ]);

        if ($this->targetRole === ($this->selected['role'] ?? null)) {
            $this->addError('targetRole', 'Choose a role different from the current role.');

            return;
        }

        $response = $this->api()->elevateBackofficeUserRole($this->selected['id'], [
            'newRole' => $this->targetRole,
        ]);

        if (($response['status'] ?? '') === 'PENDING_APPROVAL' || isset($response['approvalId'])) {
            $suffix = isset($response['approvalId']) ? ' Approval: ' . $response['approvalId'] . '.' : '';
            $this->notification = 'Role elevation submitted for approval.' . $suffix;
        } else {
            $this->notification = 'User role updated.';
        }

        $this->notificationType = 'success';
        $this->closeDrawer();
    }

    private function resetActionState(): void
    {
        $this->showElevateModal = false;
        $this->showCloseConfirm = false;
        $this->targetRole = 'SUPERVISOR';
        $this->resetErrorBag('targetRole');
    }

    private function defaultTargetRole(string $currentRole): string
    {
        return match ($currentRole) {
            'OPERATOR' => 'SUPERVISOR',
            'SUPERVISOR', 'COMPLIANCE' => 'ADMIN',
            default => 'OPERATOR',
        };
    }

    public function render(): \Illuminate\View\View
    {
        $page = $this->api()->backofficeUsersPage($this->cursorPageQuery('users'));
        $rows = $page['data'];

        return view('livewire.users.user-list', [
            'rows' => $rows,
            'paginator' => $this->cursorPaginator('users', $page, count($rows), 'team members'),
            'roleOptions' => BackofficeEnums::options(BackofficeRole::class, BackofficeEnumSets::manageableBackofficeRoles()),
        ]);
    }
};
?>

<div>
    <x-page-header
        title="Backoffice Users"
        subtitle="{{ count($rows) }} team members"
    >
        <x-slot:actions>
            <button class="btn btn-primary btn-md" wire:click="openCreateModal">
                <x-icon name="plus" size="13" /> New User
            </button>
        </x-slot:actions>
    </x-page-header>

    @if($notification)
    <div class="alert alert-{{ $notificationType }} mb-4">
        <x-icon name="{{ $notificationType === 'success' ? 'check' : 'alert-triangle' }}" size="15" />
        {{ $notification }}
    </div>
    @endif

    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>MFA</th>
                        <th>Last Login</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    <tr class="table-row-link" wire:click="selectRow('{{ $row['id'] }}')">
                        <td>
                            <div class="font-medium">{{ $row['fullName'] }}</div>
                            <x-mono>{{ $row['email'] }}</x-mono>
                        </td>
                        <td><x-badge :status="$row['role']" /></td>
                        <td><x-badge :status="$row['status']" /></td>
                        <td>
                            @if($row['mfaEnabled'])
                                <span class="text-xs text-[var(--green)]">✓ Enabled</span>
                            @else
                                <span class="text-xs text-[var(--amber)]">Disabled</span>
                            @endif
                        </td>
                        <td><x-mono>{{ isset($row['lastLoginAt']) ? \Carbon\Carbon::parse($row['lastLoginAt'])->format('d M Y, H:i') : '—' }}</x-mono></td>
                        <td><x-mono>{{ \Carbon\Carbon::parse($row['createdAt'])->format('d M Y') }}</x-mono></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-cursor-pagination :paginator="$paginator" />
    </div>

    {{-- Create User Modal --}}
    @if($showCreateModal)
    <div class="modal-overlay" wire:click.self="$set('showCreateModal', false)">
        <div class="modal">
            <div class="modal-header">
                <span class="modal-title">Add Backoffice User</span>
                <button class="modal-close" wire:click="$set('showCreateModal', false)"><x-icon name="x" size="18" /></button>
            </div>
            <div class="modal-body">
                @if($notification && $notificationType === 'danger')
                <div class="alert alert-danger mb-4">
                    <x-icon name="alert-triangle" size="15" />
                    {{ $notification }}
                </div>
                @endif
                <div class="flex flex-col gap-3">
                    <div>
                        <label class="form-label">Full Name <span class="form-required">*</span></label>
                        <input wire:model="newUser.fullName" type="text" class="form-input" placeholder="Full Name" />
                        @error('newUser.fullName') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="form-label">Email <span class="form-required">*</span></label>
                        <input wire:model="newUser.email" type="email" class="form-input" placeholder="user@komopay.km" />
                        @error('newUser.email') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="form-label">Password <span class="form-required">*</span></label>
                        <input wire:model="newUser.password" type="password" class="form-input" placeholder="Min 8 characters" />
                        @error('newUser.password') <div class="form-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="form-label">Role <span class="form-required">*</span></label>
                        <select wire:model="newUser.role" class="form-select">
                            @foreach($roleOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-md" wire:click="$set('showCreateModal', false)">Cancel</button>
                <button class="btn btn-primary btn-md" wire:click="createUser">Create User</button>
            </div>
        </div>
    </div>
    @endif

    {{-- User Detail Drawer --}}
    @if($selected)
    <div class="drawer-overlay" wire:click="closeDrawer"></div>
    <div class="drawer">
        <div class="drawer-header">
            <div>
                <span class="drawer-title">{{ $selected['fullName'] }}</span><br>
                <span class="drawer-field-value">{{ $selected['id'] }}</span>
            </div>
            <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
        </div>
        <div class="drawer-body">
            <div class="mb-4 flex gap-2">
                <x-badge :status="$selected['status']" />
                <x-badge :status="$selected['role']" />
            </div>
            <div class="drawer-section">
                <div class="drawer-field"><span class="drawer-field-label">Email</span><span class="drawer-field-value">{{ $selected['email'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">MFA</span><span class="drawer-field-value">{{ $selected['mfaEnabled'] ? 'Enabled' : 'Disabled' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Last Login</span><span class="drawer-field-value">{{ isset($selected['lastLoginAt']) ? \Carbon\Carbon::parse($selected['lastLoginAt'])->format('d M Y, H:i') : '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['createdAt'])->format('d M Y') }}</span></div>
            </div>

            @if($showElevateModal)
            <div class="drawer-section">
                <div class="drawer-section-title">Elevate Role</div>
                <label class="form-label">Target Role <span class="form-required">*</span></label>
                <select wire:model="targetRole" class="form-select">
                    @foreach($roleOptions as $option)
                        @if($option['value'] !== ($selected['role'] ?? null))
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endif
                    @endforeach
                </select>
                @error('targetRole') <div class="form-error">{{ $message }}</div> @enderror
                <div class="mt-2.5 flex gap-2">
                    <button class="btn btn-primary btn-sm" wire:click="submitRoleElevation">Submit Elevation</button>
                    <button class="btn btn-secondary btn-sm" wire:click="$set('showElevateModal', false)">Cancel</button>
                </div>
            </div>
            @endif

            @if($showCloseConfirm)
            <div class="alert alert-danger">
                <div>
                    <strong>Confirm close?</strong>
                    <br />This will close the backoffice account and prevent future login.
                    <div class="mt-2.5 flex gap-2">
                        <button class="btn btn-danger btn-sm" wire:click="closeUser">Yes, close user</button>
                        <button class="btn btn-secondary btn-sm" wire:click="$set('showCloseConfirm', false)">Cancel</button>
                    </div>
                </div>
            </div>
            @endif
        </div>
        @if(!$showElevateModal && !$showCloseConfirm)
        <div class="drawer-footer">
            @if($selected['status'] === 'ACTIVE')
                <button class="btn btn-warning btn-sm" wire:click="suspendUser">Suspend</button>
            @elseif($selected['status'] === 'SUSPENDED')
                <button class="btn btn-primary btn-sm" wire:click="reactivateUser">Reactivate</button>
            @endif
            @if(!in_array($selected['status'], ['CLOSED']) && !in_array($selected['role'], ['ADMIN', 'SUPER_ADMIN']))
                <button class="btn btn-secondary btn-sm" wire:click="openElevateModal">Elevate Role</button>
            @endif
            @if(!in_array($selected['status'], ['CLOSED']))
                <button class="btn btn-danger btn-sm" wire:click="confirmClose">Close User</button>
            @endif
        </div>
        @endif
    </div>
    @endif
</div>
