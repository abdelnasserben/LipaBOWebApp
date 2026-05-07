<?php

use Livewire\Component;
use App\Services\Mock\MockDataService;

new class extends Component
{
    public ?array $selected = null;
    public bool $showCreateModal = false;
    public array $newUser = ['email' => '', 'fullName' => '', 'password' => '', 'role' => 'OPERATOR'];
    public string $notification = '';

    public function selectRow(string $id): void
    {
        $this->selected = collect(MockDataService::backofficeUsers())->firstWhere('id', $id);
    }
    public function closeDrawer(): void { $this->selected = null; }

    public function createUser(): void
    {
        $this->validate([
            'newUser.email'    => 'required|email',
            'newUser.fullName' => 'required',
            'newUser.password' => 'required|min:8|max:100',
            'newUser.role'     => 'required',
        ]);
        // Real: POST /api/v1/backoffice/users
        $this->notification = 'Backoffice user created successfully.';
        $this->showCreateModal = false;
        $this->newUser = ['email' => '', 'fullName' => '', 'password' => '', 'role' => 'OPERATOR'];
    }

    public function suspendUser(): void
    {
        // Real: POST /api/v1/backoffice/users/{id}/suspend
        $this->notification = 'User suspended.';
        $this->closeDrawer();
    }

    public function reactivateUser(): void
    {
        // Real: POST /api/v1/backoffice/users/{id}/reactivate
        $this->notification = 'User reactivated.';
        $this->closeDrawer();
    }

    public function render(): \Illuminate\View\View
    {
        $rows = MockDataService::backofficeUsers();
        return view('livewire.users.user-list', ['rows' => $rows]);
    }
};
?>

<div>
    <x-page-header
        title="Backoffice Users"
        subtitle="{{ count($rows) }} team members"
    >
        <x-slot:actions>
            <button class="btn btn-primary btn-md" wire:click="$set('showCreateModal', true)">
                <x-icon name="plus" size="13" /> New User
            </button>
        </x-slot:actions>
    </x-page-header>

    @if($notification)
    <div class="alert alert-success mb-4"><x-icon name="check" size="15" /> {{ $notification }}</div>
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
                            <option value="OPERATOR">OPERATOR</option>
                            <option value="SUPERVISOR">SUPERVISOR</option>
                            <option value="COMPLIANCE">COMPLIANCE</option>
                            <option value="ADMIN">ADMIN</option>
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
            <span class="drawer-title">{{ $selected['fullName'] }}</span>
            <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
        </div>
        <div class="drawer-body">
            <div class="mb-4 flex gap-2">
                <x-badge :status="$selected['status']" />
                <x-badge :status="$selected['role']" />
            </div>
            <div class="drawer-section">
                <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selected['id'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Email</span><span class="drawer-field-value">{{ $selected['email'] }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">MFA</span><span class="drawer-field-value">{{ $selected['mfaEnabled'] ? 'Enabled' : 'Disabled' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Last Login</span><span class="drawer-field-value">{{ isset($selected['lastLoginAt']) ? \Carbon\Carbon::parse($selected['lastLoginAt'])->format('d M Y, H:i') : '—' }}</span></div>
                <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selected['createdAt'])->format('d M Y') }}</span></div>
            </div>
        </div>
        <div class="drawer-footer">
            @if($selected['status'] === 'ACTIVE')
                <button class="btn btn-warning btn-sm" wire:click="suspendUser">Suspend</button>
            @elseif($selected['status'] === 'SUSPENDED')
                <button class="btn btn-primary btn-sm" wire:click="reactivateUser">Reactivate</button>
            @endif
        </div>
    </div>
    @endif
</div>
